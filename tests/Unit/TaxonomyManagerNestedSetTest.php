<?php

namespace Tests\Unit;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\TaxonomyManager;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;

class TaxonomyManagerNestedSetTest extends TestCase
{
    use RefreshDatabase;

    protected TaxonomyManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = new TaxonomyManager;

        // Create test taxonomies
        $this->createTestTaxonomies();
    }

    protected function createTestTaxonomies(): void
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

    #[Test]
    public function test_get_nested_tree_returns_correct_structure(): void
    {
        $tree = $this->manager->getNestedTree(TaxonomyType::Category);

        $this->assertCount(1, $tree); // Only one root: Electronics

        $electronics = $tree->first();
        $this->assertNotNull($electronics);
        $this->assertInstanceOf(Taxonomy::class, $electronics);
        $this->assertEquals('electronics', $electronics->slug);
        $this->assertNotNull($electronics->children_nested);
        $this->assertCount(1, $electronics->children_nested);

        $computers = $electronics->children_nested->first();
        $this->assertNotNull($computers);
        $this->assertInstanceOf(Taxonomy::class, $computers);
        $this->assertEquals('computers', $computers->slug);
        $this->assertNotNull($computers->children_nested);
        $this->assertCount(1, $computers->children_nested);

        $laptops = $computers->children_nested->first();
        $this->assertNotNull($laptops);
        $this->assertInstanceOf(Taxonomy::class, $laptops);
        $this->assertEquals('laptops', $laptops->slug);
    }

    #[Test]
    public function test_get_nested_tree_caches_results(): void
    {
        // Clear cache first
        Cache::flush();

        // First call should hit database
        $tree1 = $this->manager->getNestedTree(TaxonomyType::Category);

        // Second call should hit cache
        $tree2 = $this->manager->getNestedTree(TaxonomyType::Category);

        $this->assertEquals($tree1->toArray(), $tree2->toArray());

        // Verify cache key exists
        $cacheKey = 'taxonomy_nested_tree_' . TaxonomyType::Category->value;
        $this->assertTrue(Cache::has($cacheKey));
    }

    #[Test]
    public function test_rebuild_nested_set_rebuilds_correctly(): void
    {
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics);
        $initialDescendants = $electronics->getDescendants()->count();

        // Manually corrupt nested set values
        Taxonomy::where('slug', 'computers')->update(['lft' => null, 'rgt' => null]);

        // Rebuild
        $this->manager->rebuildNestedSet(TaxonomyType::Category);

        // Check structure is restored
        $electronics->refresh();
        $newDescendants = $electronics->getDescendants()->count();

        $this->assertEquals($initialDescendants, $newDescendants);

        // Check that all taxonomies have valid nested set values
        $allTaxonomies = Taxonomy::where('type', TaxonomyType::Category->value)->get();
        foreach ($allTaxonomies as $taxonomy) {
            $this->assertNotNull($taxonomy->lft);
            $this->assertNotNull($taxonomy->rgt);
            $this->assertTrue($taxonomy->lft < $taxonomy->rgt);
        }
    }

    #[Test]
    public function test_rebuild_nested_set_clears_cache(): void
    {
        // Populate cache
        $this->manager->getNestedTree(TaxonomyType::Category);

        $cacheKey = 'taxonomy_nested_tree_' . TaxonomyType::Category->value;
        $this->assertTrue(Cache::has($cacheKey));

        // Rebuild should clear cache
        $this->manager->rebuildNestedSet(TaxonomyType::Category);

        $this->assertFalse(Cache::has($cacheKey));
    }

    #[Test]
    public function test_move_to_parent_works_correctly(): void
    {
        $computers = Taxonomy::where('slug', 'computers')->first();
        $this->assertNotNull($computers);
        $laptops = Taxonomy::where('slug', 'laptops')->first();
        $this->assertNotNull($laptops);

        // Move laptops to be a direct child of electronics (skip computers)
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics);
        $result = $this->manager->moveToParent($laptops->id, $electronics->id);

        $this->assertTrue($result);

        $laptops->refresh();
        $this->assertEquals($electronics->id, $laptops->parent_id);
        $this->assertEquals(1, $laptops->depth);
    }

    #[Test]
    public function test_move_to_parent_returns_false_for_invalid_taxonomy(): void
    {
        $result = $this->manager->moveToParent(999, 1);
        $this->assertFalse($result);
    }

    #[Test]
    public function test_move_to_parent_clears_cache(): void
    {
        // Populate cache
        $this->manager->getNestedTree(TaxonomyType::Category);

        $cacheKey = 'taxonomy_nested_tree_' . TaxonomyType::Category->value;
        $this->assertTrue(Cache::has($cacheKey));

        $laptops = Taxonomy::where('slug', 'laptops')->first();
        $this->assertNotNull($laptops);
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics);

        // Move should clear cache
        $this->manager->moveToParent($laptops->id, $electronics->id);

        $this->assertFalse(Cache::has($cacheKey));
    }

    #[Test]
    public function test_get_descendants_returns_correct_taxonomies(): void
    {
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics);
        $descendants = $this->manager->getDescendants($electronics->id);

        $this->assertCount(2, $descendants); // computers and laptops

        $descendantSlugs = $descendants->pluck('slug')->toArray();
        $this->assertContains('computers', $descendantSlugs);
        $this->assertContains('laptops', $descendantSlugs);
    }

    #[Test]
    public function test_get_descendants_returns_empty_for_invalid_taxonomy(): void
    {
        $descendants = $this->manager->getDescendants(999);
        $this->assertCount(0, $descendants);
    }

    #[Test]
    public function test_get_ancestors_returns_correct_taxonomies(): void
    {
        $laptops = Taxonomy::where('slug', 'laptops')->first();
        $this->assertNotNull($laptops);
        $ancestors = $this->manager->getAncestors($laptops->id);

        $this->assertCount(2, $ancestors); // computers and electronics

        $ancestorSlugs = $ancestors->pluck('slug')->toArray();
        $this->assertContains('computers', $ancestorSlugs);
        $this->assertContains('electronics', $ancestorSlugs);
    }

    #[Test]
    public function test_get_ancestors_returns_empty_for_invalid_taxonomy(): void
    {
        $ancestors = $this->manager->getAncestors(999);
        $this->assertCount(0, $ancestors);
    }

    #[Test]
    public function test_get_ancestors_returns_empty_for_root_taxonomy(): void
    {
        $electronics = Taxonomy::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics);
        $ancestors = $this->manager->getAncestors($electronics->id);

        $this->assertCount(0, $ancestors);
    }

    #[Test]
    public function test_nested_tree_works_with_different_types(): void
    {
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
        $categoryTree = $this->manager->getNestedTree(TaxonomyType::Category);
        $tagTree = $this->manager->getNestedTree(TaxonomyType::Tag);

        // Category tree should have electronics
        $this->assertCount(1, $categoryTree);
        $categoryFirst = $categoryTree->first();
        $this->assertNotNull($categoryFirst);
        $this->assertInstanceOf(Taxonomy::class, $categoryFirst);
        $this->assertEquals('electronics', $categoryFirst->slug);

        // Tag tree should have technology
        $this->assertCount(1, $tagTree);
        $tagFirst = $tagTree->first();
        $this->assertNotNull($tagFirst);
        $this->assertInstanceOf(Taxonomy::class, $tagFirst);
        $this->assertEquals('technology', $tagFirst->slug);
        $this->assertNotNull($tagFirst->children_nested);
        $this->assertCount(1, $tagFirst->children_nested);
        $webDevTag = $tagFirst->children_nested->first();
        $this->assertNotNull($webDevTag);
        $this->assertInstanceOf(Taxonomy::class, $webDevTag);
        $this->assertEquals('web-development', $webDevTag->slug);
    }

    #[Test]
    public function test_clear_cache_for_type_removes_correct_patterns(): void
    {
        // Populate different caches
        $this->manager->getNestedTree(TaxonomyType::Category);
        $this->manager->tree(TaxonomyType::Category);
        $this->manager->flatTree(TaxonomyType::Category);

        // Create some cache keys
        $nestedTreeKey = 'taxonomy_nested_tree_' . TaxonomyType::Category->value;
        $treeKey = 'taxonomy_tree_' . TaxonomyType::Category->value . '_';
        $flatTreeKey = 'taxonomy_flat_tree_' . TaxonomyType::Category->value . '_0_0';

        $this->assertTrue(Cache::has($nestedTreeKey));

        // Rebuild should clear caches for this type
        $this->manager->rebuildNestedSet(TaxonomyType::Category);

        $this->assertFalse(Cache::has($nestedTreeKey));
    }
}
