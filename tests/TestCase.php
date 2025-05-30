<?php

namespace Aliziodev\LaravelTaxonomy\Tests;

use Aliziodev\LaravelTaxonomy\TaxonomyProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->artisan('migrate', ['--database' => 'testing'])->run();

        // Create products table for testing
        $this->createProductsTable();
    }

    /**
     * Create products table for testing.
     */
    protected function createProductsTable(): void
    {
        $this->app['db']->connection()->getSchemaBuilder()->create('products', function ($table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            TaxonomyProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Use the default config
        $app['config']->set('taxonomy.table_names', [
            'taxonomies' => 'taxonomies',
            'taxonomables' => 'taxonomables',
        ]);

        $app['config']->set('taxonomy.morph_type', 'uuid');
    }
}
