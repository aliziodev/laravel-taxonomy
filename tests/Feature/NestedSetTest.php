<?php

namespace Tests\Feature;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class NestedSetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test taxonomies with nested structure
        $this->createTestTaxonomies();
    }

    protected function createTestTaxonomies(): void
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

    #[Test]
    public function test_nested_set_values_are_set_correctly_on_creation(): void
    {
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $computers = Taxonomy::where('slug', 'computers')->first();
        $laptops = Taxonomy::where('slug', 'laptops')->first();

        // Root should have depth 0
        $this->assertNotNull($electronics);
        $this->assertEquals(0, $electronics->depth);
        $this->assertNotNull($electronics->lft);
        $this->assertNotNull($electronics->rgt);

        // Child should have depth 1
        $this->assertNotNull($computers);
        $this->assertEquals(1, $computers->depth);
        $this->assertTrue($computers->lft > $electronics->lft);
        $this->assertTrue($computers->rgt < $electronics->rgt);

        // Grandchild should have depth 2
        $this->assertNotNull($laptops);
        $this->assertEquals(2, $laptops->depth);
        $this->assertTrue($laptops->lft > $computers->lft);
        $this->assertTrue($laptops->rgt < $computers->rgt);
    }

    #[Test]
    public function test_get_descendants_returns_correct_taxonomies(): void
    {
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics);
        $descendants = $electronics->getDescendants();

        // Electronics should have 4 descendants: computers, phones, laptops, desktops
        $this->assertCount(4, $descendants);

        $descendantSlugs = $descendants->pluck('slug')->toArray();
        $this->assertContains('computers', $descendantSlugs);
        $this->assertContains('phones', $descendantSlugs);
        $this->assertContains('laptops', $descendantSlugs);
        $this->assertContains('desktops', $descendantSlugs);
    }

    #[Test]
    public function test_get_ancestors_returns_correct_taxonomies(): void
    {
        $laptops = Taxonomy::where('slug', 'laptops')->first();
        $this->assertNotNull($laptops);
        $ancestors = $laptops->getAncestors();

        // Laptops should have 2 ancestors: computers and electronics
        $this->assertCount(2, $ancestors);

        $ancestorSlugs = $ancestors->pluck('slug')->toArray();
        $this->assertContains('computers', $ancestorSlugs);
        $this->assertContains('electronics', $ancestorSlugs);
    }

    #[Test]
    public function test_is_ancestor_of_works_correctly(): void
    {
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $computers = Taxonomy::where('slug', 'computers')->first();
        $laptops = Taxonomy::where('slug', 'laptops')->first();
        $clothing = Taxonomy::where('slug', 'clothing')->first();

        $this->assertNotNull($electronics);
        $this->assertNotNull($computers);
        $this->assertNotNull($laptops);
        $this->assertNotNull($clothing);

        $this->assertTrue($electronics->isAncestorOf($computers));
        $this->assertTrue($electronics->isAncestorOf($laptops));
        $this->assertTrue($computers->isAncestorOf($laptops));

        $this->assertFalse($computers->isAncestorOf($electronics));
        $this->assertFalse($laptops->isAncestorOf($computers));
        $this->assertFalse($electronics->isAncestorOf($clothing));
    }

    #[Test]
    public function test_is_descendant_of_works_correctly(): void
    {
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $computers = Taxonomy::where('slug', 'computers')->first();
        $laptops = Taxonomy::where('slug', 'laptops')->first();

        $this->assertNotNull($electronics);
        $this->assertNotNull($computers);
        $this->assertNotNull($laptops);

        $this->assertTrue($computers->isDescendantOf($electronics));
        $this->assertTrue($laptops->isDescendantOf($electronics));
        $this->assertTrue($laptops->isDescendantOf($computers));

        $this->assertFalse($electronics->isDescendantOf($computers));
        $this->assertFalse($computers->isDescendantOf($laptops));
    }

    #[Test]
    public function test_move_to_parent_updates_nested_set_correctly(): void
    {
        $phones = Taxonomy::where('slug', 'phones')->first();
        $clothing = Taxonomy::where('slug', 'clothing')->first();

        $this->assertNotNull($phones);
        $this->assertNotNull($clothing);

        // Move phones from electronics to clothing
        $phones->moveToParent($clothing->id);

        $phones->refresh();
        $clothing->refresh();

        // Check that phones is now under clothing
        $this->assertEquals($clothing->id, $phones->parent_id);
        $this->assertEquals(1, $phones->depth); // Should be depth 1 under clothing

        // Check that phones is now a descendant of clothing
        $this->assertTrue($clothing->isAncestorOf($phones));

        // Check that phones is no longer a descendant of electronics
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics);
        $this->assertFalse($electronics->isAncestorOf($phones));
    }

    #[Test]
    public function test_rebuild_nested_set_maintains_correct_structure(): void
    {
        // Get initial structure
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics);
        $initialDescendants = $electronics->getDescendants()->count();

        // Rebuild nested set
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        // Refresh and check structure is maintained
        $electronics->refresh();
        $newDescendants = $electronics->getDescendants()->count();

        $this->assertEquals($initialDescendants, $newDescendants);

        // Check that all taxonomies still have correct nested set values
        $allTaxonomies = Taxonomy::where('type', TaxonomyType::Category->value)->get();
        foreach ($allTaxonomies as $taxonomy) {
            $this->assertNotNull($taxonomy->lft);
            $this->assertNotNull($taxonomy->rgt);
            $this->assertNotNull($taxonomy->depth);
            $this->assertTrue($taxonomy->lft < $taxonomy->rgt);
        }
    }

    #[Test]
    public function test_get_nested_tree_returns_correct_structure(): void
    {
        $tree = Taxonomy::getNestedTree(TaxonomyType::Category);

        // Should have 2 root nodes: Electronics and Clothing
        $this->assertCount(2, $tree);

        $rootSlugs = $tree->pluck('slug')->toArray();
        $this->assertContains('electronics', $rootSlugs);
        $this->assertContains('clothing', $rootSlugs);

        // Find electronics in tree and check its children
        $electronics = $tree->firstWhere('slug', 'electronics');
        $this->assertNotNull($electronics);
        $this->assertNotNull($electronics->children_nested);
        $this->assertCount(2, $electronics->children_nested); // computers and phones

        // Check computers has children
        $computers = $electronics->children_nested->firstWhere('slug', 'computers');
        $this->assertNotNull($computers);
        $this->assertNotNull($computers->children_nested);
        $this->assertCount(2, $computers->children_nested); // laptops and desktops
    }

    #[Test]
    public function test_scopes_work_correctly(): void
    {
        // Test roots scope
        $roots = Taxonomy::roots()->where('type', TaxonomyType::Category->value)->get();
        $this->assertCount(2, $roots);

        // Test atDepth scope
        $depthOne = Taxonomy::atDepth(1)->where('type', TaxonomyType::Category->value)->get();
        $this->assertCount(4, $depthOne); // computers, phones, men-clothing, women-clothing

        $depthTwo = Taxonomy::atDepth(2)->where('type', TaxonomyType::Category->value)->get();
        $this->assertCount(2, $depthTwo); // laptops, desktops

        // Test nestedSetOrder scope
        $ordered = Taxonomy::nestedSetOrder()->where('type', TaxonomyType::Category->value)->get();
        $this->assertCount(8, $ordered);

        // Check that they are ordered by lft
        $previousLft = 0;
        foreach ($ordered as $taxonomy) {
            $this->assertTrue($taxonomy->lft > $previousLft);
            $previousLft = $taxonomy->lft;
        }
    }

    #[Test]
    public function test_deleting_taxonomy_maintains_nested_set_integrity(): void
    {
        $computers = Taxonomy::where('slug', 'computers')->first();
        $laptops = Taxonomy::where('slug', 'laptops')->first();
        $desktops = Taxonomy::where('slug', 'desktops')->first();
        $electronics = Taxonomy::where('slug', 'electronics')->first();

        $this->assertNotNull($computers);
        $this->assertNotNull($laptops);
        $this->assertNotNull($desktops);
        $this->assertNotNull($electronics);

        // Delete computers (which has children)
        $computers->delete();

        // Check that children are moved to parent
        $laptops->refresh();
        $desktops->refresh();

        $this->assertEquals($electronics->id, $laptops->parent_id);
        $this->assertEquals($electronics->id, $desktops->parent_id);
        $this->assertEquals(1, $laptops->depth);
        $this->assertEquals(1, $desktops->depth);
    }

    #[Test]
    public function test_get_level_returns_correct_depth(): void
    {
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $computers = Taxonomy::where('slug', 'computers')->first();
        $laptops = Taxonomy::where('slug', 'laptops')->first();

        $this->assertNotNull($electronics);
        $this->assertNotNull($computers);
        $this->assertNotNull($laptops);

        $this->assertEquals(0, $electronics->getLevel());
        $this->assertEquals(1, $computers->getLevel());
        $this->assertEquals(2, $laptops->getLevel());
    }

    #[Test]
    public function test_nested_set_works_with_different_types(): void
    {
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

        $this->assertEquals(0, $techTag->depth);
        $this->assertEquals(1, $webTag->depth);
        $this->assertTrue($techTag->isAncestorOf($webTag));

        // Ensure tags don't interfere with categories
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics);
        $electronics->refresh();

        $this->assertFalse($techTag->isAncestorOf($electronics));
        $this->assertFalse($electronics->isAncestorOf($techTag));
    }
}
