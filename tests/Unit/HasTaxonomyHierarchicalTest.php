<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

// Helper function to create test data
function createTestHierarchy()
{
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

    $product = Product::create([
        'name' => 'Test Product',
        'description' => 'Test Description',
    ]);

    // Rebuild nested set to ensure correct lft/rgt values
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

    return compact('electronics', 'smartphones', 'android', 'samsung', 'product');
}

it('can get hierarchical taxonomies includes descendants', function () {
    $data = createTestHierarchy();
    extract($data);

    // Attach smartphones taxonomy to product
    $product->attachTaxonomies([$smartphones->id]);

    $hierarchical = $product->getHierarchicalTaxonomies(TaxonomyType::Category);

    // Should include smartphones and its descendants (android, samsung)
    expect($hierarchical)->toHaveCount(3);
    expect($hierarchical->contains('id', $smartphones->id))->toBeTrue();
    expect($hierarchical->contains('id', $android->id))->toBeTrue();
    expect($hierarchical->contains('id', $samsung->id))->toBeTrue();
    expect($hierarchical->contains('id', $electronics->id))->toBeFalse();
});

it('can get hierarchical taxonomies with multiple taxonomies', function () {
    $data = createTestHierarchy();
    extract($data);

    // Attach both electronics and android taxonomies
    $product->attachTaxonomies([$electronics->id, $android->id]);

    $hierarchical = $product->getHierarchicalTaxonomies(TaxonomyType::Category);

    // Should include all taxonomies (electronics has smartphones, android as descendants; android has samsung as descendant)
    expect($hierarchical)->toHaveCount(4);
    expect($hierarchical->contains('id', $electronics->id))->toBeTrue();
    expect($hierarchical->contains('id', $smartphones->id))->toBeTrue();
    expect($hierarchical->contains('id', $android->id))->toBeTrue();
    expect($hierarchical->contains('id', $samsung->id))->toBeTrue();
});

it('can get ancestor taxonomies returns correct ancestors', function () {
    $data = createTestHierarchy();
    extract($data);

    // Attach samsung taxonomy to product
    $product->attachTaxonomies([$samsung->id]);

    $ancestors = $product->getAncestorTaxonomies(TaxonomyType::Category);

    // Should include all ancestors of samsung (android, smartphones, electronics)
    expect($ancestors)->toHaveCount(3);
    expect($ancestors->contains('id', $electronics->id))->toBeTrue();
    expect($ancestors->contains('id', $smartphones->id))->toBeTrue();
    expect($ancestors->contains('id', $android->id))->toBeTrue();
    expect($ancestors->contains('id', $samsung->id))->toBeFalse();
});

it('can scope with taxonomy hierarchy includes descendants', function () {
    $data = createTestHierarchy();
    extract($data);

    $product1 = Product::create(['name' => 'Product 1', 'description' => 'Desc 1']);
    $product2 = Product::create(['name' => 'Product 2', 'description' => 'Desc 2']);
    $product3 = Product::create(['name' => 'Product 3', 'description' => 'Desc 3']);

    // Attach different levels of hierarchy
    $product1->attachTaxonomies([$smartphones->id]); // smartphones
    $product2->attachTaxonomies([$android->id]);     // android (child of smartphones)
    $product3->attachTaxonomies([$electronics->id]); // electronics (parent of smartphones)

    // Query for products with smartphones hierarchy (should include descendants)
    $products = Product::withTaxonomyHierarchy($smartphones->id, true)->get();

    expect($products)->toHaveCount(2);
    expect($products->contains('id', $product1->id))->toBeTrue(); // has smartphones directly
    expect($products->contains('id', $product2->id))->toBeTrue(); // has android (descendant of smartphones)
    expect($products->contains('id', $product3->id))->toBeFalse(); // has electronics (ancestor, not descendant)
});

it('can scope with taxonomy hierarchy excludes descendants when disabled', function () {
    $data = createTestHierarchy();
    extract($data);

    $product1 = Product::create(['name' => 'Product 1', 'description' => 'Desc 1']);
    $product2 = Product::create(['name' => 'Product 2', 'description' => 'Desc 2']);

    $product1->attachTaxonomies([$smartphones->id]);
    $product2->attachTaxonomies([$android->id]);

    // Query for products with smartphones hierarchy (exclude descendants)
    $products = Product::withTaxonomyHierarchy($smartphones->id, false)->get();

    expect($products)->toHaveCount(1);
    expect($products->contains('id', $product1->id))->toBeTrue(); // has smartphones directly
    expect($products->contains('id', $product2->id))->toBeFalse(); // has android (descendant, but excluded)
});

it('can scope with taxonomy at depth filters correctly', function () {
    $data = createTestHierarchy();
    extract($data);

    $product1 = Product::create(['name' => 'Product 1', 'description' => 'Desc 1']);
    $product2 = Product::create(['name' => 'Product 2', 'description' => 'Desc 2']);
    $product3 = Product::create(['name' => 'Product 3', 'description' => 'Desc 3']);
    $product4 = Product::create(['name' => 'Product 4', 'description' => 'Desc 4']);

    $product1->attachTaxonomies([$electronics->id]);  // depth 0
    $product2->attachTaxonomies([$smartphones->id]);  // depth 1
    $product3->attachTaxonomies([$android->id]);      // depth 2
    $product4->attachTaxonomies([$samsung->id]);      // depth 3

    // Test depth 1
    $productsAtDepth1 = Product::withTaxonomyAtDepth(1, TaxonomyType::Category)->get();
    expect($productsAtDepth1)->toHaveCount(1);
    expect($productsAtDepth1->contains('id', $product2->id))->toBeTrue();

    // Test depth 2
    $productsAtDepth2 = Product::withTaxonomyAtDepth(2, TaxonomyType::Category)->get();
    expect($productsAtDepth2)->toHaveCount(1);
    expect($productsAtDepth2->contains('id', $product3->id))->toBeTrue();
});

it('can check has ancestor taxonomy returns correct result', function () {
    $data = createTestHierarchy();
    extract($data);

    // Attach electronics taxonomy to product
    $product->attachTaxonomies([$electronics->id]);

    // Check if product has ancestor of samsung (should be true, electronics is ancestor of samsung)
    expect($product->hasAncestorTaxonomy($samsung->id))->toBeTrue();

    // Check if product has ancestor of electronics (should be false, electronics has no ancestors)
    expect($product->hasAncestorTaxonomy($electronics->id))->toBeFalse();
});

it('can check has descendant taxonomy returns correct result', function () {
    $data = createTestHierarchy();
    extract($data);

    // Attach samsung taxonomy to product
    $product->attachTaxonomies([$samsung->id]);

    // Check if product has descendant of electronics (should be true, samsung is descendant of electronics)
    expect($product->hasDescendantTaxonomy($electronics->id))->toBeTrue();

    // Check if product has descendant of samsung (should be false, samsung has no descendants)
    expect($product->hasDescendantTaxonomy($samsung->id))->toBeFalse();
});

it('can handle hierarchical methods with invalid taxonomy id', function () {
    $data = createTestHierarchy();
    extract($data);

    $product->attachTaxonomies([$electronics->id]);

    // Test with non-existent taxonomy ID
    $products = Product::withTaxonomyHierarchy(99999)->get();
    expect($products)->toHaveCount(0);

    expect($product->hasAncestorTaxonomy(99999))->toBeFalse();
    expect($product->hasDescendantTaxonomy(99999))->toBeFalse();
});

it('can handle hierarchical methods work with different types', function () {
    $data = createTestHierarchy();
    extract($data);

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

    $product->attachTaxonomies([$electronics->id, $tagParent->id]);

    // Test hierarchical taxonomies for categories only
    $categoryHierarchical = $product->getHierarchicalTaxonomies(TaxonomyType::Category);
    expect($categoryHierarchical)->toHaveCount(4); // electronics + smartphones + android + samsung
    expect($categoryHierarchical->contains('id', $tagParent->id))->toBeFalse();
    expect($categoryHierarchical->contains('id', $tagChild->id))->toBeFalse();

    // Test hierarchical taxonomies for tags only
    $tagHierarchical = $product->getHierarchicalTaxonomies(TaxonomyType::Tag);
    expect($tagHierarchical)->toHaveCount(2); // tagParent + tagChild
    expect($tagHierarchical->contains('id', $tagParent->id))->toBeTrue();
    expect($tagHierarchical->contains('id', $tagChild->id))->toBeTrue();
});
