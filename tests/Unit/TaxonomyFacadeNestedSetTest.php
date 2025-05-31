<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Unit;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class TaxonomyFacadeNestedSetTest extends TestCase
{
    use RefreshDatabase;

    protected TaxonomyModel $electronics;
    protected TaxonomyModel $smartphones;
    protected TaxonomyModel $android;
    protected TaxonomyModel $samsung;
    protected TaxonomyModel $tagParent;
    protected TaxonomyModel $tagChild;

    protected function setUp(): void
    {
        parent::setUp();

        // Create hierarchical taxonomy structure for categories
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

        // Create tag hierarchy
        $this->tagParent = Taxonomy::create([
            'name' => 'Parent Tag',
            'slug' => 'parent-tag',
            'type' => TaxonomyType::Tag,
        ]);

        $this->tagChild = Taxonomy::create([
            'name' => 'Child Tag',
            'slug' => 'child-tag',
            'type' => TaxonomyType::Tag,
            'parent_id' => $this->tagParent->id,
        ]);
    }

    public function test_facade_get_nested_tree_returns_correct_structure(): void
    {
        $nestedTree = Taxonomy::getNestedTree(TaxonomyType::Category);

        $this->assertCount(1, $nestedTree); // Should have 1 root (electronics)

        $root = $nestedTree->first();
        $this->assertNotNull($root);
        $this->assertInstanceOf(Taxonomy::class, $root);
        $this->assertEquals('Electronics', $root->name);
        $this->assertNotNull($root->children);
        $this->assertCount(1, $root->children); // Should have 1 child (smartphones)

        $smartphones = $root->children->first();
        $this->assertNotNull($smartphones);
        $this->assertInstanceOf(Taxonomy::class, $smartphones);
        $this->assertEquals('Smartphones', $smartphones->name);
        $this->assertNotNull($smartphones->children);
        $this->assertCount(1, $smartphones->children); // Should have 1 child (android)

        $android = $smartphones->children->first();
        $this->assertNotNull($android);
        $this->assertInstanceOf(Taxonomy::class, $android);
        $this->assertEquals('Android', $android->name);
        $this->assertCount(1, $android->children); // Should have 1 child (samsung)

        $samsung = $android->children->first();
        $this->assertNotNull($samsung);
        $this->assertInstanceOf(Taxonomy::class, $samsung);
        $this->assertEquals('Samsung', $samsung->name);
        $this->assertNotNull($samsung->children);
        $this->assertCount(0, $samsung->children); // Should have no children
    }

    public function test_facade_get_nested_tree_filters_by_type(): void
    {
        $categoryTree = Taxonomy::getNestedTree(TaxonomyType::Category);
        $tagTree = Taxonomy::getNestedTree(TaxonomyType::Tag);

        // Category tree should have electronics as root
        $this->assertCount(1, $categoryTree);
        $categoryFirst = $categoryTree->first();
        $this->assertNotNull($categoryFirst);
        $this->assertInstanceOf(Taxonomy::class, $categoryFirst);
        $this->assertEquals('Electronics', $categoryFirst->name);

        // Tag tree should have parent tag as root
        $this->assertCount(1, $tagTree);
        $tagFirst = $tagTree->first();
        $this->assertNotNull($tagFirst);
        $this->assertInstanceOf(Taxonomy::class, $tagFirst);
        $this->assertEquals('Parent Tag', $tagFirst->name);
        $this->assertNotNull($tagFirst->children);
        $this->assertCount(1, $tagFirst->children);
        $childTag = $tagFirst->children->first();
        $this->assertNotNull($childTag);
        $this->assertInstanceOf(Taxonomy::class, $childTag);
        $this->assertEquals('Child Tag', $childTag->name);
    }

    public function test_facade_get_descendants_returns_correct_descendants(): void
    {
        $descendants = Taxonomy::getDescendants($this->electronics->id);

        $this->assertCount(3, $descendants);
        $this->assertTrue($descendants->contains('name', 'Smartphones'));
        $this->assertTrue($descendants->contains('name', 'Android'));
        $this->assertTrue($descendants->contains('name', 'Samsung'));
    }

    public function test_facade_get_descendants_handles_invalid_id(): void
    {
        $descendants = Taxonomy::getDescendants(99999);
        $this->assertCount(0, $descendants);
    }

    public function test_facade_get_ancestors_returns_correct_ancestors(): void
    {
        $ancestors = Taxonomy::getAncestors($this->samsung->id);

        $this->assertCount(3, $ancestors);
        $this->assertTrue($ancestors->contains('name', 'Electronics'));
        $this->assertTrue($ancestors->contains('name', 'Smartphones'));
        $this->assertTrue($ancestors->contains('name', 'Android'));
    }

    public function test_facade_get_ancestors_handles_root_taxonomy(): void
    {
        $ancestors = Taxonomy::getAncestors($this->electronics->id);
        $this->assertCount(0, $ancestors);
    }

    public function test_facade_get_ancestors_handles_invalid_id(): void
    {
        $ancestors = Taxonomy::getAncestors(99999);
        $this->assertCount(0, $ancestors);
    }

    public function test_facade_move_to_parent_works_correctly(): void
    {
        // Move samsung from android to smartphones
        $result = Taxonomy::moveToParent($this->samsung->id, $this->smartphones->id);
        $this->assertTrue($result);

        // Refresh models
        $this->samsung->refresh();
        $this->android->refresh();
        $this->smartphones->refresh();

        // Check that samsung is now child of smartphones
        $this->assertEquals($this->smartphones->id, $this->samsung->parent_id);
        $this->assertEquals(2, $this->samsung->depth);

        // Check nested set values are updated
        $this->assertTrue($this->samsung->lft > $this->smartphones->lft);
        $this->assertTrue($this->samsung->rgt < $this->smartphones->rgt);
    }

    public function test_facade_move_to_parent_handles_invalid_taxonomy_id(): void
    {
        $result = Taxonomy::moveToParent(99999, $this->smartphones->id);
        $this->assertFalse($result);
    }

    public function test_facade_move_to_parent_handles_invalid_parent_id(): void
    {
        $result = Taxonomy::moveToParent($this->samsung->id, 99999);
        $this->assertFalse($result);
    }

    public function test_facade_move_to_root_works_correctly(): void
    {
        // Move samsung to root (no parent)
        $result = Taxonomy::moveToParent($this->samsung->id, null);
        $this->assertTrue($result);

        // Refresh model
        $this->samsung->refresh();

        // Check that samsung is now root
        $this->assertNull($this->samsung->parent_id);
        $this->assertEquals(0, $this->samsung->depth);
    }

    public function test_facade_rebuild_nested_set_corrects_corrupted_values(): void
    {
        // Corrupt nested set values
        TaxonomyModel::where('id', $this->electronics->id)->update(['lft' => 999, 'rgt' => 999]);
        TaxonomyModel::where('id', $this->smartphones->id)->update(['lft' => 999, 'rgt' => 999]);

        // Rebuild nested set
        Taxonomy::rebuildNestedSet(TaxonomyType::Category);

        // Refresh models
        $this->electronics->refresh();
        $this->smartphones->refresh();
        $this->android->refresh();
        $this->samsung->refresh();

        // Check that nested set values are corrected
        $this->assertEquals(1, $this->electronics->lft);
        $this->assertEquals(8, $this->electronics->rgt);
        $this->assertTrue($this->smartphones->lft > $this->electronics->lft);
        $this->assertTrue($this->smartphones->rgt < $this->electronics->rgt);
    }

    public function test_facade_rebuild_nested_set_only_affects_specified_type(): void
    {
        // Corrupt nested set values for both types
        TaxonomyModel::whereIn('id', [$this->electronics->id, $this->tagParent->id])
            ->update(['lft' => 999, 'rgt' => 999]);

        // Rebuild only categories
        Taxonomy::rebuildNestedSet(TaxonomyType::Category);

        // Refresh models
        $this->electronics->refresh();
        $this->tagParent->refresh();

        // Electronics should be fixed, tag should still be corrupted
        $this->assertEquals(1, $this->electronics->lft);
        $this->assertEquals(999, $this->tagParent->lft);
    }

    public function test_facade_clear_cache_for_type_clears_correct_cache(): void
    {
        // Generate cache for both types
        Taxonomy::getNestedTree(TaxonomyType::Category);
        Taxonomy::getNestedTree(TaxonomyType::Tag);

        // Verify cache exists
        $this->assertTrue(Cache::has('taxonomy_nested_tree_category'));
        $this->assertTrue(Cache::has('taxonomy_nested_tree_tag'));

        // Clear cache for categories only
        Taxonomy::clearCacheForType(TaxonomyType::Category);

        // Verify only category cache is cleared
        $this->assertFalse(Cache::has('taxonomy_nested_tree_category'));
        $this->assertTrue(Cache::has('taxonomy_nested_tree_tag'));
    }

    public function test_facade_methods_work_with_string_type(): void
    {
        // Test with string type instead of enum
        $nestedTree = Taxonomy::getNestedTree('category');
        $this->assertCount(1, $nestedTree);
        $nestedFirst = $nestedTree->first();
        $this->assertNotNull($nestedFirst);
        $this->assertInstanceOf(Taxonomy::class, $nestedFirst);
        $this->assertEquals('Electronics', $nestedFirst->name);

        Taxonomy::rebuildNestedSet('category');
        $this->electronics->refresh();
        $this->assertEquals(1, $this->electronics->lft);

        Taxonomy::clearCacheForType('category');
        $this->assertFalse(Cache::has('taxonomy.nested_tree.category'));
    }

    public function test_facade_methods_handle_null_type(): void
    {
        // Rebuild nested set for both types to ensure correct lft/rgt values
        Taxonomy::rebuildNestedSet(TaxonomyType::Category);
        Taxonomy::rebuildNestedSet(TaxonomyType::Tag);

        // Test getNestedTree with null type (should return all root taxonomies)
        $result = Taxonomy::getNestedTree();

        $this->assertCount(2, $result); // Should have 2 roots: Electronics and Parent Tag

        $rootNames = $result->pluck('name')->toArray();
        $this->assertContains('Electronics', $rootNames);
        $this->assertContains('Parent Tag', $rootNames);
    }
}
