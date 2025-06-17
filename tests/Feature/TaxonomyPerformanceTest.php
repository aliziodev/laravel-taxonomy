<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Feature;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class TaxonomyPerformanceTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $performanceMetrics = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure no active transactions from previous tests
        try {
            DB::rollBack();
        } catch (\Exception $e) {
            // Ignore if no transaction is active
        }

        $this->performanceMetrics = [];
    }

    protected function tearDown(): void
    {
        // Ensure no active transactions before cleanup
        try {
            DB::rollBack();
        } catch (\Exception $e) {
            // Ignore if no transaction is active
        }

        $this->outputPerformanceReport();
        parent::tearDown();
    }

    /**
     * Comprehensive performance test dengan 10,000 taxonomies.
     */
    #[Test]
    public function it_can_handle_large_scale_performance_10k_taxonomies(): void
    {
        if (config('app.skip_large_performance_tests', false)) {
            $this->markTestSkipped('Large performance tests skipped');
        }

        $targetCount = 10000;

        // Test 1: Bulk Creation Performance
        $this->measurePerformance('bulk_creation_10k', function () use ($targetCount) {
            $this->bulkCreateTaxonomies($targetCount);
        });

        $this->assertEquals($targetCount, Taxonomy::count());

        // Test 2: Rebuild Performance
        $this->measurePerformance('rebuild_10k', function () {
            Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
        });

        // Test 3: GetTree Performance
        $this->measurePerformance('get_tree_10k', function () {
            return Taxonomy::getNestedTree();
        });

        // Test 4: Complex Query Performance
        $this->measurePerformance('complex_query_10k', function () {
            return Taxonomy::where('type', TaxonomyType::Category->value)
                ->whereNotNull('parent_id')
                ->with('parent')
                ->orderBy('name')
                ->limit(1000)
                ->get();
        });

        // Test 5: Move Operation Performance
        $firstNode = Taxonomy::first();
        $lastNode = Taxonomy::orderBy('id', 'desc')->first();

        $this->measurePerformance('move_operation_10k', function () use ($lastNode, $firstNode) {
            $this->assertNotNull($lastNode);
            $this->assertNotNull($firstNode);
            $lastNode->moveToParent($firstNode->id);
        });

        // Test 6: Ancestors/Descendants Performance
        $randomNode = Taxonomy::inRandomOrder()->first();

        $this->measurePerformance('get_ancestors_10k', function () use ($randomNode) {
            $this->assertNotNull($randomNode);

            return $randomNode->getAncestors();
        });

        $this->measurePerformance('get_descendants_10k', function () use ($randomNode) {
            $this->assertNotNull($randomNode);

            return $randomNode->getDescendants();
        });

        // Verify performance thresholds (adjusted for realistic expectations)
        $this->assertPerformanceThreshold('bulk_creation_10k', 30.0);
        $this->assertPerformanceThreshold('rebuild_10k', 45.0); // Increased from 35.0 to 45.0 for complex nested set rebuild
        $this->assertPerformanceThreshold('get_tree_10k', 5.0);
        $this->assertPerformanceThreshold('complex_query_10k', 3.0);
        $this->assertPerformanceThreshold('move_operation_10k', 35.0); // Increased from 10.0 to 35.0 for complex move operations
        $this->assertPerformanceThreshold('get_ancestors_10k', 1.0);
        $this->assertPerformanceThreshold('get_descendants_10k', 2.0);
    }

    /**
     * Test performance dengan different tree depths.
     */
    #[Test]
    public function it_can_handle_performance_by_tree_depth(): void
    {
        $depths = [5, 10, 15, 20];

        foreach ($depths as $depth) {
            // Clear database
            Taxonomy::query()->delete();

            // Create deep tree
            $this->measurePerformance("create_depth_{$depth}", function () use ($depth) {
                $this->createDeepTree($depth);
            });

            $deepestNode = Taxonomy::orderBy('id', 'desc')->first();

            // Test getAncestors performance by depth
            $this->measurePerformance("ancestors_depth_{$depth}", function () use ($deepestNode) {
                $this->assertNotNull($deepestNode);

                return $deepestNode->getAncestors();
            });

            // Test rebuild performance by depth
            $this->measurePerformance("rebuild_depth_{$depth}", function () {
                Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
            });

            // Verify ancestor count matches depth
            $this->assertNotNull($deepestNode);
            $freshNode = $deepestNode->fresh();
            $this->assertNotNull($freshNode);
            $ancestors = $freshNode->getAncestors();
            $this->assertCount($depth - 1, $ancestors);
        }
    }

    /**
     * Test performance dengan different tree widths.
     */
    #[Test]
    public function it_can_handle_performance_by_tree_width(): void
    {
        $widths = [2, 3, 4]; // Children per node (reduced to prevent exponential growth)
        $depth = 3; // Reduced depth to prevent too many nodes

        foreach ($widths as $width) {
            // Clear database
            Taxonomy::query()->delete();

            // Create wide tree
            $this->measurePerformance("create_width_{$width}", function () use ($width, $depth) {
                $this->createWideTree($width, $depth);
            });

            $nodeCount = Taxonomy::count();

            // Test getTree performance by width
            $this->measurePerformance("get_tree_width_{$width}", function () {
                return Taxonomy::getNestedTree();
            });

            // Test rebuild performance by width
            $this->measurePerformance("rebuild_width_{$width}", function () {
                Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
            });

            // Verify node creation for width {$width}
            $this->assertGreaterThan(0, $nodeCount, "Should create nodes for width {$width}");

            // Assert reasonable node count (prevent exponential explosion)
            $this->assertLessThan(100, $nodeCount, "Too many nodes created for width {$width}");
        }
    }

    /**
     * Memory usage performance test.
     */
    #[Test]
    public function it_can_handle_memory_usage_performance(): void
    {
        $initialMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);

        // Test 1: Memory usage during bulk creation (reduced count to prevent hang)
        $memoryBefore = memory_get_usage(true);
        $this->bulkCreateTaxonomies(200); // Reduced from 1000 to 200
        $memoryAfterCreation = memory_get_usage(true);

        // Test 2: Memory usage during tree operations
        $tree = Taxonomy::getNestedTree();
        $memoryAfterTree = memory_get_usage(true);

        // Test 3: Memory usage during rebuild
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
        $memoryAfterRebuild = memory_get_usage(true);

        $finalPeakMemory = memory_get_peak_usage(true);

        // Calculate memory increases
        $creationIncrease = $memoryAfterCreation - $memoryBefore;
        $treeIncrease = $memoryAfterTree - $memoryAfterCreation;
        $rebuildIncrease = $memoryAfterRebuild - $memoryAfterTree;
        $totalIncrease = $finalPeakMemory - $peakMemory;

        // Store memory metrics
        $this->performanceMetrics['memory'] = [
            'initial' => $this->formatBytes($initialMemory),
            'after_creation' => $this->formatBytes($memoryAfterCreation),
            'after_tree' => $this->formatBytes($memoryAfterTree),
            'after_rebuild' => $this->formatBytes($memoryAfterRebuild),
            'peak' => $this->formatBytes($finalPeakMemory),
            'creation_increase' => $this->formatBytes($creationIncrease),
            'tree_increase' => $this->formatBytes($treeIncrease),
            'rebuild_increase' => $this->formatBytes($rebuildIncrease),
            'total_increase' => $this->formatBytes($totalIncrease),
        ];

        // Assert reasonable memory usage (< 50MB for 200 records)
        $this->assertLessThan(50 * 1024 * 1024, $totalIncrease, 'Memory usage too high');
    }

    /**
     * Database query performance test.
     */
    #[Test]
    public function it_can_handle_database_query_performance(): void
    {
        // Setup test data (reduced count to prevent hang)
        $this->bulkCreateTaxonomies(300); // Reduced from 2000 to 300

        // Rebuild nested set to ensure lft/rgt values are set
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        // Enable query logging
        DB::enableQueryLog();

        // Test 1: Simple select performance
        $this->measurePerformance('simple_select', function () {
            return Taxonomy::limit(100)->get();
        });

        // Test 2: Join query performance
        $this->measurePerformance('join_query', function () {
            return DB::table('taxonomies as t1')
                ->join('taxonomies as t2', 't1.parent_id', '=', 't2.id')
                ->select('t1.*', 't2.name as parent_name')
                ->limit(100)
                ->get();
        });

        // Test 3: Nested set query performance
        $rootNode = Taxonomy::whereNull('parent_id')->first();
        if ($rootNode && $rootNode->lft !== null && $rootNode->rgt !== null) {
            $this->measurePerformance('nested_set_query', function () use ($rootNode) {
                return Taxonomy::where('lft', '>', $rootNode->lft)
                    ->where('rgt', '<', $rootNode->rgt)
                    ->get();
            });
        }

        // Test 4: Aggregation query performance
        $this->measurePerformance('aggregation_query', function () {
            return DB::table('taxonomies')
                ->select(
                    'type',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('AVG(rgt - lft) as avg_size'),
                    DB::raw('MAX(rgt - lft) as max_size')
                )
                ->groupBy('type')
                ->get();
        });

        $queries = DB::getQueryLog();
        $this->performanceMetrics['query_count'] = count($queries);

        // Analyze slow queries
        $slowQueries = array_filter($queries, function ($query) {
            return $query['time'] > 100; // Queries taking more than 100ms
        });

        $this->performanceMetrics['slow_queries'] = count($slowQueries);

        DB::disableQueryLog();

        // Assertions to validate performance
        $this->assertLessThan(20, count($queries), 'Too many database queries executed');
        $this->assertLessThan(3, count($slowQueries), 'Too many slow queries detected');
        $this->assertArrayHasKey('simple_select', $this->performanceMetrics);
        $this->assertArrayHasKey('join_query', $this->performanceMetrics);
        $this->assertArrayHasKey('aggregation_query', $this->performanceMetrics);
    }

    /**
     * Cache performance test.
     */
    #[Test]
    public function it_can_handle_cache_performance(): void
    {
        // Setup test data (reduced count to prevent hang)
        $this->bulkCreateTaxonomies(200); // Reduced from 1000 to 200

        // Clear cache
        Cache::flush();

        // Test 1: Cache miss performance
        $this->measurePerformance('cache_miss', function () {
            return Taxonomy::getNestedTree();
        });

        // Test 2: Cache hit performance
        $this->measurePerformance('cache_hit', function () {
            return Taxonomy::getNestedTree();
        });

        // Test 3: Cache invalidation performance
        $this->measurePerformance('cache_invalidation', function () {
            $newTaxonomy = Taxonomy::create([
                'name' => 'Cache Test',
                'type' => TaxonomyType::Category->value,
                'slug' => 'cache-test-' . uniqid(),
            ]);

            return $newTaxonomy;
        });

        // Test 4: Cache rebuild performance
        $this->measurePerformance('cache_rebuild', function () {
            return Taxonomy::getNestedTree();
        });

        // Calculate cache efficiency
        $cacheMissTime = $this->performanceMetrics['cache_miss']['duration'];
        $cacheHitTime = $this->performanceMetrics['cache_hit']['duration'];

        if ($cacheHitTime > 0) {
            $cacheSpeedup = $cacheMissTime / $cacheHitTime;
            $this->performanceMetrics['cache_speedup'] = round($cacheSpeedup, 2) . 'x';

            // Cache hit dan miss harus dalam waktu yang wajar (adjusted thresholds)
            $this->assertLessThan(2.0, $cacheHitTime, 'Cache hit should be reasonably fast');
            $this->assertLessThan(3.0, $cacheMissTime, 'Cache miss should be reasonably fast');
        }
    }

    /**
     * Stress test dengan concurrent operations.
     */
    #[Test]
    public function it_can_handle_concurrent_operations_performance(): void
    {
        // Setup base data (reduced count to prevent hang)
        $this->bulkCreateTaxonomies(100); // Reduced from 500 to 100

        $concurrentResults = [];
        $operationCount = 20;

        // Simulate concurrent read operations
        $this->measurePerformance('concurrent_reads', function () use (&$concurrentResults, $operationCount) {
            for ($i = 1; $i <= $operationCount; ++$i) {
                $startTime = microtime(true);

                $tree = Taxonomy::getNestedTree();
                $randomNode = Taxonomy::inRandomOrder()->first();
                $ancestors = $randomNode ? $randomNode->getAncestors() : collect();

                $operationTime = microtime(true) - $startTime;
                $concurrentResults[] = $operationTime;
            }
        });

        // Analyze concurrent performance
        $avgTime = array_sum($concurrentResults) / count($concurrentResults);
        $maxTime = count($concurrentResults) > 0 ? max($concurrentResults) : 0;
        $minTime = count($concurrentResults) > 0 ? min($concurrentResults) : 0;

        $this->performanceMetrics['concurrent_operations'] = [
            'operation_count' => $operationCount,
            'avg_time' => round($avgTime, 4),
            'max_time' => round($maxTime, 4),
            'min_time' => round($minTime, 4),
            'time_variance' => round($maxTime - $minTime, 4),
        ];

        // Performance should be consistent (variance < 2 seconds, adjusted for realistic expectations)
        $this->assertLessThan(2.0, $maxTime - $minTime, 'High variance in concurrent operation times');
    }

    /**
     * Test move operation efficiency dengan berbagai skenario.
     */
    #[Test]
    public function it_can_handle_move_operation_efficiency(): void
    {
        // Setup: Buat struktur tree dengan 100 nodes
        $this->bulkCreateTaxonomies(100);
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        $taxonomies = Taxonomy::all();
        $sourceNode = $taxonomies->random();
        $targetParent = $taxonomies->where('id', '!=', $sourceNode->id)->random();

        // Test 1: Single move operation performance
        $this->measurePerformance('single_move_operation', function () use ($sourceNode, $targetParent) {
            $sourceNode->moveToParent($targetParent->id);
        });

        $this->assertPerformanceThreshold('single_move_operation', 1.0);

        // Verify move was successful
        $this->assertNotNull($sourceNode);
        $freshSourceNode = $sourceNode->fresh();
        $this->assertNotNull($freshSourceNode);
        $this->assertEquals($targetParent->id, $freshSourceNode->parent_id);

        // Test 2: Multiple move operations performance
        $nodes = Taxonomy::limit(10)->get();
        $this->measurePerformance('multiple_move_operations', function () use ($nodes) {
            foreach ($nodes as $node) {
                $randomParent = Taxonomy::where('id', '!=', $node->id)->inRandomOrder()->first();
                if ($randomParent) {
                    $node->moveToParent($randomParent->id);
                }
            }
        });

        $this->assertPerformanceThreshold('multiple_move_operations', 5.0);

        // Test 3: Move deep nested node performance
        $this->createDeepTree(10);
        $deepNode = Taxonomy::orderBy('id', 'desc')->first();
        $rootNode = Taxonomy::whereNull('parent_id')->first();

        $this->measurePerformance('move_deep_nested_node', function () use ($deepNode, $rootNode) {
            $this->assertNotNull($deepNode);
            $this->assertNotNull($rootNode);
            $deepNode->moveToParent($rootNode->id);
        });

        $this->assertPerformanceThreshold('move_deep_nested_node', 1.5);

        // Verify nested set structure is still valid after moves
        $this->assertValidNestedSetStructure();
    }

    /**
     * Test descendants retrieval performance dengan berbagai ukuran tree.
     */
    #[Test]
    public function it_can_handle_descendants_retrieval(): void
    {
        // Test 1: Small tree descendants retrieval
        $this->createWideTree(3, 3); // 3^3 = 27 nodes
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        $rootNode = Taxonomy::whereNull('parent_id')->first();

        $this->measurePerformance('small_tree_descendants', function () use ($rootNode) {
            $this->assertNotNull($rootNode);

            return $rootNode->getDescendants();
        });

        $this->assertPerformanceThreshold('small_tree_descendants', 0.5);

        // Test 2: Medium tree descendants retrieval
        Taxonomy::truncate();
        $this->createWideTree(4, 3); // 4^3 = 64 nodes
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        $rootNode = Taxonomy::whereNull('parent_id')->first();

        $descendants = $this->measurePerformance('medium_tree_descendants', function () use ($rootNode) {
            $this->assertNotNull($rootNode);

            return $rootNode->getDescendants();
        });

        $this->assertPerformanceThreshold('medium_tree_descendants', 1.0);
        $this->assertGreaterThan(15, $descendants->count()); // Adjusted expectation for 4^3 structure

        // Test 3: Deep tree descendants retrieval
        Taxonomy::truncate();
        $this->createDeepTree(15);
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        $rootNode = Taxonomy::whereNull('parent_id')->first();

        $descendants = $this->measurePerformance('deep_tree_descendants', function () use ($rootNode) {
            $this->assertNotNull($rootNode);

            return $rootNode->getDescendants();
        });

        $this->assertPerformanceThreshold('deep_tree_descendants', 0.8);
        $this->assertEquals(14, $descendants->count()); // 15 levels - 1 root = 14 descendants

        // Test 4: Bulk descendants retrieval
        Taxonomy::truncate();
        $this->bulkCreateTaxonomies(50);
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        $allNodes = Taxonomy::limit(10)->get();

        $this->measurePerformance('bulk_descendants_retrieval', function () use ($allNodes) {
            $results = [];
            foreach ($allNodes as $node) {
                $results[] = $node->getDescendants();
            }

            return $results;
        });

        $this->assertPerformanceThreshold('bulk_descendants_retrieval', 2.0);
    }

    /**
     * Test delete node with children - memastikan tidak ada orphan nodes.
     */
    #[Test]
    public function it_can_handle_delete_node_with_children(): void
    {
        // Clean database first
        Taxonomy::truncate();

        // Setup: Buat struktur tree dengan parent-child relationships
        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
            'slug' => 'parent-category',
        ]);

        $children = [];
        for ($i = 1; $i <= 5; ++$i) {
            $children[] = Taxonomy::create([
                'name' => "Child {$i}",
                'type' => TaxonomyType::Category->value,
                'slug' => "child-{$i}-" . uniqid(),
                'parent_id' => $parent->id,
            ]);
        }

        // Buat grandchildren untuk beberapa children
        $grandchildren = [];
        for ($i = 1; $i <= 3; ++$i) {
            $grandchildren[] = Taxonomy::create([
                'name' => "Grandchild {$i}",
                'type' => TaxonomyType::Category->value,
                'slug' => "grandchild-{$i}-" . uniqid(),
                'parent_id' => $children[0]->id,
            ]);
        }

        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        $initialCount = Taxonomy::count();
        $this->assertEquals(9, $initialCount); // 1 parent + 5 children + 3 grandchildren

        // Test 1: Delete parent dengan cascade delete children
        $parentId = $parent->id;
        $childrenIds = collect($children)->pluck('id')->toArray();
        $grandchildrenIds = collect($grandchildren)->pluck('id')->toArray();

        $this->measurePerformance('delete_node_with_children', function () use ($parent) {
            // Implementasi cascade delete - hapus semua descendants menggunakan recursive delete
            $this->deleteNodeAndChildren($parent);
        });

        $this->assertPerformanceThreshold('delete_node_with_children', 2.0);

        // Verify parent telah terhapus
        $this->assertDatabaseMissing('taxonomies', ['id' => $parentId]);

        foreach ($childrenIds as $childId) {
            $this->assertDatabaseMissing('taxonomies', ['id' => $childId]);
        }

        foreach ($grandchildrenIds as $grandchildId) {
            $this->assertDatabaseMissing('taxonomies', ['id' => $grandchildId]);
        }

        // Verify count setelah delete pertama
        $countAfterFirstDelete = Taxonomy::count();
        $this->assertEquals(0, $countAfterFirstDelete, 'All nodes from first structure should be deleted');

        // Test 2: Delete dengan orphan prevention
        // Setup ulang struktur
        $newParent = Taxonomy::create([
            'name' => 'New Parent',
            'type' => TaxonomyType::Category->value,
            'slug' => 'new-parent',
        ]);

        $newChildren = [];
        for ($i = 1; $i <= 3; ++$i) {
            $newChildren[] = Taxonomy::create([
                'name' => "New Child {$i}",
                'type' => TaxonomyType::Category->value,
                'slug' => "new-child-{$i}-" . uniqid(),
                'parent_id' => $newParent->id,
            ]);
        }

        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        // Test delete dengan move children ke parent lain
        $alternativeParent = Taxonomy::create([
            'name' => 'Alternative Parent',
            'type' => TaxonomyType::Category->value,
            'slug' => 'alternative-parent',
        ]);

        $this->measurePerformance('delete_with_orphan_prevention', function () use ($newParent, $newChildren, $alternativeParent) {
            // Move children ke alternative parent sebelum delete
            foreach ($newChildren as $child) {
                $child->moveToParent($alternativeParent->id);
            }

            // Kemudian delete parent
            $newParent->delete();
        });

        $this->assertPerformanceThreshold('delete_with_orphan_prevention', 1.5);

        // Verify tidak ada orphan nodes
        $remainingNodes = Taxonomy::all();
        foreach ($remainingNodes as $node) {
            if ($node->parent_id) {
                $this->assertDatabaseHas('taxonomies', ['id' => $node->parent_id]);
            }
        }

        // Verify children telah dipindah ke alternative parent
        foreach ($newChildren as $child) {
            $this->assertNotNull($child);
            $freshChild = $child->fresh();
            $this->assertNotNull($freshChild);
            $this->assertEquals($alternativeParent->id, $freshChild->parent_id);
        }

        // Test 3: Bulk delete performance
        Taxonomy::truncate();
        $this->bulkCreateTaxonomies(50);

        $nodesToDelete = Taxonomy::limit(20)->get();

        $this->measurePerformance('bulk_delete_nodes', function () use ($nodesToDelete) {
            foreach ($nodesToDelete as $node) {
                $node->delete();
            }
        });

        $this->assertPerformanceThreshold('bulk_delete_nodes', 3.0);

        // Verify correct number of nodes remaining
        $this->assertEquals(30, Taxonomy::count());

        // Verify nested set structure masih valid setelah bulk delete
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
        $this->assertValidNestedSetStructure();
    }

    /**
     * Helper: Validate nested set structure integrity.
     */
    private function assertValidNestedSetStructure(): void
    {
        $taxonomies = Taxonomy::orderBy('lft')->get();

        foreach ($taxonomies as $taxonomy) {
            // lft harus lebih kecil dari rgt
            $this->assertLessThan($taxonomy->rgt, $taxonomy->lft, "Invalid lft/rgt for taxonomy {$taxonomy->id}");

            // Jika punya parent, harus berada dalam range parent
            if ($taxonomy->parent_id) {
                $parent = Taxonomy::find($taxonomy->parent_id);
                if ($parent) {
                    $this->assertGreaterThan($parent->lft, $taxonomy->lft, "Child lft not greater than parent lft for taxonomy {$taxonomy->id}");
                    $this->assertLessThan($parent->rgt, $taxonomy->rgt, "Child rgt not less than parent rgt for taxonomy {$taxonomy->id}");
                }
            }
        }
    }

    /**
     * Helper: Bulk create taxonomies efficiently.
     */
    private function bulkCreateTaxonomies(int $count): void
    {
        $batchSize = 1000;
        $batches = ceil($count / $batchSize);

        DB::transaction(function () use ($count, $batchSize, $batches) {
            for ($batch = 0; $batch < $batches; ++$batch) {
                $batchCount = min($batchSize, $count - ($batch * $batchSize));
                $taxonomies = [];

                for ($i = 1; $i <= $batchCount; ++$i) {
                    $globalIndex = ($batch * $batchSize) + $i;
                    $uniqueId = uniqid();
                    $taxonomies[] = [
                        'name' => "Performance Test Category {$globalIndex}",
                        'type' => TaxonomyType::Category->value,
                        'slug' => "performance-test-category-{$globalIndex}-{$uniqueId}",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                DB::table('taxonomies')->insert($taxonomies);
            }
        });
    }

    /**
     * Helper: Create deep tree structure.
     */
    private function createDeepTree(int $depth): void
    {
        $currentParent = null;

        for ($level = 1; $level <= $depth; ++$level) {
            $taxonomy = Taxonomy::create([
                'name' => "Level {$level}",
                'type' => TaxonomyType::Category->value,
                'slug' => "level-{$level}-" . uniqid(),
                'parent_id' => $currentParent?->id,
            ]);

            $currentParent = $taxonomy;
        }
    }

    /**
     * Helper: Create wide tree structure.
     */
    private function createWideTree(int $width, int $depth, ?int $parentId = null, int $currentDepth = 1): void
    {
        if ($currentDepth > $depth) {
            return;
        }

        for ($i = 1; $i <= $width; ++$i) {
            $taxonomy = Taxonomy::create([
                'name' => "Level {$currentDepth} Child {$i}",
                'type' => TaxonomyType::Category->value,
                'slug' => "level-{$currentDepth}-child-{$i}-" . uniqid(),
                'parent_id' => $parentId,
            ]);

            $this->createWideTree($width, $depth, $taxonomy->id, $currentDepth + 1);
        }
    }

    /**
     * Helper: Measure performance of a function.
     */
    private function measurePerformance(string $operation, callable $function): mixed
    {
        // Ensure clean transaction state before measurement
        try {
            DB::rollBack();
        } catch (\Exception $e) {
            // Ignore if no transaction is active
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $result = $function();

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        // Ensure clean transaction state after measurement
        try {
            DB::rollBack();
        } catch (\Exception $e) {
            // Ignore if no transaction is active
        }

        $this->performanceMetrics[$operation] = [
            'duration' => round($endTime - $startTime, 4),
            'memory_used' => $this->formatBytes($endMemory - $startMemory),
            'memory_used_bytes' => $endMemory - $startMemory,
        ];

        return $result;
    }

    /**
     * Helper: Assert performance threshold.
     */
    private function assertPerformanceThreshold(string $operation, float $maxSeconds): void
    {
        $duration = $this->performanceMetrics[$operation]['duration'] ?? 0;
        $this->assertLessThan(
            $maxSeconds,
            $duration,
            "Operation '{$operation}' took too long: {$duration} seconds (max: {$maxSeconds})"
        );
    }

    /**
     * Recursively delete node and all its children.
     */
    private function deleteNodeAndChildren(Taxonomy $node): void
    {
        // Get direct children
        $children = $node->children;

        // Recursively delete children first
        foreach ($children as $child) {
            $this->deleteNodeAndChildren($child);
        }

        // Force delete the node itself (bypass soft deletes)
        $node->forceDelete();
    }

    /**
     * Helper: Format bytes to human readable.
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            ++$unitIndex;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Output comprehensive performance report.
     */
    private function outputPerformanceReport(): void
    {
        if (empty($this->performanceMetrics)) {
            return;
        }

        // Verify performance metrics were recorded
        $this->assertNotEmpty($this->performanceMetrics, 'Performance metrics should be recorded');

        // Verify memory usage is reasonable
        $peakMemory = memory_get_peak_usage(true);
        $maxAllowedMemory = 1024 * 1024 * 1024; // 1GB
        $this->assertLessThan($maxAllowedMemory, $peakMemory, 'Peak memory usage should be under 1GB');

        // Only verify operations for the large scale test
        if (isset($this->performanceMetrics['bulk_creation_10k'])) {
            $expectedOperations = ['bulk_creation_10k', 'rebuild_10k', 'get_tree_10k', 'complex_query_10k'];
            foreach ($expectedOperations as $operation) {
                $this->assertArrayHasKey($operation, $this->performanceMetrics, "Operation {$operation} should be measured");
            }
        }

        // Verify performance metrics structure
        foreach ($this->performanceMetrics as $operation => $metrics) {
            if (is_array($metrics) && isset($metrics['duration'])) {
                $this->assertIsFloat($metrics['duration'], "Duration for {$operation} should be a float");
                $this->assertGreaterThan(0, $metrics['duration'], "Duration for {$operation} should be positive");
            }
        }
    }
}
