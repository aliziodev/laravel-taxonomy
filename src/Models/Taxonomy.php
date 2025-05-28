<?php

namespace Aliziodev\LaravelTaxonomy\Models;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Exceptions\MissingSlugException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Taxonomy model for managing taxonomies, categories, tags, and hierarchical terms.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $description
 * @property int|null $parent_id
 * @property int $sort_order
 * @property array|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\Aliziodev\LaravelTaxonomy\Models\Taxonomy[] $children
 * @property-read \Aliziodev\LaravelTaxonomy\Models\Taxonomy|null $parent
 * @property-read string $path
 * @property-read string $full_slug
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy type(string|\Aliziodev\LaravelTaxonomy\Enums\TaxonomyType $type)
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy root()
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy ordered()
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy query()
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Taxonomy create(array $attributes)
 */
class Taxonomy extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'parent_id',
        'sort_order',
        'meta',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'meta' => 'json',
        'sort_order' => 'integer',
    ];


    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $taxonomy) {
            // Check if slug is required but not provided
            if (!config('taxonomy.slugs.generate', true) && empty($taxonomy->slug)) {
                throw new MissingSlugException();
            }

            // Generate slug if enabled in config and not provided
            if (config('taxonomy.slugs.generate', true) && empty($taxonomy->slug)) {
                $taxonomy->slug = static::generateUniqueSlug($taxonomy->name, $taxonomy->type);
            }

            // If slug is provided, check if it's unique
            if (!empty($taxonomy->slug) && static::slugExists($taxonomy->slug, null)) {
                throw new DuplicateSlugException($taxonomy->slug);
            }
        });

        static::updating(function (self $taxonomy) {
            // If slug is being changed manually, check if it's unique
            if ($taxonomy->isDirty('slug') && !empty($taxonomy->slug)) {
                if (static::slugExists($taxonomy->slug, $taxonomy->id)) {
                    throw new DuplicateSlugException($taxonomy->slug);
                }
            }

            // Regenerate slug on update if enabled in config
            if (config('taxonomy.slugs.regenerate_on_update', false) && $taxonomy->isDirty('name')) {
                $taxonomy->slug = static::generateUniqueSlug($taxonomy->name, $taxonomy->type, $taxonomy->id);
            }
        });
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('taxonomy.table_names.taxonomies', parent::getTable());
    }

    /**
     * Get the parent taxonomy.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(static::class, 'parent_id');
    }

    /**
     * Get the children taxonomies.
     */
    public function children(): HasMany
    {
        return $this->hasMany(static::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all descendants of the taxonomy.
     */
    public function descendants(): Collection
    {
        $descendants = new Collection();

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->descendants());
        }

        return $descendants;
    }

    /**
     * Get all ancestors of the taxonomy.
     */
    public function ancestors(): Collection
    {
        $ancestors = new Collection();
        $parent = $this->parent;

        while ($parent) {
            $ancestors->push($parent);
            $parent = $parent->parent;
        }

        return $ancestors;
    }

    /**
     * Get the full hierarchy path of the taxonomy.
     */
    public function getPathAttribute(): string
    {
        return $this->ancestors()->reverse()->pluck('name')->push($this->name)->implode(' > ');
    }

    /**
     * Get the full slug path of the taxonomy.
     */
    public function getFullSlugAttribute(): string
    {
        return $this->ancestors()->reverse()->pluck('slug')->push($this->slug)->implode('/');
    }

    /**
     * Get all models that are assigned this taxonomy.
     */
    public function morphedByMany($related, $name, $table = null, $foreignPivotKey = null, $relatedPivotKey = null, $parentKey = null, $relatedKey = null, $relation = null): MorphToMany
    {
        $table = $table ?? config('taxonomy.table_names.taxonomables', 'taxonomables');
        $foreignPivotKey = $foreignPivotKey ?? 'taxonomy_id';
        $relatedPivotKey = $relatedPivotKey ?? $name . '_id';

        return parent::morphedByMany(
            $related,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relation
        );
    }

    /**
     * Scope a query to only include taxonomies of a given type.
     */
    public function scopeType(Builder $query, string|TaxonomyType $type): Builder
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;
        return $query->where('type', $typeValue);
    }

    /**
     * Scope a query to only include root taxonomies (no parent).
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to order taxonomies by sort_order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get a flat tree representation of the taxonomy hierarchy.
     */
    public static function flatTree(string|TaxonomyType|null $type = null, ?int $parentId = null, int $depth = 0): Collection
    {
        $query = static::query();

        if ($type) {
            $query->type($type);
        }

        $query->where('parent_id', $parentId)->ordered();

        $result = new Collection();

        foreach ($query->get() as $taxonomy) {
            $taxonomy->depth = $depth;
            $result->push($taxonomy);
            $result = $result->merge(static::flatTree($type, $taxonomy->id, $depth + 1));
        }

        return $result;
    }

    /**
     * Get a nested tree representation of the taxonomy hierarchy.
     */
    public static function tree(string|TaxonomyType|null $type = null, ?int $parentId = null): Collection
    {
        $query = static::query()->with('children');

        if ($type) {
            $query->type($type);
        }

        return $query->where('parent_id', $parentId)->ordered()->get();
    }

    /**
     * Find a taxonomy by its slug.
     */
    public static function findBySlug(string $slug, string|TaxonomyType|null $type = null): ?self
    {
        $query = static::query()->where('slug', $slug);

        if ($type) {
            $query->type($type);
        }

        return $query->first();
    }

    /**
     * Create a new taxonomy or update if it already exists.
     *
     * @param array $attributes The attributes for the taxonomy
     * @return self The created or updated taxonomy
     * @throws MissingSlugException If slug generation is disabled and no slug is provided
     * @throws DuplicateSlugException If a custom slug is provided but already exists
     */
    public static function createOrUpdate(array $attributes): self
    {
        // Check if slug is required but not provided
        if (!config('taxonomy.slugs.generate', true) && !isset($attributes['slug'])) {
            throw new MissingSlugException();
        }

        // Use the provided slug or generate a base slug (without uniqueness check)
        $baseSlug = $attributes['slug'] ?? Str::slug($attributes['name']);

        // First try to find by slug and type
        $taxonomy = static::where('slug', $baseSlug)
            ->where('type', $attributes['type'])
            ->first();

        if ($taxonomy) {
            $taxonomy->update($attributes);
        } else {
            // If a custom slug is provided, check if it's unique
            if (isset($attributes['slug']) && static::slugExists($attributes['slug'])) {
                throw new DuplicateSlugException($attributes['slug']);
            }

            // If not found, ensure the slug is unique before creating
            if (!isset($attributes['slug']) && config('taxonomy.slugs.generate', true)) {
                $attributes['slug'] = static::generateUniqueSlug($attributes['name'], $attributes['type']);
            }

            $taxonomy = static::create($attributes);
        }

        return $taxonomy;
    }
    /**
     * Check if a slug already exists.
     *
     * @param string $slug The slug to check
     * @param int|null $excludeId ID to exclude from the check (useful for updates)
     * @return bool True if the slug exists, false otherwise
     */
    public static function slugExists(string $slug, ?int $excludeId = null): bool
    {
        return static::query()
            ->where('slug', $slug)
            ->when($excludeId, function ($query) use ($excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->exists();
    }

    /**
     * Generate a unique slug for a taxonomy.
     *
     * @param string $name The name to generate the slug from
     * @param string $type The taxonomy type
     * @param int|null $excludeId ID to exclude from the uniqueness check (useful for updates)
     * @return string The unique slug
     */
    protected static function generateUniqueSlug(string $name, string $type, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        // Check if the slug already exists (regardless of type)
        while (static::slugExists($slug, $excludeId)) {
            // If it exists, append a counter and try again
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }
}
