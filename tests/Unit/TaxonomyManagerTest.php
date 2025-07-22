<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\TaxonomyManager;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;

uses(TestCase::class, RefreshDatabase::class);

it('createOrUpdate creates new taxonomy', function () {
    $manager = new TaxonomyManager;

    $taxonomy = $manager->createOrUpdate([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    expect($taxonomy)->toBeInstanceOf(Taxonomy::class);
    expect($taxonomy->name)->toBe('Test Category');
    expect($taxonomy->type)->toBe(TaxonomyType::Category->value);
    expect($taxonomy->slug)->toBe('test-category');
});

it('createOrUpdate updates existing taxonomy', function () {
    // Disable slug regeneration on update for this test
    config(['taxonomy.slugs.regenerate_on_update' => false]);

    $manager = new TaxonomyManager;

    // Create initial taxonomy
    $taxonomy1 = $manager->createOrUpdate([
        'name' => 'Original Name',
        'slug' => 'test-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    $originalId = $taxonomy1->id;

    // Update with same slug and type
    $taxonomy2 = $manager->createOrUpdate([
        'name' => 'Updated Name',
        'slug' => 'test-slug',
        'type' => TaxonomyType::Category->value,
    ]);

    expect($taxonomy1->id)->toBe($taxonomy2->id);
    expect($taxonomy2->name)->toBe('Updated Name');
    expect($taxonomy2->slug)->toBe('test-slug');

    // Verify it's the same record in database
    expect(Taxonomy::count())->toBe(1);
    $foundTaxonomy = Taxonomy::find($originalId);
    expect($foundTaxonomy)->not->toBeNull();
    /* @var Taxonomy $foundTaxonomy */
    expect($foundTaxonomy->name)->toBe('Updated Name');
});

it('exists returns true for existing taxonomy', function () {
    $manager = new TaxonomyManager;

    Taxonomy::create([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'type' => TaxonomyType::Category->value,
    ]);

    expect($manager->exists('test-category'))->toBeTrue();
    expect($manager->exists('test-category', TaxonomyType::Category))->toBeTrue();
    expect($manager->exists('test-category', TaxonomyType::Tag))->toBeFalse();
});

it('exists returns false for non-existing taxonomy', function () {
    $manager = new TaxonomyManager;

    expect($manager->exists('non-existing-slug'))->toBeFalse();
    expect($manager->exists('non-existing-slug', TaxonomyType::Category))->toBeFalse();
});

it('exists works with string type', function () {
    $manager = new TaxonomyManager;

    Taxonomy::create([
        'name' => 'Test Tag',
        'slug' => 'test-tag',
        'type' => 'tag',
    ]);

    expect($manager->exists('test-tag', 'tag'))->toBeTrue();
    expect($manager->exists('test-tag', 'category'))->toBeFalse();
});

it('find returns taxonomy by id', function () {
    $manager = new TaxonomyManager;

    $taxonomy = Taxonomy::create([
        'name' => 'Test Category',
        'slug' => 'test-category',
        'type' => TaxonomyType::Category->value,
    ]);

    $found = $manager->find($taxonomy->id);

    expect($found)->toBeInstanceOf(Taxonomy::class);
    expect($found)->not->toBeNull();
    /* @var Taxonomy $found */
    expect($found->id)->toBe($taxonomy->id);
    expect($found->name)->toBe('Test Category');
});

it('find returns null for non-existing id', function () {
    $manager = new TaxonomyManager;

    $found = $manager->find(999999);

    expect($found)->toBeNull();
});

it('getModelClass returns correct model class', function () {
    $manager = new TaxonomyManager;

    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('getModelClass');
    $method->setAccessible(true);
    $modelClass = $method->invoke($manager);

    expect($modelClass)->toBe(Taxonomy::class);
});

it('getModelClass returns custom model class from config', function () {
    config(['taxonomy.model' => Taxonomy::class]);

    $manager = new TaxonomyManager;
    $reflection = new ReflectionClass($manager);
    $method = $reflection->getMethod('getModelClass');
    $method->setAccessible(true);
    $modelClass = $method->invoke($manager);

    expect($modelClass)->toBe(Taxonomy::class);
});
