<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Unit;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;

class HasTaxonomyHierarchicalTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;
    protected Taxonomy $electronics;
    protected Taxonomy $smartphones;
    protected Taxonomy $android;
    protected Taxonomy $samsung;

    protected function setUp(): void
    {
        parent::setUp();

        // Create hierarchical taxonomy structure
        // Electronics (root)
        //   └── Smartphones (child)
        //       └── Android (grandchild)
        //           └── Samsung (great-grandchild)
        
        $this->electronics = Taxonomy::create([
            'name' => 'Electronics',
            'slug' => 'electronics',
            'type' => TaxonomyType::Category,
        ]);

        $this->smartphones = Taxonomy::create([
            'name' => 'Smartphones',
            'slug' => 'smartphones',
            'type' => TaxonomyType::Category,
            'parent_id' => $this->electronics->id,
        ]);

        $this->android = Taxonomy::create([
            'name' => 'Android',
            'slug' => 'android',
            'type' => TaxonomyType::Category,
            'parent_id' => $this->smartphones->id,
        ]);

        $this->samsung = Taxonomy::create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'type' => TaxonomyType::Category,
            'parent_id' => $this->android->id,
        ]);

        $this->product = Product::create([
            'name' => 'Test Product',
            'description' => 'Test Description',
        ]);
        
        // Rebuild nested set to ensure correct lft/rgt values
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
    }

    public function test_get_hierarchical_taxonomies_includes_descendants()
    {
        // Attach smartphones taxonomy to product
        $this->product->attachTaxonomies([$this->smartphones->id]);

        $hierarchical = $this->product->getHierarchicalTaxonomies(TaxonomyType::Category);

        // Should include smartphones and its descendants (android, samsung)
        $this->assertCount(3, $hierarchical);
        $this->assertTrue($hierarchical->contains('id', $this->smartphones->id));
        $this->assertTrue($hierarchical->contains('id', $this->android->id));
        $this->assertTrue($hierarchical->contains('id', $this->samsung->id));
        $this->assertFalse($hierarchical->contains('id', $this->electronics->id));
    }

    public function test_get_hierarchical_taxonomies_with_multiple_taxonomies()
    {
        // Attach both electronics and android taxonomies
        $this->product->attachTaxonomies([$this->electronics->id, $this->android->id]);

        $hierarchical = $this->product->getHierarchicalTaxonomies(TaxonomyType::Category);

        // Should include all taxonomies (electronics has smartphones, android as descendants; android has samsung as descendant)
        $this->assertCount(4, $hierarchical);
        $this->assertTrue($hierarchical->contains('id', $this->electronics->id));
        $this->assertTrue($hierarchical->contains('id', $this->smartphones->id));
        $this->assertTrue($hierarchical->contains('id', $this->android->id));
        $this->assertTrue($hierarchical->contains('id', $this->samsung->id));
    }

    public function test_get_ancestor_taxonomies_returns_correct_ancestors()
    {
        // Attach samsung taxonomy to product
        $this->product->attachTaxonomies([$this->samsung->id]);

        $ancestors = $this->product->getAncestorTaxonomies(TaxonomyType::Category);

        // Should include all ancestors of samsung (android, smartphones, electronics)
        $this->assertCount(3, $ancestors);
        $this->assertTrue($ancestors->contains('id', $this->electronics->id));
        $this->assertTrue($ancestors->contains('id', $this->smartphones->id));
        $this->assertTrue($ancestors->contains('id', $this->android->id));
        $this->assertFalse($ancestors->contains('id', $this->samsung->id));
    }

    public function test_scope_with_taxonomy_hierarchy_includes_descendants()
    {
        $product1 = Product::create(['name' => 'Product 1', 'description' => 'Desc 1']);
        $product2 = Product::create(['name' => 'Product 2', 'description' => 'Desc 2']);
        $product3 = Product::create(['name' => 'Product 3', 'description' => 'Desc 3']);

        // Attach different levels of hierarchy
        $product1->attachTaxonomies([$this->smartphones->id]); // smartphones
        $product2->attachTaxonomies([$this->android->id]);     // android (child of smartphones)
        $product3->attachTaxonomies([$this->electronics->id]); // electronics (parent of smartphones)

        // Query for products with smartphones hierarchy (should include descendants)
        $products = Product::withTaxonomyHierarchy($this->smartphones->id, true)->get();

        $this->assertCount(2, $products);
        $this->assertTrue($products->contains('id', $product1->id)); // has smartphones directly
        $this->assertTrue($products->contains('id', $product2->id)); // has android (descendant of smartphones)
        $this->assertFalse($products->contains('id', $product3->id)); // has electronics (ancestor, not descendant)
    }

    public function test_scope_with_taxonomy_hierarchy_excludes_descendants_when_disabled()
    {
        $product1 = Product::create(['name' => 'Product 1', 'description' => 'Desc 1']);
        $product2 = Product::create(['name' => 'Product 2', 'description' => 'Desc 2']);

        $product1->attachTaxonomies([$this->smartphones->id]);
        $product2->attachTaxonomies([$this->android->id]);

        // Query for products with smartphones hierarchy (exclude descendants)
        $products = Product::withTaxonomyHierarchy($this->smartphones->id, false)->get();

        $this->assertCount(1, $products);
        $this->assertTrue($products->contains('id', $product1->id)); // has smartphones directly
        $this->assertFalse($products->contains('id', $product2->id)); // has android (descendant, but excluded)
    }

    public function test_scope_with_taxonomy_at_depth_filters_correctly()
    {
        $product1 = Product::create(['name' => 'Product 1', 'description' => 'Desc 1']);
        $product2 = Product::create(['name' => 'Product 2', 'description' => 'Desc 2']);
        $product3 = Product::create(['name' => 'Product 3', 'description' => 'Desc 3']);
        $product4 = Product::create(['name' => 'Product 4', 'description' => 'Desc 4']);

        $product1->attachTaxonomies([$this->electronics->id]);  // depth 0
        $product2->attachTaxonomies([$this->smartphones->id]);  // depth 1
        $product3->attachTaxonomies([$this->android->id]);      // depth 2
        $product4->attachTaxonomies([$this->samsung->id]);      // depth 3

        // Test depth 1
        $productsAtDepth1 = Product::withTaxonomyAtDepth(1, TaxonomyType::Category)->get();
        $this->assertCount(1, $productsAtDepth1);
        $this->assertTrue($productsAtDepth1->contains('id', $product2->id));

        // Test depth 2
        $productsAtDepth2 = Product::withTaxonomyAtDepth(2, TaxonomyType::Category)->get();
        $this->assertCount(1, $productsAtDepth2);
        $this->assertTrue($productsAtDepth2->contains('id', $product3->id));
    }

    public function test_has_ancestor_taxonomy_returns_correct_result()
    {
        // Attach electronics taxonomy to product
        $this->product->attachTaxonomies([$this->electronics->id]);

        // Check if product has ancestor of samsung (should be true, electronics is ancestor of samsung)
        $this->assertTrue($this->product->hasAncestorTaxonomy($this->samsung->id));
        
        // Check if product has ancestor of electronics (should be false, electronics has no ancestors)
        $this->assertFalse($this->product->hasAncestorTaxonomy($this->electronics->id));
    }

    public function test_has_descendant_taxonomy_returns_correct_result()
    {
        // Attach smartphones taxonomy to product (smartphones is descendant of electronics)
        $this->product->attachTaxonomies([$this->smartphones->id]);

        // Check if product has descendant of electronics (should be true, smartphones is descendant of electronics)
        $this->assertTrue($this->product->hasDescendantTaxonomy($this->electronics->id));
        
        // Check if product has descendant of smartphones (should be false, no descendants attached)
        $this->assertFalse($this->product->hasDescendantTaxonomy($this->smartphones->id));
        
        // Check if product has descendant of samsung (should be false, samsung has no descendants)
        $this->assertFalse($this->product->hasDescendantTaxonomy($this->samsung->id));
    }

    public function test_hierarchical_methods_handle_invalid_taxonomy_id()
    {
        $this->product->attachTaxonomies([$this->electronics->id]);

        // Test with non-existent taxonomy ID
        $products = Product::withTaxonomyHierarchy(99999)->get();
        $this->assertCount(0, $products);

        $this->assertFalse($this->product->hasAncestorTaxonomy(99999));
        $this->assertFalse($this->product->hasDescendantTaxonomy(99999));
    }

    public function test_hierarchical_methods_work_with_different_types()
    {
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

        $this->product->attachTaxonomies([$this->electronics->id, $tagParent->id]);

        // Test hierarchical taxonomies for categories only
        $categoryHierarchical = $this->product->getHierarchicalTaxonomies(TaxonomyType::Category);
        $this->assertCount(4, $categoryHierarchical); // electronics + smartphones + android + samsung
        $this->assertFalse($categoryHierarchical->contains('id', $tagParent->id));
        $this->assertFalse($categoryHierarchical->contains('id', $tagChild->id));

        // Test hierarchical taxonomies for tags only
        $tagHierarchical = $this->product->getHierarchicalTaxonomies(TaxonomyType::Tag);
        $this->assertCount(2, $tagHierarchical); // tagParent + tagChild
        $this->assertTrue($tagHierarchical->contains('id', $tagParent->id));
        $this->assertTrue($tagHierarchical->contains('id', $tagChild->id));
    }
}