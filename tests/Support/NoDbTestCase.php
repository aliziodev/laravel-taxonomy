<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Support;

use Aliziodev\LaravelTaxonomy\TaxonomyProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Minimal TestCase without database migrations for provider-level tests.
 */
abstract class NoDbTestCase extends BaseTestCase
{
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
     */
    protected function defineEnvironment($app): void
    {
        // Keep lightweight defaults; no DB migrations executed here.
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');

        // Basic taxonomy defaults
        $app['config']->set('taxonomy.table_names', [
            'taxonomies' => 'taxonomies',
            'taxonomables' => 'taxonomables',
        ]);

        $app['config']->set('taxonomy.morph_type', 'uuid');
    }
}
