<?php

namespace Aliziodev\LaravelTaxonomy;

use Aliziodev\LaravelTaxonomy\Console\Commands\InstallCommand;
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
     * @var array
     */
    protected $commands = [
        InstallCommand::class,
    ];
    /**
     * Register any application services.
     * 
     * This method is called when the application is registering service providers.
     * It registers the TaxonomyManager singleton and the Taxonomy model binding.
     * 
     * @return void
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/taxonomy.php', 'taxonomy'
        );

        // Register the TaxonomyManager singleton
        $this->app->singleton('taxonomy', function ($app) {
            return new TaxonomyManager();
        });

        // Register the Taxonomy model binding
        $this->app->bind(Taxonomy::class, function ($app) {
            return new Taxonomy();
        });
    }

    /**
     * Bootstrap any application services.
     * 
     * This method is called after all service providers have been registered.
     * It publishes the package's configuration and migrations, and loads the migrations.
     * 
     * @return void
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

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }
}
