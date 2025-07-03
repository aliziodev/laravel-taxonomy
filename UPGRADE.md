# Upgrade Guide

## Upgrading to v3.0.0

### Breaking Changes

#### Unique Constraint Changes

In version 3.0.0, we've changed the unique constraint on the `slug` field from a global unique constraint to a composite unique constraint that includes both `slug` and `type`.

**Previous behavior (v2.x):**
- Slug was globally unique across all taxonomy types
- You couldn't have the same slug for different types (e.g., category "technology" and tag "technology")

**New behavior (v3.0.0):**
- Slug is unique within each taxonomy type
- You can now have the same slug for different types (e.g., category "technology" and tag "technology")
- Composite unique constraint: `['slug', 'type']`

### Migration Steps

If you're upgrading from v2.x to v3.0.0, you need to update your database schema to remove the old unique constraint and apply the new composite constraint.

#### Step 1: Create Migration File

Create a new migration file to handle the upgrade:

```bash
php artisan make:migration upgrade_taxonomies_unique_constraint_to_v3
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

**After (v3.0.0):**
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

For a complete list of changes in v3.0.0, see [CHANGELOG.md](CHANGELOG.md).