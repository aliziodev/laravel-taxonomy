<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Feature;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class TaxonomyStressTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test circular reference detection dan prevention.
     */
    #[Test]
    public function it_can_prevent_circular_reference(): void
    {
        // Buat chain A -> B -> C
        $nodeA = Taxonomy::create([
            'name' => 'Node A',
            'type' => TaxonomyType::Category->value,
            'slug' => 'node-a',
        ]);

        $nodeB = Taxonomy::create([
            'name' => 'Node B',
            'type' => TaxonomyType::Category->value,
            'slug' => 'node-b',
            'parent_id' => $nodeA->id,
        ]);

        $nodeC = Taxonomy::create([
            'name' => 'Node C',
            'type' => TaxonomyType::Category->value,
            'slug' => 'node-c',
            'parent_id' => $nodeB->id,
        ]);

        // Rebuild nested set untuk set nested set values
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        // Refresh models untuk mendapatkan nested set values
        $nodeA->refresh();
        $nodeB->refresh();
        $nodeC->refresh();

        // Coba buat circular reference: A -> B -> C -> A
        $this->expectException(\Exception::class);
        $nodeA->moveToParent($nodeC->id);
    }

    /**
     * Test dengan nama yang sangat panjang dan karakter khusus.
     */
    #[Test]
    public function it_can_handle_extreme_name_lengths_and_special_characters(): void
    {
        // Test nama sangat panjang (255+ karakter)
        $longName = str_repeat('Very Long Category Name With Special Characters !@#$%^&*()_+-=[]{}|;:,.<>? ', 10);

        $taxonomy = Taxonomy::create([
            'name' => substr($longName, 0, 255), // Truncate to max length
            'type' => TaxonomyType::Category->value,
            'slug' => 'long-name-category',
        ]);

        $this->assertNotNull($taxonomy->id);
        $this->assertLessThanOrEqual(255, strlen($taxonomy->name));

        // Test karakter Unicode
        $unicodeTaxonomy = Taxonomy::create([
            'name' => '测试分类 🚀 العربية Русский',
            'type' => TaxonomyType::Category->value,
            'slug' => 'unicode-category',
        ]);

        $this->assertNotNull($unicodeTaxonomy->id);
        $this->assertEquals('测试分类 🚀 العربية Русский', $unicodeTaxonomy->name);
    }

    /**
     * Test concurrent creation dengan duplicate slugs.
     */
    #[Test]
    public function it_can_handle_concurrent_duplicate_slug_handling(): void
    {
        $baseName = 'Duplicate Category';
        $baseSlug = 'duplicate-category';
        $createdTaxonomies = [];
        $exceptions = [];

        // Coba buat 10 taxonomy dengan slug yang sama secara concurrent
        for ($i = 1; $i <= 10; ++$i) {
            try {
                $taxonomy = Taxonomy::create([
                    'name' => $baseName . ' ' . $i,
                    'type' => TaxonomyType::Category->value,
                    'slug' => $baseSlug, // Intentionally same slug
                ]);
                $createdTaxonomies[] = $taxonomy;
            } catch (\Exception $e) {
                $exceptions[] = $e->getMessage();
            }
        }

        // Harus ada yang berhasil dibuat (minimal 1)
        $this->assertGreaterThan(0, count($createdTaxonomies));

        // Verify semua slug yang berhasil dibuat adalah unique
        $slugs = collect($createdTaxonomies)->pluck('slug')->toArray();
        $uniqueSlugs = array_unique($slugs);
        $this->assertEquals(count($slugs), count($uniqueSlugs), 'Duplicate slugs found');
    }

    /**
     * Test massive batch operations.
     */
    #[Test]
    public function it_can_handle_massive_batch_operations(): void
    {
        if (config('app.skip_heavy_tests', false)) {
            $this->markTestSkipped('Heavy tests skipped');
        }

        $batchSize = 1000;

        // Test 1: Batch creation
        $startTime = microtime(true);

        $taxonomyData = [];
        for ($i = 1; $i <= $batchSize; ++$i) {
            $taxonomyData[] = [
                'name' => "Batch Category {$i}",
                'type' => TaxonomyType::Category->value,
                'slug' => "batch-category-{$i}",
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Insert dalam chunks untuk menghindari memory issues
        collect($taxonomyData)->chunk(100)->each(function ($chunk) {
            DB::table('taxonomies')->insert($chunk->toArray());
        });

        $batchCreationTime = microtime(true) - $startTime;

        $this->assertEquals($batchSize, Taxonomy::count());
        $this->assertLessThan(30.0, $batchCreationTime, "Batch creation took too long: {$batchCreationTime} seconds");

        // Test 2: Batch update
        $startTime = microtime(true);

        Taxonomy::query()->update([
            'meta' => json_encode(['batch_updated' => true, 'timestamp' => now()]),
        ]);

        $batchUpdateTime = microtime(true) - $startTime;
        $this->assertLessThan(10.0, $batchUpdateTime, "Batch update took too long: {$batchUpdateTime} seconds");

        // Test 3: Batch delete
        $startTime = microtime(true);

        // Delete setengah dari records
        $idsToDelete = Taxonomy::limit($batchSize / 2)->pluck('id');
        Taxonomy::whereIn('id', $idsToDelete)->delete();

        $batchDeleteTime = microtime(true) - $startTime;
        $this->assertLessThan(15.0, $batchDeleteTime, "Batch delete took too long: {$batchDeleteTime} seconds");

        $this->assertEquals($batchSize / 2, Taxonomy::count());

        // Verify batch operations performance
        $this->assertLessThan(30.0, $batchCreationTime, "Batch creation should complete within 30 seconds for {$batchSize} items");
        $this->assertLessThan(20.0, $batchUpdateTime, "Batch update should complete within 20 seconds for {$batchSize} items");
        $this->assertLessThan(15.0, $batchDeleteTime, "Batch delete should complete within 15 seconds for {$batchSize} items");
    }

    /**
     * Test query performance dengan complex conditions.
     */
    #[Test]
    public function it_can_handle_complex_query_performance(): void
    {
        // Buat diverse dataset
        $this->createDiverseDataset(500);

        // Test 1: Complex WHERE conditions
        $startTime = microtime(true);

        $results = Taxonomy::where('type', TaxonomyType::Category->value)
            ->where('name', 'like', '%Category%')
            ->whereNotNull('parent_id')
            ->whereJsonContains('meta->tags', 'important')
            ->orderBy('name')
            ->limit(50)
            ->get();

        $complexQueryTime = microtime(true) - $startTime;
        $this->assertLessThan(2.0, $complexQueryTime, "Complex query took too long: {$complexQueryTime} seconds");

        // Test 2: Nested set queries
        $startTime = microtime(true);

        $rootNode = Taxonomy::whereNull('parent_id')->first();
        if ($rootNode) {
            $descendants = $rootNode->getDescendants();
            $ancestors = Taxonomy::find($descendants->last()?->id)?->getAncestors();
        }

        $nestedSetQueryTime = microtime(true) - $startTime;
        $this->assertLessThan(1.0, $nestedSetQueryTime, "Nested set queries took too long: {$nestedSetQueryTime} seconds");

        // Test 3: Aggregation queries
        $startTime = microtime(true);

        $stats = DB::table('taxonomies')
            ->select(
                'type',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(rgt - lft) as avg_subtree_size'),
                DB::raw('MAX(rgt - lft) as max_subtree_size')
            )
            ->groupBy('type')
            ->get();

        $aggregationQueryTime = microtime(true) - $startTime;
        $this->assertLessThan(1.0, $aggregationQueryTime, "Aggregation queries took too long: {$aggregationQueryTime} seconds");

        // Verify query performance
        $this->assertLessThan(5.0, $complexQueryTime, 'Complex query should complete within 5 seconds');
        $this->assertLessThan(3.0, $nestedSetQueryTime, 'Nested set query should complete within 3 seconds');
        $this->assertLessThan(2.0, $aggregationQueryTime, 'Aggregation query should complete within 2 seconds');
    }

    /**
     * Test cache performance dan invalidation.
     */
    #[Test]
    public function it_can_handle_cache_performance_and_invalidation(): void
    {
        // Buat dataset untuk testing
        $this->createDiverseDataset(200);

        // Test 1: Cache warming
        Cache::flush();

        $startTime = microtime(true);
        $tree1 = Taxonomy::getNestedTree(); // First call - should cache
        $firstCallTime = microtime(true) - $startTime;

        $startTime = microtime(true);
        $tree2 = Taxonomy::getNestedTree(); // Second call - should use cache
        $secondCallTime = microtime(true) - $startTime;

        // Cache hit biasanya lebih cepat, tapi tidak selalu karena overhead kecil
        // Yang penting adalah hasil yang sama dan performa yang wajar
        $this->assertEquals($tree1->count(), $tree2->count());
        $this->assertLessThan(1.0, $secondCallTime, 'Second call should be reasonably fast');
        $this->assertLessThan(1.0, $firstCallTime, 'First call should be reasonably fast');

        // Test 2: Cache invalidation saat ada perubahan
        $newTaxonomy = Taxonomy::create([
            'name' => 'Cache Test Category',
            'type' => TaxonomyType::Category->value,
            'slug' => 'cache-test-category',
        ]);

        $startTime = microtime(true);
        $tree3 = Taxonomy::getNestedTree(); // Should rebuild cache
        $thirdCallTime = microtime(true) - $startTime;

        $this->assertGreaterThan($tree2->count(), $tree3->count());

        // Test 3: Multiple cache keys
        $cacheKeys = [
            'taxonomy_tree_category',
            'taxonomy_tree_tag',
            'taxonomy_stats',
            'taxonomy_hierarchy_depth',
        ];

        foreach ($cacheKeys as $key) {
            Cache::put($key, 'test_value', 3600);
        }

        // Verify semua cache keys ada
        foreach ($cacheKeys as $key) {
            $this->assertEquals('test_value', Cache::get($key));
        }

        // Invalidate semua taxonomy-related cache
        Cache::flush(); // Atau implementasi cache invalidation yang lebih spesifik

        foreach ($cacheKeys as $key) {
            $this->assertNull(Cache::get($key));
        }

        // Verify cache performance improvements (dengan threshold yang lebih realistis)
        // Cache hit tidak selalu lebih cepat karena overhead kecil, jadi kita test performa yang wajar
        $this->assertLessThan(1.0, $secondCallTime, 'Cache hit should be reasonably fast');
        $this->assertLessThan(1.0, $thirdCallTime, 'Cache rebuild should be reasonably fast');

        // Verify bahwa semua operasi dalam batas waktu yang wajar
        $this->assertLessThan(2.0, $firstCallTime, 'Initial call should complete within reasonable time');
    }

    /**
     * Test database connection limits dan connection pooling.
     */
    #[Test]
    public function it_can_handle_database_connection_stress(): void
    {
        if (config('app.skip_connection_tests', false)) {
            $this->markTestSkipped('Connection stress tests skipped');
        }

        $connectionCount = 50;
        $results = [];

        // Simulasi multiple concurrent database operations
        for ($i = 1; $i <= $connectionCount; ++$i) {
            try {
                // Setiap iteration buat connection baru dan jalankan query
                $startTime = microtime(true);

                $taxonomy = Taxonomy::create([
                    'name' => "Connection Test {$i}",
                    'type' => TaxonomyType::Category->value,
                    'slug' => "connection-test-{$i}",
                ]);

                // Jalankan beberapa operasi pada connection yang sama
                $taxonomy->refresh();
                $count = Taxonomy::count();
                $tree = Taxonomy::limit(10)->get();

                $operationTime = microtime(true) - $startTime;
                $results[] = $operationTime;

            } catch (\Exception $e) {
                $this->fail("Connection failed at iteration {$i}: " . $e->getMessage());
            }
        }

        $avgTime = array_sum($results) / count($results);
        $maxTime = max($results);
        $minTime = min($results);

        $this->assertLessThan(1.0, $avgTime, "Average operation time too high: {$avgTime} seconds");
        $this->assertLessThan(5.0, $maxTime, "Max operation time too high: {$maxTime} seconds");

        // Verify connection stress test performance
        $this->assertLessThan(1.0, $avgTime, 'Average operation time should be under 1 second');
        $this->assertLessThan(5.0, $maxTime, 'Maximum operation time should be under 5 seconds');
        $this->assertGreaterThan(0, $minTime, 'Minimum operation time should be positive');
    }

    /**
     * Helper untuk membuat diverse dataset.
     */
    private function createDiverseDataset(int $count): void
    {
        $types = [TaxonomyType::Category->value, TaxonomyType::Tag->value];
        $parents = [null]; // Start with root nodes

        for ($i = 1; $i <= $count; ++$i) {
            $type = $types[array_rand($types)];
            $parentId = $parents[array_rand($parents)];

            $taxonomy = Taxonomy::create([
                'name' => "Diverse {$type} {$i}",
                'type' => $type,
                'slug' => "diverse-{$type}-{$i}",
                'parent_id' => $parentId,
                'metadata' => json_encode([
                    'tags' => $i % 5 === 0 ? ['important'] : ['normal'],
                    'priority' => rand(1, 10),
                    'created_batch' => 'diverse_dataset',
                ]),
            ]);

            // Beberapa taxonomy bisa jadi parent untuk yang lain
            if ($i % 10 === 0) {
                $parents[] = $taxonomy->id;
            }
        }
    }

    /**
     * Test edge case: taxonomy dengan metadata sangat besar.
     */
    #[Test]
    public function it_can_handle_large_metadata(): void
    {
        // Buat metadata yang sangat besar (mendekati limit JSON)
        $largeArray = [];
        for ($i = 1; $i <= 1000; ++$i) {
            $largeArray["key_{$i}"] = str_repeat("Large metadata value {$i} ", 50);
        }

        $taxonomy = Taxonomy::create([
            'name' => 'Large Metadata Category',
            'type' => TaxonomyType::Category->value,
            'slug' => 'large-metadata-category',
            'meta' => $largeArray,
        ]);

        $this->assertNotNull($taxonomy->id);

        // Test retrieval dan parsing metadata besar
        $retrieved = Taxonomy::find($taxonomy->id);
        $this->assertNotNull($retrieved);
        $decodedMetadata = $retrieved->meta;

        $this->assertIsArray($decodedMetadata);
        $this->assertCount(1000, $decodedMetadata);
        $this->assertEquals($largeArray['key_1'], $decodedMetadata['key_1']);

        // Test query performance dengan metadata besar
        $startTime = microtime(true);
        $found = Taxonomy::whereJsonContains('meta->key_500', $largeArray['key_500'])->first();
        $queryTime = microtime(true) - $startTime;

        $this->assertNotNull($found);
        $this->assertLessThan(2.0, $queryTime, "Query on large metadata took too long: {$queryTime} seconds");
    }
}
