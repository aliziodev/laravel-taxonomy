[![en](https://img.shields.io/badge/lang-en-red.svg)](README.md)
[![id](https://img.shields.io/badge/lang-id-blue.svg)](README.id.md)

# Laravel Taxonomy

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aliziodev/laravel-taxonomy.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![Total Downloads](https://img.shields.io/packagist/dt/aliziodev/laravel-taxonomy.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![License](https://img.shields.io/packagist/l/aliziodev/laravel-taxonomy.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![PHP Version](https://img.shields.io/packagist/php-v/aliziodev/laravel-taxonomy.svg?style=flat-square)](https://packagist.org/packages/aliziodev/laravel-taxonomy)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.0%2B-orange.svg?style=flat-square)](https://laravel.com/)

Laravel Taxonomy is a powerful and flexible package for managing taxonomies, categories, tags, and hierarchical terms in Laravel applications. It provides a robust solution for organizing content with features like metadata support, ordering capabilities, and efficient caching mechanisms.

## Overview

This package is ideal for:

- E-commerce category management
- Blog taxonomies
- Content organization
- Product attributes
- Dynamic navigation
- Any hierarchical data structure

## Key Features

- **Hierarchical Terms**: Create parent-child relationships between terms
- **Metadata Support**: Store additional data as JSON with each taxonomy
- **Term Ordering**: Control the order of terms with sort_order
- **Caching System**: Improve performance with built-in caching
- **Polymorphic Relationships**: Associate taxonomies with any model
- **Multiple Term Types**: Use predefined types (Category, Tag, etc.) or create custom types
- **Bulk Operations**: Attach, detach, sync, or toggle multiple taxonomies at once
- **Advanced Querying**: Filter models by taxonomies with query scopes
- **Tree Structure**: Get hierarchical or flat tree representations
- **Pagination Support**: Paginate results for better performance

## Requirements

- PHP 8.1+
- Laravel 11.0+

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

## Configuration

After publishing the configuration file, you can customize it in `config/taxonomy.php`:

```php
return [
    // Table names for taxonomies and taxonomables
    'table_names' => [
        'taxonomies' => 'taxonomies',
        'taxonomables' => 'taxonomables',
    ],

    // Morph type configuration (numeric, uuid, ulid)
    'morph_type' => 'uuid',

    // Default taxonomy types
    'types' => collect(TaxonomyType::cases())->pluck('value')->toArray(),

    // Model binding for the Taxonomy model
    'model' => \Aliziodev\LaravelTaxonomy\Models\Taxonomy::class,

    // Slug configuration
    'slugs' => [
        'generate' => true,        // If false, slugs must be provided manually
        'regenerate_on_update' => false,  // If true, slugs will be regenerated when name changes
    ],
];
```

## Custom Taxonomy Types

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

### Slug Uniqueness

The package ensures that all slugs are unique across all taxonomy types:

```php
// This will throw DuplicateSlugException if the slug already exists
$taxonomy1 = Taxonomy::create([
    'name' => 'First Category',
    'slug' => 'unique-slug',
    'type' => TaxonomyType::Category->value,
]);

// This will throw DuplicateSlugException because the slug already exists
$taxonomy2 = Taxonomy::create([
    'name' => 'Second Category',
    'slug' => 'unique-slug', // Duplicate slug
    'type' => TaxonomyType::Tag->value, // Even with different type
]);
```

### Exception Handling

The package provides the following exceptions:

- `MissingSlugException`: Thrown when a slug is required but not provided
- `DuplicateSlugException`: Thrown when a slug already exists and a unique slug is required

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

## Usage

### Using the Taxonomy Facade

The Taxonomy facade provides a convenient way to work with taxonomies:

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

// Create a taxonomy
$category = Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyType::Category->value,
    'description' => 'Electronic products',
]);

// Create a taxonomy with parent
$smartphone = Taxonomy::create([
    'name' => 'Smartphones',
    'type' => TaxonomyType::Category->value,
    'parent_id' => $category->id,
]);

// Find a taxonomy by slug
$found = Taxonomy::findBySlug('electronics');

// Check if a taxonomy exists
$exists = Taxonomy::exists('electronics');

// Search for taxonomies
$results = Taxonomy::search('electronic');

// Get taxonomy types
$types = Taxonomy::getTypes();

// Get a hierarchical tree
$tree = Taxonomy::tree(TaxonomyType::Category);

// Get a flat tree with depth information
$flatTree = Taxonomy::flatTree(TaxonomyType::Category);
```

### Using the HasTaxonomy Trait

Add the `HasTaxonomy` trait to your models to associate them with taxonomies:

```php
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasTaxonomy;

    // ...
}
```

Then you can use the following methods:

```php
// Get a product
$product = Product::find(1);

// Attach taxonomies
$product->attachTaxonomies($category);
$product->attachTaxonomies([$category, $tag]);

// Detach taxonomies
$product->detachTaxonomies($category);
$product->detachTaxonomies(); // Detach all

// Sync taxonomies (remove existing and add new)
$product->syncTaxonomies([$category, $tag]);

// Toggle taxonomies (add if not exists, remove if exists)
$product->toggleTaxonomies([$category, $tag]);

// Check if product has taxonomies
$product->hasTaxonomies($category);
$product->hasAllTaxonomies([$category, $tag]);
$product->hasTaxonomyType(TaxonomyType::Category);

// Get taxonomies of a specific type
$categories = $product->taxonomiesOfType(TaxonomyType::Category);
```

### Query Scopes

The `HasTaxonomy` trait also provides query scopes:

```php
// Find products with any of the given taxonomies
$products = Product::withAnyTaxonomies([$category, $tag])->get();

// Find products with all of the given taxonomies
$products = Product::withAllTaxonomies([$category, $tag])->get();

// Find products with a specific taxonomy type
$products = Product::withTaxonomyType(TaxonomyType::Category)->get();
```

### Pagination

The package supports pagination for search and find methods:

```php
// Paginate search results (5 items per page, page 1)
$results = Taxonomy::search('electronic', null, 5, 1);

// Paginate taxonomies by type
$categories = Taxonomy::findByType(TaxonomyType::Category, 10, 1);

// Paginate taxonomies by parent
$children = Taxonomy::findByParent($parent->id, 10, 1);
```

### Controller Example

Here's a complete example of using Laravel Taxonomy in a controller:

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

        // Get all categories for the filter sidebar
        $categories = Taxonomy::findByType(TaxonomyType::Category);

        return view('products.index', compact('products', 'categories'));
    }

    public function show(Product $product)
    {
        // Get the product's categories
        $categories = $product->taxonomiesOfType(TaxonomyType::Category);

        // Get related products that share the same categories
        $relatedProducts = Product::withAnyTaxonomies($categories)
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get();

        return view('products.show', compact('product', 'categories', 'relatedProducts'));
    }

    public function create()
    {
        // Get all categories for the product form
        $categories = Taxonomy::tree(TaxonomyType::Category);
        $tags = Taxonomy::findByType(TaxonomyType::Tag);

        return view('products.create', compact('categories', 'tags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'categories' => 'required|array',
            'tags' => 'nullable|array',
        ]);

        $product = Product::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
        ]);

        // Attach categories and tags
        $product->attachTaxonomies($validated['categories']);

        if (isset($validated['tags'])) {
            $product->attachTaxonomies($validated['tags']);
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Product created successfully.');
    }

    public function edit(Product $product)
    {
        $categories = Taxonomy::tree(TaxonomyType::Category);
        $tags = Taxonomy::findByType(TaxonomyType::Tag);

        $productCategoryIds = $product->taxonomiesOfType(TaxonomyType::Category)->pluck('id')->toArray();
        $productTagIds = $product->taxonomiesOfType(TaxonomyType::Tag)->pluck('id')->toArray();

        return view('products.edit', compact('product', 'categories', 'tags', 'productCategoryIds', 'productTagIds'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric',
            'categories' => 'required|array',
            'tags' => 'nullable|array',
        ]);

        $product->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price' => $validated['price'],
        ]);

        // Sync categories and tags
        $product->syncTaxonomies($validated['categories'], 'taxonomable');

        if (isset($validated['tags'])) {
            $product->syncTaxonomies($validated['tags'], 'taxonomable');
        } else {
            // Remove all tags if none are selected
            $product->detachTaxonomies($product->taxonomiesOfType(TaxonomyType::Tag), 'taxonomable');
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Product updated successfully.');
    }
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

- It uses prepared statements for all database queries to prevent SQL injection
- It validates input data before processing
- It uses Laravel's built-in protection mechanisms

If you discover any security issues, please email the author at aliziodev@gmail.com instead of using the issue tracker.

## Testing

The package includes comprehensive tests. You can run them with:

```bash
composer test
```

## License

The Laravel Taxonomy package is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
