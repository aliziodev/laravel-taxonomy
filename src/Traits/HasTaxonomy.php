<?php

namespace Aliziodev\LaravelTaxonomy\Traits;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * HasTaxonomy trait provides taxonomy functionality to Eloquent models.
 *
 * This trait allows models to be associated with taxonomies (categories, tags, etc.)
 * and provides methods for attaching, detaching, and querying taxonomies.
 */
trait HasTaxonomy
{
    /**
     * Boot the trait.
     * 
     * This method is called when the trait is booted.
     * It sets up event listeners for the model.
     */
    public static function bootHasTaxonomy(): void
    {
        static::deleting(function ($model) {
            // If the model is being force deleted, detach all taxonomies
            if (!method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->taxonomies()->detach();
            }
        });
    }

    /**
     * Get all taxonomies for this model.
     * 
     * @param string $name The name of the relationship (default: 'taxonomable')
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany The taxonomy relationship
     */
    public function taxonomies(string $name = 'taxonomable'): MorphToMany
    {
        return $this->morphToMany(
            config('taxonomy.model', Taxonomy::class),
            $name,
            config('taxonomy.table_names.taxonomables', 'taxonomables'),
            $name . '_id',
            'taxonomy_id'
        )->withTimestamps();
    }

    /**
     * Get all taxonomies of a specific type for this model.
     * 
     * @param string|\Aliziodev\LaravelTaxonomy\Enums\TaxonomyType $type The taxonomy type
     * @param string $name The name of the relationship (default: 'taxonomable')
     * @return \Illuminate\Database\Eloquent\Collection Collection of taxonomies
     */
    public function taxonomiesOfType(string|TaxonomyType $type, string $name = 'taxonomable'): Collection
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        return $this->taxonomies($name)->where('type', $typeValue)->get();
    }

    /**
     * Attach taxonomies to the model.
     *
     * @param int|string|array|\Aliziodev\LaravelTaxonomy\Models\Taxonomy|\Illuminate\Database\Eloquent\Collection $taxonomies The taxonomies to attach
     * @param string $name The name of the relationship (default: 'taxonomable')
     * @return $this
     */
    public function attachTaxonomies($taxonomies, string $name = 'taxonomable'): self
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);
        $this->taxonomies($name)->syncWithoutDetaching($taxonomyIds);
        return $this;
    }

    /**
     * Detach taxonomies from the model.
     *
     * @param int|string|array|\Aliziodev\LaravelTaxonomy\Models\Taxonomy|\Illuminate\Database\Eloquent\Collection|null $taxonomies The taxonomies to detach (null to detach all)
     * @param string $name The name of the relationship (default: 'taxonomable')
     * @return $this
     */
    public function detachTaxonomies($taxonomies = null, string $name = 'taxonomable'): self
    {
        if (is_null($taxonomies)) {
            $this->taxonomies($name)->detach();
            return $this;
        }

        $taxonomyIds = $this->getTaxonomyIds($taxonomies);
        $this->taxonomies($name)->detach($taxonomyIds);
        return $this;
    }

    /**
     * Sync taxonomies with the model.
     *
     * @param int|string|array|Taxonomy|Collection $taxonomies
     */
    public function syncTaxonomies($taxonomies, string $name = 'taxonomable'): self
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);
        $this->taxonomies($name)->sync($taxonomyIds);
        return $this;
    }

    /**
     * Toggle taxonomies for the model.
     *
     * @param int|string|array|Taxonomy|Collection $taxonomies
     */
    public function toggleTaxonomies($taxonomies, string $name = 'taxonomable'): self
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);
        $this->taxonomies($name)->toggle($taxonomyIds);
        return $this;
    }

    /**
     * Determine if the model has any of the given taxonomies.
     *
     * @param int|string|array|\Aliziodev\LaravelTaxonomy\Models\Taxonomy|\Illuminate\Database\Eloquent\Collection $taxonomies The taxonomies to check
     * @param string $name The name of the relationship (default: 'taxonomable')
     * @return bool True if the model has any of the given taxonomies
     */
    public function hasTaxonomies($taxonomies, string $name = 'taxonomable'): bool
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);
        return $this->taxonomies($name)->whereIn('taxonomies.id', $taxonomyIds)->exists();
    }

    /**
     * Determine if the model has all of the given taxonomies.
     *
     * @param int|string|array|Taxonomy|Collection $taxonomies
     */
    public function hasAllTaxonomies($taxonomies, string $name = 'taxonomable'): bool
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);
        return $this->taxonomies($name)->whereIn('taxonomies.id', $taxonomyIds)->count() === count($taxonomyIds);
    }

    /**
     * Determine if the model has any taxonomies of the given type.
     */
    public function hasTaxonomyType(string|TaxonomyType $type, string $name = 'taxonomable'): bool
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        return $this->taxonomies($name)->where('type', $typeValue)->exists();
    }

    /**
     * Scope a query to include models that have any of the given taxonomies.
     *
     * @param Builder $query
     * @param int|string|array|Taxonomy|Collection $taxonomies
     */
    public function scopeWithAnyTaxonomies(Builder $query, $taxonomies, string $name = 'taxonomable'): Builder
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        return $query->whereHas('taxonomies', function ($query) use ($taxonomyIds, $name) {
            $query->whereIn('taxonomies.id', $taxonomyIds);
        });
    }

    /**
     * Scope a query to include models that have all of the given taxonomies.
     *
     * @param Builder $query
     * @param int|string|array|Taxonomy|Collection $taxonomies
     */
    public function scopeWithAllTaxonomies(Builder $query, $taxonomies, string $name = 'taxonomable'): Builder
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        foreach ($taxonomyIds as $taxonomyId) {
            $query->whereHas('taxonomies', function ($query) use ($taxonomyId, $name) {
                $query->where('taxonomies.id', $taxonomyId);
            });
        }

        return $query;
    }

    /**
     * Scope a query to include models that have taxonomies of the given type.
     */
    public function scopeWithTaxonomyType(Builder $query, string|TaxonomyType $type, string $name = 'taxonomable'): Builder
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;

        return $query->whereHas('taxonomies', function ($query) use ($typeValue, $name) {
            $query->where('type', $typeValue);
        });
    }

    /**
     * Get the taxonomy IDs from the given taxonomies.
     *
     * @param int|string|array|Taxonomy|Collection $taxonomies
     * @return array
     */
    protected function getTaxonomyIds($taxonomies): array
    {
        if (is_null($taxonomies)) {
            return [];
        }

        if (is_numeric($taxonomies) || is_string($taxonomies)) {
            return [$taxonomies];
        }

        if ($taxonomies instanceof Taxonomy) {
            return [$taxonomies->id];
        }

        if (is_array($taxonomies)) {
            return array_map(function ($taxonomy) {
                return $taxonomy instanceof Taxonomy ? $taxonomy->id : $taxonomy;
            }, $taxonomies);
        }

        if ($taxonomies instanceof Collection) {
            return $taxonomies->map(function ($taxonomy) {
                return $taxonomy instanceof Taxonomy ? $taxonomy->id : $taxonomy;
            })->toArray();
        }

        return [];
    }

    /**
     * Get hierarchical taxonomies for this model including descendants.
     *
     * @param string|TaxonomyType|null $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getHierarchicalTaxonomies(string|TaxonomyType|null $type = null): \Illuminate\Database\Eloquent\Collection
    {
        $taxonomies = $type ? $this->taxonomiesOfType($type) : $this->taxonomies()->get();
        $hierarchical = new \Illuminate\Database\Eloquent\Collection();

        foreach ($taxonomies as $taxonomy) {
            // Add the taxonomy itself
            $hierarchical->push($taxonomy);
            
            // Add all its descendants
            $descendants = $taxonomy->getDescendants();
            foreach ($descendants as $descendant) {
                $hierarchical->push($descendant);
            }
        }

        return $hierarchical->unique('id');
    }

    /**
     * Get all ancestor taxonomies for this model's taxonomies.
     *
     * @param string|TaxonomyType|null $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAncestorTaxonomies(string|TaxonomyType|null $type = null): \Illuminate\Database\Eloquent\Collection
    {
        $taxonomies = $type ? $this->taxonomiesOfType($type) : $this->taxonomies()->get();
        $ancestors = new \Illuminate\Database\Eloquent\Collection();

        foreach ($taxonomies as $taxonomy) {
            // Add all its ancestors
            $taxonomyAncestors = $taxonomy->getAncestors();
            foreach ($taxonomyAncestors as $ancestor) {
                $ancestors->push($ancestor);
            }
        }

        return $ancestors->unique('id');
    }

    /**
     * Scope to get models that have taxonomies within a specific hierarchy.
     *
     * @param Builder $query
     * @param int $taxonomyId
     * @param bool $includeDescendants
     * @return Builder
     */
    public function scopeWithTaxonomyHierarchy(Builder $query, int $taxonomyId, bool $includeDescendants = true): Builder
    {
        $taxonomy = Taxonomy::find($taxonomyId);
        
        if (!$taxonomy) {
            return $query->whereRaw('1 = 0'); // Return empty result
        }

        $taxonomyIds = collect([$taxonomyId]);
        
        if ($includeDescendants) {
            $descendants = $taxonomy->getDescendants();
            $taxonomyIds = $taxonomyIds->merge($descendants->pluck('id'));
        }

        return $query->whereHas('taxonomies', function ($q) use ($taxonomyIds) {
            $q->whereIn('taxonomies.id', $taxonomyIds->toArray());
        });
    }

    /**
     * Scope to get models that have taxonomies at a specific depth level.
     *
     * @param Builder $query
     * @param int $depth
     * @param string|TaxonomyType|null $type
     * @return Builder
     */
    public function scopeWithTaxonomyAtDepth(Builder $query, int $depth, string|TaxonomyType|null $type = null): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($depth, $type) {
            $q->atDepth($depth);
            
            if ($type) {
                $q->where('taxonomies.type', $type instanceof TaxonomyType ? $type->value : $type);
            }
        });
    }

    /**
     * Check if this model has any taxonomy that is an ancestor of the given taxonomy.
     *
     * @param int $taxonomyId
     * @return bool
     */
    public function hasAncestorTaxonomy(int $taxonomyId): bool
    {
        $taxonomy = Taxonomy::find($taxonomyId);
        
        if (!$taxonomy) {
            return false;
        }

        $ancestors = $taxonomy->getAncestors();
        $modelTaxonomyIds = $this->taxonomies->pluck('id');

        return $ancestors->pluck('id')->intersect($modelTaxonomyIds)->isNotEmpty();
    }

    /**
     * Check if this model has any taxonomy that is a descendant of the given taxonomy.
     *
     * @param int $taxonomyId
     * @return bool
     */
    public function hasDescendantTaxonomy(int $taxonomyId): bool
    {
        $taxonomy = Taxonomy::find($taxonomyId);
        
        if (!$taxonomy) {
            return false;
        }

        // Check if any of the model's taxonomies are descendants of the given taxonomy
        $modelTaxonomyIds = $this->taxonomies->pluck('id');
        
        foreach ($modelTaxonomyIds as $modelTaxonomyId) {
            $modelTaxonomy = Taxonomy::find($modelTaxonomyId);
            if ($modelTaxonomy && $modelTaxonomy->isDescendantOf($taxonomy)) {
                return true;
            }
        }
        
        return false;
    }
}
