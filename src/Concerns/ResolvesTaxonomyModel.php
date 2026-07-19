<?php

namespace Aliziodev\LaravelTaxonomy\Concerns;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;

/**
 * Resolves the configured taxonomy model.
 *
 * Shared so the manager, the console commands and the trait all read the same
 * config key instead of each carrying their own copy of this lookup.
 */
trait ResolvesTaxonomyModel
{
    /**
     * Get the taxonomy model class from config.
     *
     * @return class-string<Taxonomy>
     */
    protected function getModelClass(): string
    {
        return config('taxonomy.model', Taxonomy::class);
    }
}
