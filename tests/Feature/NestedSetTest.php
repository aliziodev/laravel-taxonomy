<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create test taxonomies with nested structure
    createTestTaxonomies();
});

function createTestTaxonomies(): void
{
    // Create root categories
    $electronics = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
        'slug' => 'electronics',
    ]);

    $clothing = Taxonomy::create([
        'name' => 'Clothing',
        'type' => TaxonomyType::Category->value,
        'slug' => 'clothing',
    ]);

    // Create subcategories for Electronics
    $computers = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
        'slug' => 'computers',
        'parent_id' => $electronics->id,
    ]);

    $phones = Taxonomy::create([
        'name' => 'Phones',
        'type' => TaxonomyType::Category->value,
        'slug' => 'phones',
        'parent_id' => $electronics->id,
    ]);

    // Create sub-subcategories for Computers
    $laptops = Taxonomy::create([
        'name' => 'Laptops',
        'type' => TaxonomyType::Category->value,
        'slug' => 'laptops',
        'parent_id' => $computers->id,
    ]);

    $desktops = Taxonomy::create([
        'name' => 'Desktops',
        'type' => TaxonomyType::Category->value,
        'slug' => 'desktops',
        'parent_id' => $computers->id,
    ]);

    // Create subcategories for Clothing
    $menClothing = Taxonomy::create([
        'name' => 'Men Clothing',
        'type' => TaxonomyType::Category->value,
        'slug' => 'men-clothing',
        'parent_id' => $clothing->id,
    ]);

    $womenClothing = Taxonomy::create([
        'name' => 'Women Clothing',
        'type' => TaxonomyType::Category->value,
        'slug' => 'women-clothing',
        'parent_id' => $clothing->id,
    ]);
}

it('sets nested set values correctly on creation', function () {
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    $computers = Taxonomy::where('slug', 'computers')->first();
    $laptops = Taxonomy::where('slug', 'laptops')->first();

    // Root should have depth 0
    expect($electronics)->not->toBeNull();
    expect($electronics->depth)->toBe(0);
    expect($electronics->lft)->not->toBeNull();
    expect($electronics->rgt)->not->toBeNull();

    // Child should have depth 1
    expect($computers)->not->toBeNull();
    expect($computers->depth)->toBe(1);
    expect($computers->lft)->toBeGreaterThan($electronics->lft);
    expect($computers->rgt)->toBeLessThan($electronics->rgt);

    // Grandchild should have depth 2
    expect($laptops)->not->toBeNull();
    expect($laptops->depth)->toBe(2);
    expect($laptops->lft)->toBeGreaterThan($computers->lft);
    expect($laptops->rgt)->toBeLessThan($computers->rgt);
});

it('returns correct descendants', function () {
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();
    $descendants = $electronics->getDescendants();

    // Electronics should have 4 descendants: computers, phones, laptops, desktops
    expect($descendants)->toHaveCount(4);

    $descendantSlugs = $descendants->pluck('slug')->toArray();
    expect($descendantSlugs)->toContain('computers');
    expect($descendantSlugs)->toContain('phones');
    expect($descendantSlugs)->toContain('laptops');
    expect($descendantSlugs)->toContain('desktops');
});

it('returns correct ancestors', function () {
    $laptops = Taxonomy::where('slug', 'laptops')->first();
    expect($laptops)->not->toBeNull();
    $ancestors = $laptops->getAncestors();

    // Laptops should have 2 ancestors: computers and electronics
    expect($ancestors)->toHaveCount(2);

    $ancestorSlugs = $ancestors->pluck('slug')->toArray();
    expect($ancestorSlugs)->toContain('computers');
    expect($ancestorSlugs)->toContain('electronics');
});

it('checks ancestor relationships correctly', function () {
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    $computers = Taxonomy::where('slug', 'computers')->first();
    $laptops = Taxonomy::where('slug', 'laptops')->first();
    $clothing = Taxonomy::where('slug', 'clothing')->first();

    expect($electronics)->not->toBeNull();
    expect($computers)->not->toBeNull();
    expect($laptops)->not->toBeNull();
    expect($clothing)->not->toBeNull();

    expect($electronics->isAncestorOf($computers))->toBeTrue();
    expect($electronics->isAncestorOf($laptops))->toBeTrue();
    expect($computers->isAncestorOf($laptops))->toBeTrue();

    expect($computers->isAncestorOf($electronics))->toBeFalse();
    expect($laptops->isAncestorOf($computers))->toBeFalse();
    expect($electronics->isAncestorOf($clothing))->toBeFalse();
});

it('checks descendant relationships correctly', function () {
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    $computers = Taxonomy::where('slug', 'computers')->first();
    $laptops = Taxonomy::where('slug', 'laptops')->first();

    expect($electronics)->not->toBeNull();
    expect($computers)->not->toBeNull();
    expect($laptops)->not->toBeNull();

    expect($computers->isDescendantOf($electronics))->toBeTrue();
    expect($laptops->isDescendantOf($electronics))->toBeTrue();
    expect($laptops->isDescendantOf($computers))->toBeTrue();

    expect($electronics->isDescendantOf($computers))->toBeFalse();
    expect($computers->isDescendantOf($laptops))->toBeFalse();
});

it('updates nested set correctly when moving to parent', function () {
    $phones = Taxonomy::where('slug', 'phones')->first();
    $clothing = Taxonomy::where('slug', 'clothing')->first();

    expect($phones)->not->toBeNull();
    expect($clothing)->not->toBeNull();

    // Move phones from electronics to clothing
    $phones->moveToParent($clothing->id);

    $phones->refresh();
    $clothing->refresh();

    // Check that phones is now under clothing
    expect($phones->parent_id)->toBe($clothing->id);
    expect($phones->depth)->toBe(1); // Should be depth 1 under clothing

    // Check that phones is now a descendant of clothing
    expect($clothing->isAncestorOf($phones))->toBeTrue();

    // Check that phones is no longer a descendant of electronics
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();
    expect($electronics->isAncestorOf($phones))->toBeFalse();
});

it('maintains correct structure when rebuilding nested set', function () {
    // Get initial structure
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();
    $initialDescendants = $electronics->getDescendants()->count();

    // Rebuild nested set
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

    // Refresh and check structure is maintained
    $electronics->refresh();
    $newDescendants = $electronics->getDescendants()->count();

    expect($newDescendants)->toBe($initialDescendants);

    // Check that all taxonomies still have correct nested set values
    $allTaxonomies = Taxonomy::where('type', TaxonomyType::Category->value)->get();
    foreach ($allTaxonomies as $taxonomy) {
        expect($taxonomy->lft)->not->toBeNull();
        expect($taxonomy->rgt)->not->toBeNull();
        expect($taxonomy->depth)->not->toBeNull();
        expect($taxonomy->lft)->toBeLessThan($taxonomy->rgt);
    }
});

it('returns correct nested tree structure', function () {
    $tree = Taxonomy::getNestedTree(TaxonomyType::Category);

    // Should have 2 root nodes: Electronics and Clothing
    expect($tree)->toHaveCount(2);

    $rootSlugs = $tree->pluck('slug')->toArray();
    expect($rootSlugs)->toContain('electronics');
    expect($rootSlugs)->toContain('clothing');

    // Find electronics in tree and check its children
    $electronics = $tree->firstWhere('slug', 'electronics');
    expect($electronics)->not->toBeNull();
    expect($electronics->children_nested)->not->toBeNull();
    expect($electronics->children_nested)->toHaveCount(2); // computers and phones

    // Check computers has children
    $computers = $electronics->children_nested->firstWhere('slug', 'computers');
    expect($computers)->not->toBeNull();
    expect($computers->children_nested)->not->toBeNull();
    expect($computers->children_nested)->toHaveCount(2); // laptops and desktops
});

it('works correctly with scopes', function () {
    // Test roots scope
    $roots = Taxonomy::roots()->where('type', TaxonomyType::Category->value)->get();
    expect($roots)->toHaveCount(2);

    // Test atDepth scope
    $depthOne = Taxonomy::atDepth(1)->where('type', TaxonomyType::Category->value)->get();
    expect($depthOne)->toHaveCount(4); // computers, phones, men-clothing, women-clothing

    $depthTwo = Taxonomy::atDepth(2)->where('type', TaxonomyType::Category->value)->get();
    expect($depthTwo)->toHaveCount(2); // laptops, desktops

    // Test nestedSetOrder scope
    $ordered = Taxonomy::nestedSetOrder()->where('type', TaxonomyType::Category->value)->get();
    expect($ordered)->toHaveCount(8);

    // Check that they are ordered by lft
    $previousLft = 0;
    foreach ($ordered as $taxonomy) {
        expect($taxonomy->lft)->toBeGreaterThan($previousLft);
        $previousLft = $taxonomy->lft;
    }
});

it('maintains nested set integrity when deleting taxonomy', function () {
    $computers = Taxonomy::where('slug', 'computers')->first();
    $laptops = Taxonomy::where('slug', 'laptops')->first();
    $desktops = Taxonomy::where('slug', 'desktops')->first();
    $electronics = Taxonomy::where('slug', 'electronics')->first();

    expect($computers)->not->toBeNull();
    expect($laptops)->not->toBeNull();
    expect($desktops)->not->toBeNull();
    expect($electronics)->not->toBeNull();

    // Delete computers (which has children)
    $computers->delete();

    // Check that children are moved to parent
    $laptops->refresh();
    $desktops->refresh();

    expect($laptops->parent_id)->toBe($electronics->id);
    expect($desktops->parent_id)->toBe($electronics->id);
    expect($laptops->depth)->toBe(1);
    expect($desktops->depth)->toBe(1);
});

it('returns correct depth with getLevel method', function () {
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    $computers = Taxonomy::where('slug', 'computers')->first();
    $laptops = Taxonomy::where('slug', 'laptops')->first();

    expect($electronics)->not->toBeNull();
    expect($computers)->not->toBeNull();
    expect($laptops)->not->toBeNull();

    expect($electronics->getLevel())->toBe(0);
    expect($computers->getLevel())->toBe(1);
    expect($laptops->getLevel())->toBe(2);
});

it('works with different taxonomy types', function () {
    // Create tags with nested structure
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

    $techTag->refresh();
    $webTag->refresh();

    expect($techTag->depth)->toBe(0);
    expect($webTag->depth)->toBe(1);
    expect($techTag->isAncestorOf($webTag))->toBeTrue();

    // Ensure tags don't interfere with categories
    $electronics = Taxonomy::where('slug', 'electronics')->first();
    expect($electronics)->not->toBeNull();
    $electronics->refresh();

    expect($techTag->isAncestorOf($electronics))->toBeFalse();
    expect($electronics->isAncestorOf($techTag))->toBeFalse();
});

it('works when models are strict', function () {
    Model::shouldBeStrict();
    $tree = Taxonomy::getNestedTree(TaxonomyType::Category);
    expect($tree)->toHaveCount(2);
});
