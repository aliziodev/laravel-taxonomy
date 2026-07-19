---
name: laravel-taxonomy
description: "Use when working with aliziodev/laravel-taxonomy in a Laravel project. Trigger whenever the query mentions taxonomy taxonomies categories tags terms or hierarchical term structures backed by this package. Tasks include creating taxonomy terms attaching them to models with the HasTaxonomy trait filtering models by taxonomy with query scopes working with parent child hierarchies and nested sets bulk importing terms configuring slugs and caching and isolating taxonomy caches per tenant. Do not trigger for generic Eloquent relationships Laravel categories built by hand or other taxonomy packages."
license: MIT
metadata:
  author: aliziodev
---

# Laravel Taxonomy

Categories, tags and hierarchical terms. Terms live in one `taxonomies` table,
attach to any model through a polymorphic `taxonomables` pivot, and hierarchies
are kept as a nested set so ancestor/descendant lookups are one query.

## Two different `Taxonomy` classes

This is the most common mistake. They are not interchangeable.

```php
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;            // create, find, tree
use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;  // query building
```

The facade proxies `TaxonomyManager`. It has **no** `where()`, `whereIn()`,
`withCount()` or any other Eloquent builder method. For queries, use the model.

There is also **no `models` relation** on the taxonomy model. To count records
per term, count from the other side or aggregate over the pivot.

## Creating terms

```php
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

$electronics = Taxonomy::create([
    'name' => 'Electronics',
    'type' => TaxonomyType::Category,   // or any string: 'color', 'season'
    'meta' => ['icon' => 'devices'],
]);

$phones = Taxonomy::create([
    'name'      => 'Smartphones',
    'type'      => TaxonomyType::Category,
    'parent_id' => $electronics->id,
]);
```

`type` accepts the enum or a plain string; custom types need no registration.
Slugs are generated from the name and are unique **within a type**, so a
`featured` category and a `featured` tag can coexist.

## Attaching to models

```php
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;

class Product extends Model
{
    use HasTaxonomy;
}
```

```php
$product->attachTaxonomies([$phones->id]);   // add
$product->syncTaxonomies([$a->id, $b->id]);  // replace all
$product->detachTaxonomies([$a->id]);
$product->toggleTaxonomies([$a->id]);
```

**These take ids or model instances, never slugs.** Passing a slug string
attaches nothing and raises no error. Resolve it first:

```php
$tag = Taxonomy::findBySlug('featured', TaxonomyType::Tag);
$product->attachTaxonomies([$tag->id]);
```

Every method has an `*OfType` twin that touches only one type and leaves the
model's other taxonomies alone — use these when a model carries several types:

```php
$product->syncTaxonomiesOfType(TaxonomyType::Category, [$catId]);  // tags untouched
$product->attachTaxonomiesOfType(TaxonomyType::Tag, [$tagId]);
$product->detachTaxonomiesOfType(TaxonomyType::Tag);
```

## Filtering models

```php
Product::withTaxonomy($ids)->get();                  // has any of
Product::withAllTaxonomies($ids)->get();             // has every one
Product::withoutTaxonomies($ids)->get();             // has none of
Product::withTaxonomyType(TaxonomyType::Category)->get();
Product::withTaxonomySlug('smartphones', TaxonomyType::Category)->get();
Product::withAnyTaxonomiesOfType(TaxonomyType::Tag, $ids)->get();
Product::withTaxonomyHierarchy($categoryId)->get();  // term plus its descendants
Product::orderByTaxonomyType(TaxonomyType::Category, 'asc', 'name')->get();
```

Chaining scopes ANDs them. For request-driven filters:

```php
Product::filterByTaxonomies([
    'category' => 'smartphones',    // type => slug
    'color'    => ['red', 'blue'],  // OR within the type
    'exclude'  => $excludedIds,
])->get();
```

## Hierarchies

```php
$node->children;            // relation
$node->parent;              // relation
$node->ancestors();         // follows parent_id, always correct
$node->descendants();       // follows parent_id, always correct
$node->getAncestors();      // nested set, single query, fastest
$node->getDescendants();    // nested set, single query, fastest
$node->path;                // "Electronics > Smartphones"
$node->moveToParent($id);   // throws on a circular move

Taxonomy::getNestedTree(TaxonomyType::Category);  // full tree, one query, cached
Taxonomy::flatTree(TaxonomyType::Category);       // flat list with `depth`
```

**`getDescendants()`/`getAncestors()` read `lft`/`rgt` off the instance.**
Adding a child widens the parent's `rgt` in the database, so a parent object
loaded beforehand returns an empty collection. Call `refresh()` first, or use
`descendants()`, which is keyed on the id.

## Bulk imports

Looping over `create()` costs four to seven queries per row. For seeders and
imports use `bulkCreate()` — measured at 0.95s and 32 queries for 10,000 rows,
against 14.8s and 40,000 queries for the loop:

```php
Taxonomy::bulkCreate([
    ['name' => 'Electronics', 'type' => TaxonomyType::Category],
    ['name' => 'Fiction', 'type' => TaxonomyType::Category, 'parent_id' => $booksId],
]);
```

It accepts any iterable, so a generator keeps memory flat. It generates slugs,
fills the nested set and clears the cache — but **does not fire model events**.
Code relying on observers should keep using `create()`.

Never insert taxonomies with `DB::table('taxonomies')->insert()`: that bypasses
the model hooks, leaving `lft`/`rgt`/`depth` null and the tree unusable. If you
must, call `Taxonomy::rebuildNestedSet($type)` afterwards.

## Caching

`tree()`, `flatTree()` and `getNestedTree()` are cached and invalidated
automatically on every write. Do not wrap them in another `Cache::remember()`;
the second layer will not see the invalidation and will serve stale trees.

## Multi-tenancy

Cache keys are global unless a scope is registered, so one tenant can be served
another tenant's tree. Register a resolver:

```php
use Aliziodev\LaravelTaxonomy\TaxonomyManager;

// AppServiceProvider::boot()
TaxonomyManager::resolveCacheScopeUsing(fn () => tenant()?->getKey());
```

The package ships no `tenant_id` column. If you add one, also replace the
`unique(['slug', 'type', 'deleted_at'])` index, which otherwise stops two
tenants using the same slug within a type.

## Deprecated

The optional `$name` relationship parameter on every method is deprecated and
will be removed in 3.0. It selects pivot morph columns the migration does not
create. Use taxonomy **types** to separate categories from tags.
