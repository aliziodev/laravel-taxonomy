# E-commerce product catalog

**Scenario**: a catalog needs three shapes of classification at once — a nested
category tree, flat attributes like colour and size, and free-form tags. All
three are taxonomy **types**.

```php
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
```

> `Facades\Taxonomy` proxies the manager (create, find, tree, …).
> `Models\Taxonomy` is the Eloquent model, for query building. They are not
> interchangeable — the facade has no `where()`.

## 1. The category tree

```php
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

$android = Taxonomy::create([
    'name'      => 'Android',
    'type'      => TaxonomyType::Category,
    'parent_id' => $phones->id,
]);
```

`parent_id` is all you set; `lft`, `rgt` and `depth` are maintained for you.

## 2. Attributes and tags

Colour and size are flat, so give them their own types and use `meta` and
`sort_order` for presentation:

```php
foreach ([['Red', '#ef4444'], ['Blue', '#3b82f6']] as [$name, $hex]) {
    Taxonomy::create(['name' => $name, 'type' => 'color', 'meta' => ['hex' => $hex]]);
}

foreach (['S', 'M', 'L', 'XL'] as $i => $size) {
    Taxonomy::create(['name' => $size, 'type' => 'size', 'sort_order' => $i]);
}

Taxonomy::create(['name' => 'Bestseller', 'type' => TaxonomyType::Tag]);
```

Custom types need no registration — any string works. Listing them in
`config('taxonomy.types')` only helps tooling and the rebuild command.

## 3. Assigning to a product

```php
class Product extends Model
{
    use HasTaxonomy;
}

$product->syncTaxonomiesOfType(TaxonomyType::Category, [$android->id]);
$product->syncTaxonomiesOfType('color', $colorIds);
$product->attachTaxonomiesOfType(TaxonomyType::Tag, [$bestsellerId]);
```

The `*OfType` variants only touch one type, so re-syncing categories leaves
colours and tags untouched. They take **ids or models**, never slugs — resolve
a slug first with `Taxonomy::findBySlug('android', TaxonomyType::Category)`.

## 4. Browsing a category

Shoppers expect a category page to include everything nested beneath it.
`withTaxonomyHierarchy()` covers the term plus its descendants:

```php
Product::withTaxonomyHierarchy($phones->id)->paginate(24);   // includes Android
Product::withTaxonomy([$phones->id])->paginate(24);          // that exact term only
```

## 5. Faceted filtering

`filterByTaxonomies()` maps a request payload straight onto types. Values
within one type are OR-ed; separate types are AND-ed:

```php
// ?category=android&color[]=red&color[]=blue
Product::filterByTaxonomies([
    'category' => $request->input('category'),   // type => slug
    'color'    => $request->input('color', []),  // red OR blue
])->paginate(24);
```

For explicit control, use the scopes directly:

```php
Product::withTaxonomySlug('android', TaxonomyType::Category)
    ->withAnyTaxonomiesOfType('color', $colorIds)
    ->withAllTaxonomiesOfType('size', $sizeIds)
    ->withoutTaxonomiesOfType(TaxonomyType::Tag, $discontinuedIds)
    ->paginate(24);
```

## 6. Navigation and breadcrumbs

```php
// Menu: full tree, one query, cached
$menu = Taxonomy::getNestedTree(TaxonomyType::Category);

// Breadcrumb for the current category
$category->path;   // "Electronics > Smartphones > Android"

$trail = $category->ancestors()->reverse()->push($category);
```

## 7. Counting products per category

`Taxonomy` has no `models` relation, so count from the product side:

```php
$total = Product::withTaxonomyHierarchy($phones->id)->count();
```

Or aggregate over the pivot for a whole level at once:

```php
$countsByTaxonomy = DB::table(config('taxonomy.table_names.taxonomables'))
    ->where('taxonomable_type', (new Product)->getMorphClass())
    ->whereIn('taxonomy_id', $categoryIds)
    ->selectRaw('taxonomy_id, count(*) as total')
    ->groupBy('taxonomy_id')
    ->pluck('total', 'taxonomy_id');
```

Read the table name from config rather than hard-coding it; both the table
names and the morph column type are configurable.
