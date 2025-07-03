<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('can get siblings for root taxonomy', function () {
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

    expect($siblings)->toHaveCount(2);
    expect($siblings->pluck('id'))->toContain($taxonomy2->id);
    expect($siblings->pluck('id'))->toContain($taxonomy3->id);
    expect($siblings->pluck('id'))->not->toContain($taxonomy1->id);
    expect($siblings->pluck('id'))->not->toContain($tagTaxonomy->id);
});

it('can get siblings for child taxonomy', function () {
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

    expect($siblings)->toHaveCount(2);
    expect($siblings->pluck('id'))->toContain($child2->id);
    expect($siblings->pluck('id'))->toContain($child3->id);
    expect($siblings->pluck('id'))->not->toContain($child1->id);
    expect($siblings->pluck('id'))->not->toContain($otherChild->id);
    expect($siblings->pluck('id'))->not->toContain($parent->id);
});

it('returns empty collection when no siblings exist', function () {
    // Create a single root taxonomy
    $taxonomy = Taxonomy::create([
        'name' => 'Only Child',
        'type' => TaxonomyType::Category->value,
    ]);

    $siblings = $taxonomy->getSiblings();

    expect($siblings)->toHaveCount(0);
});

it('returns siblings in correct order', function () {
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

    expect($siblings)->toHaveCount(2);
    expect($siblings->first()?->id)->toBe($child1->id);
    expect($siblings->last()?->id)->toBe($child3->id);
});
