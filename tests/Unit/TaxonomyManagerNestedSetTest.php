<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\TaxonomyManager;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(TestCase::class, RefreshDatabase::class);

function createManagerTestData()
{
    $manager = new TaxonomyManager;

    // Create test taxonomies
    createManagerTestTaxonomies();

    return compact('manager');
}

function createManagerTestTaxonomies()
{
    // Create root category
    $electronics = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
        'slug' => 'electronics',
    ]);

    // Create subcategory
    $computers = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
        'slug' => 'computers',
        'parent_id' => $electronics->id,
    ]);

    // Create sub-subcategory
    $laptops = Taxonomy::create([
        'name' => 'Laptops',
        'type' => TaxonomyType::Category->value,
        'slug' => 'laptops',
        'parent_id' => $computers->id,
    ]);
}

it('get nested tree returns correct structure', function () {
    $data = createManagerTestData();
    extract($data);

    $tree = $manager->getNestedTree(TaxonomyType::Category);

    expect($tree)->toHaveCount(1); // Only one root: Electronics

    $electronics = $tree->first();
    expect($electronics)->not->toBeNull();
    expect($electronics)->toBeInstanceOf(Taxonomy::class);
    expect($electronics->slug)->toBe('electronics');
    expect($electronics->children_nested)->not->toBeNull();
    expect($electronics->children_nested)->toHaveCount(1);

    $computers = $electronics->children_nested->first();
    expect($computers)->not->toBeNull();
    expect($computers)->toBeInstanceOf(Taxonomy::class);
    expect($computers->slug)->toBe('computers');
    expect($computers->children_nested)->not->toBeNull();
    expect($computers->children_nested)->toHaveCount(1);

    $laptops = $computers->children_nested->first();
    expect($laptops)->not->toBeNull();
    expect($laptops)->toBeInstanceOf(Taxonomy::class);
    expect($laptops->slug)->toBe('laptops');
});

it('get nested tree caches results', function () {
    $data = createManagerTestData();
    extract($data);

    // Clear cache first
    Cache::flush();

    // First call should hit database
    $tree1 = $manager->getNestedTree(TaxonomyType::Category);

    // Second call should hit cache
    $tree2 = $manager->getNestedTree(TaxonomyType::Category);

    expect($tree1->toArray())->toEqual($tree2->toArray());

    // Verify cache key exists
    $cacheKey = 'taxonomy_nested_tree_' . TaxonomyType::Category->value;
    expect(Cache::has($cacheKey))->toBeTrue();
});

it('rebuild nested set rebuilds correctly', function () {
    $data = createManagerTestData();
    extract($data);

    $electronics = Taxonomy::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();
    $initialDescendants = $electronics->getDescendants()->count();

    // Manually corrupt nested set values
    Taxonomy::where('slug', 'computers')->update(['lft' => null, 'rgt' => null]);

    // Rebuild
    $manager->rebuildNestedSet(TaxonomyType::Category);

    // Check structure is restored
    $electronics->refresh();
    $newDescendants = $electronics->getDescendants()->count();

    expect($newDescendants)->toBe($initialDescendants);

    // Check that all taxonomies have valid nested set values
    $allTaxonomies = Taxonomy::where('type', TaxonomyType::Category->value)->get();
    foreach ($allTaxonomies as $taxonomy) {
        expect($taxonomy->lft)->not->toBeNull();
        expect($taxonomy->rgt)->not->toBeNull();
        expect($taxonomy->lft)->toBeLessThan($taxonomy->rgt);
    }
});

it('rebuild nested set clears cache', function () {
    $data = createManagerTestData();
    extract($data);

    // Populate cache
    $manager->getNestedTree(TaxonomyType::Category);

    $cacheKey = 'taxonomy_nested_tree_' . TaxonomyType::Category->value;
    expect(Cache::has($cacheKey))->toBeTrue();

    // Rebuild should clear cache
    $manager->rebuildNestedSet(TaxonomyType::Category);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('move to parent works correctly', function () {
    $data = createManagerTestData();
    extract($data);

    $computers = Taxonomy::where('slug', 'computers')->first();
    expect($computers)->not->toBeNull();
    $laptops = Taxonomy::where('slug', 'laptops')->first();
    expect($laptops)->not->toBeNull();

    // Move laptops to be a direct child of electronics (skip computers)
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();
    $result = $manager->moveToParent($laptops->id, $electronics->id);

    expect($result)->toBeTrue();

    $laptops->refresh();
    expect($laptops->parent_id)->toBe($electronics->id);
    expect($laptops->depth)->toBe(1);

    // Check nested set values are correct
    $tree = $manager->getNestedTree(TaxonomyType::Category);
    expect($tree)->toHaveCount(1);

    $electronics = $tree->first();
    expect($electronics->children_nested)->toHaveCount(2); // computers and laptops
});

it('move to parent returns false for invalid taxonomy', function () {
    $data = createManagerTestData();
    extract($data);

    $result = $manager->moveToParent(999, 1);
    expect($result)->toBeFalse();
});

it('move to parent clears cache', function () {
    $data = createManagerTestData();
    extract($data);

    // Populate cache
    $manager->getNestedTree(TaxonomyType::Category);

    $cacheKey = 'taxonomy_nested_tree_' . TaxonomyType::Category->value;
    expect(Cache::has($cacheKey))->toBeTrue();

    $laptops = Taxonomy::where('slug', 'laptops')->first();
    expect($laptops)->not->toBeNull();
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();

    // Move should clear cache
    $manager->moveToParent($laptops->id, $electronics->id);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('get descendants returns correct taxonomies', function () {
    $data = createManagerTestData();
    extract($data);

    $electronics = Taxonomy::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();
    $descendants = $manager->getDescendants($electronics->id);

    expect($descendants)->toHaveCount(2); // computers and laptops

    $descendantSlugs = $descendants->pluck('slug')->toArray();
    expect($descendantSlugs)->toContain('computers');
    expect($descendantSlugs)->toContain('laptops');
});

it('get descendants returns empty for invalid taxonomy', function () {
    $data = createManagerTestData();
    extract($data);

    $descendants = $manager->getDescendants(999);
    expect($descendants)->toHaveCount(0);
});

it('get ancestors returns correct taxonomies', function () {
    $data = createManagerTestData();
    extract($data);

    $laptops = Taxonomy::where('slug', 'laptops')->first();
    expect($laptops)->not->toBeNull();
    $ancestors = $manager->getAncestors($laptops->id);

    expect($ancestors)->toHaveCount(2); // computers and electronics

    $ancestorSlugs = $ancestors->pluck('slug')->toArray();
    expect($ancestorSlugs)->toContain('computers');
    expect($ancestorSlugs)->toContain('electronics');
});

it('get ancestors returns empty for invalid taxonomy', function () {
    $data = createManagerTestData();
    extract($data);

    $ancestors = $manager->getAncestors(999);
    expect($ancestors)->toHaveCount(0);
});

it('get ancestors returns empty for root taxonomy', function () {
    $data = createManagerTestData();
    extract($data);

    $electronics = Taxonomy::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();
    $ancestors = $manager->getAncestors($electronics->id);

    expect($ancestors)->toHaveCount(0);
});

it('nested tree works with different types', function () {
    $data = createManagerTestData();
    extract($data);

    // Create tags
    $techTag = Taxonomy::create([
        'name' => 'Technology',
        'type' => TaxonomyType::Tag->value,
        'slug' => 'technology',
    ]);

    $webTag = Taxonomy::create([
        'name' => 'Web Development',
        'type' => TaxonomyType::Tag->value,
        'slug' => 'web-development',
        'parent_id' => $techTag->id,
    ]);

    // Get trees for different types
    $categoryTree = $manager->getNestedTree(TaxonomyType::Category);
    $tagTree = $manager->getNestedTree(TaxonomyType::Tag);

    // Category tree should have electronics
    expect($categoryTree)->toHaveCount(1);
    $categoryFirst = $categoryTree->first();
    expect($categoryFirst)->not->toBeNull();
    expect($categoryFirst)->toBeInstanceOf(Taxonomy::class);
    expect($categoryFirst->slug)->toBe('electronics');

    // Tag tree should have technology
    expect($tagTree)->toHaveCount(1);
    $tagFirst = $tagTree->first();
    expect($tagFirst)->not->toBeNull();
    expect($tagFirst)->toBeInstanceOf(Taxonomy::class);
    expect($tagFirst->slug)->toBe('technology');
    expect($tagFirst->children_nested)->not->toBeNull();
    expect($tagFirst->children_nested)->toHaveCount(1);
    $webDevTag = $tagFirst->children_nested->first();
    expect($webDevTag)->not->toBeNull();
    expect($webDevTag)->toBeInstanceOf(Taxonomy::class);
    expect($webDevTag->slug)->toBe('web-development');
});

it('clear cache for type removes correct patterns', function () {
    $data = createManagerTestData();
    extract($data);

    // Populate different caches
    $manager->getNestedTree(TaxonomyType::Category);
    $manager->tree(TaxonomyType::Category);
    $manager->flatTree(TaxonomyType::Category);

    // Create some cache keys
    $nestedTreeKey = 'taxonomy_nested_tree_' . TaxonomyType::Category->value;
    $treeKey = 'taxonomy_tree_' . TaxonomyType::Category->value . '_';
    $flatTreeKey = 'taxonomy_flat_tree_' . TaxonomyType::Category->value . '_0_0';

    expect(Cache::has($nestedTreeKey))->toBeTrue();

    // Rebuild should clear caches for this type
    $manager->rebuildNestedSet(TaxonomyType::Category);

    expect(Cache::has($nestedTreeKey))->toBeFalse();
});
