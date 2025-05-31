<?php

namespace Aliziodev\LaravelTaxonomy\Facades;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Facade;

/**
 * Taxonomy facade provides a convenient static interface to the TaxonomyManager.
 *
 * This facade allows for easy access to taxonomy management functionality throughout the application.
 *
 * @method static TaxonomyModel create(array<string, mixed> $attributes) Create a new taxonomy
 * @method static TaxonomyModel createOrUpdate(array<string, mixed> $attributes) Create a new taxonomy or update if it already exists
 * @method static TaxonomyModel|null findBySlug(string $slug, string|TaxonomyType|null $type = null) Find a taxonomy by its slug
 * @method static Collection<int, TaxonomyModel> tree(string|TaxonomyType|null $type = null, int $parentId = null) Get a nested tree representation of the taxonomy hierarchy
 * @method static Collection<int, TaxonomyModel> flatTree(string|TaxonomyType|null $type = null, int $parentId = null, int $depth = 0) Get a flat tree representation of the taxonomy hierarchy
 * @method static Collection<int, TaxonomyModel> getTypes() Get all available taxonomy types
 * @method static bool exists(string $slug, string|TaxonomyType|null $type = null) Check if a taxonomy with the given slug exists
 * @method static TaxonomyModel|null find(int $id) Find a taxonomy by its ID
 * @method static Collection<int, TaxonomyModel>|LengthAwarePaginator<int, TaxonomyModel> findMany(array<int> $ids, int|null $perPage = null, int $page = 1) Find multiple taxonomies by their IDs
 * @method static Collection<int, TaxonomyModel>|LengthAwarePaginator<int, TaxonomyModel> findByType(string|TaxonomyType $type, int|null $perPage = null, int $page = 1) Find taxonomies by type
 * @method static Collection<int, TaxonomyModel>|LengthAwarePaginator<int, TaxonomyModel> findByParent(int $parentId = null, int|null $perPage = null, int $page = 1) Find taxonomies by parent ID
 * @method static Collection<int, TaxonomyModel>|LengthAwarePaginator<int, TaxonomyModel> search(string $term, string|TaxonomyType|null $type = null, int|null $perPage = null, int $page = 1) Search for taxonomies by name or description
 *
 * Nested Set Methods:
 * @method static Collection<int, TaxonomyModel> getNestedTree(string|TaxonomyType|null $type = null) Get a nested tree structure with proper hierarchy
 * @method static void rebuildNestedSet(string|TaxonomyType|null $type = null) Rebuild the nested set values for all taxonomies of a given type
 * @method static bool moveToParent(int $taxonomyId, int|null $newParentId = null) Move a taxonomy to a new parent
 * @method static Collection<int, TaxonomyModel> getDescendants(int $taxonomyId) Get all descendants of a taxonomy
 * @method static Collection<int, TaxonomyModel> getAncestors(int $taxonomyId) Get all ancestors of a taxonomy
 * @method static void clearCacheForType(string|TaxonomyType $type) Clear cache for a specific taxonomy type
 *
 * @see \Aliziodev\LaravelTaxonomy\TaxonomyManager
 */
class Taxonomy extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'taxonomy';
    }
}
