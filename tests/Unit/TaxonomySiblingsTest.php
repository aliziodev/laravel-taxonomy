<?php

namespace Tests\Unit;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class TaxonomySiblingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_get_siblings_for_root_taxonomy(): void
    {
        // Create root taxonomies of the same type
        $taxonomy1 = Taxonomy::create([
            'name' => 'Root Category 1',
            'type' => TaxonomyType::Category->value,
        ]);

        $taxonomy2 = Taxonomy::create([
            'name' => 'Root Category 2',
            'type' => TaxonomyType::Category->value,
        ]);

        $taxonomy3 = Taxonomy::create([
            'name' => 'Root Category 3',
            'type' => TaxonomyType::Category->value,
        ]);

        // Create a root taxonomy of different type
        $tagTaxonomy = Taxonomy::create([
            'name' => 'Root Tag',
            'type' => TaxonomyType::Tag->value,
        ]);

        $siblings = $taxonomy1->getSiblings();

        $this->assertCount(2, $siblings);
        $this->assertContains($taxonomy2->id, $siblings->pluck('id'));
        $this->assertContains($taxonomy3->id, $siblings->pluck('id'));
        $this->assertNotContains($taxonomy1->id, $siblings->pluck('id'));
        $this->assertNotContains($tagTaxonomy->id, $siblings->pluck('id'));
    }

    #[Test]
    public function it_can_get_siblings_for_child_taxonomy(): void
    {
        // Create parent taxonomy
        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
        ]);

        // Create child taxonomies
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

        $child3 = Taxonomy::create([
            'name' => 'Child Category 3',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
        ]);

        // Create child of different parent
        $otherParent = Taxonomy::create([
            'name' => 'Other Parent',
            'type' => TaxonomyType::Category->value,
        ]);

        $otherChild = Taxonomy::create([
            'name' => 'Other Child',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $otherParent->id,
        ]);

        $siblings = $child1->getSiblings();

        $this->assertCount(2, $siblings);
        $this->assertContains($child2->id, $siblings->pluck('id'));
        $this->assertContains($child3->id, $siblings->pluck('id'));
        $this->assertNotContains($child1->id, $siblings->pluck('id'));
        $this->assertNotContains($otherChild->id, $siblings->pluck('id'));
        $this->assertNotContains($parent->id, $siblings->pluck('id'));
    }

    #[Test]
    public function it_returns_empty_collection_when_no_siblings_exist(): void
    {
        // Create a single root taxonomy
        $taxonomy = Taxonomy::create([
            'name' => 'Only Child',
            'type' => TaxonomyType::Category->value,
        ]);

        $siblings = $taxonomy->getSiblings();

        $this->assertCount(0, $siblings);
    }

    #[Test]
    public function it_returns_siblings_in_correct_order(): void
    {
        // Create parent taxonomy
        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
        ]);

        // Create child taxonomies with specific order
        $child1 = Taxonomy::create([
            'name' => 'Child Category 1',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
            'sort_order' => 1,
        ]);

        $child2 = Taxonomy::create([
            'name' => 'Child Category 2',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
            'sort_order' => 2,
        ]);

        $child3 = Taxonomy::create([
            'name' => 'Child Category 3',
            'type' => TaxonomyType::Category->value,
            'parent_id' => $parent->id,
            'sort_order' => 3,
        ]);

        $siblings = $child2->getSiblings();

        $this->assertCount(2, $siblings);
        $this->assertEquals($child1->id, $siblings->first()?->id);
        $this->assertEquals($child3->id, $siblings->last()?->id);
    }
}
