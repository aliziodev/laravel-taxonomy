<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Exceptions\MissingSlugException;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('can create a taxonomy', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
        'description' => 'This is a test category',
    ]);

    expect($taxonomy)->toBeInstanceOf(Taxonomy::class);
    expect($taxonomy->name)->toBe('Test Category');
    expect($taxonomy->slug)->toBe('test-category');
    expect($taxonomy->type)->toBe(TaxonomyType::Category->value);
    expect($taxonomy->description)->toBe('This is a test category');

    expect($taxonomy->slug)->toBe('test-category');
});

it('can create a taxonomy with custom slug', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Test Category',
        'slug' => 'custom-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    expect($taxonomy->slug)->toBe('custom-slug');
});

it('can create a taxonomy with parent', function () {
    $parent = Taxonomy::create([
        'name' => 'Parent Category',
        'type' => TaxonomyType::Category->value,
    ]);

    $child = Taxonomy::create([
        'name' => 'Child Category',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $parent->id,
    ]);

    expect($child->parent_id)->toBe($parent->id);
    expect($child->parent)->not->toBeNull();
    expect($child->parent->name)->toBe('Parent Category');
    expect($parent->children)->toHaveCount(1);
    $firstChild = $parent->children->first();
    expect($firstChild)->not->toBeNull();
    expect($firstChild->name)->toBe('Child Category');
});

it('can create a taxonomy with metadata', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
        'meta' => ['color' => 'red', 'featured' => true],
    ]);

    expect($taxonomy->meta)->not->toBeNull();
    expect($taxonomy->meta['color'])->toBe('red');
    expect($taxonomy->meta['featured'])->toBeTrue();
});

it('can get ancestors', function () {
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

    expect($ancestors)->toHaveCount(2);
    $firstAncestor = $ancestors->first();
    expect($firstAncestor)->not->toBeNull();
    expect($firstAncestor->name)->toBe('Parent Category');
    $lastAncestor = $ancestors->last();
    expect($lastAncestor)->not->toBeNull();
    expect($lastAncestor->name)->toBe('Grandparent Category');
});

it('can get descendants', function () {
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

    $parent->load('children.children'); // Eager load nested children
    $descendants = $parent->descendants();

    expect($descendants)->toHaveCount(3);
    expect($descendants->pluck('id'))->toContain($child1->id);
    expect($descendants->pluck('id'))->toContain($child2->id);
    expect($descendants->pluck('id'))->toContain($grandchild->id);
});

it('can get path attribute', function () {
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

    expect($child->path)->toBe('Grandparent Category > Parent Category > Child Category');
});

it('can get full slug attribute', function () {
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

    expect($child->full_slug)->toBe('grandparent-category/parent-category/child-category');
});

it('can find by slug', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    $found = Taxonomy::findBySlug('test-category');

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($taxonomy->id);
});

it('can find by slug and type', function () {
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

    expect($foundCategory)->not->toBeNull();
    expect($foundTag)->not->toBeNull();
    expect($foundCategory->id)->toBe($category->id);
    expect($foundTag->id)->toBe($tag->id);
});

it('can create or update taxonomy', function () {
    // Create new taxonomy
    $taxonomy = Taxonomy::createOrUpdate([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
        'description' => 'Initial description',
    ]);

    expect($taxonomy)->toBeInstanceOf(Taxonomy::class);
    expect($taxonomy->name)->toBe('Test Category');
    expect($taxonomy->slug)->toBe('test-category');
    expect($taxonomy->description)->toBe('Initial description');

    // Update existing taxonomy
    $updated = Taxonomy::createOrUpdate([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
        'description' => 'Updated description',
    ]);

    expect($updated->id)->toBe($taxonomy->id);
    expect($updated->description)->toBe('Updated description');
    expect($updated->name)->toBe('Test Category');
    expect($updated->slug)->toBe('test-category');
    expect($updated->description)->toBe('Updated description');
});

it('can get flat tree', function () {
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

    expect($flatTree)->toHaveCount(4);
    expect($flatTree[0])->not->toBeNull();
    expect($flatTree[1])->not->toBeNull();
    expect($flatTree[2])->not->toBeNull();
    expect($flatTree[3])->not->toBeNull();
    expect($flatTree[0]->depth)->toBe(0);
    expect($flatTree[1]->depth)->toBe(1);
    expect($flatTree[2]->depth)->toBe(1);
    expect($flatTree[3]->depth)->toBe(2);

    // Check ordering
    expect($flatTree[0]->name)->toBe('Parent Category');
    expect($flatTree[1]->name)->toBe('Child Category 2'); // Lower sort_order comes first
    expect($flatTree[2]->name)->toBe('Child Category 1');
    expect($flatTree[3]->name)->toBe('Grandchild Category');
});

it('can get tree', function () {
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

    expect($tree)->toHaveCount(1); // Only root level items
    expect($tree[0])->not->toBeNull();
    expect($tree[0]->name)->toBe('Parent Category');
    expect($tree[0]->children)->toHaveCount(2);
    expect($tree[0]->children[0])->not->toBeNull();
    expect($tree[0]->children[0]->children)->toHaveCount(1);
});

it('generates unique slugs for same name and type', function () {
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
    expect($taxonomy1->slug)->toBe('test-category');
    expect($taxonomy2->slug)->toBe('test-category-1');

    // Create third taxonomy with same name and type
    $taxonomy3 = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    // Verify slug has incremented counter
    expect($taxonomy3->slug)->toBe('test-category-2');
});

it('allows same slugs for different types', function () {
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

    // Verify same slugs are allowed for different types
    expect($taxonomy1->slug)->toBe('test-term');
    expect($taxonomy2->slug)->toBe('test-term');
    expect($taxonomy1->type)->toBe(TaxonomyType::Category->value);
    expect($taxonomy2->type)->toBe(TaxonomyType::Tag->value);
});

it('generates unique slug when updating', function () {
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
    expect($taxonomy1->slug)->toBe('first-category');
    expect($taxonomy2->slug)->toBe('first-category-1');
});

it('throws exception when slug generation is disabled and no slug provided', function () {
    // Disable slug generation
    config(['taxonomy.slugs.generate' => false]);

    // Expect MissingSlugException when creating without a slug
    expect(function () {
        Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);
    })->toThrow(MissingSlugException::class);
});

it('throws exception when custom slug already exists in same type', function () {
    // Create a taxonomy with a specific slug
    Taxonomy::create([
        'name' => 'First Category',
        'slug' => 'existing-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    // Expect DuplicateSlugException when creating with the same slug in same type
    expect(function () {
        Taxonomy::create([
            'name' => 'Second Category',
            'slug' => 'existing-slug',
            'type' => TaxonomyType::Category->value, // Same type, same slug
        ]);
    })->toThrow(DuplicateSlugException::class);
});

it('can get direct children using nested set', function () {
    $parent = Taxonomy::create([
        'name' => 'Parent Category',
        'type' => TaxonomyType::Category->value,
    ]);

    $child1 = Taxonomy::create([
        'name' => 'Child 1',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $parent->id,
    ]);

    $child2 = Taxonomy::create([
        'name' => 'Child 2',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $parent->id,
    ]);

    // Create a grandchild to ensure only direct children are returned
    $grandchild = Taxonomy::create([
        'name' => 'Grandchild',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $child1->id,
    ]);

    $children = $parent->getChildren();

    expect($children)->toHaveCount(2);
    expect($children->pluck('id')->toArray())->toContain($child1->id, $child2->id);
    expect($children->pluck('id')->toArray())->not->toContain($grandchild->id);
});

it('can check if taxonomy is ancestor of another', function () {
    $grandparent = Taxonomy::create([
        'name' => 'Grandparent',
        'type' => TaxonomyType::Category->value,
    ]);

    $parent = Taxonomy::create([
        'name' => 'Parent',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $grandparent->id,
    ]);

    $child = Taxonomy::create([
        'name' => 'Child',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $parent->id,
    ]);

    // Different type taxonomy
    $differentType = Taxonomy::create([
        'name' => 'Different Type',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Rebuild nested set to ensure proper lft/rgt values
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
    Taxonomy::rebuildNestedSet(TaxonomyType::Tag->value);

    // Refresh models to get updated lft/rgt values
    $grandparent->refresh();
    $parent->refresh();
    $child->refresh();
    $differentType->refresh();

    expect($grandparent->isAncestorOf($parent))->toBeTrue();
    expect($grandparent->isAncestorOf($child))->toBeTrue();
    expect($parent->isAncestorOf($child))->toBeTrue();
    expect($child->isAncestorOf($parent))->toBeFalse();
    expect($parent->isAncestorOf($grandparent))->toBeFalse();
    expect($grandparent->isAncestorOf($differentType))->toBeFalse(); // Different type
});

it('returns false for isAncestorOf when nested set values are null', function () {
    $taxonomy1 = Taxonomy::create([
        'name' => 'Taxonomy 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $taxonomy2 = Taxonomy::create([
        'name' => 'Taxonomy 2',
        'type' => TaxonomyType::Category->value,
    ]);

    // Manually set nested set values to null
    $taxonomy1->update(['lft' => null, 'rgt' => null]);
    $taxonomy2->update(['lft' => null, 'rgt' => null]);

    $taxonomy1->refresh();
    $taxonomy2->refresh();

    expect($taxonomy1->isAncestorOf($taxonomy2))->toBeFalse();
});

it('allows same custom slug for different types', function () {
    // Create a taxonomy with a specific slug
    $taxonomy1 = Taxonomy::create([
        'name' => 'First Category',
        'slug' => 'existing-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    // Should allow same slug for different type
    $taxonomy2 = Taxonomy::create([
        'name' => 'Second Tag',
        'slug' => 'existing-slug',
        'type' => TaxonomyType::Tag->value, // Different type, same slug
    ]);

    expect($taxonomy1->slug)->toBe('existing-slug');
    expect($taxonomy2->slug)->toBe('existing-slug');
    expect($taxonomy1->type)->toBe(TaxonomyType::Category->value);
    expect($taxonomy2->type)->toBe(TaxonomyType::Tag->value);
});

it('throws exception when updating with duplicate slug', function () {
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
    expect(function () use ($taxonomy2) {
        $taxonomy2->update([
            'slug' => 'first-slug',
        ]);
    })->toThrow(DuplicateSlugException::class);
});

it('throws exception when create or update with missing slug', function () {
    // Disable slug generation
    config(['taxonomy.slugs.generate' => false]);

    // Expect MissingSlugException when using createOrUpdate without a slug
    expect(function () {
        Taxonomy::createOrUpdate([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);
    })->toThrow(MissingSlugException::class);
});

it('updates existing taxonomy when create or update with same slug and type', function () {
    // Create a taxonomy with a specific slug
    $original = Taxonomy::create([
        'name' => 'First Category',
        'slug' => 'existing-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    // createOrUpdate should update the existing taxonomy when slug and type match
    $updated = Taxonomy::createOrUpdate([
        'name' => 'First Category', // Keep same name to preserve slug
        'slug' => 'existing-slug',
        'type' => TaxonomyType::Category->value, // Same type, same slug
        'description' => 'Updated description',
    ]);

    // Should be the same instance (updated)
    expect($updated->id)->toBe($original->id);
    expect($updated->name)->toBe('First Category');
    expect($updated->slug)->toBe('existing-slug');
    expect($updated->description)->toBe('Updated description');
    expect($updated->type)->toBe(TaxonomyType::Category->value);

    // Should only have one taxonomy in database
    expect(Taxonomy::count())->toBe(1);
});

it('updates existing taxonomy when create or update finds matching slug and type', function () {
    // Create a taxonomy with a specific slug
    $original = Taxonomy::create([
        'name' => 'First Category',
        'slug' => 'existing-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    // createOrUpdate should update the existing taxonomy when it finds matching slug and type
    $updated = Taxonomy::createOrUpdate([
        'name' => 'First Category', // Keep same name to preserve slug
        'slug' => 'existing-slug', // Same slug
        'type' => TaxonomyType::Category->value, // Same type
        'description' => 'Updated via createOrUpdate',
    ]);

    // Should be the same instance (updated)
    expect($updated->id)->toBe($original->id);
    expect($updated->name)->toBe('First Category');
    expect($updated->slug)->toBe('existing-slug');
    expect($updated->description)->toBe('Updated via createOrUpdate');
    expect($updated->type)->toBe(TaxonomyType::Category->value);

    // Should only have one taxonomy in database
    expect(Taxonomy::count())->toBe(1);
});

it('allows create or update with same slug for different types', function () {
    // Create a taxonomy with a specific slug
    $taxonomy1 = Taxonomy::create([
        'name' => 'First Category',
        'slug' => 'existing-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    // Should allow createOrUpdate with same slug for different type
    $taxonomy2 = Taxonomy::createOrUpdate([
        'name' => 'Second Tag',
        'slug' => 'existing-slug',
        'type' => TaxonomyType::Tag->value, // Different type, same slug
    ]);

    expect($taxonomy1->slug)->toBe('existing-slug');
    expect($taxonomy2->slug)->toBe('existing-slug');
    expect($taxonomy1->type)->toBe(TaxonomyType::Category->value);
    expect($taxonomy2->type)->toBe(TaxonomyType::Tag->value);

    // Should have two taxonomies in database
    expect(Taxonomy::count())->toBe(2);
});

it('throws exception when creating with duplicate slug in same type', function () {
    // Create a taxonomy with a specific slug
    Taxonomy::create([
        'name' => 'First Category',
        'slug' => 'existing-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    // Expect DuplicateSlugException when creating (not createOrUpdate) with existing slug in same type
    expect(function () {
        Taxonomy::create([
            'name' => 'Different Category',
            'slug' => 'existing-slug', // Same slug
            'type' => TaxonomyType::Category->value, // Same type
        ]);
    })->toThrow(DuplicateSlugException::class);
});
