<?php

namespace Tests\Feature;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;

class TaxonomyFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_use_taxonomy_facade()
    {
        // Create taxonomy using facade
        $category = Taxonomy::create([
            'name' => 'Electronics',
            'type' => TaxonomyType::Category->value,
            'description' => 'Electronic products',
        ]);

        $this->assertInstanceOf(TaxonomyModel::class, $category);
        $this->assertEquals('Electronics', $category->name);
        $this->assertEquals('electronics', $category->slug);
        $this->assertEquals(TaxonomyType::Category->value, $category->type);
    }

    #[Test]
    public function it_can_create_taxonomy_hierarchy_using_facade()
    {
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

        $this->assertCount(1, $tree);
        $this->assertEquals('Electronics', $tree[0]->name);
        $this->assertCount(1, $tree[0]->children);
        $this->assertEquals('Smartphones', $tree[0]->children[0]->name);
        $this->assertCount(1, $tree[0]->children[0]->children);
        $this->assertEquals('Android Phones', $tree[0]->children[0]->children[0]->name);
    }

    #[Test]
    public function it_can_find_taxonomy_by_slug_using_facade()
    {
        // Create taxonomy
        $category = Taxonomy::create([
            'name' => 'Electronics',
            'type' => TaxonomyType::Category->value,
        ]);

        // Find by slug using facade
        $found = Taxonomy::findBySlug('electronics');

        $this->assertNotNull($found);
        $this->assertEquals($category->id, $found->id);
    }

    #[Test]
    public function it_can_check_if_taxonomy_exists_using_facade()
    {
        // Create taxonomy
        Taxonomy::create([
            'name' => 'Electronics',
            'type' => TaxonomyType::Category->value,
        ]);

        // Check if exists using facade
        $exists = Taxonomy::exists('electronics');
        $notExists = Taxonomy::exists('not-exists');

        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    #[Test]
    public function it_can_search_taxonomies_using_facade()
    {
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

        $this->assertCount(2, $results);
        $this->assertContains('Electronics', $results->pluck('name'));
        $this->assertContains('Electronic Accessories', $results->pluck('name'));
    }

    #[Test]
    public function it_can_get_taxonomy_types_using_facade()
    {
        // Get types using facade
        $types = Taxonomy::getTypes();

        $this->assertContains(TaxonomyType::Category->value, $types);
        $this->assertContains(TaxonomyType::Tag->value, $types);
    }

    #[Test]
    public function it_can_use_taxonomy_with_models()
    {
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
        $this->assertCount(2, $electronicsProducts);

        $saleProducts = Product::withAnyTaxonomies($tag1)->get();
        $this->assertCount(2, $saleProducts);
        $this->assertContains('Smartphone', $saleProducts->pluck('name'));
        $this->assertContains('T-shirt', $saleProducts->pluck('name'));

        $newElectronicsProducts = Product::withAllTaxonomies([$category, $tag2])->get();
        $this->assertCount(1, $newElectronicsProducts);
        $this->assertEquals('Laptop', $newElectronicsProducts->first()->name);
    }

    #[Test]
    public function it_caches_taxonomy_trees()
    {
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

        $this->assertCount(1, $tree1[0]->children);
        $this->assertCount(1, $tree2[0]->children);

        // Clear cache and get fresh result
        Cache::flush();
        $tree3 = Taxonomy::tree(TaxonomyType::Category);

        $this->assertCount(2, $tree3[0]->children);
    }

    #[Test]
    public function it_can_paginate_find_many_taxonomies()
    {
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
        $this->assertInstanceOf(LengthAwarePaginator::class, $page1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page2);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page3);

        // Assert that each page has the correct number of items
        $this->assertCount(5, $page1->items());
        $this->assertCount(5, $page2->items());
        $this->assertCount(5, $page3->items());

        // Assert total count is correct
        $this->assertEquals(15, $page1->total());

        // Assert that different pages have different items
        $this->assertNotEquals($page1->items()[0]->id, $page2->items()[0]->id);
        $this->assertNotEquals($page2->items()[0]->id, $page3->items()[0]->id);

        // Test without pagination (should return all items)
        $allTaxonomies = Taxonomy::findMany($ids);
        $this->assertInstanceOf(Collection::class, $allTaxonomies);
        $this->assertCount(15, $allTaxonomies);
    }

    #[Test]
    public function it_can_paginate_find_by_type()
    {
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
        $this->assertInstanceOf(LengthAwarePaginator::class, $page1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page2);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page3);

        // Assert that each page has the correct number of items
        $this->assertCount(4, $page1->items());
        $this->assertCount(4, $page2->items());
        $this->assertCount(2, $page3->items()); // Last page has only 2 items

        // Assert total count is correct
        $this->assertEquals(10, $page1->total());

        // Test without pagination (should return all items)
        $allCategories = Taxonomy::findByType(TaxonomyType::Category);
        $allTags = Taxonomy::findByType(TaxonomyType::Tag);

        $this->assertInstanceOf(Collection::class, $allCategories);
        $this->assertInstanceOf(Collection::class, $allTags);
        $this->assertCount(10, $allCategories);
        $this->assertCount(5, $allTags);
    }

    #[Test]
    public function it_can_paginate_find_by_parent()
    {
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
        $this->assertInstanceOf(LengthAwarePaginator::class, $page1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page2);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page3);

        // Assert that each page has the correct number of items
        $this->assertCount(5, $page1->items());
        $this->assertCount(5, $page2->items());
        $this->assertCount(2, $page3->items()); // Last page has only 2 items

        // Assert total count is correct
        $this->assertEquals(12, $page1->total());

        // Test without pagination (should return all items)
        $allChildren = Taxonomy::findByParent($parent->id);
        $this->assertInstanceOf(Collection::class, $allChildren);
        $this->assertCount(12, $allChildren);

        // Test root level taxonomies
        $rootTaxonomies = Taxonomy::findByParent(null);
        $this->assertCount(1, $rootTaxonomies); // Only the parent taxonomy
    }

    #[Test]
    public function it_can_paginate_search_results()
    {
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
        $this->assertInstanceOf(LengthAwarePaginator::class, $page1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page2);
        $this->assertInstanceOf(LengthAwarePaginator::class, $page3);

        // Assert that each page has the correct number of items
        $this->assertCount(5, $page1->items());
        $this->assertCount(5, $page2->items());
        $this->assertCount(5, $page3->items());

        // Assert total count is correct (all 15 items should match)
        $this->assertEquals(15, $page1->total());

        // Test search with type filter
        $categoryResults = Taxonomy::search('searchable', TaxonomyType::Category, 10, 1);
        $this->assertInstanceOf(LengthAwarePaginator::class, $categoryResults);
        $this->assertCount(8, $categoryResults->items());
        $this->assertEquals(8, $categoryResults->total());

        // Test without pagination (should return all matching items)
        $allResults = Taxonomy::search('searchable');
        $this->assertInstanceOf(Collection::class, $allResults);
        $this->assertCount(15, $allResults);
    }
}

// Test model class for feature tests
class Product extends Model
{
    use HasTaxonomy;

    protected $fillable = ['name'];
}
