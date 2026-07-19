<?php

namespace Aliziodev\LaravelTaxonomy\Traits;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection as SupportCollection;
use Traversable;

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
            if (! method_exists($model, 'isForceDeleting') || ! is_object($model) || $model->isForceDeleting()) {
                $model->taxonomies()->detach();
            }
        });
    }

    /**
     * Get all taxonomies for this model.
     *
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return MorphToMany<Taxonomy, $this> The taxonomy relationship
     */
    public function taxonomies(string $name = 'taxonomable'): MorphToMany
    {
        static::warnAboutRelationshipName($name);

        /** @var class-string<Taxonomy> $related */
        $related = config('taxonomy.model', Taxonomy::class);

        return $this->morphToMany(
            $related,
            $name,
            config('taxonomy.table_names.taxonomables', 'taxonomables'),
            $name . '_id',
            'taxonomy_id'
        )->withTimestamps();
    }

    /**
     * Emit a deprecation notice when a non-default relationship name is used.
     *
     * Deduplicated per name so a loop cannot flood the log.
     *
     * @internal
     */
    protected static function warnAboutRelationshipName(string $name): void
    {
        if ($name === 'taxonomable') {
            return;
        }

        static $warned = [];

        if (isset($warned[$name])) {
            return;
        }

        $warned[$name] = true;

        trigger_error(
            "Passing a custom relationship name ('{$name}') to the taxonomy methods is deprecated "
            . "and will be removed in 3.0. It selects the pivot morph columns '{$name}_id' and "
            . "'{$name}_type', which the package's migration does not create, and Eloquent resolves "
            . 'relationships statically so query scopes cannot honour it at all. '
            . 'Use taxonomy types to separate categories from tags.',
            E_USER_DEPRECATED
        );
    }

    /**
     * Resolve the configured taxonomy model class.
     *
     * @return class-string<Taxonomy>
     */
    protected static function taxonomyModelClass(): string
    {
        return config('taxonomy.model', Taxonomy::class);
    }

    /**
     * Resolve the taxonomies table name.
     *
     * Queries must not hard-code 'taxonomies': the table is configurable via
     * `taxonomy.table_names.taxonomies`, and a custom model may override it.
     */
    protected static function taxonomyTable(): string
    {
        // Deliberately not memoised in a static: the configured table (and the
        // configured model) can change between requests and between tests, and
        // a cached value would silently keep pointing at the old table.
        $modelClass = static::taxonomyModelClass();

        return (new $modelClass)->getTable();
    }

    /**
     * Resolve the taxonomables pivot table name.
     */
    protected static function taxonomyPivotTable(): string
    {
        return config('taxonomy.table_names.taxonomables', 'taxonomables');
    }

    /**
     * Reduce a set of taxonomies to the IDs that actually belong to $type.
     *
     * Extracted from six identical copies that previously lived in the
     * *OfType methods.
     *
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>|null  $taxonomies
     * @return array<int, int|string>
     */
    protected function resolveTaxonomyIdsOfType(string|TaxonomyType $type, $taxonomies): array
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        if (empty($taxonomyIds)) {
            return [];
        }

        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $modelClass = static::taxonomyModelClass();
        $instance = new $modelClass;

        // This lookup answers one question: of the IDs the caller handed us,
        // which are of this type? Running it through the application's global
        // scopes answers a different one, and in multi-tenant setups it
        // silently dropped taxonomies the caller had explicitly selected --
        // shared rows visible to every tenant were the usual casualty.
        //
        // syncTaxonomies() already attaches whatever IDs it is given without
        // re-filtering, so scoping only the *OfType variants was inconsistent
        // as well. Validating input IDs remains the application's job.
        $query = $modelClass::query()->withoutGlobalScopes();

        // withoutGlobalScopes() also drops SoftDeletingScope, so trashed rows
        // are excluded explicitly rather than becoming attachable.
        if ($instance instanceof Model && method_exists($instance, 'getDeletedAtColumn')) {
            $query->whereNull($instance->getQualifiedDeletedAtColumn());
        }

        return $query->whereIn($instance->getQualifiedKeyName(), $taxonomyIds)
            ->where('type', $typeValue)
            ->pluck($instance->getKeyName())
            ->all();
    }

    /**
     * Get all taxonomies of a specific type for this model.
     *
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Collection<int, Taxonomy> Collection of taxonomies
     */
    public function taxonomiesOfType(string|TaxonomyType $type, string $name = 'taxonomable'): Collection
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;

        return $this->taxonomies($name)->where('type', $typeValue)->get();
    }

    /**
     * Attach taxonomies to the model.
     *
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to attach
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
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
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>|null  $taxonomies  The taxonomies to detach (null to detach all)
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
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
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return $this
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
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return $this
     */
    public function toggleTaxonomies($taxonomies, string $name = 'taxonomable'): self
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);
        $this->taxonomies($name)->toggle($taxonomyIds);

        return $this;
    }

    /**
     * Detach taxonomies of a specific type from the model.
     *
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>|null  $taxonomies  The taxonomies to detach (null to detach all of this type)
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return $this
     */
    public function detachTaxonomiesOfType(string|TaxonomyType $type, $taxonomies = null, string $name = 'taxonomable'): self
    {
        if (is_null($taxonomies)) {
            // Detach all taxonomies of this type
            $taxonomyIds = $this->taxonomiesOfType($type, $name)->pluck('id')->all();
        } else {
            $taxonomyIds = $this->resolveTaxonomyIdsOfType($type, $taxonomies);
        }

        if (! empty($taxonomyIds)) {
            $this->taxonomies($name)->detach($taxonomyIds);
        }

        return $this;
    }

    /**
     * Sync taxonomies of a specific type with the model.
     *
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to sync
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return $this
     */
    public function syncTaxonomiesOfType(string|TaxonomyType $type, $taxonomies, string $name = 'taxonomable'): self
    {
        $validTaxonomyIds = $this->resolveTaxonomyIdsOfType($type, $taxonomies);

        // Get current taxonomies of this type
        $currentTaxonomiesOfType = $this->taxonomiesOfType($type, $name)->pluck('id')->toArray();

        // Detach current taxonomies of this type
        if (! empty($currentTaxonomiesOfType)) {
            $this->taxonomies($name)->detach($currentTaxonomiesOfType);
        }

        // Attach new taxonomies of this type
        if (! empty($validTaxonomyIds)) {
            $this->taxonomies($name)->attach($validTaxonomyIds);
        }

        return $this;
    }

    /**
     * Attach taxonomies of a specific type to the model.
     *
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to attach
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return $this
     */
    public function attachTaxonomiesOfType(string|TaxonomyType $type, $taxonomies, string $name = 'taxonomable'): self
    {
        $validTaxonomyIds = $this->resolveTaxonomyIdsOfType($type, $taxonomies);

        if (! empty($validTaxonomyIds)) {
            $this->taxonomies($name)->syncWithoutDetaching($validTaxonomyIds);
        }

        return $this;
    }

    /**
     * Toggle taxonomies of a specific type for the model.
     *
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to toggle
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return $this
     */
    public function toggleTaxonomiesOfType(string|TaxonomyType $type, $taxonomies, string $name = 'taxonomable'): self
    {
        $validTaxonomyIds = $this->resolveTaxonomyIdsOfType($type, $taxonomies);

        if (! empty($validTaxonomyIds)) {
            $this->taxonomies($name)->toggle($validTaxonomyIds);
        }

        return $this;
    }

    /**
     * Determine if the model has any of the given taxonomies.
     *
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to check
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return bool True if the model has any of the given taxonomies
     */
    public function hasTaxonomies($taxonomies, string $name = 'taxonomable'): bool
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        return $this->taxonomies($name)->whereIn(static::taxonomyTable() . '.id', $taxonomyIds)->exists();
    }

    /**
     * Determine if the model has all of the given taxonomies.
     *
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     */
    public function hasAllTaxonomies($taxonomies, string $name = 'taxonomable'): bool
    {
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        return $this->taxonomies($name)->whereIn(static::taxonomyTable() . '.id', $taxonomyIds)->count() === count($taxonomyIds);
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
     * Determine if the model has any of the given taxonomies of a specific type.
     *
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to check
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return bool True if the model has any of the given taxonomies of the specified type
     */
    public function hasTaxonomiesOfType(string|TaxonomyType $type, $taxonomies, string $name = 'taxonomable'): bool
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        return $this->taxonomies($name)
            ->where('type', $typeValue)
            ->whereIn(static::taxonomyTable() . '.id', $taxonomyIds)
            ->exists();
    }

    /**
     * Determine if the model has all of the given taxonomies of a specific type.
     *
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to check
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return bool True if the model has all of the given taxonomies of the specified type
     */
    public function hasAllTaxonomiesOfType(string|TaxonomyType $type, $taxonomies, string $name = 'taxonomable'): bool
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $validTaxonomyIds = $this->resolveTaxonomyIdsOfType($type, $taxonomies);

        if (empty($validTaxonomyIds)) {
            return false;
        }

        return $this->taxonomies($name)
            ->where('type', $typeValue)
            ->whereIn(static::taxonomyTable() . '.id', $validTaxonomyIds)
            ->count() === count($validTaxonomyIds);
    }

    /**
     * Scope a query to include models that have any of the given taxonomies.
     *
     * @param  Builder<$this>  $query
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeWithAnyTaxonomies(Builder $query, $taxonomies, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        return $query->whereHas('taxonomies', function ($query) use ($taxonomyIds) {
            $query->whereIn(static::taxonomyTable() . '.id', $taxonomyIds);
        });
    }

    /**
     * Scope a query to include models that have all of the given taxonomies.
     *
     * @param  Builder<$this>  $query
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeWithAllTaxonomies(Builder $query, $taxonomies, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        if (empty($taxonomyIds)) {
            return $query;
        }

        // One counted subquery instead of one subquery per taxonomy.
        // getTaxonomyIds() de-duplicates, so the count is exact.
        return $query->whereHas('taxonomies', function ($query) use ($taxonomyIds) {
            $query->whereIn(static::taxonomyTable() . '.id', $taxonomyIds);
        }, '=', count($taxonomyIds));
    }

    /**
     * Scope a query to include models that have taxonomies of the given type.
     *
     * @param  Builder<$this>  $query
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeWithTaxonomyType(Builder $query, string|TaxonomyType $type, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;

        return $query->whereHas('taxonomies', function ($query) use ($typeValue) {
            $query->where('type', $typeValue);
        });
    }

    /**
     * Scope a query to include models that have specific taxonomies.
     *
     * @param  Builder<$this>  $query
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeWithTaxonomy(Builder $query, $taxonomies, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        return $query->whereHas('taxonomies', function ($query) use ($taxonomyIds) {
            $query->whereIn(static::taxonomyTable() . '.id', $taxonomyIds);
        });
    }

    /**
     * Scope a query to exclude models that have specific taxonomies.
     *
     * @param  Builder<$this>  $query
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeWithoutTaxonomies(Builder $query, $taxonomies, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        return $query->whereDoesntHave('taxonomies', function ($query) use ($taxonomyIds) {
            $query->whereIn(static::taxonomyTable() . '.id', $taxonomyIds);
        });
    }

    /**
     * Scope a query to include models that have any of the given taxonomies of a specific type.
     *
     * @param  Builder<$this>  $query
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to filter by
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeWithAnyTaxonomiesOfType(Builder $query, string|TaxonomyType $type, $taxonomies, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        return $query->whereHas('taxonomies', function ($query) use ($typeValue, $taxonomyIds) {
            $query->where('type', $typeValue)
                ->whereIn(static::taxonomyTable() . '.id', $taxonomyIds);
        });
    }

    /**
     * Scope a query to include models that have all of the given taxonomies of a specific type.
     *
     * @param  Builder<$this>  $query
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to filter by
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeWithAllTaxonomiesOfType(Builder $query, string|TaxonomyType $type, $taxonomies, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $validTaxonomyIds = $this->resolveTaxonomyIdsOfType($type, $taxonomies);

        if (empty($validTaxonomyIds)) {
            return $query;
        }

        // One counted subquery instead of one subquery per taxonomy. The IDs are
        // distinct, so matching all of them is equivalent to the previous
        // chain of whereHas calls.
        return $query->whereHas('taxonomies', function ($query) use ($typeValue, $validTaxonomyIds) {
            $query->where('type', $typeValue)
                ->whereIn(static::taxonomyTable() . '.id', $validTaxonomyIds);
        }, '=', count($validTaxonomyIds));
    }

    /**
     * Scope a query to exclude models that have any of the given taxonomies of a specific type.
     *
     * @param  Builder<$this>  $query
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>  $taxonomies  The taxonomies to exclude
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeWithoutTaxonomiesOfType(Builder $query, string|TaxonomyType $type, $taxonomies, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $taxonomyIds = $this->getTaxonomyIds($taxonomies);

        return $query->whereDoesntHave('taxonomies', function ($query) use ($typeValue, $taxonomyIds) {
            $query->where('type', $typeValue)
                ->whereIn(static::taxonomyTable() . '.id', $taxonomyIds);
        });
    }

    /**
     * Filter models by multiple taxonomy criteria.
     *
     * @param  Builder<$this>  $query
     * @param  array<string, mixed>  $filters
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeFilterByTaxonomies(Builder $query, array $filters, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        foreach ($filters as $key => $value) {
            if ($key === 'exclude' && ! empty($value)) {
                $query->withoutTaxonomies($value, $name);
            } elseif ($key === 'include' && ! empty($value)) {
                $query->withTaxonomy($value, $name);
            } elseif (! empty($value)) {
                // Handle specific taxonomy types
                if (is_array($value)) {
                    // For array values, use OR logic within the same type
                    $query->where(function ($subQuery) use ($key, $value) {
                        foreach ($value as $val) {
                            $subQuery->orWhereHas('taxonomies', function ($q) use ($key, $val) {
                                $q->where('type', $key)->where('slug', $val);
                            });
                        }
                    });
                } elseif (is_string($value)) {
                    $query->withTaxonomySlug($value, $key, $name);
                } else {
                    $query->withTaxonomy($value, $name);
                }
            }
        }

        return $query;
    }

    /**
     * Scope a query to include models that have taxonomy with the given slug.
     *
     * @param  Builder<$this>  $query
     * @param  string  $slug  The taxonomy slug
     * @param  string|TaxonomyType|null  $type  Optional taxonomy type filter
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Builder<$this>
     */
    public function scopeWithTaxonomySlug(Builder $query, string $slug, string|TaxonomyType|null $type = null, string $name = 'taxonomable'): Builder
    {
        static::warnAboutRelationshipName($name);

        return $query->whereHas('taxonomies', function ($q) use ($slug, $type) {
            $q->where('slug', $slug);

            if ($type) {
                $q->where('type', $type instanceof TaxonomyType ? $type->value : $type);
            }
        });
    }

    /**
     * Get the taxonomy IDs from the given taxonomies.
     *
     * @param  int|string|array<int, int|string|Taxonomy>|Taxonomy|Collection<int, Taxonomy>|null  $taxonomies
     * @return array<int, int|string>
     */
    protected function getTaxonomyIds($taxonomies): array
    {
        if (is_null($taxonomies)) {
            return [];
        }

        if (is_numeric($taxonomies) || is_string($taxonomies)) {
            return [$taxonomies];
        }

        if ($taxonomies instanceof Model) {
            return [$taxonomies->getKey()];
        }

        // Accept any collection type, not just Eloquent ones. Previously a
        // Support\Collection (what pluck()/collect() return) fell through to
        // the empty array below, so attach/sync silently did nothing.
        // Support\Collection is checked first because it is also Arrayable and
        // Traversable, and only ->all() preserves the model instances.
        if ($taxonomies instanceof SupportCollection) {
            $taxonomies = $taxonomies->all();
        } elseif ($taxonomies instanceof Traversable) {
            $taxonomies = iterator_to_array($taxonomies);
        } elseif ($taxonomies instanceof Arrayable) {
            $taxonomies = $taxonomies->toArray();
        }

        if (is_array($taxonomies)) {
            return array_values(array_unique(array_map(function ($taxonomy) {
                return $taxonomy instanceof Model ? $taxonomy->getKey() : $taxonomy;
            }, $taxonomies), SORT_REGULAR));
        }

        return [];
    }

    /**
     * Get hierarchical taxonomies for this model including descendants.
     *
     * @return Collection<int, Taxonomy>
     */
    public function getHierarchicalTaxonomies(string|TaxonomyType|null $type = null): Collection
    {
        $taxonomies = $type ? $this->taxonomiesOfType($type) : $this->taxonomies()->get();
        $hierarchical = new Collection;

        foreach ($taxonomies as $taxonomy) {
            // Add the taxonomy itself
            $hierarchical->push($taxonomy);

            // Add all its descendants
            $descendants = $taxonomy->descendants();
            foreach ($descendants as $descendant) {
                $hierarchical->push($descendant);
            }
        }

        return $hierarchical->unique('id');
    }

    /**
     * Get all ancestor taxonomies for this model's taxonomies.
     *
     * @return Collection<int, Taxonomy>
     */
    public function getAncestorTaxonomies(string|TaxonomyType|null $type = null): Collection
    {
        $taxonomies = $type ? $this->taxonomiesOfType($type) : $this->taxonomies()->get();
        $ancestors = new Collection;

        foreach ($taxonomies as $taxonomy) {
            // Add all its ancestors
            $taxonomyAncestors = $taxonomy->ancestors();
            foreach ($taxonomyAncestors as $ancestor) {
                $ancestors->push($ancestor);
            }
        }

        return $ancestors->unique('id');
    }

    /**
     * Scope to get models that have taxonomies within a specific hierarchy.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWithTaxonomyHierarchy(Builder $query, int $taxonomyId, bool $includeDescendants = true): Builder
    {
        /** @var class-string<Taxonomy> $modelClass */
        $modelClass = config('taxonomy.model', Taxonomy::class);
        $taxonomy = $modelClass::find($taxonomyId);

        if (! $taxonomy) {
            return $query->whereRaw('1 = 0'); // Return empty result
        }

        $taxonomyIds = collect([$taxonomyId]);

        if ($includeDescendants) {
            $descendants = $taxonomy->descendants();
            $taxonomyIds = $taxonomyIds->merge($descendants->pluck('id'));
        }

        return $query->whereHas('taxonomies', function ($q) use ($taxonomyIds) {
            $q->whereIn(static::taxonomyTable() . '.id', $taxonomyIds->toArray());
        });
    }

    /**
     * Scope to get models that have taxonomies at a specific depth level.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWithTaxonomyAtDepth(Builder $query, int $depth, string|TaxonomyType|null $type = null): Builder
    {
        return $query->whereHas('taxonomies', function ($q) use ($depth, $type) {
            $q->where(static::taxonomyTable() . '.depth', $depth);

            if ($type) {
                $q->where(static::taxonomyTable() . '.type', $type instanceof TaxonomyType ? $type->value : $type);
            }
        });
    }

    /**
     * Check if this model has any taxonomy that is an ancestor of the given taxonomy.
     */
    public function hasAncestorTaxonomy(int $taxonomyId): bool
    {
        /** @var class-string<Taxonomy> $modelClass */
        $modelClass = config('taxonomy.model', Taxonomy::class);
        $taxonomy = $modelClass::find($taxonomyId);

        if (! $taxonomy) {
            return false;
        }

        $ancestors = $taxonomy->ancestors();
        $modelTaxonomyIds = $this->taxonomies->pluck('id');

        return $ancestors->pluck('id')->intersect($modelTaxonomyIds)->isNotEmpty();
    }

    /**
     * Check if this model has any taxonomy that is a descendant of the given taxonomy.
     */
    public function hasDescendantTaxonomy(int $taxonomyId): bool
    {
        /** @var class-string<Taxonomy> $modelClass */
        $modelClass = config('taxonomy.model', Taxonomy::class);
        $taxonomy = $modelClass::find($taxonomyId);

        if (! $taxonomy) {
            return false;
        }

        // Check if any of the model's taxonomies are descendants of the given taxonomy
        $modelTaxonomyIds = $this->taxonomies->pluck('id');
        $descendants = $taxonomy->descendants();
        $descendantIds = $descendants->pluck('id');

        return $modelTaxonomyIds->intersect($descendantIds)->isNotEmpty();
    }

    /**
     * Get the count of taxonomies of a specific type for this model.
     *
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return int The count of taxonomies of the specified type
     */
    public function getTaxonomyCountByType(string|TaxonomyType $type, string $name = 'taxonomable'): int
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;

        return $this->taxonomies($name)->where('type', $typeValue)->count();
    }

    /**
     * Get the first taxonomy of a specific type for this model.
     *
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  string  $name  The relationship name. DEPRECATED since 2.11.0, removed in 3.0:
     *                        only the default 'taxonomable' works on the shipped schema.
     * @return Taxonomy|null The first taxonomy of the specified type or null if none found
     */
    public function getFirstTaxonomyOfType(string|TaxonomyType $type, string $name = 'taxonomable'): ?Taxonomy
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;

        return $this->taxonomies($name)->where('type', $typeValue)->first();
    }

    /**
     * Scope a query to order models by taxonomy type and optionally by taxonomy name.
     *
     * @param  Builder<$this>  $query
     * @param  string|TaxonomyType  $type  The taxonomy type to order by
     * @param  string  $direction  The sort direction ('asc' or 'desc')
     * @param  string  $orderBy  The field to order by ('name', 'slug', or 'sort_order')
     * @return Builder<$this>
     */
    public function scopeOrderByTaxonomyType(Builder $query, string|TaxonomyType $type, string $direction = 'asc', string $orderBy = 'name'): Builder
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';
        $orderBy = in_array($orderBy, ['name', 'slug', 'sort_order']) ? $orderBy : 'name';

        $pivotTable = static::taxonomyPivotTable();
        $taxonomyTable = static::taxonomyTable();
        $morphType = $this->getMorphClass();

        return $query->join($pivotTable . ' as tax_order', function ($join) use ($morphType) {
            $join->on($this->getTable() . '.' . $this->getKeyName(), '=', 'tax_order.taxonomable_id')
                ->where('tax_order.taxonomable_type', '=', $morphType);
        })
            ->join($taxonomyTable . ' as tax_sort', 'tax_order.taxonomy_id', '=', 'tax_sort.id')
            ->where('tax_sort.type', $typeValue)
            ->orderBy('tax_sort.' . $orderBy, $direction)
            ->select($this->getTable() . '.*')
            ->distinct();
    }
}
