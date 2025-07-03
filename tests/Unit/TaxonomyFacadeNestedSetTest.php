<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(TestCase::class, RefreshDatabase::class);

function createNestedSetTestData()
{
    // Create hierarchical taxonomy structure for categories
    // Electronics (root)
    //   └── Smartphones (child)
    //       └── Android (grandchild)
    //           └── Samsung (great-grandchild)

    $electronics = Taxonomy::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'type' => TaxonomyType::Category,
    ]);

    $smartphones = Taxonomy::create([
        'name' => 'Smartphones',
        'slug' => 'smartphones',
        'type' => TaxonomyType::Category,
        'parent_id' => $electronics->id,
    ]);

    $android = Taxonomy::create([
        'name' => 'Android',
        'slug' => 'android',
        'type' => TaxonomyType::Category,
        'parent_id' => $smartphones->id,
    ]);

    $samsung = Taxonomy::create([
        'name' => 'Samsung',
        'slug' => 'samsung',
        'type' => TaxonomyType::Category,
        'parent_id' => $android->id,
    ]);

    // Create tag hierarchy
    $tagParent = Taxonomy::create([
        'name' => 'Parent Tag',
        'slug' => 'parent-tag',
        'type' => TaxonomyType::Tag,
    ]);

    $tagChild = Taxonomy::create([
        'name' => 'Child Tag',
        'slug' => 'child-tag',
        'type' => TaxonomyType::Tag,
        'parent_id' => $tagParent->id,
    ]);

    return compact('electronics', 'smartphones', 'android', 'samsung', 'tagParent', 'tagChild');
}

it('facade get nested tree returns correct structure', function () {
    createNestedSetTestData();

    $nestedTree = Taxonomy::getNestedTree(TaxonomyType::Category);

    expect($nestedTree)->toHaveCount(1); // Should have 1 root (electronics)

    $root = $nestedTree->first();
    expect($root)->not->toBeNull();
    expect($root)->toBeInstanceOf(TaxonomyModel::class);
    expect($root->name)->toBe('Electronics');
    expect($root->children)->not->toBeNull();
    expect($root->children)->toHaveCount(1); // Should have 1 child (smartphones)

    $smartphones = $root->children->first();
    expect($smartphones)->not->toBeNull();
    expect($smartphones)->toBeInstanceOf(TaxonomyModel::class);
    expect($smartphones->name)->toBe('Smartphones');
    expect($smartphones->children)->not->toBeNull();
    expect($smartphones->children)->toHaveCount(1); // Should have 1 child (android)

    $android = $smartphones->children->first();
    expect($android)->not->toBeNull();
    expect($android)->toBeInstanceOf(TaxonomyModel::class);
    expect($android->name)->toBe('Android');
    expect($android->children)->toHaveCount(1); // Should have 1 child (samsung)

    $samsung = $android->children->first();
    expect($samsung)->not->toBeNull();
    expect($samsung)->toBeInstanceOf(TaxonomyModel::class);
    expect($samsung->name)->toBe('Samsung');
    expect($samsung->children)->not->toBeNull();
    expect($samsung->children)->toHaveCount(0); // Should have no children
});

it('facade get nested tree filters by type', function () {
    createNestedSetTestData();

    $categoryTree = Taxonomy::getNestedTree(TaxonomyType::Category);
    $tagTree = Taxonomy::getNestedTree(TaxonomyType::Tag);

    // Category tree should have electronics as root
    expect($categoryTree)->toHaveCount(1);
    $categoryFirst = $categoryTree->first();
    expect($categoryFirst)->not->toBeNull();
    expect($categoryFirst)->toBeInstanceOf(TaxonomyModel::class);
    expect($categoryFirst->name)->toBe('Electronics');

    // Tag tree should have parent tag as root
    expect($tagTree)->toHaveCount(1);
    $tagFirst = $tagTree->first();
    expect($tagFirst)->not->toBeNull();
    expect($tagFirst)->toBeInstanceOf(TaxonomyModel::class);
    expect($tagFirst->name)->toBe('Parent Tag');
    expect($tagFirst->children)->not->toBeNull();
    expect($tagFirst->children)->toHaveCount(1);
    $childTag = $tagFirst->children->first();
    expect($childTag)->not->toBeNull();
    expect($childTag)->toBeInstanceOf(TaxonomyModel::class);
    expect($childTag->name)->toBe('Child Tag');
});

it('facade get descendants returns correct descendants', function () {
    $data = createNestedSetTestData();
    extract($data);

    $descendants = Taxonomy::getDescendants($electronics->id);

    expect($descendants)->toHaveCount(3);
    expect($descendants->contains('name', 'Smartphones'))->toBeTrue();
    expect($descendants->contains('name', 'Android'))->toBeTrue();
    expect($descendants->contains('name', 'Samsung'))->toBeTrue();
});

it('facade get descendants handles invalid id', function () {
    $descendants = Taxonomy::getDescendants(99999);
    expect($descendants)->toHaveCount(0);
});

it('facade get ancestors returns correct ancestors', function () {
    $data = createNestedSetTestData();
    extract($data);

    $ancestors = Taxonomy::getAncestors($samsung->id);

    expect($ancestors)->toHaveCount(3);
    expect($ancestors->contains('name', 'Electronics'))->toBeTrue();
    expect($ancestors->contains('name', 'Smartphones'))->toBeTrue();
    expect($ancestors->contains('name', 'Android'))->toBeTrue();
});

it('facade get ancestors handles root taxonomy', function () {
    $data = createNestedSetTestData();
    extract($data);

    $ancestors = Taxonomy::getAncestors($electronics->id);
    expect($ancestors)->toHaveCount(0);
});

it('facade get ancestors handles invalid id', function () {
    $ancestors = Taxonomy::getAncestors(99999);
    expect($ancestors)->toHaveCount(0);
});

it('facade move to parent works correctly', function () {
    $data = createNestedSetTestData();
    extract($data);

    // Move samsung from android to smartphones
    $result = Taxonomy::moveToParent($samsung->id, $smartphones->id);
    expect($result)->toBeTrue();

    // Refresh models
    $samsung->refresh();
    $android->refresh();
    $smartphones->refresh();

    // Check that samsung is now child of smartphones
    expect($samsung->parent_id)->toBe($smartphones->id);
    expect($samsung->depth)->toBe(2);

    // Check nested set values are updated
    expect($samsung->lft > $smartphones->lft)->toBeTrue();
    expect($samsung->rgt < $smartphones->rgt)->toBeTrue();
});

it('facade move to parent handles invalid taxonomy id', function () {
    $data = createNestedSetTestData();
    extract($data);

    $result = Taxonomy::moveToParent(99999, $smartphones->id);
    expect($result)->toBeFalse();
});

it('facade move to parent handles invalid parent id', function () {
    $data = createNestedSetTestData();
    extract($data);

    $result = Taxonomy::moveToParent($samsung->id, 99999);
    expect($result)->toBeFalse();
});

it('facade move to root works correctly', function () {
    $data = createNestedSetTestData();
    extract($data);

    // Move samsung to root (no parent)
    $result = Taxonomy::moveToParent($samsung->id, null);
    expect($result)->toBeTrue();

    // Refresh model
    $samsung->refresh();

    // Check that samsung is now root
    expect($samsung->parent_id)->toBeNull();
    expect($samsung->depth)->toBe(0);
});

it('facade rebuild nested set corrects corrupted values', function () {
    $data = createNestedSetTestData();
    extract($data);

    // Corrupt nested set values
    TaxonomyModel::where('id', $electronics->id)->update(['lft' => 999, 'rgt' => 999]);
    TaxonomyModel::where('id', $smartphones->id)->update(['lft' => 999, 'rgt' => 999]);

    // Rebuild nested set
    Taxonomy::rebuildNestedSet(TaxonomyType::Category);

    // Refresh models
    $electronics->refresh();
    $smartphones->refresh();
    $android->refresh();
    $samsung->refresh();

    // Check that nested set values are corrected
    expect($electronics->lft)->toBe(1);
    expect($electronics->rgt)->toBe(8);
    expect($smartphones->lft > $electronics->lft)->toBeTrue();
    expect($smartphones->rgt < $electronics->rgt)->toBeTrue();
});

it('facade rebuild nested set only affects specified type', function () {
    $data = createNestedSetTestData();
    extract($data);

    // Corrupt nested set values for both types
    TaxonomyModel::whereIn('id', [$electronics->id, $tagParent->id])
        ->update(['lft' => 999, 'rgt' => 999]);

    // Rebuild only categories
    Taxonomy::rebuildNestedSet(TaxonomyType::Category);

    // Refresh models
    $electronics->refresh();
    $tagParent->refresh();

    // Electronics should be fixed, tag should still be corrupted
    expect($electronics->lft)->toBe(1);
    expect($tagParent->lft)->toBe(999);
});

it('facade clear cache for type clears correct cache', function () {
    createNestedSetTestData();

    // Generate cache for both types
    Taxonomy::getNestedTree(TaxonomyType::Category);
    Taxonomy::getNestedTree(TaxonomyType::Tag);

    // Verify cache exists
    expect(Cache::has('taxonomy_nested_tree_category'))->toBeTrue();
    expect(Cache::has('taxonomy_nested_tree_tag'))->toBeTrue();

    // Clear cache for categories only
    Taxonomy::clearCacheForType(TaxonomyType::Category);

    // Verify only category cache is cleared
    expect(Cache::has('taxonomy_nested_tree_category'))->toBeFalse();
    expect(Cache::has('taxonomy_nested_tree_tag'))->toBeTrue();
});

it('facade methods work with string type', function () {
    $data = createNestedSetTestData();
    extract($data);

    // Test with string type instead of enum
    $nestedTree = Taxonomy::getNestedTree('category');
    expect($nestedTree)->toHaveCount(1);
    $nestedFirst = $nestedTree->first();
    expect($nestedFirst)->not->toBeNull();
    expect($nestedFirst)->toBeInstanceOf(TaxonomyModel::class);
    expect($nestedFirst->name)->toBe('Electronics');

    Taxonomy::rebuildNestedSet('category');
    $electronics->refresh();
    expect($electronics->lft)->toBe(1);

    Taxonomy::clearCacheForType('category');
    expect(Cache::has('taxonomy.nested_tree.category'))->toBeFalse();
});

it('facade methods handle null type', function () {
    createNestedSetTestData();

    // Rebuild nested set for both types to ensure correct lft/rgt values
    Taxonomy::rebuildNestedSet(TaxonomyType::Category);
    Taxonomy::rebuildNestedSet(TaxonomyType::Tag);

    // Test getNestedTree with null type (should return all root taxonomies)
    $result = Taxonomy::getNestedTree();

    expect($result)->toHaveCount(2); // Should have 2 roots: Electronics and Parent Tag

    $rootNames = $result->pluck('name')->toArray();
    expect($rootNames)->toContain('Electronics');
    expect($rootNames)->toContain('Parent Tag');
});
