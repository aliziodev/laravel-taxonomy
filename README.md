<picture>
    <source media="(prefers-color-scheme: dark)" srcset="art/header-dark.png">
    <img alt="Logo for essentials" src="art/header-light.png">
</picture>

# Laravel Taxonomy

[![codecov](https://codecov.io/gh/aliziodev/laravel-taxonomy/branch/master/graph/badge.svg)](https://codecov.io/gh/aliziodev/laravel-taxonomy)
[![Tests](https://github.com/aliziodev/laravel-taxonomy/workflows/Tests/badge.svg)](https://github.com/aliziodev/laravel-taxonomy/actions)
[![Code Quality](https://github.com/aliziodev/laravel-taxonomy/workflows/Code%20Quality/badge.svg)](https://github.com/aliziodev/laravel-taxonomy/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/aliziodev/laravel-taxonomy.svg)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![Total Downloads](https://img.shields.io/packagist/dt/aliziodev/laravel-taxonomy.svg)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![PHP Version](https://img.shields.io/packagist/php-v/aliziodev/laravel-taxonomy.svg)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.0%2B-orange.svg)](https://laravel.com/)

Laravel Taxonomy is a flexible and powerful package for managing taxonomies, categories, tags, and hierarchical structures in Laravel applications. Features nested-set support for optimal query performance on hierarchical data structures.

## 📖 Documentation

-   [🇺🇸 English Documentation](README.md)
-   [🇮🇩 Dokumentasi Bahasa Indonesia](README.id.md)

## 📋 Table of Contents

-   [Overview](#overview)
-   [Key Features](#key-features)
-   [Requirements](#requirements)
-   [Installation](#installation)
-   [Configuration](#️-configuration)
-   [Quick Start](#-quick-start)
-   [Basic Usage](#-basic-usage)
-   [Hierarchical Data & Nested Sets](#-hierarchical-data--nested-sets)
-   [Metadata Support](#-metadata-support)
-   [Bulk Operations](#-bulk-operations)
-   [Caching & Performance](#-caching--performance)
-   [Custom Taxonomy Types](#️-custom-taxonomy-types)
-   [Real-World Usage Scenarios](#-real-world-usage-scenarios)
-   [Advanced Features](#-advanced-features)
-   [Best Practices](#-best-practices)
-   [Custom Slugs and Error Handling](#custom-slugs-and-error-handling)
-   [Troubleshooting](#troubleshooting)
-   [Security](#security)
-   [Testing](#testing)
-   [License](#license)

## Overview

This package is ideal for:

-   E-commerce category management
-   Blog taxonomies
-   Content organization
-   Product attributes
-   Dynamic navigation
-   Any hierarchical data structure

## Key Features

### Core Functionality

-   **Hierarchical Terms**: Create parent-child relationships between terms
-   **Metadata Support**: Store additional data as JSON with each taxonomy
-   **Term Ordering**: Control the order of terms with sort_order
-   **Polymorphic Relationships**: Associate taxonomies with any model
-   **Multiple Term Types**: Use predefined types (Category, Tag, etc.) or create custom types
-   **Composite Unique Slugs**: Slugs are unique within their type, allowing same slug across different types
-   **Bulk Operations**: Attach, detach, sync, or toggle multiple taxonomies at once
-   **Advanced Querying**: Filter models by taxonomies with query scopes

### Nested Set Features

-   **Tree Navigation**: Efficient ancestor and descendant queries
-   **Tree Manipulation**: Move, insert, and reorganize tree nodes
-   **Depth Management**: Track and query by hierarchy depth
-   **Tree Validation**: Maintain tree integrity automatically
-   **Efficient Queries**: Optimized database queries for hierarchical data

### Performance & Scalability

-   **Caching System**: Improve performance with built-in caching
-   **Database Indexing**: Optimized indexes for fast queries
-   **Lazy Loading**: Efficient relationship loading
-   **Tree Structure**: Get hierarchical or flat tree representations
-   **Pagination Support**: Paginate results for better performance

### Developer Experience

-   **Intuitive API**: Clean and expressive syntax
-   **Comprehensive Documentation**: Detailed guides and examples
-   **Type Safety**: Full support for Laravel's type system
-   **Testing Support**: Built-in testing utilities

## Requirements

-   PHP 8.2+
-   Laravel 11.0+ or 12.0+
-   Composer 2.0+

## Installation

### Via Composer

```bash
composer require aliziodev/laravel-taxonomy
```

### Publish Configuration and Migrations

You can publish the configuration and migrations using the provided install command:

```bash
php artisan taxonomy:install
```

Or manually:

```bash
php artisan vendor:publish --provider="Aliziodev\LaravelTaxonomy\TaxonomyProvider" --tag="taxonomy-config"
php artisan vendor:publish --provider="Aliziodev\LaravelTaxonomy\TaxonomyProvider" --tag="taxonomy-migrations"
```

### Run Migrations

```bash
php artisan migrate
```

## ⚙️ Configuration

After publishing the configuration file, you can customize it in `config/taxonomy.php`. Here's a detailed explanation of each option:

```php
return [
    // Database table names
    'table_names' => [
        'taxonomies' => 'taxonomies',      // Main taxonomy table
        'taxonomables' => 'taxonomables',  // Polymorphic pivot table
    ],

    // Primary key type for polymorphic relationships
    // Options: 'numeric' (default), 'uuid', 'ulid'
    'morph_type' => 'uuid',

    // Available taxonomy types (can be extended)
    'types' => collect(TaxonomyType::cases())->pluck('value')->toArray(),

    // Custom model binding (for extending the base Taxonomy model)
    'model' => \Aliziodev\LaravelTaxonomy\Models\Taxonomy::class,

    // Slug generation settings
    'slugs' => [
        'generate' => true,                // Auto-generate slugs from names
        'regenerate_on_update' => false,  // Regenerate slug when name changes
    ],
];
```

### Configuration Options Explained

#### Table Names

Customize database table names if you need to avoid conflicts or follow specific naming conventions:

```php
'table_names' => [
    'taxonomies' => 'custom_taxonomies',
    'taxonomables' => 'custom_taxonomables',
],
```

#### Morph Type

Choose the appropriate morph type based on your model's primary key:

```php
// For auto-incrementing integer IDs
'morph_type' => 'numeric',

// For UUID primary keys
'morph_type' => 'uuid',

// For ULID primary keys
'morph_type' => 'ulid',
```

#### Custom Types

Extend or replace the default taxonomy types:

```php
'types' => [
    'category',
    'tag',
    'brand',
    'collection',
    'custom_type',
],
```

#### Slug Configuration

Control slug generation behavior:

```php
'slugs' => [
    'generate' => false,               // Require manual slug input
    'regenerate_on_update' => true,   // Auto-update slugs when names change
],
```

#### Important: Composite Unique Constraint

Starting from version 2.3.0, slugs are unique within their taxonomy type, not globally. This means:

-   ✅ You can have `slug: 'featured'` for both `Category` and `Tag` types
-   ✅ Better flexibility for organizing different taxonomy types
-   ⚠️ **Breaking Change**: If upgrading from v2.2.x, see [UPGRADE.md](UPGRADE.md) for migration instructions

```php
// This is now possible:
Taxonomy::create(['name' => 'Featured', 'slug' => 'featured', 'type' => 'category']);
Taxonomy::create(['name' => 'Featured', 'slug' => 'featured', 'type' => 'tag']);
```

## 🚀 Quick Start

Get up and running with Laravel Taxonomy in minutes:

### 1. Create Your First Taxonomy

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

// Create a category
$electronics = Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyType::Category->value,
    'description' => 'Electronic products and gadgets',
]);

// Create a subcategory
$smartphones = Taxonomy::create([
    'name' => 'Smartphones',
    'type' => TaxonomyType::Category->value,
    'parent_id' => $electronics->id,
]);

// Create tags
$featured = Taxonomy::create([
    'name' => 'Featured',
    'type' => TaxonomyType::Tag->value,
]);
```

### 2. Associate with Models

```php
// Assuming you have a Product model with HasTaxonomy trait
$product = Product::create([
    'name' => 'iPhone 15 Pro',
    'price' => 999.99,
]);

// Attach taxonomies
$product->attachTaxonomies([$electronics->id, $smartphones->id, $featured->id]);

// Or attach by slug
$product->attachTaxonomies(['electronics', 'smartphones', 'featured']);
```

### 3. Query and Filter

```php
// Find products in electronics category
$products = Product::withTaxonomyType(TaxonomyType::Category)
    ->withTaxonomySlug('electronics')
    ->get();

// Get all taxonomies of a specific type
$categories = Taxonomy::findByType(TaxonomyType::Category);

// Get hierarchical tree
$categoryTree = Taxonomy::tree(TaxonomyType::Category);
```

## 📖 Basic Usage

### Working with the Taxonomy Facade

The `Taxonomy` facade provides a clean, expressive API for all taxonomy operations:

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

// Create taxonomies
$category = Taxonomy::create([
    'name' => 'Books',
    'type' => TaxonomyType::Category->value,
    'description' => 'All kinds of books',
    'meta' => [
        'icon' => 'book',
        'color' => '#3498db',
        'featured' => true,
    ],
]);

// Find taxonomies
$taxonomy = Taxonomy::findBySlug('books');
$exists = Taxonomy::exists('books');
$categories = Taxonomy::findByType(TaxonomyType::Category);

// Search taxonomies
$results = Taxonomy::search('science', TaxonomyType::Category);

// Get hierarchical data
$tree = Taxonomy::tree(TaxonomyType::Category);
$flatTree = Taxonomy::flatTree(TaxonomyType::Category);
$nestedTree = Taxonomy::getNestedTree(TaxonomyType::Category);
```

### Working with Model Relationships

Once you've added the `HasTaxonomy` trait to your models, you get access to powerful relationship methods:

```php
// Basic operations
$product->attachTaxonomies($taxonomyIds);
$product->detachTaxonomies($taxonomyIds);
$product->syncTaxonomies($taxonomyIds);
$product->toggleTaxonomies($taxonomyIds);

// Check relationships
$hasCategory = $product->hasTaxonomies($categoryIds);
$hasAllTags = $product->hasAllTaxonomies($tagIds);
$hasType = $product->hasTaxonomyType(TaxonomyType::Category);

// Get related taxonomies
$allTaxonomies = $product->taxonomies;
$categories = $product->taxonomiesOfType(TaxonomyType::Category);
$hierarchical = $product->getHierarchicalTaxonomies(TaxonomyType::Category);
```

### Query Scopes for Filtering

Filter your models using powerful query scopes:

```php
// Filter by taxonomy type
$products = Product::withTaxonomyType(TaxonomyType::Category)->get();

// Filter by specific taxonomies
$products = Product::withAnyTaxonomies([$category1, $category2])->get();
$products = Product::withAllTaxonomies([$tag1, $tag2])->get();

// Filter by taxonomy slug (any type)
$products = Product::withTaxonomySlug('electronics')->get();

// Filter by taxonomy slug with specific type (recommended)
$products = Product::withTaxonomySlug('electronics', TaxonomyType::Category)->get();

// Filter by hierarchy (includes descendants)
$products = Product::withTaxonomyHierarchy($parentCategoryId)->get();

// Filter by depth level
$products = Product::withTaxonomyAtDepth(2, TaxonomyType::Category)->get();
```

#### Scope Chaining vs Single Scope with Type

With the composite unique constraint, you have two approaches for filtering:

```php
// Approach 1: Single scope with type parameter (Recommended)
// Finds products with taxonomy slug='electronics' AND type='category'
$products = Product::withTaxonomySlug('electronics', TaxonomyType::Category)->get();

// Approach 2: Chaining scopes (More flexible for complex queries)
// Finds products that have ANY taxonomy with type='category' AND ANY taxonomy with slug='electronics'
$products = Product::withTaxonomyType(TaxonomyType::Category)
    ->withTaxonomySlug('electronics')
    ->get();

// Note: These may return different results if a product has multiple taxonomies
```

### Pagination Support

The package supports pagination for search and find methods:

```php
// Paginate search results (5 items per page, page 1)
$results = Taxonomy::search('electronic', null, 5, 1);

// Paginate taxonomies by type
$categories = Taxonomy::findByType(TaxonomyType::Category, 10, 1);

// Paginate taxonomies by parent
$children = Taxonomy::findByParent($parent->id, 10, 1);
```

### Complete Controller Example

Here's a comprehensive example of using Laravel Taxonomy in a controller:

```php
namespace App\Http\Controllers;

use App\Models\Product;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Get products filtered by category
        $categorySlug = $request->input('category');
        $query = Product::query();

        if ($categorySlug) {
            $category = Taxonomy::findBySlug($categorySlug, TaxonomyType::Category);
            if ($category) {
                $query->withAnyTaxonomies($category);
            }
        }

        $products = $query->paginate(12);
        $categories = Taxonomy::findByType(TaxonomyType::Category);

        return view('products.index', compact('products', 'categories'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'categories' => 'required|array',
            'tags' => 'nullable|array',
        ]);

        $product = Product::create($validated);

        // Attach categories and tags
        $product->attachTaxonomies($validated['categories']);
        if (isset($validated['tags'])) {
            $product->attachTaxonomies($validated['tags']);
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Product created successfully.');
    }
}
```

## 🌳 Hierarchical Data & Nested Sets

Laravel Taxonomy uses the Nested Set Model for efficient hierarchical data management:

### Understanding Nested Sets

The nested set model stores hierarchical data using `lft` (left) and `rgt` (right) values, along with `depth` for each node. This allows for efficient querying of hierarchical relationships.

```php
// Get all descendants of a taxonomy (children, grandchildren, etc.)
$descendants = $taxonomy->getDescendants();

// Get all ancestors of a taxonomy (parent, grandparent, etc.)
$ancestors = $taxonomy->getAncestors();

// Check hierarchical relationships
$isParent = $parent->isAncestorOf($child);
$isChild = $child->isDescendantOf($parent);

// Get the depth level
$level = $taxonomy->getLevel();

// Get only root taxonomies
$roots = Taxonomy::roots()->get();

// Get taxonomies at specific depth
$level2 = Taxonomy::atDepth(2)->get();
```

### Tree Operations

```php
// Move a taxonomy to a new parent
Taxonomy::moveToParent($taxonomyId, $newParentId);

// Rebuild nested set values (useful after bulk operations)
Taxonomy::rebuildNestedSet(TaxonomyType::Category);

// Get different tree representations
$hierarchicalTree = Taxonomy::tree(TaxonomyType::Category);           // Parent-child relationships
$flatTree = Taxonomy::flatTree(TaxonomyType::Category);               // Flat list with depth info
$nestedTree = Taxonomy::getNestedTree(TaxonomyType::Category);        // Nested set ordered
```

## 📊 Metadata Support

Store additional data with each taxonomy using JSON meta:

```php
// Create taxonomy with meta
$category = Taxonomy::create([
    'name' => 'Premium Products',
    'type' => TaxonomyType::Category->value,
    'meta' => [
        'icon' => 'star',
        'color' => '#gold',
        'display_order' => 1,
        'seo' => [
            'title' => 'Premium Products - Best Quality',
            'description' => 'Discover our premium product collection',
            'keywords' => ['premium', 'quality', 'luxury'],
        ],
        'settings' => [
            'show_in_menu' => true,
            'featured' => true,
            'requires_auth' => false,
        ],
    ],
]);

// Access meta
$icon = $category->meta['icon'] ?? 'default';
$seoTitle = $category->meta['seo']['title'] ?? $category->name;

// Update meta
$category->update([
    'meta' => array_merge($category->meta ?? [], [
        'updated_at' => now()->toISOString(),
        'view_count' => ($category->meta['view_count'] ?? 0) + 1,
    ]),
]);
```

## 🔄 Bulk Operations

Efficiently manage multiple taxonomy relationships:

### Basic Bulk Operations

```php
// Attach multiple taxonomies (won't duplicate existing)
$product->attachTaxonomies([1, 2, 3, 'electronics', 'featured']);

// Detach specific taxonomies
$product->detachTaxonomies([1, 2]);

// Detach all taxonomies
$product->detachTaxonomies();

// Sync taxonomies (removes old, adds new)
$product->syncTaxonomies([1, 2, 3]);

// Toggle taxonomies (attach if not present, detach if present)
$product->toggleTaxonomies([1, 2, 3]);

// Work with different relationship names
$product->attachTaxonomies($categoryIds, 'categories');
$product->attachTaxonomies($tagIds, 'tags');
```

### Advanced Bulk Management

```php
class BulkTaxonomyService
{
    public function bulkAttach(Collection $models, array $taxonomyIds): void
    {
        $data = [];
        $timestamp = now();

        foreach ($models as $model) {
            foreach ($taxonomyIds as $taxonomyId) {
                $data[] = [
                    'taxonomy_id' => $taxonomyId,
                    'taxonomable_id' => $model->id,
                    'taxonomable_type' => get_class($model),
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        DB::table('taxonomables')->insert($data);
    }

    public function bulkDetach(Collection $models, array $taxonomyIds): void
    {
        $modelIds = $models->pluck('id');
        $modelType = get_class($models->first());

        DB::table('taxonomables')
            ->whereIn('taxonomy_id', $taxonomyIds)
            ->whereIn('taxonomable_id', $modelIds)
            ->where('taxonomable_type', $modelType)
            ->delete();
    }

    public function bulkSync(Collection $models, array $taxonomyIds): void
    {
        $modelIds = $models->pluck('id');
        $modelType = get_class($models->first());

        // Remove existing associations
        DB::table('taxonomables')
            ->whereIn('taxonomable_id', $modelIds)
            ->where('taxonomable_type', $modelType)
            ->delete();

        // Add new associations
        $this->bulkAttach($models, $taxonomyIds);
    }

    public function migrateType(string $oldType, string $newType): int
    {
        return Taxonomy::where('type', $oldType)
            ->update(['type' => $newType]);
    }

    public function mergeTaxonomies(Taxonomy $source, Taxonomy $target): void
    {
        DB::transaction(function () use ($source, $target) {
            // Move all associations to target
            DB::table('taxonomables')
                ->where('taxonomy_id', $source->id)
                ->update(['taxonomy_id' => $target->id]);

            // Move children to target
            Taxonomy::where('parent_id', $source->id)
                ->update(['parent_id' => $target->id]);

            // Delete source taxonomy
            $source->delete();

            // Rebuild nested set for target's tree
            $target->rebuildNestedSet();
        });
    }
}
```

## ⚡ Caching & Performance

Laravel Taxonomy includes intelligent caching for optimal performance:

### Automatic Caching

```php
// These operations are automatically cached
$tree = Taxonomy::tree(TaxonomyType::Category);           // Cached for 24 hours
$flatTree = Taxonomy::flatTree(TaxonomyType::Category);   // Cached for 24 hours
$nestedTree = Taxonomy::getNestedTree(TaxonomyType::Category); // Cached for 24 hours
```

### Manual Cache Management

```php
// Clear cache for specific type
Taxonomy::clearCacheForType(TaxonomyType::Category);

// Cache is automatically cleared when:
// - Taxonomies are created, updated, or deleted
// - Nested set is rebuilt
// - Taxonomies are moved in hierarchy
```

### Performance Tips

```php
// Use eager loading to avoid N+1 queries
$products = Product::with(['taxonomies' => function ($query) {
    $query->where('type', TaxonomyType::Category->value);
}])->get();

// Use pagination for large datasets
$taxonomies = Taxonomy::findByType(TaxonomyType::Category, 20); // 20 per page

// Use specific queries instead of loading all relationships
$categories = $product->taxonomiesOfType(TaxonomyType::Category);
```

## 🏷️ Custom Taxonomy Types

While the package comes with predefined taxonomy types in the `TaxonomyType` enum (Category, Tag, Color, Size, etc.), you can easily define and use your own custom types.

### Defining Custom Types

There are two ways to use custom taxonomy types:

#### 1. Override the types configuration

You can override the default types by modifying the `types` array in your `config/taxonomy.php` file:

```php
'types' => [
    'category',
    'tag',
    // Default types you want to keep

    // Your custom types
    'genre',
    'location',
    'season',
    'difficulty',
],
```

#### 2. Use custom types directly

You can also use custom types directly in your code without modifying the configuration:

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;

// Create a taxonomy with a custom type
$genre = Taxonomy::create([
    'name' => 'Science Fiction',
    'type' => 'genre', // Custom type not defined in TaxonomyType enum
    'description' => 'Science fiction genre',
]);

// Find taxonomies by custom type
$genres = Taxonomy::findByType('genre');

// Check if a model has taxonomies of a custom type
$product->hasTaxonomyType('genre');

// Get taxonomies of a custom type
$productGenres = $product->taxonomiesOfType('genre');

// Filter models by custom taxonomy type
$products = Product::withTaxonomyType('genre')->get();
```

### Creating a Custom Type Enum

For better type safety and organization, you can create your own enum for custom types:

```php
namespace App\Enums;

enum GenreType: string
{
    case SciFi = 'sci-fi';
    case Fantasy = 'fantasy';
    case Horror = 'horror';
    case Romance = 'romance';
    case Mystery = 'mystery';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

Then use it in your code:

```php
use App\Enums\GenreType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;

// Create a taxonomy with a custom type from enum
$genre = Taxonomy::create([
    'name' => 'Science Fiction',
    'type' => GenreType::SciFi->value,
    'description' => 'Science fiction genre',
]);

// Find taxonomies by custom type from enum
$sciFiBooks = Taxonomy::findByType(GenreType::SciFi);
```

## 🎯 Real-World Usage Scenarios

For comprehensive examples of how to use Laravel Taxonomy in real-world applications, please refer to our detailed scenario documentation:

### Available Scenarios

1. **[E-commerce Product Catalog](docs/en/ecommerce-product-catalog.md)** - Building a comprehensive e-commerce platform with hierarchical categories, product tags, dynamic navigation, and advanced filtering.

2. **[Content Management System](docs/en/content-management-system.md)** - Creating a flexible CMS with content categorization, tagging, filtering, and SEO optimization.

3. **[Learning Management System](docs/en/learning-management-system.md)** - Developing an educational platform with courses, skills, difficulty levels, and personalized learning paths.

4. **[Multi-tenant Business Application](docs/en/multi-tenant-business-application.md)** - Building a SaaS platform with tenant-specific taxonomies, project management, and customizable workflows.

5. **[Analytics and Reporting](docs/en/analytics-and-reporting.md)** - Implementing comprehensive analytics, reporting dashboards, and automated insights using taxonomy data.

Each scenario includes:

-   Complete code examples
-   Database setup and migrations
-   Controller implementations
-   Service layer patterns

## 🚀 Advanced Features

### 🔄 Nested Set Model

Laravel Taxonomy uses the Nested Set Model for efficient hierarchical data management:

```php
// Get all descendants of a taxonomy
$category = Taxonomy::find(1);
$descendants = $category->getDescendants();

// Get all ancestors of a taxonomy
$ancestors = $category->getAncestors();

// Get siblings
$siblings = $category->getSiblings();

// Check if taxonomy is descendant of another
$isDescendant = $category->isDescendantOf($parentCategory);
```

### Performance Optimization

**Caching Strategies**:

```php
// Cache taxonomy trees for better performance
class CachedTaxonomyService
{
    public function getCachedTree(string $type, int $ttl = 3600): Collection
    {
        return Cache::remember("taxonomy_tree_{$type}", $ttl, function () use ($type) {
            return Taxonomy::tree($type);
        });
    }

    public function invalidateCache(string $type): void
    {
        Cache::forget("taxonomy_tree_{$type}");
    }

    public function warmCache(): void
    {
        $types = Taxonomy::distinct('type')->pluck('type');

        foreach ($types as $type) {
            $this->getCachedTree($type);
        }
    }
}

// Use in your models
class Product extends Model
{
    use HasTaxonomy;

    protected static function booted()
    {
        static::saved(function ($product) {
            // Invalidate related caches when product taxonomies change
            $types = $product->taxonomies->pluck('type')->unique();
            foreach ($types as $type) {
                Cache::forget("taxonomy_tree_{$type}");
            }
        });
    }
}
```

**Eager Loading for Performance**:

```php
// Efficient loading of taxonomies with models
$products = Product::with([
    'taxonomies' => function ($query) {
        $query->select('id', 'name', 'slug', 'type', 'meta')
              ->orderBy('type')
              ->orderBy('name');
    }
])->get();

// Load specific taxonomy types only
$products = Product::with([
    'taxonomies' => function ($query) {
        $query->whereIn('type', ['category', 'brand']);
    }
])->get();

// Preload taxonomy counts
$categories = Taxonomy::where('type', 'category')
    ->withCount(['models as product_count' => function ($query) {
        $query->where('taxonomable_type', Product::class);
    }])
    ->get();
```

### Advanced Querying

**Complex Taxonomy Filters**:

```php
class ProductFilterService
{
    public function filterByTaxonomies(array $filters): Builder
    {
        $query = Product::query();

        // Filter by multiple categories (OR condition)
        if (!empty($filters['categories'])) {
            $query->withAnyTaxonomies($filters['categories']);
        }

        // Filter by required tags (AND condition)
        if (!empty($filters['required_tags'])) {
            $query->withAllTaxonomies($filters['required_tags']);
        }

        // Filter by brand (exact match)
        if (!empty($filters['brand'])) {
            $query->withTaxonomy($filters['brand']);
        }

        // Filter by price range taxonomy
        if (!empty($filters['price_range'])) {
            $priceRange = Taxonomy::findBySlug($filters['price_range'], 'price_range');
            if ($priceRange) {
                $min = $priceRange->meta['min_price'] ?? 0;
                $max = $priceRange->meta['max_price'] ?? PHP_INT_MAX;
                $query->whereBetween('price', [$min, $max]);
            }
        }

        // Exclude certain taxonomies
        if (!empty($filters['exclude'])) {
            $query->withoutTaxonomies($filters['exclude']);
        }

        return $query;
    }

    public function getFilterOptions(array $currentFilters = []): array
    {
        $baseQuery = $this->filterByTaxonomies($currentFilters);

        return [
            'categories' => $this->getAvailableOptions($baseQuery, 'category'),
            'brands' => $this->getAvailableOptions($baseQuery, 'brand'),
            'tags' => $this->getAvailableOptions($baseQuery, 'tag'),
            'price_ranges' => $this->getAvailableOptions($baseQuery, 'price_range'),
        ];
    }

    private function getAvailableOptions(Builder $query, string $type): Collection
    {
        return Taxonomy::where('type', $type)
            ->whereHas('models', function ($q) use ($query) {
                $q->whereIn('taxonomable_id', $query->pluck('id'));
            })
            ->withCount('models')
            ->orderBy('models_count', 'desc')
            ->get();
    }
}
```

### Data Import/Export

**Import/Export Functionality**:

```php
class TaxonomyImportExportService
{
    public function exportToJson(string $type = null): string
    {
        $query = Taxonomy::with('children');

        if ($type) {
            $query->where('type', $type);
        }

        $taxonomies = $query->whereNull('parent_id')
            ->orderBy('lft')
            ->get();

        return json_encode($this->buildExportTree($taxonomies), JSON_PRETTY_PRINT);
    }

    public function importFromJson(string $json, bool $replaceExisting = false): array
    {
        $data = json_decode($json, true);
        $imported = [];
        $errors = [];

        DB::transaction(function () use ($data, $replaceExisting, &$imported, &$errors) {
            foreach ($data as $item) {
                try {
                    $taxonomy = $this->importTaxonomyItem($item, null, $replaceExisting);
                    $imported[] = $taxonomy->id;
                } catch (Exception $e) {
                    $errors[] = [
                        'item' => $item['name'] ?? 'Unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        });

        return [
            'imported' => count($imported),
            'errors' => $errors,
            'taxonomy_ids' => $imported,
        ];
    }

    private function buildExportTree(Collection $taxonomies): array
    {
        return $taxonomies->map(function ($taxonomy) {
            $item = [
                'name' => $taxonomy->name,
                'slug' => $taxonomy->slug,
                'type' => $taxonomy->type,
                'description' => $taxonomy->description,
                'meta' => $taxonomy->meta,
                'sort_order' => $taxonomy->sort_order,
            ];

            if ($taxonomy->children->isNotEmpty()) {
                $item['children'] = $this->buildExportTree($taxonomy->children);
            }

            return $item;
        })->toArray();
    }

    private function importTaxonomyItem(array $item, ?int $parentId, bool $replaceExisting): Taxonomy
    {
        $existing = null;

        if ($replaceExisting) {
            $existing = Taxonomy::where('slug', $item['slug'])
                ->where('type', $item['type'])
                ->first();
        }

        $taxonomy = $existing ?: new Taxonomy();

        $taxonomy->fill([
            'name' => $item['name'],
            'slug' => $item['slug'],
            'type' => $item['type'],
            'description' => $item['description'] ?? null,
            'parent_id' => $parentId,
            'meta' => $item['meta'] ?? [],
            'sort_order' => $item['sort_order'] ?? 0,
        ]);

        $taxonomy->save();

        // Import children
        if (!empty($item['children'])) {
            foreach ($item['children'] as $child) {
                $this->importTaxonomyItem($child, $taxonomy->id, $replaceExisting);
            }
        }

        return $taxonomy;
    }

    public function exportToCsv(string $type): string
    {
        $taxonomies = Taxonomy::where('type', $type)
            ->with('parent')
            ->orderBy('lft')
            ->get();

        $csv = "Name,Slug,Type,Parent,Description,Meta\n";

        foreach ($taxonomies as $taxonomy) {
            $csv .= sprintf(
                "\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"\n",
                $taxonomy->name,
                $taxonomy->slug,
                $taxonomy->type,
                $taxonomy->parent?->name ?? '',
                $taxonomy->description ?? '',
                json_encode($taxonomy->meta)
            );
        }

        return $csv;
    }
}
```

## 📋 Best Practices

### 1. **Taxonomy Design Principles**

```php
// ✅ Good: Clear, specific taxonomy types
class TaxonomyTypes
{
    const PRODUCT_CATEGORY = 'product_category';
    const PRODUCT_TAG = 'product_tag';
    const CONTENT_CATEGORY = 'content_category';
    const USER_SKILL = 'user_skill';
}

// ❌ Avoid: Generic, ambiguous types
// 'category', 'tag', 'type' - too generic
```

### 2. **Metadata Best Practices**

```php
// ✅ Good: Structured metadata with validation
class CategoryMetadata
{
    public static function validate(array $metadata): array
    {
        return Validator::make($metadata, [
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'featured' => 'boolean',
            'seo_title' => 'nullable|string|max:60',
            'seo_description' => 'nullable|string|max:160',
        ])->validated();
    }
}

// Usage
$category = Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyTypes::PRODUCT_CATEGORY,
    'meta' => CategoryMetadata::validate([
        'icon' => 'laptop',
        'color' => '#007bff',
        'featured' => true,
    ]),
]);
```

### 3. **Performance Optimization**

```php
// ✅ Good: Efficient querying with proper indexing
class OptimizedTaxonomyQueries
{
    public function getProductsByCategory(string $categorySlug): Collection
    {
        return Product::select(['id', 'name', 'price', 'slug'])
            ->withTaxonomy(
                Taxonomy::where('slug', $categorySlug)
                    ->where('type', TaxonomyTypes::PRODUCT_CATEGORY)
                    ->first()
            )
            ->with(['taxonomies' => function ($query) {
                $query->select(['id', 'name', 'slug', 'type'])
                      ->whereIn('type', [TaxonomyTypes::PRODUCT_TAG, 'brand']);
            }])
            ->limit(20)
            ->get();
    }

    // ✅ Good: Batch operations for better performance
    public function attachCategoriesInBatch(Collection $products, array $categoryIds): void
    {
        $products->chunk(100)->each(function ($chunk) use ($categoryIds) {
            foreach ($chunk as $product) {
                $product->attachTaxonomies($categoryIds);
            }
        });
    }
}
```

### 4. **Error Handling and Validation**

```php
class TaxonomyService
{
    public function createWithValidation(array $data): Taxonomy
    {
        $validator = Validator::make($data, [
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:50',
            'parent_id' => 'nullable|exists:taxonomies,id',
            'meta' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Check for circular references
        if (isset($data['parent_id'])) {
            $this->validateNoCircularReference($data['parent_id'], $data);
        }

        return Taxonomy::create($validator->validated());
    }

    private function validateNoCircularReference(int $parentId, array $data): void
    {
        $parent = Taxonomy::find($parentId);

        if (!$parent) {
            throw new InvalidArgumentException('Parent taxonomy not found');
        }

        // Check if parent type matches (optional business rule)
        if ($parent->type !== $data['type']) {
            throw new InvalidArgumentException('Parent must be of the same type');
        }

        // Prevent deep nesting (optional business rule)
        if ($parent->depth >= 5) {
            throw new InvalidArgumentException('Maximum nesting depth exceeded');
        }
    }
}
```

### 5. **Testing Strategies**

```php
class TaxonomyTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTestTaxonomies();
    }

    private function createTestTaxonomies(): void
    {
        $this->electronics = Taxonomy::create([
            'name' => 'Electronics',
            'type' => 'category',
        ]);

        $this->smartphones = Taxonomy::create([
            'name' => 'Smartphones',
            'type' => 'category',
            'parent_id' => $this->electronics->id,
        ]);
    }

    /** @test */
    public function it_can_attach_taxonomies_to_models(): void
    {
        $product = Product::factory()->create();

        $product->attachTaxonomy($this->electronics);

        $this->assertTrue($product->hasTaxonomy($this->electronics));
        $this->assertCount(1, $product->taxonomies);
    }

    /** @test */
    public function it_maintains_nested_set_integrity(): void
    {
        $this->electronics->rebuildNestedSet();

        $this->electronics->refresh();
        $this->smartphones->refresh();

        $this->assertEquals(1, $this->electronics->lft);
        $this->assertEquals(4, $this->electronics->rgt);
        $this->assertEquals(2, $this->smartphones->lft);
        $this->assertEquals(3, $this->smartphones->rgt);
    }
}
```

## Custom Slugs and Error Handling

The package provides robust error handling for slug generation and uniqueness:

### Manual Slug Management

When `slugs.generate` is set to `false` in the configuration, you must provide slugs manually:

```php
// This will throw MissingSlugException if slugs.generate is false
$taxonomy = Taxonomy::create([
    'name' => 'Test Category',
    'type' => TaxonomyType::Category->value,
    // Missing slug will cause an exception
]);

// Correct way when slugs.generate is false
$taxonomy = Taxonomy::create([
    'name' => 'Test Category',
    'type' => TaxonomyType::Category->value,
    'slug' => 'test-category', // Manually provided slug
]);
```

### Slug Uniqueness (Composite Unique)

Starting from v3.0, slug uniqueness is enforced within the same taxonomy type (composite unique constraint):

```php
// This will work - different types can have same slug
$taxonomy1 = Taxonomy::create([
    'name' => 'Featured Category',
    'slug' => 'featured',
    'type' => TaxonomyType::Category->value,
]);

$taxonomy2 = Taxonomy::create([
    'name' => 'Featured Tag',
    'slug' => 'featured', // Same slug, different type - OK!
    'type' => TaxonomyType::Tag->value,
]);

// This will throw DuplicateSlugException - same type, same slug
$taxonomy3 = Taxonomy::create([
    'name' => 'Another Featured Category',
    'slug' => 'featured', // Duplicate within same type
    'type' => TaxonomyType::Category->value,
]);
```

### Duplicate Slug Detection

```php
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;

try {
    Taxonomy::create([
        'name' => 'Another Featured Category',
        'slug' => 'featured', // Duplicate within same type
        'type' => TaxonomyType::Category->value,
    ]);
} catch (DuplicateSlugException $e) {
    // Handle duplicate slug error within the same type
    return response()->json([
        'error' => 'A taxonomy with this slug already exists in this type.',
        'slug' => $e->getSlug(),
        'type' => $e->getType(), // Available in v3.0+
    ], 422);
}
```

### Exception Handling

The package provides the following exceptions:

-   `MissingSlugException`: Thrown when a slug is required but not provided
-   `DuplicateSlugException`: Thrown when a slug already exists and a unique slug is required

You can catch these exceptions to provide custom error handling:

```php
use Aliziodev\LaravelTaxonomy\Exceptions\MissingSlugException;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;

try {
    $taxonomy = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);
} catch (MissingSlugException $e) {
    // Handle missing slug error
    return back()->withErrors(['slug' => 'A slug is required.']);
} catch (DuplicateSlugException $e) {
    // Handle duplicate slug error
    return back()->withErrors(['slug' => 'This slug already exists. Please choose another.']);
}
```

## Troubleshooting

### Common Issues

#### Taxonomy Not Found

If you're having trouble finding a taxonomy by slug, make sure the slug is correct and consider using the `exists` method to check if it exists:

```php
if (Taxonomy::exists('electronics')) {
    $taxonomy = Taxonomy::findBySlug('electronics');
}
```

#### Relationship Issues

If you're having trouble with relationships, make sure you're using the correct morph type in your configuration. If you're using UUIDs or ULIDs for your models, make sure to set the `morph_type` configuration accordingly.

#### Cache Issues

If you're not seeing updated data after making changes, you might need to clear the cache:

```php
\Illuminate\Support\Facades\Cache::flush();
```

## Security

The Laravel Taxonomy package follows good security practices:

-   It uses prepared statements for all database queries to prevent SQL injection
-   It validates input data before processing
-   It uses Laravel's built-in protection mechanisms

If you discover any security issues, please email the author at aliziodev@gmail.com instead of using the issue tracker.

## Testing

The package includes comprehensive tests. You can run them with:

```bash
composer test

// or

vendor/bin/pest
```

## 📝 Automatic Changelog

This package uses **automated changelog generation** based on [Conventional Commits](https://www.conventionalcommits.org/) and [Semantic Versioning](https://semver.org/).

### How It Works

-   **Commit Analysis**: Every commit message is analyzed to determine the type of change
-   **Automatic Versioning**: Version numbers are automatically determined based on commit types
-   **Changelog Generation**: `CHANGELOG.md` is automatically updated with release notes
-   **GitHub Releases**: Releases are automatically created with detailed release notes

### Commit Message Format

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

**Examples:**

```bash
feat: add moveToParent method with performance optimization
fix: resolve nested set corruption on concurrent operations
feat!: change taxonomy structure for multi-tenancy support
```

### Release Types

| Commit Type                          | Release Type  | Example                   |
| ------------------------------------ | ------------- | ------------------------- |
| `fix:`                               | Patch (1.0.1) | Bug fixes                 |
| `feat:`                              | Minor (1.1.0) | New features              |
| `feat!:` or `BREAKING CHANGE:`       | Major (2.0.0) | Breaking changes          |
| `docs:`, `style:`, `test:`, `chore:` | No Release    | Documentation, formatting |

### Automated Workflows

-   **Auto Changelog**: Triggered on every push to main branch
-   **Commitlint**: Validates commit messages on PRs and pushes
-   **Release Creation**: Automatically creates GitHub releases with changelogs

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details on our automated changelog system and development workflow.

## License

The Laravel Taxonomy package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
