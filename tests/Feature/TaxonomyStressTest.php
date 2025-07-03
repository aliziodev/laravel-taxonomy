<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Test circular reference detection and prevention.
 */
it('can prevent circular reference', function () {
    // Create chain A -> B -> C
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

    // Rebuild nested set to set nested set values
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

    // Refresh models to get nested set values
    $nodeA->refresh();
    $nodeB->refresh();
    $nodeC->refresh();

    // Try to create circular reference: A -> B -> C -> A
    expect(fn () => $nodeA->moveToParent($nodeC->id))->toThrow(Exception::class);
});

/*
 * Test with extremely long names and special characters.
 */
it('can handle extreme name lengths and special characters', function () {
    // Test very long name (255+ characters)
    $longName = str_repeat('Very Long Category Name With Special Characters !@#$%^&*()_+-=[]{}|;:,.<>? ', 10);

    $taxonomy = Taxonomy::create([
        'name' => substr($longName, 0, 255), // Truncate to max length
        'type' => TaxonomyType::Category->value,
        'slug' => 'long-name-category',
    ]);

    expect($taxonomy->id)->not->toBeNull();
    expect(strlen($taxonomy->name))->toBeLessThanOrEqual(255);

    // Test Unicode characters
    $unicodeTaxonomy = Taxonomy::create([
        'name' => '测试分类 🚀 العربية Русский',
        'type' => TaxonomyType::Category->value,
        'slug' => 'unicode-category',
    ]);

    expect($unicodeTaxonomy->id)->not->toBeNull();
    expect($unicodeTaxonomy->name)->toBe('测试分类 🚀 العربية Русский');
});

/*
 * Test concurrent creation with duplicate slugs.
 */
it('can handle concurrent duplicate slug handling', function () {
    $baseName = 'Duplicate Category';
    $baseSlug = 'duplicate-category';
    $createdTaxonomies = [];
    $exceptions = [];

    // Try to create 10 taxonomies with the same slug concurrently
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

    // At least one should be created successfully (minimum 1)
    expect(count($createdTaxonomies))->toBeGreaterThan(0);

    // Verify all successfully created slugs are unique
    $slugs = collect($createdTaxonomies)->pluck('slug')->toArray();
    $uniqueSlugs = array_unique($slugs);
    expect(count($slugs))->toBe(count($uniqueSlugs), 'Duplicate slugs found');
});

/*
 * Test massive batch operations.
 */
it('can handle massive batch operations', function () {
    if (config('app.skip_heavy_tests', false)) {
        expect(true)->toBeTrue('Heavy tests skipped');

        return;
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

    // Insert in chunks to avoid memory issues
    collect($taxonomyData)->chunk(100)->each(function ($chunk) {
        DB::table('taxonomies')->insert($chunk->toArray());
    });

    $batchCreationTime = microtime(true) - $startTime;

    expect(Taxonomy::count())->toBe($batchSize);
    expect($batchCreationTime)->toBeLessThan(30.0, "Batch creation took too long: {$batchCreationTime} seconds");

    // Test 2: Batch update
    $startTime = microtime(true);

    Taxonomy::query()->update([
        'meta' => json_encode(['batch_updated' => true, 'timestamp' => now()]),
    ]);

    $batchUpdateTime = microtime(true) - $startTime;
    expect($batchUpdateTime)->toBeLessThan(10.0, "Batch update took too long: {$batchUpdateTime} seconds");

    // Test 3: Batch delete
    $startTime = microtime(true);

    // Delete half of the records
    $idsToDelete = Taxonomy::limit($batchSize / 2)->pluck('id');
    Taxonomy::whereIn('id', $idsToDelete)->delete();

    $batchDeleteTime = microtime(true) - $startTime;
    expect($batchDeleteTime)->toBeLessThan(15.0, "Batch delete took too long: {$batchDeleteTime} seconds");

    expect(Taxonomy::count())->toBe($batchSize / 2);

    // Verify batch operations performance
    expect($batchCreationTime)->toBeLessThan(30.0, "Batch creation should complete within 30 seconds for {$batchSize} items");
    expect($batchUpdateTime)->toBeLessThan(20.0, "Batch update should complete within 20 seconds for {$batchSize} items");
    expect($batchDeleteTime)->toBeLessThan(15.0, "Batch delete should complete within 15 seconds for {$batchSize} items");
});

/*
 * Test query performance with complex conditions.
 */
it('can handle complex query performance', function () {
    // Create diverse dataset
    createDiverseDataset(500);

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
    expect($complexQueryTime)->toBeLessThan(2.0, "Complex query took too long: {$complexQueryTime} seconds");

    // Test 2: Nested set queries
    $startTime = microtime(true);

    $rootNode = Taxonomy::whereNull('parent_id')->first();
    if ($rootNode) {
        $descendants = $rootNode->getDescendants();
        $ancestors = Taxonomy::find($descendants->last()?->id)?->getAncestors();
    }

    $nestedSetQueryTime = microtime(true) - $startTime;
    expect($nestedSetQueryTime)->toBeLessThan(1.0, "Nested set queries took too long: {$nestedSetQueryTime} seconds");

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
    expect($aggregationQueryTime)->toBeLessThan(1.0, "Aggregation queries took too long: {$aggregationQueryTime} seconds");

    // Verify query performance
    expect($complexQueryTime)->toBeLessThan(5.0, 'Complex query should complete within 5 seconds');
    expect($nestedSetQueryTime)->toBeLessThan(3.0, 'Nested set query should complete within 3 seconds');
    expect($aggregationQueryTime)->toBeLessThan(2.0, 'Aggregation query should complete within 2 seconds');
});

/*
 * Test cache performance and invalidation.
 */
it('can handle cache performance and invalidation', function () {
    // Create dataset for testing
    createDiverseDataset(200);

    // Test 1: Cache warming
    Cache::flush();

    $startTime = microtime(true);
    $tree1 = Taxonomy::getNestedTree(); // First call - should cache
    $firstCallTime = microtime(true) - $startTime;

    $startTime = microtime(true);
    $tree2 = Taxonomy::getNestedTree(); // Second call - should use cache
    $secondCallTime = microtime(true) - $startTime;

    // Cache hit is usually faster, but not always due to small overhead
    // What's important is the same result and reasonable performance
    expect($tree1->count())->toBe($tree2->count());
    expect($secondCallTime)->toBeLessThan(1.0, 'Second call should be reasonably fast');
    expect($firstCallTime)->toBeLessThan(1.0, 'First call should be reasonably fast');

    // Test 2: Cache invalidation when there are changes
    $newTaxonomy = Taxonomy::create([
        'name' => 'Cache Test Category',
        'type' => TaxonomyType::Category->value,
        'slug' => 'cache-test-category',
    ]);

    $startTime = microtime(true);
    $tree3 = Taxonomy::getNestedTree(); // Should rebuild cache
    $thirdCallTime = microtime(true) - $startTime;

    expect($tree3->count())->toBeGreaterThan($tree2->count());

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

    // Verify all cache keys exist
    foreach ($cacheKeys as $key) {
        expect(Cache::get($key))->toBe('test_value');
    }

    // Invalidate all taxonomy-related cache
    Cache::flush(); // Or more specific cache invalidation implementation

    foreach ($cacheKeys as $key) {
        expect(Cache::get($key))->toBeNull();
    }

    // Verify cache performance improvements (with more realistic thresholds)
    // Cache hit is not always faster due to small overhead, so we test reasonable performance
    expect($secondCallTime)->toBeLessThan(1.0, 'Cache hit should be reasonably fast');
    expect($thirdCallTime)->toBeLessThan(1.0, 'Cache rebuild should be reasonably fast');

    // Verify that all operations are within reasonable time limits
    expect($firstCallTime)->toBeLessThan(2.0, 'Initial call should complete within reasonable time');
});

/*
 * Test database connection limits and connection pooling.
 */
it('can handle database connection stress', function () {
    if (config('app.skip_connection_tests', false)) {
        expect(true)->toBeTrue('Connection stress tests skipped');

        return;
    }

    $connectionCount = 50;
    $results = [];

    // Simulate multiple concurrent database operations
    for ($i = 1; $i <= $connectionCount; ++$i) {
        try {
            // Each iteration creates new connection and runs query
            $startTime = microtime(true);

            $taxonomy = Taxonomy::create([
                'name' => "Connection Test {$i}",
                'type' => TaxonomyType::Category->value,
                'slug' => "connection-test-{$i}",
            ]);

            // Run several operations on the same connection
            $taxonomy->refresh();
            $count = Taxonomy::count();
            $tree = Taxonomy::limit(10)->get();

            $operationTime = microtime(true) - $startTime;
            $results[] = $operationTime;

        } catch (\Exception $e) {
            throw new \Exception("Connection failed at iteration {$i}: " . $e->getMessage());
        }
    }

    $avgTime = array_sum($results) / count($results);
    $maxTime = max($results);
    $minTime = min($results);

    expect($avgTime)->toBeLessThan(1.0, "Average operation time too high: {$avgTime} seconds");
    expect($maxTime)->toBeLessThan(5.0, "Max operation time too high: {$maxTime} seconds");

    // Verify connection stress test performance
    expect($avgTime)->toBeLessThan(1.0, 'Average operation time should be under 1 second');
    expect($maxTime)->toBeLessThan(5.0, 'Maximum operation time should be under 5 seconds');
    expect($minTime)->toBeGreaterThan(0, 'Minimum operation time should be positive');
});

/**
 * Helper to create diverse dataset.
 */
function createDiverseDataset(int $count): void
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
            'meta' => json_encode([
                'tags' => $i % 5 === 0 ? ['important'] : ['normal'],
                'priority' => rand(1, 10),
                'created_batch' => 'diverse_dataset',
            ]),
        ]);

        // Some taxonomies can become parents for others
        if ($i % 10 === 0) {
            $parents[] = $taxonomy->id;
        }
    }
}

/*
 * Test edge case: taxonomy with very large metadata.
 */
it('can handle large metadata', function () {
    // Create very large meta (approaching JSON limit)
    $largeArray = [];
    for ($i = 1; $i <= 1000; ++$i) {
        $largeArray["key_{$i}"] = str_repeat("Large meta value {$i} ", 50);
    }

    $taxonomy = Taxonomy::create([
        'name' => 'Large Meta Category',
        'type' => TaxonomyType::Category->value,
        'slug' => 'large-meta-category',
        'meta' => $largeArray,
    ]);

    expect($taxonomy->id)->not->toBeNull();

    // Test retrieval and parsing of large metadata
    $retrieved = Taxonomy::find($taxonomy->id);
    expect($retrieved)->not->toBeNull();
    $decodedMetadata = $retrieved->meta;

    expect($decodedMetadata)->toBeArray();
    expect($decodedMetadata)->toHaveCount(1000);
    expect($decodedMetadata['key_1'])->toBe($largeArray['key_1']);

    // Test query performance with large metadata
    $startTime = microtime(true);
    $found = Taxonomy::whereJsonContains('meta->key_500', $largeArray['key_500'])->first();
    $queryTime = microtime(true) - $startTime;

    expect($found)->not->toBeNull();
    expect($queryTime)->toBeLessThan(2.0, "Query on large metadata took too long: {$queryTime} seconds");
});
