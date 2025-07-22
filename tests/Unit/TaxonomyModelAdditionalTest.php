<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(TestCase::class, RefreshDatabase::class);

it('morphedByMany returns correct relation', function () {
    $taxonomy = new Taxonomy;

    $relation = $taxonomy->morphedByMany(Product::class, 'taxonomable');

    expect($relation)->toBeInstanceOf(MorphToMany::class);
    expect($relation->getTable())->toBe('taxonomables');
    expect($relation->getForeignPivotKeyName())->toBe('taxonomy_id');
    expect($relation->getRelatedPivotKeyName())->toBe('taxonomable_id');
});

it('morphedByMany uses custom table name from config', function () {
    config(['taxonomy.table_names.taxonomables' => 'custom_taxonomables']);

    $taxonomy = new Taxonomy;
    $relation = $taxonomy->morphedByMany(Product::class, 'taxonomable');

    expect($relation->getTable())->toBe('custom_taxonomables');
});

it('morphedByMany uses custom foreign pivot key', function () {
    $taxonomy = new Taxonomy;

    $relation = $taxonomy->morphedByMany(
        Product::class,
        'taxonomable',
        null,
        'custom_taxonomy_id'
    );

    expect($relation->getForeignPivotKeyName())->toBe('custom_taxonomy_id');
});

it('morphedByMany uses custom related pivot key', function () {
    $taxonomy = new Taxonomy;

    $relation = $taxonomy->morphedByMany(
        Product::class,
        'taxonomable',
        null,
        null,
        'custom_taxonomable_id'
    );

    expect($relation->getRelatedPivotKeyName())->toBe('custom_taxonomable_id');
});

it('scopeRoot filters only root taxonomies', function () {
    // Create root taxonomy
    $root = Taxonomy::create([
        'name' => 'Root Category',
        'type' => TaxonomyType::Category->value,
    ]);

    // Create child taxonomy
    $child = Taxonomy::create([
        'name' => 'Child Category',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $root->id,
    ]);

    $rootTaxonomies = Taxonomy::root()->get();

    expect($rootTaxonomies)->toHaveCount(1);
    $firstRoot = $rootTaxonomies->first();
    expect($firstRoot)->not->toBeNull();
    /* @var Taxonomy $firstRoot */
    expect($firstRoot->id)->toBe($root->id);
    expect($rootTaxonomies->contains($child))->toBeFalse();
});

it('scopeRoot works with multiple root taxonomies', function () {
    // Create multiple root taxonomies
    $root1 = Taxonomy::create([
        'name' => 'Root 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $root2 = Taxonomy::create([
        'name' => 'Root 2',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Create child taxonomy
    Taxonomy::create([
        'name' => 'Child',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $root1->id,
    ]);

    $rootTaxonomies = Taxonomy::root()->get();

    expect($rootTaxonomies)->toHaveCount(2);
    expect($rootTaxonomies->pluck('id')->toArray())->toContain($root1->id, $root2->id);
});

it('wouldCreateCircularReference detects self reference', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    $reflection = new ReflectionClass($taxonomy);
    $method = $reflection->getMethod('wouldCreateCircularReference');
    $method->setAccessible(true);

    $result = $method->invoke($taxonomy, $taxonomy->id);

    expect($result)->toBeTrue();
});

it('wouldCreateCircularReference detects circular reference in chain', function () {
    // Create hierarchy: grandparent -> parent -> child
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

    $reflection = new ReflectionClass($grandparent);
    $method = $reflection->getMethod('wouldCreateCircularReference');
    $method->setAccessible(true);

    // Try to make grandparent a child of child (would create circular reference)
    $result = $method->invoke($grandparent, $child->id);

    expect($result)->toBeTrue();
});

it('wouldCreateCircularReference returns false for valid parent', function () {
    $parent = Taxonomy::create([
        'name' => 'Parent',
        'type' => TaxonomyType::Category->value,
    ]);

    $child = Taxonomy::create([
        'name' => 'Child',
        'type' => TaxonomyType::Category->value,
    ]);

    $reflection = new ReflectionClass($child);
    $method = $reflection->getMethod('wouldCreateCircularReference');
    $method->setAccessible(true);

    $result = $method->invoke($child, $parent->id);

    expect($result)->toBeFalse();
});

it('wouldCreateCircularReference handles infinite loop prevention', function () {
    // Create two taxonomies that reference each other (corrupted data scenario)
    $taxonomy1 = Taxonomy::create([
        'name' => 'Taxonomy 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $taxonomy2 = Taxonomy::create([
        'name' => 'Taxonomy 2',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $taxonomy1->id,
    ]);

    // Manually create circular reference in database (simulating corrupted data)
    $taxonomy1->update(['parent_id' => $taxonomy2->id]);

    $reflection = new ReflectionClass($taxonomy1);
    $method = $reflection->getMethod('wouldCreateCircularReference');
    $method->setAccessible(true);

    // This should detect the circular reference and return true
    $result = $method->invoke($taxonomy1, $taxonomy2->id);

    expect($result)->toBeTrue();
});

it('descendants returns empty collection when lft or rgt is null', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Test',
        'type' => TaxonomyType::Category->value,
    ]);

    // Ensure taxonomy was created successfully
    expect($taxonomy)->not->toBeNull();
    expect($taxonomy->id)->not->toBeNull();

    // Manually set lft and rgt to null to simulate the condition
    $taxonomy->update(['lft' => null, 'rgt' => null]);

    $descendants = $taxonomy->descendants();

    expect($descendants)->toBeInstanceOf(Collection::class);
    expect($descendants)->toHaveCount(0);
});

it('descendants returns empty collection when only lft is null', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Test',
        'type' => TaxonomyType::Category->value,
    ]);

    // Update to set lft to null
    $taxonomy->update(['lft' => null, 'rgt' => 10]);

    $descendants = $taxonomy->descendants();

    expect($descendants)->toBeInstanceOf(Collection::class);
    expect($descendants)->toHaveCount(0);
});

it('descendants returns empty collection when only rgt is null', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Test',
        'type' => TaxonomyType::Category->value,
    ]);

    // Update to set rgt to null
    $taxonomy->update(['lft' => 1, 'rgt' => null]);

    $descendants = $taxonomy->descendants();

    expect($descendants)->toBeInstanceOf(Collection::class);
    expect($descendants)->toHaveCount(0);
});

it('makeRoot is called when parent_id is null', function () {
    $taxonomy = Taxonomy::create([
        'name' => 'Root Category',
        'type' => TaxonomyType::Category->value,
        'parent_id' => null, // Explicitly set to null
    ]);

    // Verify it's a root node
    expect($taxonomy->parent_id)->toBeNull();
    expect($taxonomy->lft)->toBe(1);
    expect($taxonomy->rgt)->toBe(2);
});

it('throws DuplicateSlugException with type context', function () {
    // Create first taxonomy
    Taxonomy::create([
        'name' => 'First Category',
        'slug' => 'duplicate-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    // Try to create second taxonomy with same slug and type
    expect(function () {
        Taxonomy::create([
            'name' => 'Second Category',
            'slug' => 'duplicate-slug',
            'type' => TaxonomyType::Category->value,
        ]);
    })->toThrow(DuplicateSlugException::class);
});
