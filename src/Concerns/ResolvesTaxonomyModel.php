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

    /**
     * Get the taxonomies table name.
     *
     * Read from the resolved model rather than straight from config, so a
     * custom model that overrides getTable() is honoured too.
     */
    protected function getTaxonomyTable(): string
    {
        $modelClass = $this->getModelClass();

        return (new $modelClass)->getTable();
    }
}
