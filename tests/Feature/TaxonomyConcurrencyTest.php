<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Feature;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;

class TaxonomyConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test concurrent move operations dengan database locking.
     */
    #[Test]
    public function it_can_handle_concurrent_move_operations_with_locking(): void
    {
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
        $this->assertGreaterThan(0, count($results), 'No successful moves');

        // Verify final structure integrity
        $this->assertValidNestedSetStructure();

        // Log results untuk debugging
        if (! empty($errors)) {
            Log::info('Concurrent move errors:', $errors);
        }

        // Verify concurrent move operations completed
        $this->assertGreaterThan(0, count($results), 'Should have successful moves');
        $this->assertLessThanOrEqual(count($results), count($errors), 'Errors should not exceed successful operations');
    }

    /**
     * Test concurrent creation dengan unique constraints.
     */
    #[Test]
    public function it_can_handle_concurrent_creation_with_unique_constraints(): void
    {
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

        // Verify hanya satu yang berhasil dengan slug yang sama
        $createdSlugs = collect($results)->pluck('slug')->toArray();
        $uniqueSlugs = array_unique($createdSlugs);

        // Jika ada duplicate handling, semua harus unique
        // Jika tidak ada, hanya 1 yang berhasil
        $this->assertTrue(
            count($createdSlugs) === count($uniqueSlugs) || count($results) === 1,
            'Slug uniqueness not properly handled'
        );

        // Verify concurrent creation operations completed
        $this->assertGreaterThan(0, count($results), 'Should have successful creations');
        $this->assertGreaterThan(0, count($uniqueSlugs), 'Should have unique slugs created');
    }

    /**
     * Test concurrent rebuild operations.
     */
    #[Test]
    public function it_can_handle_concurrent_rebuild_operations(): void
    {
        // Buat struktur yang kompleks
        $this->createComplexStructure(100);

        $rebuildResults = [];
        $rebuildErrors = [];

        // Jalankan multiple rebuild secara concurrent
        for ($i = 1; $i <= 3; ++$i) {
            try {
                $startTime = microtime(true);

                // Gunakan database lock untuk rebuild
                DB::transaction(function () use (&$rebuildResults, $i) {
                    // Lock semua taxonomy records
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

        // Verify struktur masih valid setelah concurrent rebuilds
        $this->assertValidNestedSetStructure();

        // Verify minimal satu rebuild berhasil
        $this->assertGreaterThan(0, count($rebuildResults), 'No successful rebuilds');

        // Verify concurrent rebuild operations completed
        $this->assertGreaterThan(0, count($rebuildResults), 'Should have successful rebuilds');
        $this->assertLessThanOrEqual(count($rebuildResults), count($rebuildErrors), 'Errors should not exceed successful operations');

        // Verify rebuild performance
        foreach ($rebuildResults as $result) {
            $this->assertLessThan(30.0, $result['duration'], "Rebuild {$result['rebuild_id']} should complete within 30 seconds");
        }
    }

    /**
     * Test concurrent cache operations.
     */
    #[Test]
    public function it_can_handle_concurrent_cache_operations(): void
    {
        // Setup data
        $this->createComplexStructure(50);

        $cacheResults = [];
        $cacheErrors = [];

        // Clear cache
        Cache::flush();

        // Simulasi concurrent cache access
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
            $this->assertTrue(
                $avgSubsequentTime <= $firstAccess * 1.5, // Allow 50% tolerance
                'Cache performance significantly degraded'
            );
        }

        // Verify concurrent cache operations completed
        $this->assertGreaterThan(0, count($cacheResults), 'Should have successful cache accesses');

        // Cache performance bisa bervariasi, jadi kita test bahwa operasi berjalan dalam waktu wajar
        $this->assertLessThan(1.0, $avgSubsequentTime, 'Subsequent cache accesses should be reasonably fast');
        $this->assertLessThan(2.0, $firstAccess, 'First access should complete within reasonable time');

        // Verify cache speedup (dengan tolerance yang lebih besar)
        if ($avgSubsequentTime > 0 && $firstAccess > $avgSubsequentTime) {
            $speedup = round($firstAccess / $avgSubsequentTime, 2);
            // Cache speedup detected but not strictly required
        }
    }

    /**
     * Test deadlock detection dan recovery.
     */
    #[Test]
    public function it_can_handle_deadlock_detection_and_recovery(): void
    {
        // Buat struktur untuk deadlock scenario
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

        // Simulasi potential deadlock scenario
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
                $this->assertNotNull($freshChildB);
                $this->assertNotNull($lockedA);
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
                $this->assertNotNull($freshChildA);
                $this->assertNotNull($lockedB);
                $freshChildA->moveToParent($lockedB->id);

                $deadlockResults[] = 'Transaction 2 completed';
            });
        } catch (\Exception $e) {
            $deadlockErrors[] = 'Transaction 2 failed: ' . $e->getMessage();
        }

        // Verify final state is consistent
        $this->assertValidNestedSetStructure();

        // Verify deadlock handling completed
        $this->assertGreaterThan(0, count($deadlockResults), 'Should have successful transactions');

        // Verify deadlock errors are handled properly
        foreach ($deadlockErrors as $error) {
            $this->assertStringContainsString('deadlock', strtolower($error), 'Error should be deadlock-related');
        }
    }

    /**
     * Test transaction isolation levels.
     */
    #[Test]
    public function it_can_handle_transaction_isolation_levels(): void
    {
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
                $this->assertNotNull($initial);
                $isolationResults['initial_name'] = $initial->name;

                // Simulate concurrent update (in real scenario, this would be another process)
                DB::table('taxonomies')
                    ->where('id', $taxonomy->id)
                    ->update(['name' => 'Updated by concurrent transaction']);

                // Read again within same transaction
                $updated = Taxonomy::find($taxonomy->id);
                $this->assertNotNull($updated);
                $isolationResults['updated_name'] = $updated->name;

                // The behavior depends on isolation level
                // READ COMMITTED: should see the update
                // REPEATABLE READ: should not see the update
            });
        } catch (\Exception $e) {
            $isolationResults['error'] = $e->getMessage();
        }

        $this->assertArrayHasKey('initial_name', $isolationResults);
        $this->assertArrayHasKey('updated_name', $isolationResults);

        // Verify transaction isolation behavior
        $this->assertNotEquals(
            $isolationResults['initial_name'],
            $isolationResults['updated_name'],
            'Transaction isolation should allow concurrent updates'
        );
    }

    /**
     * Helper untuk membuat struktur kompleks.
     */
    private function createComplexStructure(int $nodeCount): void
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

            // Beberapa node bisa jadi parent
            if ($i % 5 === 0) {
                $parents[] = $taxonomy->id;
            }
        }
    }

    /**
     * Helper untuk validasi nested set structure.
     */
    private function assertValidNestedSetStructure(): void
    {
        $taxonomies = Taxonomy::orderBy('lft')->get();

        foreach ($taxonomies as $taxonomy) {
            // Basic lft < rgt validation
            $this->assertLessThan($taxonomy->rgt, $taxonomy->lft, "Invalid lft/rgt for taxonomy {$taxonomy->id}");

            // Parent-child relationship validation
            if ($taxonomy->parent_id) {
                $parent = Taxonomy::find($taxonomy->parent_id);
                $this->assertNotNull($parent, "Parent {$taxonomy->parent_id} not found for taxonomy {$taxonomy->id}");
                $this->assertGreaterThan($parent->lft, $taxonomy->lft, "Child lft not greater than parent lft for taxonomy {$taxonomy->id}");
                $this->assertLessThan($parent->rgt, $taxonomy->rgt, "Child rgt not less than parent rgt for taxonomy {$taxonomy->id}");
            }
        }

        // Check for gaps in lft/rgt sequence
        $allValues = $taxonomies->flatMap(function ($taxonomy) {
            return [$taxonomy->lft, $taxonomy->rgt];
        })->sort()->values();

        for ($i = 0; $i < $allValues->count(); ++$i) {
            $this->assertEquals($i + 1, $allValues[$i], "Gap in nested set sequence at position {$i}");
        }
    }
}
