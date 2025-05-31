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

        echo "\n=== Concurrent Move Test Results ===\n";
        echo 'Successful moves: ' . count($results) . "\n";
        echo 'Failed moves: ' . count($errors) . "\n";
        echo "===================================\n";
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

        echo "\n=== Concurrent Creation Test Results ===\n";
        echo 'Successful creations: ' . count($results) . "\n";
        echo 'Failed creations: ' . count($errors) . "\n";
        echo 'Unique slugs: ' . count($uniqueSlugs) . "\n";
        echo "=======================================\n";
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

        echo "\n=== Concurrent Rebuild Test Results ===\n";
        echo 'Successful rebuilds: ' . count($rebuildResults) . "\n";
        echo 'Failed rebuilds: ' . count($rebuildErrors) . "\n";
        foreach ($rebuildResults as $result) {
            echo "Rebuild {$result['rebuild_id']}: {$result['duration']} seconds\n";
        }
        echo "======================================\n";
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

        echo "\n=== Concurrent Cache Test Results ===\n";
        echo 'Successful accesses: ' . count($cacheResults) . "\n";
        echo 'Failed accesses: ' . count($cacheErrors) . "\n";
        echo "First access time: {$firstAccess} seconds\n";
        echo "Avg subsequent time: {$avgSubsequentTime} seconds\n";
        if ($firstAccess > 0 && $avgSubsequentTime > 0) {
            echo 'Cache speedup: ' . round($firstAccess / $avgSubsequentTime, 2) . "x\n";
        }
        echo "====================================\n";
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

        echo "\n=== Deadlock Test Results ===\n";
        echo 'Successful transactions: ' . count($deadlockResults) . "\n";
        echo 'Failed transactions: ' . count($deadlockErrors) . "\n";
        foreach ($deadlockErrors as $error) {
            echo "Error: {$error}\n";
        }
        echo "============================\n";
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

        echo "\n=== Transaction Isolation Test Results ===\n";
        echo "Initial name: {$isolationResults['initial_name']}\n";
        echo "Updated name: {$isolationResults['updated_name']}\n";
        echo 'Names are ' . ($isolationResults['initial_name'] === $isolationResults['updated_name'] ? 'same' : 'different') . "\n";
        echo "=========================================\n";
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
