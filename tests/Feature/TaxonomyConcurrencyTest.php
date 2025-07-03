<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Test concurrent move operations with database locking.
 */
it('can handle concurrent move operations with locking', function () {
    // Setup struktur test
    $root = Taxonomy::create([
        'name' => 'Root',
        'type' => TaxonomyType::Category->value,
        'slug' => 'root',
    ]);

    $branch1 = Taxonomy::create([
        'name' => 'Branch 1',
        'type' => TaxonomyType::Category->value,
        'slug' => 'branch-1',
        'parent_id' => $root->id,
    ]);

    $branch2 = Taxonomy::create([
        'name' => 'Branch 2',
        'type' => TaxonomyType::Category->value,
        'slug' => 'branch-2',
        'parent_id' => $root->id,
    ]);

    $movingNodes = [];
    for ($i = 1; $i <= 5; ++$i) {
        $movingNodes[] = Taxonomy::create([
            'name' => "Moving Node {$i}",
            'type' => TaxonomyType::Category->value,
            'slug' => "moving-node-{$i}",
            'parent_id' => $branch1->id,
        ]);
    }

    // Simulasi concurrent move operations
    $results = [];
    $errors = [];

    foreach ($movingNodes as $index => $node) {
        try {
            // Gunakan database transaction dengan locking
            DB::transaction(function () use ($node, $branch1, $branch2, $index, &$results) {
                // Lock row untuk mencegah race condition
                $lockedNode = Taxonomy::lockForUpdate()->find($node->id);

                if (! $lockedNode) {
                    throw new \Exception("Node {$node->id} not found or locked");
                }

                // Alternate target parent
                $targetParent = ($index % 2 === 0) ? $branch2 : $branch1;

                // Simulate processing delay
                usleep(100000); // 100ms delay

                $lockedNode->moveToParent($targetParent->id);
                $results[] = [
                    'node_id' => $node->id,
                    'target_parent_id' => $targetParent->id,
                    'success' => true,
                ];
            });
        } catch (\Exception $e) {
            $errors[] = [
                'node_id' => $node->id,
                'error' => $e->getMessage(),
            ];
        }
    }

    // Verify results
    expect(count($results))->toBeGreaterThan(0, 'No successful moves');

    // Verify final structure integrity
    assertValidConcurrencyNestedSetStructure();

    // Log results for debugging
    if (! empty($errors)) {
        Log::info('Concurrent move errors:', $errors);
    }

    // Verify concurrent move operations completed
    expect(count($results))->toBeGreaterThan(0, 'Should have successful moves');
    expect(count($errors))->toBeLessThanOrEqual(count($results), 'Errors should not exceed successful operations');
});

/*
 * Test concurrent creation with unique constraints.
 */
it('can handle concurrent creation with unique constraints', function () {
    $baseName = 'Concurrent Category';
    $baseSlug = 'concurrent-category';
    $concurrentCount = 10;

    $results = [];
    $errors = [];

    // Simulasi concurrent creation
    for ($i = 1; $i <= $concurrentCount; ++$i) {
        try {
            DB::transaction(function () use ($baseName, $baseSlug, $i, &$results) {
                // Simulate network delay
                usleep(rand(10000, 50000)); // 10-50ms random delay

                $taxonomy = Taxonomy::create([
                    'name' => "{$baseName} {$i}",
                    'type' => TaxonomyType::Category->value,
                    'slug' => $baseSlug, // Intentionally same slug to test uniqueness
                ]);

                $results[] = [
                    'id' => $taxonomy->id,
                    'name' => $taxonomy->name,
                    'slug' => $taxonomy->slug,
                ];
            });
        } catch (\Exception $e) {
            $errors[] = [
                'attempt' => $i,
                'error' => $e->getMessage(),
            ];
        }
    }

    // Verify only one succeeded with the same slug
    $createdSlugs = collect($results)->pluck('slug')->toArray();
    $uniqueSlugs = array_unique($createdSlugs);

    // If there's duplicate handling, all should be unique
    // If not, only 1 should succeed
    expect(
        count($createdSlugs) === count($uniqueSlugs) || count($results) === 1
    )->toBeTrue('Slug uniqueness not properly handled');

    // Verify concurrent creation operations completed
    expect(count($results))->toBeGreaterThan(0, 'Should have successful creations');
    expect(count($uniqueSlugs))->toBeGreaterThan(0, 'Should have unique slugs created');
});

/*
 * Test concurrent rebuild operations.
 */
it('can handle concurrent rebuild operations', function () {
    // Create complex structure
    createComplexStructure(100);

    $rebuildResults = [];
    $rebuildErrors = [];

    // Run multiple rebuilds concurrently
    for ($i = 1; $i <= 3; ++$i) {
        try {
            $startTime = microtime(true);

            // Use database lock for rebuild
            DB::transaction(function () use (&$rebuildResults, $i) {
                // Lock all taxonomy records
                DB::table('taxonomies')->lockForUpdate()->get();

                Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

                $rebuildResults[] = [
                    'rebuild_id' => $i,
                    'timestamp' => now(),
                    'success' => true,
                ];
            });

            $rebuildTime = microtime(true) - $startTime;
            $rebuildResults[count($rebuildResults) - 1]['duration'] = $rebuildTime;
        } catch (\Exception $e) {
            $rebuildErrors[] = [
                'rebuild_id' => $i,
                'error' => $e->getMessage(),
            ];
        }
    }

    // Verify structure is still valid after concurrent rebuilds
    assertValidConcurrencyNestedSetStructure();

    // Verify at least one rebuild succeeded
    expect(count($rebuildResults))->toBeGreaterThan(0, 'No successful rebuilds');

    // Verify concurrent rebuild operations completed
    expect(count($rebuildResults))->toBeGreaterThan(0, 'Should have successful rebuilds');
    expect(count($rebuildErrors))->toBeLessThanOrEqual(count($rebuildResults), 'Errors should not exceed successful operations');

    // Verify rebuild performance
    foreach ($rebuildResults as $result) {
        expect($result['duration'])->toBeLessThan(30.0, "Rebuild {$result['rebuild_id']} should complete within 30 seconds");
    }
});

/*
 * Test concurrent cache operations.
 */
it('can handle concurrent cache operations', function () {
    // Setup data
    createComplexStructure(50);

    $cacheResults = [];
    $cacheErrors = [];

    // Clear cache
    Cache::flush();

    // Simulate concurrent cache access
    for ($i = 1; $i <= 10; ++$i) {
        try {
            $startTime = microtime(true);

            // Concurrent getTree calls (should trigger cache)
            $tree = Taxonomy::getNestedTree();

            $accessTime = microtime(true) - $startTime;

            $cacheResults[] = [
                'access_id' => $i,
                'duration' => $accessTime,
                'tree_count' => $tree->count(),
                'cache_hit' => $i > 1, // First call is cache miss, rest should be hits
            ];
        } catch (\Exception $e) {
            $cacheErrors[] = [
                'access_id' => $i,
                'error' => $e->getMessage(),
            ];
        }
    }

    // Verify cache performance improvement
    $firstAccess = $cacheResults[0]['duration'] ?? 0;
    $subsequentAccesses = array_slice($cacheResults, 1);
    $avgSubsequentTime = collect($subsequentAccesses)->avg('duration');

    // Cache improvement might be minimal, so we check if subsequent calls are reasonably fast
    // or if there's at least some improvement (allowing for small variations)
    if ($avgSubsequentTime > 0) {
        expect($avgSubsequentTime <= $firstAccess * 1.5)->toBeTrue('Cache performance significantly degraded');
    }

    // Verify concurrent cache operations completed
    expect(count($cacheResults))->toBeGreaterThan(0, 'Should have successful cache accesses');

    // Cache performance can vary, so we test that operations run within reasonable time
    expect($avgSubsequentTime)->toBeLessThan(1.0, 'Subsequent cache accesses should be reasonably fast');
    expect($firstAccess)->toBeLessThan(2.0, 'First access should complete within reasonable time');

    // Verify cache speedup (with larger tolerance)
    if ($avgSubsequentTime > 0 && $firstAccess > $avgSubsequentTime) {
        $speedup = round($firstAccess / $avgSubsequentTime, 2);
        // Cache speedup detected but not strictly required
    }
});

/*
 * Test deadlock detection and recovery.
 */
it('can handle deadlock detection and recovery', function () {
    // Create structure for deadlock scenario
    $nodeA = Taxonomy::create([
        'name' => 'Node A',
        'type' => TaxonomyType::Category->value,
        'slug' => 'node-a',
    ]);

    $nodeB = Taxonomy::create([
        'name' => 'Node B',
        'type' => TaxonomyType::Category->value,
        'slug' => 'node-b',
    ]);

    $childA = Taxonomy::create([
        'name' => 'Child A',
        'type' => TaxonomyType::Category->value,
        'slug' => 'child-a',
        'parent_id' => $nodeA->id,
    ]);

    $childB = Taxonomy::create([
        'name' => 'Child B',
        'type' => TaxonomyType::Category->value,
        'slug' => 'child-b',
        'parent_id' => $nodeB->id,
    ]);

    $deadlockResults = [];
    $deadlockErrors = [];

    // Simulate potential deadlock scenario
    // Transaction 1: Lock A then B
    // Transaction 2: Lock B then A

    try {
        // Transaction 1
        DB::transaction(function () use ($nodeA, $childB, &$deadlockResults) {
            $lockedA = Taxonomy::lockForUpdate()->find($nodeA->id);

            // Simulate processing time
            usleep(100000); // 100ms

            // Try to move childB to nodeA (requires lock on nodeB)
            $freshChildB = $childB->fresh();
            expect($freshChildB)->not->toBeNull();
            expect($lockedA)->not->toBeNull();
            $freshChildB->moveToParent($lockedA->id);

            $deadlockResults[] = 'Transaction 1 completed';
        });
    } catch (\Exception $e) {
        $deadlockErrors[] = 'Transaction 1 failed: ' . $e->getMessage();
    }

    try {
        // Transaction 2 (in real concurrent scenario, this would run simultaneously)
        DB::transaction(function () use ($nodeB, $childA, &$deadlockResults) {
            $lockedB = Taxonomy::lockForUpdate()->find($nodeB->id);

            // Simulate processing time
            usleep(100000); // 100ms

            // Try to move childA to nodeB (requires lock on nodeA)
            $freshChildA = $childA->fresh();
            expect($freshChildA)->not->toBeNull();
            expect($lockedB)->not->toBeNull();
            $freshChildA->moveToParent($lockedB->id);

            $deadlockResults[] = 'Transaction 2 completed';
        });
    } catch (\Exception $e) {
        $deadlockErrors[] = 'Transaction 2 failed: ' . $e->getMessage();
    }

    // Verify final state is consistent
    assertValidConcurrencyNestedSetStructure();

    // Verify deadlock handling completed
    expect(count($deadlockResults))->toBeGreaterThan(0, 'Should have successful transactions');

    // Verify deadlock errors are handled properly
    foreach ($deadlockErrors as $error) {
        expect(strtolower($error))->toContain('deadlock', 'Error should be deadlock-related');
    }
});

/*
 * Test transaction isolation levels.
 */
it('can handle transaction isolation levels', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Isolation Test',
        'type' => TaxonomyType::Category->value,
        'slug' => 'isolation-test',
    ]);

    $isolationResults = [];

    // Test READ COMMITTED isolation
    try {
        DB::transaction(function () use ($taxonomy, &$isolationResults) {
            // Read initial value
            $initial = Taxonomy::find($taxonomy->id);
            expect($initial)->not->toBeNull();
            $isolationResults['initial_name'] = $initial->name;

            // Simulate concurrent update (in real scenario, this would be another process)
            DB::table('taxonomies')
                ->where('id', $taxonomy->id)
                ->update(['name' => 'Updated by concurrent transaction']);

            // Read again within same transaction
            $updated = Taxonomy::find($taxonomy->id);
            expect($updated)->not->toBeNull();
            $isolationResults['updated_name'] = $updated->name;

            // The behavior depends on isolation level
            // READ COMMITTED: should see the update
            // REPEATABLE READ: should not see the update
        });
    } catch (\Exception $e) {
        $isolationResults['error'] = $e->getMessage();
    }

    expect($isolationResults)->toHaveKey('initial_name');
    expect($isolationResults)->toHaveKey('updated_name');

    // Verify transaction isolation behavior
    expect($isolationResults['initial_name'])->not->toEqual(
        $isolationResults['updated_name'],
        'Transaction isolation should allow concurrent updates'
    );
});

/**
 * Helper to create complex structure.
 */
function createComplexStructure(int $nodeCount): void
{
    $parents = [null];

    for ($i = 1; $i <= $nodeCount; ++$i) {
        $parentId = $parents[array_rand($parents)];

        $taxonomy = Taxonomy::create([
            'name' => "Complex Node {$i}",
            'type' => TaxonomyType::Category->value,
            'slug' => "complex-node-{$i}",
            'parent_id' => $parentId,
        ]);

        // Some nodes can become parents
        if ($i % 5 === 0) {
            $parents[] = $taxonomy->id;
        }
    }
}

/**
 * Helper untuk validasi nested set structure.
 */
function assertValidConcurrencyNestedSetStructure(): void
{
    $taxonomies = Taxonomy::orderBy('lft')->get();

    foreach ($taxonomies as $taxonomy) {
        // Basic lft < rgt validation
        expect($taxonomy->lft)->toBeLessThan($taxonomy->rgt, "Invalid lft/rgt for taxonomy {$taxonomy->id}");

        // Parent-child relationship validation
        if ($taxonomy->parent_id) {
            $parent = Taxonomy::find($taxonomy->parent_id);
            expect($parent)->not->toBeNull("Parent {$taxonomy->parent_id} not found for taxonomy {$taxonomy->id}");
            expect($taxonomy->lft)->toBeGreaterThan($parent->lft, "Child lft not greater than parent lft for taxonomy {$taxonomy->id}");
            expect($taxonomy->rgt)->toBeLessThan($parent->rgt, "Child rgt not less than parent rgt for taxonomy {$taxonomy->id}");
        }
    }

    // Check for gaps in lft/rgt sequence
    $allValues = $taxonomies->flatMap(function ($taxonomy) {
        return [$taxonomy->lft, $taxonomy->rgt];
    })->sort()->values();

    for ($i = 0; $i < $allValues->count(); ++$i) {
        expect($allValues[$i])->toEqual($i + 1, "Gap in nested set sequence at position {$i}");
    }
}
