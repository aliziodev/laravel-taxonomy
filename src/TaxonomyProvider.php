<?php

namespace Aliziodev\LaravelTaxonomy;

use Aliziodev\LaravelTaxonomy\Console\Commands\InstallCommand;
use Aliziodev\LaravelTaxonomy\Console\Commands\RebuildNestedSetCommand;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Support\ServiceProvider;

/**
 * TaxonomyProvider is the service provider for the Laravel Taxonomy package.
 *
 * This provider registers the necessary services, publishes configuration and migrations,
 * and bootstraps the package within a Laravel application.
 */
class TaxonomyProvider extends ServiceProvider
{
    /**
     * The commands to be registered.
     *
     * @var array<int, class-string>
     */
    protected $commands = [
        InstallCommand::class,
        RebuildNestedSetCommand::class,
    ];

    /**
     * Register any application services.
     *
     * This method is called when the application is registering service providers.
     * It registers the TaxonomyManager singleton and the Taxonomy model binding.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/taxonomy.php',
            'taxonomy'
        );

        // Register the TaxonomyManager singleton
        $this->app->singleton('taxonomy', function ($app) {
            return new TaxonomyManager;
        });

        // Register the Taxonomy model binding
        $this->app->bind(Taxonomy::class, function ($app) {
            $modelClass = config('taxonomy.model', Taxonomy::class);

            return new $modelClass;
        });
    }

    /**
     * Bootstrap any application services.
     *
     * This method is called after all service providers have been registered.
     * It publishes the package's configuration and migrations, and loads the migrations.
     */
    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/taxonomy.php' => config_path('taxonomy.php'),
        ], 'taxonomy-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'taxonomy-migrations');

        // Load migrations conditionally based on config
        $autoload = (bool) config('taxonomy.migrations.autoload', true);
        $paths = (array) config('taxonomy.migrations.paths', []);
        if ($autoload) {
            $pathsToLoad = ! empty($paths) ? $paths : [__DIR__ . '/../database/migrations'];
            $this->loadMigrationsFrom($pathsToLoad);
        }

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }
}
