<?php

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\TaxonomyManager;
use Aliziodev\LaravelTaxonomy\TaxonomyProvider;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(TestCase::class, RefreshDatabase::class);

it('registers taxonomy manager singleton via alias', function () {
    // Test that TaxonomyManager is registered as singleton via 'taxonomy' alias
    $manager1 = App::make('taxonomy');
    $manager2 = App::make('taxonomy');

    expect($manager1 instanceof TaxonomyManager)->toBeTrue();
    expect($manager2 instanceof TaxonomyManager)->toBeTrue();
    expect($manager1 === $manager2)->toBeTrue(); // Same instance (singleton)
});

it('can create taxonomy manager instances', function () {
    // Test that TaxonomyManager can be instantiated (not singleton for class binding)
    $manager1 = App::make(TaxonomyManager::class);
    $manager2 = App::make(TaxonomyManager::class);

    expect($manager1)->toBeInstanceOf(TaxonomyManager::class);
    expect($manager2)->toBeInstanceOf(TaxonomyManager::class);
    // These are different instances since TaxonomyManager class is not bound as singleton
});

it('registers taxonomy model binding', function () {
    // Test that Taxonomy model is properly bound
    $taxonomy = App::make(Taxonomy::class);

    expect($taxonomy)->toBeInstanceOf(Taxonomy::class);
});

it('registers taxonomy model binding with custom model class', function () {
    // Test custom model class binding
    config(['taxonomy.model' => Taxonomy::class]);

    $taxonomy = App::make(Taxonomy::class);

    expect($taxonomy)->toBeInstanceOf(Taxonomy::class);
});

it('provider is properly registered', function () {
    // Test that the provider is registered in the application
    $providers = App::getLoadedProviders();

    expect($providers)->toHaveKey(TaxonomyProvider::class);
});

it('registers services correctly', function () {
    // Test that services are registered correctly
    expect(App::bound('taxonomy'))->toBeTrue();
    expect(App::bound(Taxonomy::class))->toBeTrue();

    // Test that taxonomy service returns TaxonomyManager instance
    $taxonomyService = App::make('taxonomy');
    expect($taxonomyService instanceof TaxonomyManager)->toBeTrue();
});
