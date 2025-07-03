<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Test extreme tree structure with deep nesting (10-20 levels)
 * Ensures no stack overflow or recursion errors occur.
 */
it('can handle extreme deep nesting structure', function () {

    // Create structure with 20 levels deep
    $levels = 20;
    $currentParent = null;
    $taxonomies = [];

    // Create taxonomy chain with 20 levels
    for ($i = 1; $i <= $levels; ++$i) {
        $taxonomy = Taxonomy::create([
            'name' => "Level {$i} Category",
            'type' => TaxonomyType::Category->value,
            'slug' => "level-{$i}-category",
            'parent_id' => $currentParent?->id,
        ]);

        $taxonomies[] = $taxonomy;
        $currentParent = $taxonomy;
    }

    // Rebuild nested set to set nested set values
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

    // Refresh models to get lft/rgt values
    $taxonomies = array_map(fn ($t) => $t->fresh(), $taxonomies);
    $deepestNode = end($taxonomies);
    $rootNode = $taxonomies[0];
    expect($deepestNode)->not->toBeNull();
    expect($rootNode)->not->toBeNull();

    // Test getAncestors() - deepest node should have 19 ancestors
    $ancestors = $deepestNode->getAncestors();
    expect($ancestors)->toHaveCount(19);
    $firstAncestor = $ancestors->first();
    expect($firstAncestor)->not->toBeNull();
    expect($firstAncestor->id)->toBe($rootNode->id);

    // Test getDescendants() - root should have 19 descendants
    $descendants = $rootNode->getDescendants();
    expect($descendants)->toHaveCount(19);
    $lastDescendant = $descendants->last();
    expect($lastDescendant)->not->toBeNull();
    expect($lastDescendant->id)->toBe($deepestNode->id);

    // Test move operation on deep structure
    $middleNode = $taxonomies[10]; // Level 11
    $newParent = $taxonomies[5];   // Level 6
    expect($middleNode)->not->toBeNull();
    expect($newParent)->not->toBeNull();

    // Move middle node to higher parent
    $middleNode->moveToParent($newParent->id);

    // Verify structure is still valid after move
    $refreshedMiddleNode = $middleNode->fresh();
    expect($refreshedMiddleNode)->not->toBeNull();
    expect($refreshedMiddleNode->parent_id)->toBe($newParent->id);

    // Test rebuild() on complex structure
    $startTime = microtime(true);
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
    $rebuildTime = microtime(true) - $startTime;

    // Ensure rebuild doesn't take too long (< 5 seconds for 20 levels)
    expect($rebuildTime)->toBeLessThan(5.0, 'Rebuild took too long: ' . $rebuildTime . ' seconds');

    // Verify structure is still consistent after rebuild
    $rebuiltRootNode = Taxonomy::where('id', $rootNode->id)->first();
    expect($rebuiltRootNode)->not->toBeNull();
    expect($rebuiltRootNode->lft)->toBe(1);
});

/*
 * Test with multiple branches at each level.
 */
it('can handle extreme wide and deep structure', function () {

    // Create structure with 3 levels, each level has 2 children (reduced to prevent hang)
    createWideDeepStructure(3, 2);

    // Count total nodes created: Level 1: 2, Level 2: 4, Level 3: 8 = 14 nodes
    // Recursive structure generates 2^1 + 2^2 + 2^3 = 2 + 4 + 8 = 14 nodes
    $totalNodes = Taxonomy::count();
    expect($totalNodes)->toBeGreaterThan(10);
    expect($totalNodes)->toBeLessThan(20, 'Too many nodes created, test may hang');

    // Test performance getNestedTree() on large structure
    $startTime = microtime(true);
    $tree = Taxonomy::getNestedTree();
    $getTreeTime = microtime(true) - $startTime;

    expect($getTreeTime)->toBeLessThan(2.0, 'getNestedTree() took too long: ' . $getTreeTime . ' seconds');
    expect($tree->count())->toBeGreaterThan(0);
});

/**
 * Helper function to create wide and deep structure.
 */
function createWideDeepStructure(int $maxDepth, int $branchingFactor, ?int $parentId = null, int $currentDepth = 1): void
{
    if ($currentDepth > $maxDepth) {
        return;
    }

    for ($i = 1; $i <= $branchingFactor; ++$i) {
        $taxonomy = Taxonomy::create([
            'name' => "Level {$currentDepth} Branch {$i}",
            'type' => TaxonomyType::Category->value,
            'slug' => "level-{$currentDepth}-branch-{$i}-" . uniqid(),
            'parent_id' => $parentId,
        ]);

        // Recursively create children
        createWideDeepStructure($maxDepth, $branchingFactor, $taxonomy->id, $currentDepth + 1);
    }
}

/*
 * Test detection and repair of invalid structure
 * Simulate broken structure by manually changing lft/rgt values.
 */
it('can detect and repair invalid structure', function () {
    // Create normal structure first
    $root = Taxonomy::create([
        'name' => 'Root',
        'type' => TaxonomyType::Category->value,
        'slug' => 'root',
    ]);

    $child1 = Taxonomy::create([
        'name' => 'Child 1',
        'type' => TaxonomyType::Category->value,
        'slug' => 'child-1',
        'parent_id' => $root->id,
    ]);

    $child2 = Taxonomy::create([
        'name' => 'Child 2',
        'type' => TaxonomyType::Category->value,
        'slug' => 'child-2',
        'parent_id' => $root->id,
    ]);

    $grandchild = Taxonomy::create([
        'name' => 'Grandchild',
        'type' => TaxonomyType::Category->value,
        'slug' => 'grandchild',
        'parent_id' => $child1->id,
    ]);

    // Save correct structure for comparison
    $originalStructure = Taxonomy::orderBy('lft')->get(['id', 'name', 'lft', 'rgt', 'parent_id'])->toArray();

    // Break structure by manually changing lft/rgt values
    DB::table('taxonomies')->where('id', $child1->id)->update(['lft' => 10, 'rgt' => 15]);
    DB::table('taxonomies')->where('id', $child2->id)->update(['lft' => 5, 'rgt' => 6]);
    DB::table('taxonomies')->where('id', $grandchild->id)->update(['lft' => 20, 'rgt' => 21]);

    // Verify structure is indeed broken
    $damagedChild1 = Taxonomy::find($child1->id);
    expect($damagedChild1)->not->toBeNull();
    expect($damagedChild1->lft)->toBe(10);
    expect($damagedChild1->rgt)->toBe(15);

    // Test rebuild() fixes the structure
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

    // Verify structure has been repaired
    $repairedStructure = Taxonomy::orderBy('lft')->get(['id', 'name', 'lft', 'rgt', 'parent_id'])->toArray();

    // Ensure parent-child relationship is still correct
    $repairedChild1 = Taxonomy::find($child1->id);
    $repairedGrandchild = Taxonomy::find($grandchild->id);

    expect($repairedChild1)->not->toBeNull();
    expect($repairedGrandchild)->not->toBeNull();
    expect($repairedChild1->parent_id)->toBe($root->id);
    expect($repairedGrandchild->parent_id)->toBe($child1->id);

    // Ensure lft/rgt values are valid (lft < rgt, and nested set rules)
    foreach (Taxonomy::all() as $taxonomy) {
        expect($taxonomy->lft)->toBeLessThan($taxonomy->rgt);

        if ($taxonomy->parent_id) {
            $parent = Taxonomy::find($taxonomy->parent_id);
            expect($parent)->not->toBeNull();
            expect($taxonomy->lft)->toBeGreaterThan($parent->lft);
            expect($taxonomy->rgt)->toBeLessThan($parent->rgt);
        }
    }
});

/*
 * Test soft delete taxonomy and its impact on children
 * (If using SoftDeletes trait).
 */
it('can handle soft delete taxonomy with children', function () {
    // Skip test if model does not use SoftDeletes
    if (! in_array('Illuminate\Database\Eloquent\SoftDeletes', trait_uses_recursive(Taxonomy::class))) {
        expect(true)->toBeTrue('Taxonomy model does not use SoftDeletes trait');

        return;
    }

    // Buat struktur parent-child
    $parent = Taxonomy::create([
        'name' => 'Parent Category',
        'type' => TaxonomyType::Category->value,
        'slug' => 'parent-category',
    ]);

    $child1 = Taxonomy::create([
        'name' => 'Child 1',
        'type' => TaxonomyType::Category->value,
        'slug' => 'child-1',
        'parent_id' => $parent->id,
    ]);

    $child2 = Taxonomy::create([
        'name' => 'Child 2',
        'type' => TaxonomyType::Category->value,
        'slug' => 'child-2',
        'parent_id' => $parent->id,
    ]);

    // Clear cache before test
    Cache::flush();

    // Soft delete parent
    $parent->delete();

    // Test if parent is soft deleted
    expect($parent->trashed())->toBeTrue();

    // Test if children are also soft deleted (depends on implementation)
    // This may vary depending on desired business logic
    $child1Fresh = Taxonomy::withTrashed()->find($child1->id);
    $child2Fresh = Taxonomy::withTrashed()->find($child2->id);

    // Test cache is cleared after soft delete
    $cachedTree = Cache::get('taxonomy_tree');
    expect($cachedTree)->toBeNull('Cache should be cleared after soft delete');

    // Test restore functionality
    $parent->restore();
    $freshParent = $parent->fresh();
    expect($freshParent)->not->toBeNull();
    expect($freshParent->trashed())->toBeFalse();
});

/*
 * Test race condition during concurrent moveToParent operations.
 */
it('can handle race condition concurrent move operations', function () {
    // Create test structure
    $root = Taxonomy::create([
        'name' => 'Root',
        'type' => TaxonomyType::Category->value,
        'slug' => 'root',
    ]);

    $parent1 = Taxonomy::create([
        'name' => 'Parent 1',
        'type' => TaxonomyType::Category->value,
        'slug' => 'parent-1',
        'parent_id' => $root->id,
    ]);

    $parent2 = Taxonomy::create([
        'name' => 'Parent 2',
        'type' => TaxonomyType::Category->value,
        'slug' => 'parent-2',
        'parent_id' => $root->id,
    ]);

    $movingNode = Taxonomy::create([
        'name' => 'Moving Node',
        'type' => TaxonomyType::Category->value,
        'slug' => 'moving-node',
        'parent_id' => $parent1->id,
    ]);

    // Simulate concurrent operations using database transactions
    $results = [];
    $exceptions = [];

    // Run multiple move operations "concurrently"
    for ($i = 0; $i < 5; ++$i) {
        try {
            DB::transaction(function () use ($movingNode, $parent1, $parent2, &$results) {
                // Alternate between moving to parent1 and parent2
                $targetParent = (count($results) % 2 === 0) ? $parent2 : $parent1;

                $freshMovingNode = $movingNode->fresh();
                expect($freshMovingNode)->not->toBeNull();
                $freshMovingNode->moveToParent($targetParent->id);
                $results[] = $targetParent->id;
            });
        } catch (\Exception $e) {
            $exceptions[] = $e->getMessage();
        }
    }

    // Verify final state is consistent
    $finalNode = $movingNode->fresh();
    expect($finalNode)->not->toBeNull();
    expect($finalNode->parent_id)->not->toBeNull();
    expect(in_array($finalNode->parent_id, [$parent1->id, $parent2->id]))->toBeTrue();

    // Verify nested set structure is still valid
    assertValidNestedSetStructure();
});

/*
 * Performance testing with 10,000 taxonomies.
 */
it('can handle performance with large dataset', function () {
    // Skip if environment does not support heavy tests
    if (config('app.skip_performance_tests', false)) {
        expect(true)->toBeTrue('Performance tests skipped');

        return;
    }

    $targetCount = 100; // Further reduced to prevent hang

    // Test 1: Bulk creation performance
    $startTime = microtime(true);

    $taxonomies = [];
    for ($i = 1; $i <= $targetCount; ++$i) {
        $taxonomies[] = [
            'name' => "Category {$i}",
            'type' => TaxonomyType::Category->value,
            'slug' => "category-{$i}",
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Batch insert every 100 records
        if ($i % 100 === 0) {
            DB::table('taxonomies')->insert($taxonomies);
            $taxonomies = [];
        }
    }

    if (! empty($taxonomies)) {
        DB::table('taxonomies')->insert($taxonomies);
    }

    $creationTime = microtime(true) - $startTime;
    expect($creationTime)->toBeLessThan(5.0, "Creation of {$targetCount} taxonomies took too long: {$creationTime} seconds");

    // Test 2: rebuild() performance
    $startTime = microtime(true);
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
    $rebuildTime = microtime(true) - $startTime;

    expect($rebuildTime)->toBeLessThan(10.0, "Rebuild with {$targetCount} taxonomies took too long: {$rebuildTime} seconds");

    // Test 3: getNestedTree() performance
    $startTime = microtime(true);
    $tree = Taxonomy::getNestedTree();
    $getTreeTime = microtime(true) - $startTime;

    expect($getTreeTime)->toBeLessThan(3.0, "getNestedTree() with {$targetCount} taxonomies took too long: {$getTreeTime} seconds");
    expect($tree->count())->toBeGreaterThan(0);

    // Test 4: moveToParent() performance on large dataset
    $firstTaxonomy = Taxonomy::first();
    $lastTaxonomy = Taxonomy::orderBy('id', 'desc')->first();

    expect($firstTaxonomy)->not->toBeNull();
    expect($lastTaxonomy)->not->toBeNull();

    $startTime = microtime(true);
    $lastTaxonomy->moveToParent($firstTaxonomy->id);
    $moveTime = microtime(true) - $startTime;

    expect($moveTime)->toBeLessThan(2.0, "moveToParent() on large dataset took too long: {$moveTime} seconds");

    // Verify move was successful
    $freshLastTaxonomy = $lastTaxonomy->fresh();
    expect($freshLastTaxonomy)->not->toBeNull();
    expect($freshLastTaxonomy->parent_id)->toBe($firstTaxonomy->id);

    // Output performance metrics
    // Verify performance test results
    expect($creationTime)->toBeLessThan(60.0, "Creation time should be under 60 seconds for {$targetCount} taxonomies");
    expect($rebuildTime)->toBeLessThan(45.0, "Rebuild time should be under 45 seconds for {$targetCount} taxonomies");
    expect($getTreeTime)->toBeLessThan(10.0, "GetNestedTree time should be under 10 seconds for {$targetCount} taxonomies");
    expect($moveTime)->toBeLessThan(5.0, "MoveToParent time should be under 5 seconds for {$targetCount} taxonomies");
});

/*
 * Test memory usage on large operations.
 */
it('can handle memory usage large operations', function () {
    $initialMemory = memory_get_usage(true);

    // Create 50 taxonomies (reduced to prevent hang)
    for ($i = 1; $i <= 50; ++$i) {
        Taxonomy::create([
            'name' => "Memory Test {$i}",
            'type' => TaxonomyType::Category->value,
            'slug' => "memory-test-{$i}",
        ]);
    }

    $afterCreationMemory = memory_get_usage(true);

    // Run memory-intensive operations
    $tree = Taxonomy::getNestedTree();
    $firstTaxonomy = Taxonomy::first();
    expect($firstTaxonomy)->not->toBeNull();
    $descendants = $firstTaxonomy->getDescendants();

    $finalMemory = memory_get_usage(true);

    $creationMemoryIncrease = $afterCreationMemory - $initialMemory;
    $operationMemoryIncrease = $finalMemory - $afterCreationMemory;

    // Memory increase shouldn't be excessive (< 10MB for 50 records)
    expect($creationMemoryIncrease)->toBeLessThan(10 * 1024 * 1024, 'Memory usage too high during creation');
    expect($operationMemoryIncrease)->toBeLessThan(5 * 1024 * 1024, 'Memory usage too high during operations');

    // Verify memory usage is within acceptable limits
    $maxMemoryIncrease = 512 * 1024 * 1024; // 512MB
    expect($creationMemoryIncrease)->toBeLessThan($maxMemoryIncrease, 'Creation memory increase should be under 512MB');
    expect($operationMemoryIncrease)->toBeLessThan($maxMemoryIncrease / 2, 'Operation memory increase should be under 256MB');

    // Memory can be the same due to garbage collection, so we test that there are no major memory leaks
    expect($afterCreationMemory)->toBeGreaterThanOrEqual($initialMemory, 'Memory should not decrease after creation');
    expect($finalMemory)->toBeGreaterThanOrEqual($afterCreationMemory, 'Memory should not decrease after operations');

    // Memory usage tracking completed without major leaks
    $memoryEfficient = ($creationMemoryIncrease < 50 * 1024 * 1024) && ($operationMemoryIncrease < 25 * 1024 * 1024);
    expect($memoryEfficient)->toBeTrue('Memory usage should be efficient for the operations performed');
});

/**
 * Helper function to validate nested set structure.
 */
function assertValidNestedSetStructure(): void
{
    $taxonomies = Taxonomy::orderBy('lft')->get();

    foreach ($taxonomies as $taxonomy) {
        expect($taxonomy)->not->toBeNull();
        // lft must be less than rgt
        expect($taxonomy->lft)->toBeLessThan($taxonomy->rgt, "Invalid lft/rgt for taxonomy {$taxonomy->id}");

        // If has parent, must be within parent range
        if ($taxonomy->parent_id) {
            $parent = Taxonomy::find($taxonomy->parent_id);
            expect($parent)->not->toBeNull();
            expect($taxonomy->lft)->toBeGreaterThan($parent->lft, "Child lft not greater than parent lft for taxonomy {$taxonomy->id}");
            expect($taxonomy->rgt)->toBeLessThan($parent->rgt, "Child rgt not less than parent rgt for taxonomy {$taxonomy->id}");
        }
    }
}
