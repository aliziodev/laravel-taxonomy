<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

uses(TestCase::class, RefreshDatabase::class);

it('can use taxonomy facade', function () {
    // Create taxonomy using facade
    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
        'description' => 'Electronic products',
    ]);

    expect($category)->toBeInstanceOf(TaxonomyModel::class);
    expect($category->name)->toBe('Electronics');
    expect($category->slug)->toBe('electronics');
    expect($category->type)->toBe(TaxonomyType::Category->value);
});

it('can create taxonomy hierarchy using facade', function () {
    // Create parent taxonomy
    $parent = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    // Create child taxonomy
    $child = Taxonomy::create([
        'name' => 'Smartphones',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $parent->id,
    ]);

    // Create grandchild taxonomy
    $grandchild = Taxonomy::create([
        'name' => 'Android Phones',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $child->id,
    ]);

    // Get tree using facade
    $tree = Taxonomy::tree(TaxonomyType::Category);

    expect($tree)->toHaveCount(1);
    expect($tree[0])->not->toBeNull();
    expect($tree[0])->toBeInstanceOf(TaxonomyModel::class);
    expect($tree[0]->name)->toBe('Electronics');
    expect($tree[0]->children)->not->toBeNull();
    expect($tree[0]->children)->toHaveCount(1);
    expect($tree[0]->children[0])->not->toBeNull();
    expect($tree[0]->children[0])->toBeInstanceOf(TaxonomyModel::class);
    expect($tree[0]->children[0]->name)->toBe('Smartphones');
    expect($tree[0]->children[0]->children)->not->toBeNull();
    expect($tree[0]->children[0]->children)->toHaveCount(1);
    expect($tree[0]->children[0]->children[0])->not->toBeNull();
    expect($tree[0]->children[0]->children[0])->toBeInstanceOf(TaxonomyModel::class);
    expect($tree[0]->children[0]->children[0]->name)->toBe('Android Phones');
});

it('can find taxonomy by slug using facade', function () {
    // Create taxonomy
    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    // Find by slug using facade
    $found = Taxonomy::findBySlug('electronics');

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($category->id);
});

it('can check if taxonomy exists using facade', function () {
    // Create taxonomy
    Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    // Check if exists using facade
    $exists = Taxonomy::exists('electronics');
    $notExists = Taxonomy::exists('not-exists');

    expect($exists)->toBeTrue();
    expect($notExists)->toBeFalse();
});

it('can search taxonomies using facade', function () {
    // Create taxonomies
    Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
        'description' => 'Electronic products',
    ]);

    Taxonomy::create([
        'name' => 'Clothing',
        'type' => TaxonomyType::Category->value,
        'description' => 'Clothing products',
    ]);

    Taxonomy::create([
        'name' => 'Electronic Accessories',
        'type' => TaxonomyType::Category->value,
    ]);

    // Search using facade
    $results = Taxonomy::search('Electronic');

    expect($results)->toHaveCount(2);
    expect($results->pluck('name'))->toContain('Electronics');
    expect($results->pluck('name'))->toContain('Electronic Accessories');
});

it('can get taxonomy types using facade', function () {
    // Get types using facade
    $types = Taxonomy::getTypes();

    expect($types)->toContain(TaxonomyType::Category->value);
    expect($types)->toContain(TaxonomyType::Tag->value);
});

it('can use taxonomy with models', function () {
    // Create taxonomies
    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag1 = Taxonomy::create([
        'name' => 'Sale',
        'type' => TaxonomyType::Tag->value,
    ]);

    $tag2 = Taxonomy::create([
        'name' => 'New',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Create products
    $product1 = Product::create(['name' => 'Smartphone']);
    $product2 = Product::create(['name' => 'Laptop']);
    $product3 = Product::create(['name' => 'T-shirt']);

    // Attach taxonomies to products
    $product1->attachTaxonomies([$category, $tag1]);
    $product2->attachTaxonomies([$category, $tag2]);
    $product3->attachTaxonomies($tag1);

    // Test querying products by taxonomy
    $electronicsProducts = Product::withTaxonomyType(TaxonomyType::Category)->get();
    expect($electronicsProducts)->toHaveCount(2);

    $saleProducts = Product::withAnyTaxonomies($tag1)->get();
    expect($saleProducts)->toHaveCount(2);
    expect($saleProducts->pluck('name'))->toContain('Smartphone');
    expect($saleProducts->pluck('name'))->toContain('T-shirt');

    $newElectronicsProducts = Product::withAllTaxonomies([$category, $tag2])->get();
    expect($newElectronicsProducts)->toHaveCount(1);
    $firstProduct = $newElectronicsProducts->first();
    expect($firstProduct)->not->toBeNull();
    expect($firstProduct)->toBeInstanceOf(Product::class);
    expect($firstProduct->name)->toBe('Laptop');
});

it('caches taxonomy trees', function () {
    // Create taxonomies
    $parent = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    Taxonomy::create([
        'name' => 'Smartphones',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $parent->id,
    ]);

    // Clear any existing cache
    Cache::flush();

    // First call should cache the result
    $tree1 = Taxonomy::tree(TaxonomyType::Category);

    // Create another taxonomy after the cache is set
    Taxonomy::create([
        'name' => 'Laptops',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $parent->id,
    ]);

    // Second call should return cached result (without the new taxonomy)
    $tree2 = Taxonomy::tree(TaxonomyType::Category);

    expect($tree1[0])->not->toBeNull();
    expect($tree1[0])->toBeInstanceOf(TaxonomyModel::class);
    expect($tree1[0]->children)->not->toBeNull();
    expect($tree1[0]->children)->toHaveCount(1);
    expect($tree2[0])->not->toBeNull();
    expect($tree2[0])->toBeInstanceOf(TaxonomyModel::class);
    expect($tree2[0]->children)->not->toBeNull();
    expect($tree2[0]->children)->toHaveCount(1);

    // Clear cache and get fresh result
    Cache::flush();
    $tree3 = Taxonomy::tree(TaxonomyType::Category);

    expect($tree3[0])->not->toBeNull();
    expect($tree3[0])->toBeInstanceOf(TaxonomyModel::class);
    expect($tree3[0]->children)->not->toBeNull();
    expect($tree3[0]->children)->toHaveCount(2);
});

it('can paginate find many taxonomies', function () {
    // Create multiple taxonomies
    $taxonomies = [];
    for ($i = 1; $i <= 15; ++$i) {
        $taxonomies[] = Taxonomy::create([
            'name' => "Test Taxonomy {$i}",
            'type' => TaxonomyType::Category->value,
        ]);
    }

    // Get IDs of all taxonomies
    $ids = collect($taxonomies)->pluck('id')->toArray();

    // Test pagination with 5 items per page
    $page1 = Taxonomy::findMany($ids, 5, 1);
    $page2 = Taxonomy::findMany($ids, 5, 2);
    $page3 = Taxonomy::findMany($ids, 5, 3);

    // Assert that we get LengthAwarePaginator instances
    expect($page1)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($page2)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($page3)->toBeInstanceOf(LengthAwarePaginator::class);

    // Assert that each page has the correct number of items
    expect($page1->items())->toHaveCount(5);
    expect($page2->items())->toHaveCount(5);
    expect($page3->items())->toHaveCount(5);

    // Assert total count is correct
    expect($page1->total())->toBe(15);

    // Assert that different pages have different items
    expect($page1->items()[0]->id)->not->toBe($page2->items()[0]->id);
    expect($page2->items()[0]->id)->not->toBe($page3->items()[0]->id);

    // Test without pagination (should return all items)
    $allTaxonomies = Taxonomy::findMany($ids);
    expect($allTaxonomies)->toBeInstanceOf(Collection::class);
    expect($allTaxonomies)->toHaveCount(15);
});

it('can paginate find by type', function () {
    // Create multiple taxonomies of different types
    for ($i = 1; $i <= 10; ++$i) {
        Taxonomy::create([
            'name' => "Category {$i}",
            'type' => TaxonomyType::Category->value,
        ]);
    }

    for ($i = 1; $i <= 5; ++$i) {
        Taxonomy::create([
            'name' => "Tag {$i}",
            'type' => TaxonomyType::Tag->value,
        ]);
    }

    // Test pagination with 4 items per page for categories
    $page1 = Taxonomy::findByType(TaxonomyType::Category, 4, 1);
    $page2 = Taxonomy::findByType(TaxonomyType::Category, 4, 2);
    $page3 = Taxonomy::findByType(TaxonomyType::Category, 4, 3);

    // Assert that we get LengthAwarePaginator instances
    expect($page1)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($page2)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($page3)->toBeInstanceOf(LengthAwarePaginator::class);

    // Assert that each page has the correct number of items
    expect($page1->items())->toHaveCount(4);
    expect($page2->items())->toHaveCount(4);
    expect($page3->items())->toHaveCount(2); // Last page has only 2 items

    // Assert total count is correct
    expect($page1->total())->toBe(10);

    // Test without pagination (should return all items)
    $allCategories = Taxonomy::findByType(TaxonomyType::Category);
    $allTags = Taxonomy::findByType(TaxonomyType::Tag);

    expect($allCategories)->toBeInstanceOf(Collection::class);
    expect($allTags)->toBeInstanceOf(Collection::class);
    expect($allCategories)->toHaveCount(10);
    expect($allTags)->toHaveCount(5);
});

it('can paginate find by parent', function () {
    // Create a parent taxonomy
    $parent = Taxonomy::create([
        'name' => 'Parent Category',
        'type' => TaxonomyType::Category->value,
    ]);

    // Create multiple child taxonomies
    for ($i = 1; $i <= 12; ++$i) {
        Taxonomy::create([
            'name' => "Child Category {$i}",
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);
    }

    // Test pagination with 5 items per page
    $page1 = Taxonomy::findByParent($parent->id, 5, 1);
    $page2 = Taxonomy::findByParent($parent->id, 5, 2);
    $page3 = Taxonomy::findByParent($parent->id, 5, 3);

    // Assert that we get LengthAwarePaginator instances
    expect($page1)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($page2)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($page3)->toBeInstanceOf(LengthAwarePaginator::class);

    // Assert that each page has the correct number of items
    expect($page1->items())->toHaveCount(5);
    expect($page2->items())->toHaveCount(5);
    expect($page3->items())->toHaveCount(2); // Last page has only 2 items

    // Assert total count is correct
    expect($page1->total())->toBe(12);

    // Test without pagination (should return all items)
    $allChildren = Taxonomy::findByParent($parent->id);
    expect($allChildren)->toBeInstanceOf(Collection::class);
    expect($allChildren)->toHaveCount(12);

    // Test root level taxonomies
    $rootTaxonomies = Taxonomy::findByParent(null);
    expect($rootTaxonomies)->toHaveCount(1); // Only the parent taxonomy
});

it('can paginate search results', function () {
    // Create taxonomies with searchable terms
    for ($i = 1; $i <= 8; ++$i) {
        Taxonomy::create([
            'name' => "Searchable Item {$i}",
            'type' => TaxonomyType::Category->value,
            'description' => "This is a searchable description {$i}",
        ]);
    }

    for ($i = 1; $i <= 7; ++$i) {
        Taxonomy::create([
            'name' => "Another Item {$i}",
            'type' => TaxonomyType::Tag->value,
            'description' => "This contains searchable content {$i}",
        ]);
    }

    // Test pagination with 5 items per page
    $page1 = Taxonomy::search('searchable', null, 5, 1);
    $page2 = Taxonomy::search('searchable', null, 5, 2);
    $page3 = Taxonomy::search('searchable', null, 5, 3);

    // Assert that we get LengthAwarePaginator instances
    expect($page1)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($page2)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($page3)->toBeInstanceOf(LengthAwarePaginator::class);

    // Assert that each page has the correct number of items
    expect($page1->items())->toHaveCount(5);
    expect($page2->items())->toHaveCount(5);
    expect($page3->items())->toHaveCount(5);

    // Assert total count is correct (all 15 items should match)
    expect($page1->total())->toBe(15);

    // Test search with type filter
    $categoryResults = Taxonomy::search('searchable', TaxonomyType::Category, 10, 1);
    expect($categoryResults)->toBeInstanceOf(LengthAwarePaginator::class);
    expect($categoryResults->items())->toHaveCount(8);
    expect($categoryResults->total())->toBe(8);

    // Test without pagination (should return all matching items)
    $allResults = Taxonomy::search('searchable');
    expect($allResults)->toBeInstanceOf(Collection::class);
    expect($allResults)->toHaveCount(15);
});
