<?php

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\TaxonomyManager;
use Aliziodev\LaravelTaxonomy\TaxonomyProvider;
use Aliziodev\LaravelTaxonomy\Tests\Support\NoDbTestCase as TestCase;
use Illuminate\Support\Facades\App;

uses(TestCase::class);

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

it('respects configured migration paths when autoload is enabled', function () {
    // Enable autoload and set a custom path to ensure provider uses it
    $customPath = base_path('database/migrations/tenants');
    config(['taxonomy.migrations.autoload' => true]);
    config(['taxonomy.migrations.paths' => [$customPath]]);
    
    // Bind a fake migrator to avoid real migration dependencies
    $fakeMigrator = new class {
        private array $paths = [];
        public function path(string $path): void { $this->paths[] = $path; }
        public function paths(): array { return $this->paths; }
    };
    App::instance('migrator', $fakeMigrator);
    App::instance(\Illuminate\Database\Migrations\Migrator::class, $fakeMigrator);

    // Re-run provider boot to apply current config and register migration paths
    $provider = new TaxonomyProvider(App::getFacadeRoot());
    $provider->boot();

    // Resolve migrator so provider's afterResolving callback applies paths
    $migrator = App::make('migrator');
    $paths = $migrator->paths();

    expect($paths)->toContain($customPath);
});

it('does not register custom migration paths when autoload is disabled', function () {
    $customPath = base_path('database/migrations/tenants');
    config(['taxonomy.migrations.autoload' => false]);
    config(['taxonomy.migrations.paths' => [$customPath]]);

    // Bind a fresh fake migrator to avoid real migration dependencies
    $fakeMigrator = new class {
        private array $paths = [];
        public function path(string $path): void { $this->paths[] = $path; }
        public function paths(): array { return $this->paths; }
    };
    App::instance('migrator', $fakeMigrator);
    App::instance(\Illuminate\Database\Migrations\Migrator::class, $fakeMigrator);

    // Re-run provider boot; with autoload disabled it should not add the custom path
    $provider = new TaxonomyProvider(App::getFacadeRoot());
    $provider->boot();

    $afterPaths = App::make('migrator')->paths();

    expect($afterPaths)->not->toContain($customPath);
    expect($afterPaths)->toBeArray();
    expect(count($afterPaths))->toBe(0);
});
