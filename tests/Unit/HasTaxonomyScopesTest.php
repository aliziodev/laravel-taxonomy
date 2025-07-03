<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

// Helper function to create test data
function createScopesTestData()
{
    // Create taxonomies
    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Books',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag1 = Taxonomy::create([
        'name' => 'Featured',
        'type' => TaxonomyType::Tag->value,
    ]);

    $tag2 = Taxonomy::create([
        'name' => 'Sale',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Create products
    $product1 = Product::create(['name' => 'Laptop']);
    $product2 = Product::create(['name' => 'Book']);
    $product3 = Product::create(['name' => 'Phone']);

    // Attach taxonomies to products
    $product1->attachTaxonomies([$category1, $tag1]);
    $product2->attachTaxonomies([$category2, $tag2]);
    $product3->attachTaxonomies([$category1, $tag2]);

    return compact('category1', 'category2', 'tag1', 'tag2', 'product1', 'product2', 'product3');
}

it('can filter by taxonomy id', function () {
    $data = createScopesTestData();
    extract($data);

    $products = Product::withTaxonomy($category1->id)->get();

    expect($products)->toHaveCount(2);
    expect($products->pluck('id'))->toContain($product1->id);
    expect($products->pluck('id'))->toContain($product3->id);
    expect($products->pluck('id'))->not->toContain($product2->id);
});

it('can filter by taxonomy instance', function () {
    $data = createScopesTestData();
    extract($data);

    $products = Product::withTaxonomy($category2)->get();

    expect($products)->toHaveCount(1);
    expect($products->pluck('id'))->toContain($product2->id);
    expect($products->pluck('id'))->not->toContain($product1->id);
    expect($products->pluck('id'))->not->toContain($product3->id);
});

it('can exclude taxonomies by ids', function () {
    $data = createScopesTestData();
    extract($data);

    $products = Product::withoutTaxonomies([$category1->id])->get();

    expect($products)->toHaveCount(1);
    expect($products->pluck('id'))->toContain($product2->id);
    expect($products->pluck('id'))->not->toContain($product1->id);
    expect($products->pluck('id'))->not->toContain($product3->id);
});

it('can exclude taxonomies by instances', function () {
    $data = createScopesTestData();
    extract($data);

    $products = Product::withoutTaxonomies([$tag1, $tag2])->get();

    expect($products)->toHaveCount(0);
});

it('can exclude multiple taxonomies', function () {
    $data = createScopesTestData();
    extract($data);

    $products = Product::withoutTaxonomies([$category1->id, $tag2->id])->get();

    expect($products)->toHaveCount(0);
});

it('can filter by multiple taxonomy criteria', function () {
    $data = createScopesTestData();
    extract($data);

    $filters = [
        TaxonomyType::Category->value => 'electronics',
        TaxonomyType::Tag->value => 'featured',
    ];

    $products = Product::filterByTaxonomies($filters)->get();

    expect($products)->toHaveCount(1);
    expect($products->pluck('id'))->toContain($product1->id);
});

it('can filter by taxonomy type with multiple values', function () {
    $data = createScopesTestData();
    extract($data);

    $filters = [
        TaxonomyType::Tag->value => ['featured', 'sale'],
    ];

    $products = Product::filterByTaxonomies($filters)->get();

    expect($products)->toHaveCount(3);
    expect($products->pluck('id'))->toContain($product1->id);
    expect($products->pluck('id'))->toContain($product2->id);
    expect($products->pluck('id'))->toContain($product3->id);
});

it('can filter by single taxonomy value', function () {
    $data = createScopesTestData();
    extract($data);

    $filters = [
        TaxonomyType::Category->value => 'books',
    ];

    $products = Product::filterByTaxonomies($filters)->get();

    expect($products)->toHaveCount(1);
    expect($products->pluck('id'))->toContain($product2->id);
});

it('returns empty result when no matches found', function () {
    $data = createScopesTestData();
    extract($data);

    $filters = [
        TaxonomyType::Category->value => 'nonexistent',
    ];

    $products = Product::filterByTaxonomies($filters)->get();

    expect($products)->toHaveCount(0);
});

it('can chain multiple scopes', function () {
    $data = createScopesTestData();
    extract($data);

    $products = Product::withTaxonomy($category1)
        ->withoutTaxonomies([$tag1])
        ->get();

    expect($products)->toHaveCount(1);
    expect($products->pluck('id'))->toContain($product3->id);
    expect($products->pluck('id'))->not->toContain($product1->id);
});

it('can combine filter by taxonomies with other scopes', function () {
    $data = createScopesTestData();
    extract($data);

    $filters = [
        TaxonomyType::Category->value => 'electronics',
    ];

    $products = Product::filterByTaxonomies($filters)
        ->withoutTaxonomies([$tag2])
        ->get();

    expect($products)->toHaveCount(1);
    expect($products->pluck('id'))->toContain($product1->id);
    expect($products->pluck('id'))->not->toContain($product3->id);
});
