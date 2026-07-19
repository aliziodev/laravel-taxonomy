<p align="center"><img src="https://raw.githubusercontent.com/aliziodev/laravel-taxonomy/refs/heads/master/art/new-header.svg" width="400" alt="Laravel Taxonomy"></p>

<p align="center">
  <a href="https://codecov.io/gh/aliziodev/laravel-taxonomy"><img src="https://codecov.io/gh/aliziodev/laravel-taxonomy/branch/master/graph/badge.svg" alt="codecov"></a>
  <a href="https://github.com/aliziodev/laravel-taxonomy/actions"><img src="https://github.com/aliziodev/laravel-taxonomy/workflows/Tests/badge.svg" alt="Tests"></a>
  <a href="https://github.com/aliziodev/laravel-taxonomy/actions"><img src="https://github.com/aliziodev/laravel-taxonomy/workflows/Code%20Quality/badge.svg" alt="Code Quality"></a>
</br>
  <a href="https://packagist.org/packages/aliziodev/laravel-taxonomy"><img src="https://img.shields.io/packagist/v/aliziodev/laravel-taxonomy.svg" alt="Latest Version on Packagist"></a>
  <a href="https://packagist.org/packages/aliziodev/laravel-taxonomy"><img src="https://img.shields.io/packagist/dt/aliziodev/laravel-taxonomy.svg" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/aliziodev/laravel-taxonomy"><img src="https://img.shields.io/packagist/php-v/aliziodev/laravel-taxonomy.svg" alt="PHP Version"></a>
  <a href="https://laravel.com/"><img src="https://img.shields.io/badge/Laravel-11%20%7C%2012%20%7C%2013-orange.svg" alt="Laravel Version"></a>
  <a href="https://deepwiki.com/aliziodev/laravel-taxonomy"><img src="https://deepwiki.com/badge.svg" alt="Ask DeepWiki"></a>
</p>

Manage categories, tags and any hierarchical structure in Laravel. Terms live in one table, attach to any model through a polymorphic pivot, and hierarchies are maintained as a nested set so ancestor and descendant lookups stay a single query.

[🇮🇩 Dokumentasi Bahasa Indonesia](README.id.md)

## Contents

- [Requirements](#requirements) · [Installation](#installation) · [Configuration](#configuration)
- [Quick start](#quick-start) · [Working with taxonomies](#working-with-taxonomies) · [Attaching to models](#attaching-to-models)
- [Query scopes](#query-scopes) · [Hierarchies](#hierarchies) · [Types](#types) · [Metadata](#metadata)
- [Caching](#caching) · [Multi-tenancy](#multi-tenancy) · [Slugs and exceptions](#slugs-and-exceptions)
- [Console commands](#console-commands) · [Examples](#examples) · [Troubleshooting](#troubleshooting)

## Requirements

| Requirement | Version        |
|-------------|----------------|
| PHP         | 8.2 or newer   |
| Laravel     | 11, 12 or 13   |

## Installation

```bash
composer require aliziodev/laravel-taxonomy
php artisan taxonomy:install
php artisan migrate
```

`taxonomy:install` publishes the config and the migration. Pass `--force` to overwrite files that already exist — without it, existing files are left untouched and the command tells you so.

To publish individually:

```bash
php artisan vendor:publish --tag=taxonomy-config
php artisan vendor:publish --tag=taxonomy-migrations
```

## Configuration

`config/taxonomy.php`, with the shipped defaults:

```php
return [
    'table_names' => [
        'taxonomies'   => 'taxonomies',
        'taxonomables' => 'taxonomables',
    ],

    // Morph column type on the pivot: 'numeric', 'uuid' or 'ulid'.
    // Must match how YOUR models are keyed. Set before the first migrate.
    'morph_type' => 'uuid',

    'types' => collect(TaxonomyType::cases())->pluck('value')->toArray(),

    // Swap in your own model; it must extend the package's Taxonomy.
    'model' => Taxonomy::class,

    'slugs' => [
        'generate'               => true,  // auto-generate from name when omitted
        'regenerate_on_update'   => true,  // rewrite the slug when the name changes
        'consider_trashed'       => false, // count soft-deleted rows when checking uniqueness
        'regenerate_on_restore'  => true,  // resolve a conflict on restore instead of throwing
    ],

    'cache' => [
        'ttl'   => 86400, // seconds
        'scope' => null,  // see Multi-tenancy
    ],

    'migrations' => [
        'autoload' => env('TAXONOMY_AUTOLOAD_MIGRATIONS', true),
        'paths'    => [],
    ],
];
```

**`morph_type` is the one setting to get right up front.** It decides whether the pivot stores `taxonomable_id` as an integer, UUID or ULID, and it cannot be changed after you migrate without rewriting the table. Use `numeric` for the usual auto-incrementing keys.

**`migrations.autoload`** controls whether the package registers its migration path with `php artisan migrate`. Disable it when you run migrations per tenant connection:

```php
'migrations' => ['autoload' => false],
```

```bash
php artisan migrate --path=database/migrations/tenants --database=tenant
```

## Quick start

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

$electronics = Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyType::Category,
    'meta' => ['icon' => 'devices'],
]);

$phones = Taxonomy::create([
    'name'      => 'Smartphones',
    'type'      => TaxonomyType::Category,
    'parent_id' => $electronics->id,
]);
```

Add the trait to any model that should carry taxonomies:

```php
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;

class Product extends Model
{
    use HasTaxonomy;
}
```

```php
$product->attachTaxonomies([$phones->id]);
$product->taxonomies;                                  // all attached terms
Product::withTaxonomySlug('smartphones')->get();       // filter by slug
```

## Working with taxonomies

The `Taxonomy` facade proxies `TaxonomyManager` and exposes exactly these methods:

```php
Taxonomy::create($attributes);                   // create
Taxonomy::createOrUpdate($attributes);           // create, or update a matching slug+type
Taxonomy::find($id);
Taxonomy::findMany($ids, $perPage = null, $page = 1);
Taxonomy::findBySlug('smartphones', TaxonomyType::Category);
Taxonomy::findByType(TaxonomyType::Category, $perPage = null, $page = 1);
Taxonomy::findByParent($parentId, $perPage = null, $page = 1);
Taxonomy::search('phone', TaxonomyType::Category, $perPage = null, $page = 1);
Taxonomy::exists('smartphones', TaxonomyType::Category);
Taxonomy::getTypes();                            // Support\Collection<string>

Taxonomy::tree($type = null, $parentId = null);        // nested, one level of children
Taxonomy::flatTree($type = null, $parentId = null);    // flat, each node carries `depth`
Taxonomy::getNestedTree($type = null);                 // fully nested, via the nested set

Taxonomy::getDescendants($taxonomyId);
Taxonomy::getAncestors($taxonomyId);
Taxonomy::moveToParent($taxonomyId, $parentId);
Taxonomy::rebuildNestedSet($type);
Taxonomy::clearCacheForType($type);
```

> The facade is **not** an Eloquent builder. `Taxonomy::where(...)` and friends do not exist on it. For query building, import the model instead:
>
> ```php
> use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
>
> TaxonomyModel::type(TaxonomyType::Category)->ordered()->get();
> ```

Model scopes: `type()`, `root()`, `ordered()`, `roots()`, `atDepth()`, `nestedSetOrder()`.

## Attaching to models

```php
$product->attachTaxonomies([$a->id, $b->id]);   // add, keep existing
$product->syncTaxonomies([$a->id, $b->id]);     // replace all
$product->detachTaxonomies([$a->id]);           // remove some
$product->detachTaxonomies();                   // remove all
$product->toggleTaxonomies([$a->id]);           // flip
```

Each accepts an id, a `Taxonomy`, an array, or any collection:

```php
$product->attachTaxonomies($taxonomy);
$product->attachTaxonomies(collect([$a, $b]));
$product->attachTaxonomies(TaxonomyModel::type('category')->pluck('id'));
```

> These take taxonomy **ids or models**, not slugs. Passing a slug string attaches nothing. Resolve it first: `Taxonomy::findBySlug('featured', TaxonomyType::Tag)`.

Reading and checking:

```php
$product->taxonomies;                                    // relation
$product->taxonomiesOfType(TaxonomyType::Category);      // Collection
$product->getFirstTaxonomyOfType(TaxonomyType::Category);
$product->getTaxonomyCountByType(TaxonomyType::Tag);

$product->hasTaxonomies([$a->id]);                       // any of
$product->hasAllTaxonomies([$a->id, $b->id]);            // all of
$product->hasTaxonomyType(TaxonomyType::Category);       // any of this type
```

### Type-specific variants

Every attach/detach/sync/toggle has an `*OfType` twin that only touches terms of one type, leaving the rest of the model's taxonomies alone:

```php
$product->syncTaxonomiesOfType(TaxonomyType::Category, [$catA->id]);   // tags untouched
$product->attachTaxonomiesOfType(TaxonomyType::Tag, [$tagA->id]);
$product->detachTaxonomiesOfType(TaxonomyType::Tag);                   // all tags
$product->toggleTaxonomiesOfType(TaxonomyType::Tag, [$tagA->id]);

$product->hasTaxonomiesOfType(TaxonomyType::Tag, [$tagA->id]);
$product->hasAllTaxonomiesOfType(TaxonomyType::Tag, [$tagA->id]);
```

Ids that are not of the given type are skipped — that filtering is the point of these methods.

## Query scopes

```php
Product::withTaxonomy($ids)->get();               // has any of
Product::withAnyTaxonomies($ids)->get();          // has any of
Product::withAllTaxonomies($ids)->get();          // has every one
Product::withoutTaxonomies($ids)->get();          // has none of
Product::withTaxonomyType(TaxonomyType::Category)->get();
Product::withTaxonomySlug('smartphones', TaxonomyType::Category)->get();

Product::withAnyTaxonomiesOfType(TaxonomyType::Tag, $ids)->get();
Product::withAllTaxonomiesOfType(TaxonomyType::Tag, $ids)->get();
Product::withoutTaxonomiesOfType(TaxonomyType::Tag, $ids)->get();

Product::withTaxonomyHierarchy($categoryId)->get();          // term + its descendants
Product::withTaxonomyAtDepth(1, TaxonomyType::Category)->get();
Product::orderByTaxonomyType(TaxonomyType::Category, 'asc', 'name')->get();
```

Scopes chain, so combining them is an AND:

```php
Product::withTaxonomySlug('smartphones', TaxonomyType::Category)
    ->withAnyTaxonomiesOfType(TaxonomyType::Tag, $featuredIds)
    ->get();
```

`filterByTaxonomies()` takes a keyed array, handy for request filters:

```php
Product::filterByTaxonomies([
    'category' => 'smartphones',        // type => slug
    'color'    => ['red', 'blue'],      // OR within the type
    'exclude'  => $discontinuedIds,
])->get();
```

## Hierarchies

Hierarchy is stored twice: as `parent_id`, and as nested-set `lft`/`rgt`/`depth` columns kept in sync automatically on create, update, delete and restore.

```php
$node->parent;              // relation
$node->children;            // relation, ordered by sort_order
$node->ancestors();         // walks parent_id — correct even if lft/rgt drifted
$node->descendants();       // depth-first, one query per level
$node->getAncestors();      // nested set, single query
$node->getDescendants();    // nested set, single query
$node->getSiblings();
$node->getChildren();

$node->isAncestorOf($other);
$node->isDescendantOf($other);
$node->getLevel();          // depth, 0 for roots

$node->path;                // "Electronics > Smartphones"
$node->full_slug;           // "electronics/smartphones"

$node->moveToParent($newParentId);   // throws on a circular move
```

`getAncestors()`/`getDescendants()` read `lft`/`rgt` and are the fastest option. `ancestors()`/`descendants()` follow `parent_id` and stay correct even if the nested set has drifted — use them if you write to the table outside the model.

> **Refresh before using the nested-set variants on a model you already held.** Adding a child widens its parent's `rgt` in the database, and an instance loaded before that still carries the old bounds — `getDescendants()` then returns an empty collection with no error:
>
> ```php
> $parent = Taxonomy::create(['name' => 'Electronics', 'type' => 'category']);
> Taxonomy::create(['name' => 'Phones', 'type' => 'category', 'parent_id' => $parent->id]);
>
> $parent->getDescendants();            // empty — $parent->rgt is stale
> $parent->refresh()->getDescendants(); // 1
> ```
>
> `descendants()` is keyed on the id, so it is immune to this.

Trees:

```php
Taxonomy::tree(TaxonomyType::Category);          // roots with one level eager-loaded
Taxonomy::flatTree(TaxonomyType::Category);      // flat list, `depth` set on each node
Taxonomy::getNestedTree(TaxonomyType::Category); // full depth, `children_nested` + `tree_depth`
```

## Types

`TaxonomyType` ships `Category`, `Tag`, `Color`, `Size`, `Unit`, `Type`, `Brand`, `Model`, `Variant`. Everywhere a type is accepted you may pass the enum or a plain string, so custom types need no registration:

```php
Taxonomy::create(['name' => 'Winter', 'type' => 'season']);
Product::withTaxonomyType('season')->get();
```

List them in config so tooling and the rebuild command know about them:

```php
'types' => ['category', 'tag', 'season', 'department'],
```

Enum helpers:

```php
TaxonomyType::values();               // ['category', 'tag', ...]
TaxonomyType::options();              // [['value' => ..., 'label' => ...], ...]
TaxonomyType::Category->label();      // 'Category'
TaxonomyType::Category->getLabel();   // same, named for Filament's HasLabel contract
```

## Metadata

`meta` is a JSON column, cast to array:

```php
Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyType::Category,
    'meta' => ['icon' => 'devices', 'color' => '#3498db', 'featured' => true],
]);

$taxonomy->meta['icon'];

TaxonomyModel::where('meta->featured', true)->get();
TaxonomyModel::whereJsonContains('meta->tags', 'sale')->get();
```

There is no translation layer; `meta` is a reasonable home for translations:

```php
'meta' => ['translations' => ['id' => ['name' => 'Elektronik']]],
```

## Caching

`tree()`, `flatTree()` and `getNestedTree()` are cached for `cache.ttl` (24 hours by default) and invalidated automatically whenever a taxonomy is created, updated, deleted, restored, moved, or rebuilt.

```php
Taxonomy::clearCacheForType(TaxonomyType::Category);   // manual, rarely needed
```

Invalidation works by bumping a version key, so entries expire logically rather than being enumerated and deleted — that keeps it correct on cache stores without tag support.

> Do not wrap these calls in another `Cache::remember()`. A second, unversioned layer will not see the package's invalidation and will serve stale trees.

## Multi-tenancy

Two things need attention.

**1. Isolate the cache.** Cache keys are global unless you say otherwise, so without a scope one tenant can be served another tenant's tree. Register a resolver:

```php
use Aliziodev\LaravelTaxonomy\TaxonomyManager;

// AppServiceProvider::boot()
TaxonomyManager::resolveCacheScopeUsing(fn () => tenant()?->getKey());
```

Or point `taxonomy.cache.scope` at an invokable class — a class name rather than a closure, so the config survives `php artisan config:cache`:

```php
class TenantCacheScope
{
    public function __invoke(): ?string
    {
        return tenant()?->getKey();
    }
}
```

With no scope registered the keys are unchanged from earlier releases, so single-tenant apps need no action.

**2. Scope the data yourself.** The package ships no `tenant_id` column. Add one, and replace the unique index — the shipped `unique(['slug', 'type', 'deleted_at'])` otherwise stops two tenants using the same slug within a type:

```php
Schema::table('taxonomies', function (Blueprint $table) {
    $table->dropUnique(['slug', 'type', 'deleted_at']);
    $table->foreignId('tenant_id')->nullable()->index();
    $table->unique(['tenant_id', 'slug', 'type', 'deleted_at']);
});
```

Then point the package at a model carrying your scope:

```php
namespace App\Models;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy as BaseTaxonomy;

class Taxonomy extends BaseTaxonomy
{
    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('tenant', function ($query) {
            if ($tenantId = tenant()?->getKey()) {
                $query->where('tenant_id', $tenantId);
            }
        });
    }
}
```

```php
'model' => \App\Models\Taxonomy::class,
```

It must extend the package's `Taxonomy` — that is where slug generation and nested-set maintenance live.

> The `*OfType` methods validate the ids you pass **without** applying global scopes, so a taxonomy shared across tenants is not silently discarded. The flip side: an id from another tenant will attach if your application passes it through. Validate user input, e.g. `Rule::exists()` scoped to the tenant.

## Slugs and exceptions

Slugs are generated from the name and are unique **within a type**, so a `featured` category and a `featured` tag can coexist.

```php
Taxonomy::create(['name' => 'Electronics', 'type' => TaxonomyType::Category]);           // 'electronics'
Taxonomy::create(['name' => 'Electronics', 'type' => TaxonomyType::Category]);           // 'electronics-1'
Taxonomy::create(['name' => 'Electronics', 'slug' => 'custom', 'type' => 'category']);   // 'custom'
```

Two exceptions, both extending `TaxonomyException`:

```php
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Exceptions\MissingSlugException;

try {
    Taxonomy::create(['name' => 'Electronics', 'slug' => 'taken', 'type' => 'category']);
} catch (DuplicateSlugException $e) {
    $e->getSlug();   // 'taken'
    $e->getType();   // 'category'
}
```

`MissingSlugException` is thrown when `slugs.generate` is `false` and no slug was supplied.

Soft deletes interact with uniqueness through two settings: `consider_trashed` decides whether trashed rows block a slug, and `regenerate_on_restore` decides whether restoring a row with a now-taken slug renames it or throws.

## Console commands

```bash
php artisan taxonomy:install [--force]
php artisan taxonomy:rebuild-nested-set [type] [--force]
```

`taxonomy:rebuild-nested-set` recomputes `lft`, `rgt` and `depth`. You need it only if rows were written outside the model — direct SQL, a raw seeder, a bulk import. It rebuilds every type when no type is given, uses one transaction per type, and clears the caches afterwards. `--force` is required when running non-interactively.

## Examples

- [E-commerce product catalog](docs/en/ecommerce-product-catalog.md)
- [Content management system](docs/en/content-management-system.md)

## Troubleshooting

**Attaching does nothing, no error.** You are probably passing slugs. These methods take ids or models; resolve slugs with `Taxonomy::findBySlug()` first.

**`Call to undefined method ... where()` on the facade.** The facade proxies `TaxonomyManager`, not Eloquent. Import `Models\Taxonomy` for query building.

**Wrong column type on the pivot.** `morph_type` must match your models' keys and is fixed at migration time. Check it before your first `migrate`.

**Tree looks stale.** It should invalidate itself; if you write rows with raw SQL, call `Taxonomy::clearCacheForType()` and, if `lft`/`rgt` are involved, `php artisan taxonomy:rebuild-nested-set`.

**Ancestors or descendants look wrong.** The nested set has drifted, usually from direct SQL writes. Run the rebuild command, or use `ancestors()`/`descendants()`, which follow `parent_id`.

## Upgrading

See [UPGRADE.md](UPGRADE.md). Notable in 2.11: cache isolation for multi-tenant apps, and custom relationship names are deprecated for removal in 3.0.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Commits follow Conventional Commits; releases and the changelog are generated from them.

```bash
composer test      # Pest
composer analyse   # PHPStan
composer format    # Pint
```

## Security

Report vulnerabilities to <aliziodev@gmail.com> rather than the public tracker.

## License

MIT. See [LICENSE](LICENSE).
