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
 * @property int|null $lft
 * @property int|null $rgt
 * @property int|null $depth
 * @property array<string, mixed>|null $meta
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy> $children
 * @property-read \Aliziodev\LaravelTaxonomy\Models\Taxonomy|null $parent
 * @property-read string $path
 * @property-read string $full_slug
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Aliziodev\LaravelTaxonomy\Models\Taxonomy>|null $children_nested
 * @property-read int|null $tree_depth
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> type(string|\Aliziodev\LaravelTaxonomy\Enums\TaxonomyType $type)
 * @method static \Illuminate\Database\Eloquent\Builder<static> root()
 * @method static \Illuminate\Database\Eloquent\Builder<static> ordered()
 * @method static \Illuminate\Database\Eloquent\Builder<static> roots()
 * @method static \Illuminate\Database\Eloquent\Builder<static> atDepth(int $depth)
 * @method static \Illuminate\Database\Eloquent\Builder<static> nestedSetOrder()
 * @method static \Illuminate\Database\Eloquent\Builder<static> newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static> newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static \Illuminate\Database\Eloquent\Builder<static> whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static> whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static> whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static> whereType($value)
 * @method static static create(array<string, mixed> $attributes = [])
 * @method static static|null find(mixed $id, array<int, string> $columns = ['*'])
 * @method static static findOrFail(mixed $id, array<int, string> $columns = ['*'])
 * @method static static|null first(array<int, string> $columns = ['*'])
 * @method static static firstOrFail(array<int, string> $columns = ['*'])
 */
class Taxonomy extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'parent_id',
        'sort_order',
        'lft',
        'rgt',
        'depth',
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
        'lft' => 'integer',
        'rgt' => 'integer',
        'depth' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $taxonomy) {
            // Check if slug is required but not provided
            if (! config('taxonomy.slugs.generate', true) && empty($taxonomy->slug)) {
                throw new MissingSlugException;
            }

            // Generate slug if enabled in config and not provided
            if (config('taxonomy.slugs.generate', true) && empty($taxonomy->slug)) {
                $taxonomy->slug = static::generateUniqueSlug($taxonomy->name, $taxonomy->type);
            }

            // If slug is provided, check if it's unique within the same type
            if (! empty($taxonomy->slug) && static::slugExists($taxonomy->slug, $taxonomy->type, null)) {
                throw new DuplicateSlugException($taxonomy->slug, $taxonomy->type);
            }

            // Set nested set values for new taxonomy
            $taxonomy->setNestedSetValues();
        });

        static::updating(function (self $taxonomy) {
            // If slug is being changed manually, check if it's unique within the same type
            if ($taxonomy->isDirty('slug') && ! empty($taxonomy->slug)) {
                if (static::slugExists($taxonomy->slug, $taxonomy->type, $taxonomy->id)) {
                    throw new DuplicateSlugException($taxonomy->slug, $taxonomy->type);
                }
            }

            // Regenerate slug on update if enabled in config
            if (config('taxonomy.slugs.regenerate_on_update', false) && $taxonomy->isDirty('name')) {
                $taxonomy->slug = static::generateUniqueSlug($taxonomy->name, $taxonomy->type, $taxonomy->id);
            }
        });

        static::updated(function (self $taxonomy) {
            // Update nested set values if parent changed
            if ($taxonomy->wasChanged('parent_id')) {
                static::rebuildNestedSet($taxonomy->type);
            }
        });

        static::deleting(function (self $taxonomy) {
            // Move children to parent before deleting
            if ($taxonomy->children()->count() > 0) {
                $taxonomy->children()->update(['parent_id' => $taxonomy->parent_id]);
                // Rebuild nested set for affected nodes
                static::rebuildNestedSet($taxonomy->type);
            } else {
                // Remove gap in nested set
                $taxonomy->removeFromNestedSet();
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
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Get the children taxonomies.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get all descendants of the taxonomy.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function descendants(): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, self> $descendants */
        $descendants = new Collection;

        foreach ($this->children as $child) {
            /* @var self $child */
            $descendants->push($child);
            /** @var \Illuminate\Database\Eloquent\Collection<int, self> $childDescendants */
            $childDescendants = $child->descendants();
            $descendants = $descendants->merge($childDescendants);
        }

        return $descendants;
    }

    /**
     * Get all ancestors of the taxonomy.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function ancestors(): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, self> $ancestors */
        $ancestors = new Collection;
        $parent = $this->parent;

        while ($parent) {
            /* @var self $parent */
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
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeType(Builder $query, string|TaxonomyType $type): Builder
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;

        return $query->where('type', $typeValue);
    }

    /**
     * Scope a query to only include root taxonomies (no parent).
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Scope a query to order taxonomies by sort_order.
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /**
     * Get a flat tree representation of the taxonomy hierarchy.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function flatTree(string|TaxonomyType|null $type = null, ?int $parentId = null, int $depth = 0): Collection
    {
        $query = static::query();

        if ($type) {
            $query->type($type);
        }

        $query->where('parent_id', $parentId)->ordered();

        /** @var \Illuminate\Database\Eloquent\Collection<int, static> $result */
        $result = new Collection;

        foreach ($query->get() as $taxonomy) {
            $taxonomy->depth = $depth;
            $result->push($taxonomy);
            $result = $result->merge(static::flatTree($type, $taxonomy->id, $depth + 1));
        }

        return $result;
    }

    /**
     * Get a nested tree representation of the taxonomy hierarchy.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
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
     * @param  array  $attributes  The attributes for the taxonomy
     * @return self The created or updated taxonomy
     *
     * @throws MissingSlugException If slug generation is disabled and no slug is provided
     * @throws DuplicateSlugException If a custom slug is provided but already exists
     */
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createOrUpdate(array $attributes): self
    {
        // Check if slug is required but not provided
        if (! config('taxonomy.slugs.generate', true) && ! isset($attributes['slug'])) {
            throw new MissingSlugException;
        }

        // Use the provided slug or generate a base slug (without uniqueness check)
        $slug = $attributes['slug'] ?? Str::slug($attributes['name']);

        // First try to find by slug and type
        $taxonomy = static::where('slug', $slug)
            ->where('type', $attributes['type'])
            ->first();

        if ($taxonomy) {
            // Update existing taxonomy
            $taxonomy->update($attributes);
        } else {
            // If a custom slug is provided, check if it's unique within the same type
            if (isset($attributes['slug']) && static::slugExists($attributes['slug'], $attributes['type'])) {
                throw new DuplicateSlugException($attributes['slug'], $attributes['type']);
            }

            // If not found, ensure the slug is unique before creating
            if (! isset($attributes['slug']) && config('taxonomy.slugs.generate', true)) {
                $attributes['slug'] = static::generateUniqueSlug($attributes['name'], $attributes['type']);
            }

            $taxonomy = static::create($attributes);
        }

        return $taxonomy;
    }

    /**
     * Check if a slug exists within a specific type.
     *
     * @param  string  $slug  The slug to check
     * @param  string|TaxonomyType  $type  The taxonomy type to check within
     * @param  int|null  $excludeId  ID to exclude from the check (useful for updates)
     * @return bool True if the slug exists, false otherwise
     */
    public static function slugExists(string $slug, string|TaxonomyType $type, ?int $excludeId = null): bool
    {
        $typeValue = $type instanceof TaxonomyType ? $type->value : $type;

        return static::query()
            ->where('slug', $slug)
            ->where('type', $typeValue)
            ->when($excludeId, function ($query) use ($excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->exists();
    }

    /**
     * Generate a unique slug for a taxonomy within its type scope.
     *
     * @param  string  $name  The name to generate the slug from
     * @param  string|TaxonomyType  $type  The taxonomy type
     * @param  int|null  $excludeId  ID to exclude from the uniqueness check (useful for updates)
     * @return string The unique slug
     */
    protected static function generateUniqueSlug(string $name, string|TaxonomyType $type, ?int $excludeId = null): string
    {
        $baseSlug = Str::slug($name);
        $slug = $baseSlug;
        $counter = 1;

        // Check if the slug already exists within the same type
        while (static::slugExists($slug, $type, $excludeId)) {
            // If it exists, append a counter and try again
            $slug = $baseSlug . '-' . $counter++;
        }

        return $slug;
    }

    // ========================================
    // NESTED SET METHODS
    // ========================================

    /**
     * Set nested set values for a new taxonomy.
     */
    protected function setNestedSetValues(): void
    {
        if ($this->parent_id) {
            /** @var static|null $parent */
            $parent = static::find($this->parent_id);
            if ($parent && $parent->rgt !== null && $parent->depth !== null) {
                $this->depth = $parent->depth + 1;

                // Update all nodes with rgt >= parent->rgt first
                static::where('rgt', '>=', $parent->rgt)
                    ->where('type', $this->type)
                    ->where('id', '!=', $this->id)
                    ->increment('rgt', 2);

                // Update all nodes with lft > parent->rgt
                static::where('lft', '>', $parent->rgt)
                    ->where('type', $this->type)
                    ->where('id', '!=', $this->id)
                    ->increment('lft', 2);

                // Set this node's values
                $this->lft = $parent->rgt;
                $this->rgt = $parent->rgt + 1;
            } else {
                $this->makeRoot();
            }
        } else {
            $this->makeRoot();
        }
    }

    /**
     * Make this taxonomy a root node.
     */
    protected function makeRoot(): void
    {
        $maxRgt = static::where('type', $this->type)->max('rgt') ?? 0;
        $this->depth = 0;
        $this->lft = $maxRgt + 1;
        $this->rgt = $maxRgt + 2;
    }

    /**
     * Move taxonomy to a new parent.
     */
    public function moveToParent(?int $parentId): void
    {
        if ($this->parent_id == $parentId) {
            return;
        }

        // Check for circular reference using parent_id relationships
        if ($parentId !== null && $this->wouldCreateCircularReference($parentId)) {
            throw new \Exception('Moving this taxonomy would create a circular reference.');
        }

        // Set new parent and save
        $this->parent_id = $parentId;
        $this->save();

        // Rebuild nested set structure for this taxonomy type
        static::rebuildNestedSet($this->type);
    }

    /**
     * Check if moving to the given parent would create a circular reference.
     */
    protected function wouldCreateCircularReference(int $parentId): bool
    {
        // If trying to move to itself
        if ($parentId === $this->id) {
            return true;
        }

        // Check if the target parent is a descendant of this node using parent_id relationships
        // This is more reliable than using nested set values which might be outdated
        $currentParentId = $parentId;
        $visited = [];

        while ($currentParentId !== null) {
            // Prevent infinite loops
            if (in_array($currentParentId, $visited)) {
                return true;
            }

            $visited[] = $currentParentId;

            // If we reach the current node, it would create a circular reference
            if ($currentParentId === $this->id) {
                return true;
            }

            // Get the parent of the current node
            $parent = static::find($currentParentId);
            $currentParentId = $parent ? $parent->parent_id : null;
        }

        return false;
    }

    /**
     * Remove taxonomy from nested set structure.
     */
    protected function removeFromNestedSet(): void
    {
        if ($this->lft === null || $this->rgt === null) {
            return;
        }

        $width = $this->rgt - $this->lft + 1;

        // Update all nodes with lft > this->rgt
        static::where('lft', '>', $this->rgt)
            ->where('type', $this->type)
            ->decrement('lft', $width);

        // Update all nodes with rgt > this->rgt
        static::where('rgt', '>', $this->rgt)
            ->where('type', $this->type)
            ->decrement('rgt', $width);
    }

    /**
     * Rebuild nested set for a specific type.
     */
    public static function rebuildNestedSet(string $type): void
    {
        $taxonomies = static::where('type', $type)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $counter = 1;
        foreach ($taxonomies as $taxonomy) {
            $counter = static::rebuildNode($taxonomy, $counter, 0);
        }
    }

    /**
     * Rebuild a single node and its children.
     */
    protected static function rebuildNode(self $node, int $counter, int $depth): int
    {
        $node->lft = $counter++;
        $node->depth = $depth;

        $children = static::where('parent_id', $node->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {
            $counter = static::rebuildNode($child, $counter, $depth + 1);
        }

        $node->rgt = $counter++;
        $node->saveQuietly();

        return $counter;
    }

    /**
     * Get all descendants using nested set.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public function getDescendants(): Collection
    {
        if ($this->lft === null || $this->rgt === null) {
            return new Collection;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, static> $result */
        $result = static::where('type', $this->type)
            ->where('lft', '>', $this->lft)
            ->where('rgt', '<', $this->rgt)
            ->orderBy('lft')
            ->get();

        return $result;
    }

    /**
     * Get all ancestors using nested set.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public function getAncestors(): Collection
    {
        if ($this->lft === null || $this->rgt === null) {
            return new Collection;
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, static> $result */
        $result = static::where('type', $this->type)
            ->where('lft', '<', $this->lft)
            ->where('rgt', '>', $this->rgt)
            ->orderBy('lft')
            ->get();

        return $result;
    }

    /**
     * Get siblings of this taxonomy (same parent, same level).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
     */
    public function getSiblings()
    {
        if ($this->parent_id === null) {
            // For root nodes, get all other root nodes of the same type
            return static::where('type', $this->type)
                ->whereNull('parent_id')
                ->where('id', '!=', $this->id)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        // For non-root nodes, get all children of the same parent
        return static::where('parent_id', $this->parent_id)
            ->where('id', '!=', $this->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get direct children using nested set.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public function getChildren(): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, static> $result */
        $result = static::where('parent_id', $this->id)
            ->orderBy('lft')
            ->get();

        return $result;
    }

    /**
     * Check if this taxonomy is an ancestor of another.
     */
    public function isAncestorOf(self $other): bool
    {
        if ($this->lft === null || $this->rgt === null || $other->lft === null || $other->rgt === null) {
            return false;
        }

        return $this->type === $other->type
            && $this->lft < $other->lft
            && $this->rgt > $other->rgt;
    }

    /**
     * Check if this taxonomy is a descendant of another.
     */
    public function isDescendantOf(self $other): bool
    {
        return $other->isAncestorOf($this);
    }

    /**
     * Get the level/depth of this taxonomy.
     */
    public function getLevel(): int
    {
        return $this->depth ?? 0;
    }

    /**
     * Scope to get only root taxonomies using nested set.
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->where('depth', 0);
    }

    /**
     * Scope to get taxonomies at a specific depth.
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeAtDepth(Builder $query, int $depth): Builder
    {
        return $query->where('depth', $depth);
    }

    /**
     * Scope to get taxonomies ordered by nested set.
     */
    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeNestedSetOrder(Builder $query): Builder
    {
        return $query->orderBy('lft');
    }

    /**
     * Get nested tree using nested set (more efficient than recursive queries).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    public static function getNestedTree(string|TaxonomyType|null $type = null): Collection
    {
        if ($type !== null) {
            $query = static::query()->type($type);
            $taxonomies = $query->nestedSetOrder()->get();

            return static::buildNestedTree($taxonomies);
        }

        // When no type is specified, get all types and build separate trees
        $allTypes = static::select('type')->distinct()->pluck('type');
        /** @var \Illuminate\Database\Eloquent\Collection<int, static> $allTrees */
        $allTrees = new Collection;

        foreach ($allTypes as $typeValue) {
            $query = static::query()->type($typeValue);
            $taxonomies = $query->nestedSetOrder()->get();
            $typeTree = static::buildNestedTree($taxonomies);
            $allTrees = $allTrees->merge($typeTree);
        }

        return $allTrees;
    }

    /**
     * Build nested tree structure from flat collection.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, static>  $taxonomies
     * @return \Illuminate\Database\Eloquent\Collection<int, static>
     */
    protected static function buildNestedTree(Collection $taxonomies): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, static> $tree */
        $tree = new Collection;
        /** @var array<static> $stack */
        $stack = [];

        foreach ($taxonomies as $taxonomy) {
            // Remove items from stack that are not ancestors
            while (! empty($stack) && ($lastItem = end($stack)) && $lastItem->rgt !== null && $taxonomy->lft !== null && $lastItem->rgt < $taxonomy->lft) {
                array_pop($stack);
            }

            // Set depth based on stack size
            $taxonomy->setAttribute('tree_depth', count($stack));

            if (empty($stack)) {
                // Root level
                $tree->push($taxonomy);
            } else {
                // Child level - add to parent's children collection
                $parent = end($stack);
                if ($parent && empty($parent->children_nested)) {
                    /** @var \Illuminate\Database\Eloquent\Collection<int, static> $childrenNested */
                    $childrenNested = new Collection;
                    $parent->setAttribute('children_nested', $childrenNested);
                }
                if ($parent && $parent->children_nested) {
                    $parent->children_nested->push($taxonomy);
                }
            }

            // Add to stack if it has children
            if ($taxonomy->rgt !== null && $taxonomy->lft !== null && $taxonomy->rgt > $taxonomy->lft + 1) {
                $stack[] = $taxonomy;
            }
        }

        return $tree;
    }
}
