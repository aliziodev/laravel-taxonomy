<?php

namespace Aliziodev\LaravelTaxonomy;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * TaxonomyManager provides a centralized way to manage taxonomies.
 *
 * This class offers methods for creating, retrieving, and manipulating taxonomies,
 * with built-in caching for improved performance.
 */
class TaxonomyManager
{
    /**
     * Get the taxonomy model class from config.
     *
     * @return class-string<\Aliziodev\LaravelTaxonomy\Models\Taxonomy>
     */
    protected function getModelClass(): string
    {
        return config('taxonomy.model', Taxonomy::class);
    }

    /**
     * Create a new taxonomy.
     *
     * @param  array<string, mixed>  $attributes  The attributes for the new taxonomy
     * @return \Aliziodev\LaravelTaxonomy\Models\Taxonomy The created taxonomy
     */
    public function create(array $attributes): Taxonomy
    {
        $modelClass = $this->getModelClass();

        return $modelClass::create($attributes);
    }

    /**
     * Create a new taxonomy or update if it already exists.
     *
     * @param  array<string, mixed>  $attributes  The attributes for the new taxonomy
     */
    public function createOrUpdate(array $attributes): Taxonomy
    {
        $modelClass = $this->getModelClass();

        return $modelClass::createOrUpdate($attributes);
    }

    /**
     * Find a taxonomy by its slug.
     */
    public function findBySlug(string $slug, string|TaxonomyType|null $type = null): ?Taxonomy
    {
        $modelClass = $this->getModelClass();

        return $modelClass::findBySlug($slug, $type);
    }

    /**
     * Get a nested tree representation of the taxonomy hierarchy.
     *
     * This method returns a hierarchical tree of taxonomies with parent-child relationships.
     * Results are cached for 24 hours for improved performance.
     *
     * @param  string|\Aliziodev\LaravelTaxonomy\Enums\TaxonomyType|null  $type  The taxonomy type to filter by (optional)
     * @param  int|null  $parentId  The parent ID to start the tree from (null for root level)
     * @return \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy> The hierarchical collection of taxonomies
     */
    public function tree(string|TaxonomyType|null $type = null, ?int $parentId = null): Collection
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $cacheKey = "taxonomy_tree_{$typeValue}_{$parentId}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($type, $parentId) {
            $modelClass = $this->getModelClass();

            return $modelClass::tree($type, $parentId);
        });
    }

    /**
     * Get a flat tree representation of the taxonomy hierarchy.
     *
     * This method returns a flat list of taxonomies with depth information.
     * Results are cached for 24 hours for improved performance.
     *
     * @param  string|\Aliziodev\LaravelTaxonomy\Enums\TaxonomyType|null  $type  The taxonomy type to filter by (optional)
     * @param  int|null  $parentId  The parent ID to start the tree from (null for root level)
     * @param  int  $depth  The current depth level (used internally for recursion)
     * @return \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy> The flat collection of taxonomies with depth information
     */
    public function flatTree(string|TaxonomyType|null $type = null, ?int $parentId = null, int $depth = 0): Collection
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $cacheKey = "taxonomy_flat_tree_{$typeValue}_{$parentId}_{$depth}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($type, $parentId, $depth) {
            $modelClass = $this->getModelClass();

            return $modelClass::flatTree($type, $parentId, $depth);
        });
    }

    /**
     * Get all available taxonomy types.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function getTypes(): \Illuminate\Support\Collection
    {
        /** @var array<int, string> $typesArray */
        $typesArray = config('taxonomy.types', TaxonomyType::values());

        return collect($typesArray);
    }

    /**
     * Get nested tree using nested set (more efficient).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy>
     */
    public function getNestedTree(string|TaxonomyType|null $type = null): Collection
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $cacheKey = "taxonomy_nested_tree_{$typeValue}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($type) {
            $modelClass = $this->getModelClass();

            return $modelClass::getNestedTree($type);
        });
    }

    /**
     * Rebuild nested set for a specific type.
     */
    public function rebuildNestedSet(string|TaxonomyType $type): void
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $modelClass = $this->getModelClass();
        $modelClass::rebuildNestedSet($typeValue);

        // Clear related caches
        $this->clearCacheForTypeInternal($typeValue);
    }

    /**
     * Move taxonomy to a new parent.
     */
    public function moveToParent(int $taxonomyId, ?int $parentId): bool
    {
        $modelClass = $this->getModelClass();
        $taxonomy = $modelClass::find($taxonomyId);
        if (! $taxonomy) {
            return false;
        }

        // Validate parent exists if parentId is provided
        if ($parentId !== null && ! $modelClass::find($parentId)) {
            return false;
        }

        $taxonomy->moveToParent($parentId);

        // Clear related caches
        $this->clearCacheForTypeInternal($taxonomy->type);

        return true;
    }

    /**
     * Get all descendants of a taxonomy.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy>
     */
    public function getDescendants(int $taxonomyId): Collection
    {
        $modelClass = $this->getModelClass();
        $taxonomy = $modelClass::find($taxonomyId);
        if (! $taxonomy) {
            return new Collection;
        }

        return $taxonomy->getDescendants();
    }

    /**
     * Get all ancestors of a taxonomy.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy>
     */
    public function getAncestors(int $taxonomyId): Collection
    {
        $modelClass = $this->getModelClass();
        $taxonomy = $modelClass::find($taxonomyId);
        if (! $taxonomy) {
            return new Collection;
        }

        return $taxonomy->getAncestors();
    }

    /**
     * Clear cache for a specific type (public method).
     */
    public function clearCacheForType(string|TaxonomyType $type): void
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $this->clearCacheForTypeInternal($typeValue);
    }

    /**
     * Clear cache for a specific type (internal method).
     */
    protected function clearCacheForTypeInternal(string $type): void
    {
        $patterns = [
            "taxonomy_tree_{$type}_*",
            "taxonomy_flat_tree_{$type}_*",
            "taxonomy_nested_tree_{$type}",
        ];

        // For exact cache keys, use forget directly
        Cache::forget("taxonomy_nested_tree_{$type}");

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Check if a taxonomy with the given slug exists.
     */
    public function exists(string $slug, string|TaxonomyType|null $type = null): bool
    {
        $modelClass = $this->getModelClass();

        return $modelClass::where('slug', $slug)
            ->when($type, function ($query) use ($type) {
                $typeValue = $type instanceof TaxonomyType ? $type->value : $type;

                return $query->where('type', $typeValue);
            })
            ->exists();
    }

    /**
     * Find a taxonomy by its ID.
     */
    public function find(int $id): ?Taxonomy
    {
        $modelClass = $this->getModelClass();

        return $modelClass::find($id);
    }

    /**
     * Find multiple taxonomies by their IDs.
     *
     * @param  array<int, int>  $ids  The taxonomy IDs
     * @param  int|null  $perPage  Number of items per page (null for no pagination)
     * @param  int  $page  The page number
     * @return \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy>|\Illuminate\Pagination\LengthAwarePaginator<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy> Collection of taxonomies or paginator
     */
    public function findMany(array $ids, ?int $perPage = null, int $page = 1): Collection|LengthAwarePaginator
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::whereIn('id', $ids);

        if ($perPage) {
            return $query->paginate($perPage, ['*'], 'page', $page);
        }

        return $query->get();
    }

    /**
     * Find taxonomies by type.
     *
     * @param  string|\Aliziodev\LaravelTaxonomy\Enums\TaxonomyType  $type  The taxonomy type
     * @param  int|null  $perPage  Number of items per page (null for no pagination)
     * @param  int  $page  The page number
     * @return \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy>|\Illuminate\Pagination\LengthAwarePaginator<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy> Collection of taxonomies or paginator
     */
    public function findByType(string|TaxonomyType $type, ?int $perPage = null, int $page = 1): Collection|LengthAwarePaginator
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::type($type)->ordered();

        if ($perPage) {
            return $query->paginate($perPage, ['*'], 'page', $page);
        }

        return $query->get();
    }

    /**
     * Find taxonomies by parent ID.
     *
     * @param  int|null  $parentId  The parent ID (null for root level)
     * @param  int|null  $perPage  Number of items per page (null for no pagination)
     * @param  int  $page  The page number
     * @return \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy>|\Illuminate\Pagination\LengthAwarePaginator<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy> Collection of taxonomies or paginator
     */
    public function findByParent(?int $parentId = null, ?int $perPage = null, int $page = 1): Collection|LengthAwarePaginator
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::where('parent_id', $parentId)->ordered();

        if ($perPage) {
            return $query->paginate($perPage, ['*'], 'page', $page);
        }

        return $query->get();
    }

    /**
     * Search for taxonomies by name or description.
     *
     * @param  string  $term  The search term
     * @param  string|\Aliziodev\LaravelTaxonomy\Enums\TaxonomyType|null  $type  The taxonomy type to filter by (optional)
     * @param  int|null  $perPage  Number of items per page (null for no pagination)
     * @param  int  $page  The page number
     * @return \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy>|\Illuminate\Pagination\LengthAwarePaginator<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy> Collection of taxonomies or paginator
     */
    public function search(string $term, string|TaxonomyType|null $type = null, ?int $perPage = null, int $page = 1): Collection|LengthAwarePaginator
    {
        $modelClass = $this->getModelClass();
        $query = $modelClass::query()
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            })
            ->when($type, function ($query) use ($type) {
                $typeValue = $type instanceof TaxonomyType ? $type->value : $type;

                return $query->where('type', $typeValue);
            })
            ->ordered();

        if ($perPage) {
            return $query->paginate($perPage, ['*'], 'page', $page);
        }

        return $query->get();
    }
}
