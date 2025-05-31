<?php

namespace Tests\Unit;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Exceptions\MissingSlugException;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class TaxonomyModelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_taxonomy(): void
    {
        $taxonomy = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
            'description' => 'This is a test category',
        ]);

        $this->assertDatabaseHas('taxonomies', [
            'name' => 'Test Category',
            'slug' => 'test-category',
            'type' => TaxonomyType::Category->value,
            'description' => 'This is a test category',
        ]);

        $this->assertEquals('test-category', $taxonomy->slug);
    }

    #[Test]
    public function it_can_create_a_taxonomy_with_custom_slug(): void
    {
        $taxonomy = Taxonomy::create([
            'name' => 'Test Category',
            'slug' => 'custom-slug',
            'type' => TaxonomyType::Category->value,
        ]);

        $this->assertEquals('custom-slug', $taxonomy->slug);
    }

    #[Test]
    public function it_can_create_a_taxonomy_with_parent(): void
    {
        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $child = Taxonomy::create([
            'name' => 'Child Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals($parent->id, $child->parent_id);
        $this->assertNotNull($child->parent);
        $this->assertEquals('Parent Category', $child->parent->name);
        $this->assertCount(1, $parent->children);
        $firstChild = $parent->children->first();
        $this->assertNotNull($firstChild);
        $this->assertEquals('Child Category', $firstChild->name);
    }

    #[Test]
    public function it_can_create_a_taxonomy_with_metadata(): void
    {
        $taxonomy = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
            'meta' => ['color' => 'red', 'featured' => true],
        ]);

        $this->assertNotNull($taxonomy->meta);
        $this->assertEquals('red', $taxonomy->meta['color']);
        $this->assertTrue($taxonomy->meta['featured']);
    }

    #[Test]
    public function it_can_get_ancestors(): void
    {
        $grandparent = Taxonomy::create([
            'name' => 'Grandparent Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $grandparent->id,
        ]);

        $child = Taxonomy::create([
            'name' => 'Child Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);

        $ancestors = $child->ancestors();

        $this->assertCount(2, $ancestors);
        $firstAncestor = $ancestors->first();
        $this->assertNotNull($firstAncestor);
        $this->assertEquals('Parent Category', $firstAncestor->name);
        $lastAncestor = $ancestors->last();
        $this->assertNotNull($lastAncestor);
        $this->assertEquals('Grandparent Category', $lastAncestor->name);
    }

    #[Test]
    public function it_can_get_descendants(): void
    {
        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $child1 = Taxonomy::create([
            'name' => 'Child Category 1',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);

        $child2 = Taxonomy::create([
            'name' => 'Child Category 2',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);

        $grandchild = Taxonomy::create([
            'name' => 'Grandchild Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $child1->id,
        ]);

        $descendants = $parent->descendants();

        $this->assertCount(3, $descendants);
        $this->assertContains($child1->id, $descendants->pluck('id'));
        $this->assertContains($child2->id, $descendants->pluck('id'));
        $this->assertContains($grandchild->id, $descendants->pluck('id'));
    }

    #[Test]
    public function it_can_get_path_attribute(): void
    {
        $grandparent = Taxonomy::create([
            'name' => 'Grandparent Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $grandparent->id,
        ]);

        $child = Taxonomy::create([
            'name' => 'Child Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals('Grandparent Category > Parent Category > Child Category', $child->path);
    }

    #[Test]
    public function it_can_get_full_slug_attribute(): void
    {
        $grandparent = Taxonomy::create([
            'name' => 'Grandparent Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $grandparent->id,
        ]);

        $child = Taxonomy::create([
            'name' => 'Child Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);

        $this->assertEquals('grandparent-category/parent-category/child-category', $child->full_slug);
    }

    #[Test]
    public function it_can_find_by_slug(): void
    {
        $taxonomy = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $found = Taxonomy::findBySlug('test-category');

        $this->assertNotNull($found);
        $this->assertEquals($taxonomy->id, $found->id);
    }

    #[Test]
    public function it_can_find_by_slug_and_type(): void
    {
        $category = Taxonomy::create([
            'name' => 'Test Term',
            'slug' => 'test-term-category',
            'type' => TaxonomyType::Category->value,
        ]);

        $tag = Taxonomy::create([
            'name' => 'Test Term',
            'slug' => 'test-term-tag',
            'type' => TaxonomyType::Tag->value,
        ]);

        $foundCategory = Taxonomy::findBySlug('test-term-category', TaxonomyType::Category);
        $foundTag = Taxonomy::findBySlug('test-term-tag', TaxonomyType::Tag);

        $this->assertNotNull($foundCategory);
        $this->assertNotNull($foundTag);
        $this->assertEquals($category->id, $foundCategory->id);
        $this->assertEquals($tag->id, $foundTag->id);
    }

    #[Test]
    public function it_can_create_or_update_taxonomy(): void
    {
        // Create new taxonomy
        $taxonomy = Taxonomy::createOrUpdate([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
            'description' => 'Initial description',
        ]);

        $this->assertDatabaseHas('taxonomies', [
            'name' => 'Test Category',
            'slug' => 'test-category',
            'description' => 'Initial description',
        ]);

        // Update existing taxonomy
        $updated = Taxonomy::createOrUpdate([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
            'description' => 'Updated description',
        ]);

        $this->assertEquals($taxonomy->id, $updated->id);
        $this->assertEquals('Updated description', $updated->description);
        $this->assertDatabaseHas('taxonomies', [
            'name' => 'Test Category',
            'slug' => 'test-category',
            'description' => 'Updated description',
        ]);
    }

    #[Test]
    public function it_can_get_flat_tree(): void
    {
        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $child1 = Taxonomy::create([
            'name' => 'Child Category 1',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
            'sort_order' => 2,
        ]);

        $child2 = Taxonomy::create([
            'name' => 'Child Category 2',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
            'sort_order' => 1,
        ]);

        $grandchild = Taxonomy::create([
            'name' => 'Grandchild Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $child1->id,
        ]);

        $flatTree = Taxonomy::flatTree(TaxonomyType::Category);

        $this->assertCount(4, $flatTree);
        $this->assertNotNull($flatTree[0]);
        $this->assertNotNull($flatTree[1]);
        $this->assertNotNull($flatTree[2]);
        $this->assertNotNull($flatTree[3]);
        $this->assertEquals(0, $flatTree[0]->depth);
        $this->assertEquals(1, $flatTree[1]->depth);
        $this->assertEquals(1, $flatTree[2]->depth);
        $this->assertEquals(2, $flatTree[3]->depth);

        // Check ordering
        $this->assertEquals('Parent Category', $flatTree[0]->name);
        $this->assertEquals('Child Category 2', $flatTree[1]->name); // Lower sort_order comes first
        $this->assertEquals('Child Category 1', $flatTree[2]->name);
        $this->assertEquals('Grandchild Category', $flatTree[3]->name);
    }

    #[Test]
    public function it_can_get_tree(): void
    {
        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $child1 = Taxonomy::create([
            'name' => 'Child Category 1',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);

        $child2 = Taxonomy::create([
            'name' => 'Child Category 2',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);

        $grandchild = Taxonomy::create([
            'name' => 'Grandchild Category',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $child1->id,
        ]);

        $tree = Taxonomy::tree(TaxonomyType::Category);

        $this->assertCount(1, $tree); // Only root level items
        $this->assertNotNull($tree[0]);
        $this->assertEquals('Parent Category', $tree[0]->name);
        $this->assertCount(2, $tree[0]->children);
        $this->assertNotNull($tree[0]->children[0]);
        $this->assertCount(1, $tree[0]->children[0]->children);
    }

    #[Test]
    public function it_generates_unique_slugs_for_same_name_and_type(): void
    {
        // Create first taxonomy
        $taxonomy1 = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);

        // Create second taxonomy with same name and type
        $taxonomy2 = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);

        // Verify slugs are different
        $this->assertEquals('test-category', $taxonomy1->slug);
        $this->assertEquals('test-category-1', $taxonomy2->slug);

        // Create third taxonomy with same name and type
        $taxonomy3 = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);

        // Verify slug has incremented counter
        $this->assertEquals('test-category-2', $taxonomy3->slug);
    }

    #[Test]
    public function it_ensures_unique_slugs_across_different_types(): void
    {
        // Create first taxonomy with Category type
        $taxonomy1 = Taxonomy::create([
            'name' => 'Test Term',
            'type' => TaxonomyType::Category->value,
        ]);

        // Create second taxonomy with same name but Tag type
        $taxonomy2 = Taxonomy::create([
            'name' => 'Test Term',
            'type' => TaxonomyType::Tag->value,
        ]);

        // Verify slugs are unique even across different types
        $this->assertEquals('test-term', $taxonomy1->slug);
        $this->assertEquals('test-term-1', $taxonomy2->slug);
    }

    #[Test]
    public function it_generates_unique_slug_when_updating(): void
    {
        // Create two taxonomies
        $taxonomy1 = Taxonomy::create([
            'name' => 'First Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $taxonomy2 = Taxonomy::create([
            'name' => 'Second Category',
            'type' => TaxonomyType::Category->value,
        ]);

        // Enable slug regeneration on update
        config(['taxonomy.slugs.regenerate_on_update' => true]);

        // Update second taxonomy to have same name as first
        $taxonomy2->update([
            'name' => 'First Category',
        ]);

        // Refresh from database
        $taxonomy2->refresh();

        // Verify slug is unique
        $this->assertEquals('first-category', $taxonomy1->slug);
        $this->assertEquals('first-category-1', $taxonomy2->slug);
    }

    #[Test]
    public function it_throws_exception_when_slug_generation_is_disabled_and_no_slug_provided(): void
    {
        // Disable slug generation
        config(['taxonomy.slugs.generate' => false]);

        // Expect MissingSlugException when creating without a slug
        $this->expectException(MissingSlugException::class);

        Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_custom_slug_already_exists(): void
    {
        // Create a taxonomy with a specific slug
        Taxonomy::create([
            'name' => 'First Category',
            'slug' => 'existing-slug',
            'type' => TaxonomyType::Category->value,
        ]);

        // Expect DuplicateSlugException when creating with the same slug
        $this->expectException(DuplicateSlugException::class);

        Taxonomy::create([
            'name' => 'Second Category',
            'slug' => 'existing-slug',
            'type' => TaxonomyType::Tag->value, // Different type, but same slug
        ]);
    }

    #[Test]
    public function it_throws_exception_when_updating_with_duplicate_slug(): void
    {
        // Create two taxonomies
        $taxonomy1 = Taxonomy::create([
            'name' => 'First Category',
            'slug' => 'first-slug',
            'type' => TaxonomyType::Category->value,
        ]);

        $taxonomy2 = Taxonomy::create([
            'name' => 'Second Category',
            'slug' => 'second-slug',
            'type' => TaxonomyType::Category->value,
        ]);

        // Expect DuplicateSlugException when updating with an existing slug
        $this->expectException(DuplicateSlugException::class);

        $taxonomy2->update([
            'slug' => 'first-slug',
        ]);
    }

    #[Test]
    public function it_throws_exception_when_create_or_update_with_missing_slug(): void
    {
        // Disable slug generation
        config(['taxonomy.slugs.generate' => false]);

        // Expect MissingSlugException when using createOrUpdate without a slug
        $this->expectException(MissingSlugException::class);

        Taxonomy::createOrUpdate([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);
    }

    #[Test]
    public function it_throws_exception_when_create_or_update_with_duplicate_slug(): void
    {
        // Create a taxonomy with a specific slug
        Taxonomy::create([
            'name' => 'First Category',
            'slug' => 'existing-slug',
            'type' => TaxonomyType::Category->value,
        ]);

        // Expect DuplicateSlugException when using createOrUpdate with the same slug
        $this->expectException(DuplicateSlugException::class);

        Taxonomy::createOrUpdate([
            'name' => 'Second Category',
            'slug' => 'existing-slug',
            'type' => TaxonomyType::Tag->value, // Different type, but same slug
        ]);
    }
}
