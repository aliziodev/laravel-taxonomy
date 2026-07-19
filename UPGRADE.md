# Upgrade Guide

## Upgrading to v2.11.0

No database changes are required and no public method signatures changed. Read the two notes below before deploying.

### New: cache isolation for multi-tenant applications

Tree lookups are cached, and those cache entries were previously global. In a multi-tenant application one tenant could be served another tenant's cached taxonomy tree. Register a scope resolver:

```php
use Aliziodev\LaravelTaxonomy\TaxonomyManager;

TaxonomyManager::resolveCacheScopeUsing(fn () => tenant()?->getKey());
```

Single-tenant applications need no changes: with no resolver registered the cache keys are byte-identical to previous releases, so upgrading does not orphan a warm cache.

### Deprecated: custom relationship names

Every taxonomy method takes an optional relationship name (default
`taxonomable`). Passing anything else is deprecated and will be removed in
3.0; doing so now raises an `E_USER_DEPRECATED` notice, once per name.

It was never usable on the shipped schema. The name selects the pivot morph
columns `{$name}_id` and `{$name}_type`, and the migration creates only
`taxonomable_id` / `taxonomable_type`, so a call such as

```php
$product->attachTaxonomies($categoryIds, 'categories');
```

fails with `no such column: taxonomables.categories_id`. Query scopes could
never honour it at all: Eloquent resolves relationships by name at the point
of use, so `whereHas('taxonomies', ...)` has nowhere to pass a runtime
argument, and the parameter was silently ignored there.

Use taxonomy **types** to separate categories from tags. If you genuinely need
several roles for the same type, add a single `relation` column to the pivot
and filter with `wherePivot()` — one column covers any number of roles, and it
keeps working with `whereHas`.

### Behaviour change: type validation ignores global scopes

The `*OfType` methods (`syncTaxonomiesOfType()`, `attachTaxonomiesOfType()`,
and friends) verify the IDs they are given by re-querying the taxonomy model.
That lookup no longer runs through the application's global scopes.

This fixes multi-tenant setups where a tenant scope silently discarded
taxonomies the caller had explicitly selected — shared rows visible to every
tenant were the usual casualty (issue #15). Soft deletes are still enforced,
and taxonomies of the wrong type are still rejected.

Note the trade-off: if you relied on a global scope to stop a taxonomy from
another tenant being attached, that filtering is gone. It was never a designed
protection — `syncTaxonomies()` has always attached whatever IDs it is handed
— but if your application passes user-supplied IDs straight through, validate
them (for example with `Rule::exists()` scoped to the tenant).

### Fixes worth knowing about

- `attachTaxonomies()`, `syncTaxonomies()` and friends now accept a `Support\Collection` (what `collect()` and `pluck()` return). These previously resolved to an empty set and did nothing, without raising an error. Code that silently no-opped will now actually write.
- `taxonomy:rebuild-nested-set` no longer blocks forever when run without `--force` from a non-interactive context (CI, cron, deploy scripts, `Artisan::call()`). It now reports that `--force` is required and exits.
- `taxonomy:rebuild-nested-set` now invalidates cached trees; previously a rebuild succeeded while the application kept serving the pre-rebuild tree for the rest of the cache TTL.
- Query scopes now qualify columns with the configured table name instead of a hard-coded `taxonomies`, so `table_names.taxonomies` works.

## Upgrading to v2.3.0

### Breaking Changes

#### Unique Constraint Changes

In version 2.3.0, we've changed the unique constraint on the `slug` field from a global unique constraint to a composite unique constraint that includes both `slug` and `type`.

**Previous behavior (v2.x):**
- Slug was globally unique across all taxonomy types
- You couldn't have the same slug for different types (e.g., category "technology" and tag "technology")

**New behavior (v2.3.0):**
- Slug is unique within each taxonomy type
- You can now have the same slug for different types (e.g., category "technology" and tag "technology")
- Composite unique constraint: `['slug', 'type']`

### Migration Steps

If you're upgrading from v2.2.x to v2.3.0, you need to update your database schema to remove the old unique constraint and apply the new composite constraint.

#### Step 1: Create Migration File

Create a new migration file to handle the upgrade:

```bash
php artisan make:migration upgrade_taxonomies_unique_constraint_to_v2_3
```

#### Step 2: Migration Content

Add the following content to your migration file:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('taxonomy.table_names');
        $tableName = $tableNames['taxonomies'] ?? 'taxonomies';
        
        Schema::table($tableName, function (Blueprint $table) {
            // Drop the old unique constraint on slug
            $table->dropUnique(['slug']);
            
            // Add the new composite unique constraint
            $table->unique(['slug', 'type']);
        });
    }

    public function down(): void
    {
        $tableNames = config('taxonomy.table_names');
        $tableName = $tableNames['taxonomies'] ?? 'taxonomies';
        
        Schema::table($tableName, function (Blueprint $table) {
            // Drop the composite unique constraint
            $table->dropUnique(['slug', 'type']);
            
            // Restore the old unique constraint on slug
            $table->unique(['slug']);
        });
    }
};
```

#### Step 3: Handle Data Conflicts (If Any)

Before running the migration, check if you have any potential conflicts:

```php
// Check for potential slug conflicts across different types
$conflicts = DB::table('taxonomies')
    ->select('slug')
    ->groupBy('slug')
    ->havingRaw('COUNT(DISTINCT type) > 1')
    ->get();

if ($conflicts->isNotEmpty()) {
    // Handle conflicts manually or update slugs
    foreach ($conflicts as $conflict) {
        echo "Conflict found for slug: {$conflict->slug}\n";
    }
}
```

#### Step 4: Run Migration

```bash
php artisan migrate
```

### Code Changes Required

#### Model Validation

If you have custom validation rules for taxonomy creation, update them to consider the composite unique constraint:

**Before (v2.x):**
```php
'slug' => 'required|string|unique:taxonomies,slug'
```

**After (v2.3.0):**
```php
'slug' => [
    'required',
    'string',
    Rule::unique('taxonomies')->where(function ($query) use ($type) {
        return $query->where('type', $type);
    })
]
```

#### Custom Queries

If you have custom queries that rely on global slug uniqueness, review and update them:

**Before:**
```php
// Using model directly
$taxonomy = Taxonomy::where('slug', 'technology')->first();

// Or using facade
$taxonomy = \Aliziodev\LaravelTaxonomy\Facades\Taxonomy::findBySlug('technology');
```

**After:**
```php
// Using model with type constraint
$taxonomy = Taxonomy::where('slug', 'technology')
    ->where('type', TaxonomyType::Category)
    ->first();

// Using facade with type constraint
$taxonomy = \Aliziodev\LaravelTaxonomy\Facades\Taxonomy::findBySlug('technology', TaxonomyType::Category);

// Or using available scope
$taxonomy = Taxonomy::type(TaxonomyType::Category)
    ->where('slug', 'technology')
    ->first();
```

### Benefits of This Change

1. **More Flexible Taxonomy Management**: You can now use the same slug across different taxonomy types
2. **Better Organization**: Categories and tags can have meaningful, similar names without conflicts
3. **Improved User Experience**: More intuitive taxonomy creation and management

### Rollback Instructions

If you need to rollback to the previous behavior:

```bash
php artisan migrate:rollback
```

**Note:** Before rolling back, ensure you don't have duplicate slugs across different types, as this would violate the global unique constraint.

### Testing Your Upgrade

After upgrading, test the following scenarios:

1. **Create taxonomies with same slug but different types:**
   ```php
   Taxonomy::create([
       'name' => 'Technology',
       'slug' => 'technology',
       'type' => TaxonomyType::Category
   ]);
   
   Taxonomy::create([
       'name' => 'Technology',
       'slug' => 'technology',
       'type' => TaxonomyType::Tag
   ]);
   ```

2. **Verify unique constraint within same type:**
   ```php
   // This should fail
   Taxonomy::create([
       'name' => 'Tech',
       'slug' => 'technology', // Same slug
       'type' => TaxonomyType::Category // Same type
   ]);
   ```

3. **Test existing functionality:**
   - Taxonomy creation and updates
   - Nested set operations
   - Relationship queries

### Support

If you encounter any issues during the upgrade process, please:

1. Check the [GitHub Issues](https://github.com/aliziodev/laravel-taxonomy/issues)
2. Create a new issue with detailed information about your setup
3. Include your Laravel version, PHP version, and database type

### Changelog

For a complete list of changes in v2.3.0, see [CHANGELOG.md](CHANGELOG.md).