# Content management system

**Scenario**: articles filed under a category tree, tagged freely, and surfaced
through archives and related-content listings.

```php
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
```

## 1. Categories and tags

An article usually belongs to **one** category but carries **many** tags. Both
are taxonomies; only the type differs.

```php
$news = Taxonomy::create([
    'name' => 'News',
    'type' => TaxonomyType::Category,
    'meta' => ['icon' => 'newspaper', 'colour' => '#2563eb'],
]);

$releases = Taxonomy::create([
    'name'      => 'Product Releases',
    'type'      => TaxonomyType::Category,
    'parent_id' => $news->id,
]);

foreach (['Laravel', 'PHP', 'Testing'] as $tag) {
    Taxonomy::create(['name' => $tag, 'type' => TaxonomyType::Tag]);
}
```

```php
class Article extends Model
{
    use HasTaxonomy;
}
```

## 2. Publishing an article

```php
// One category, replacing whatever was there
$article->syncTaxonomiesOfType(TaxonomyType::Category, [$releases->id]);

// Tags, leaving the category alone
$article->syncTaxonomiesOfType(TaxonomyType::Tag, $tagIds);
```

If the editor supplies tag names rather than ids, create-or-fetch them first —
attaching a raw string attaches nothing:

```php
$tagIds = collect($request->input('tags', []))
    ->map(fn (string $name) => Taxonomy::createOrUpdate([
        'name' => $name,
        'type' => TaxonomyType::Tag->value,
    ])->id);

$article->syncTaxonomiesOfType(TaxonomyType::Tag, $tagIds);
```

`createOrUpdate()` matches on slug + type, so repeated names reuse one term.

## 3. Category and tag archives

```php
// Everything in a category, including its child categories
Article::withTaxonomyHierarchy($news->id)
    ->where('status', 'published')
    ->latest()
    ->paginate(15);

// A single tag
Article::withTaxonomySlug($slug, TaxonomyType::Tag)
    ->where('status', 'published')
    ->paginate(15);

// Articles matching every one of several tags
Article::withAllTaxonomiesOfType(TaxonomyType::Tag, $tagIds)->get();
```

## 4. Related articles

Share-a-tag is a decent proxy for relatedness:

```php
$tagIds = $article->taxonomiesOfType(TaxonomyType::Tag)->pluck('id');

$related = Article::withAnyTaxonomiesOfType(TaxonomyType::Tag, $tagIds)
    ->whereKeyNot($article->getKey())
    ->where('status', 'published')
    ->limit(5)
    ->get();
```

## 5. Navigation

```php
// Category menu: full depth, one query, cached
$menu = Taxonomy::getNestedTree(TaxonomyType::Category);

// Top-level only
TaxonomyModel::type(TaxonomyType::Category)->root()->ordered()->get();

// Breadcrumb
$category->path;                                        // "News > Product Releases"
$category->ancestors()->reverse()->push($category);     // Collection
```

## 6. A tag cloud

Weight by usage, counted over the pivot:

```php
$usage = DB::table(config('taxonomy.table_names.taxonomables'))
    ->where('taxonomable_type', (new Article)->getMorphClass())
    ->selectRaw('taxonomy_id, count(*) as total')
    ->groupBy('taxonomy_id')
    ->pluck('total', 'taxonomy_id');

$cloud = TaxonomyModel::type(TaxonomyType::Tag)
    ->whereIn('id', $usage->keys())
    ->get()
    ->map(fn ($tag) => [
        'name'  => $tag->name,
        'slug'  => $tag->slug,
        'count' => $usage[$tag->id] ?? 0,
    ])
    ->sortByDesc('count');
```

There is no `models` relation on `Taxonomy`, so counts come from the pivot or
from the article side — and the table name comes from config, never a literal.

## 7. SEO metadata

`meta` is a JSON column, which makes it a natural home for per-term SEO fields:

```php
Taxonomy::create([
    'name' => 'News',
    'type' => TaxonomyType::Category,
    'meta' => [
        'seo' => [
            'title'       => 'Latest News and Updates',
            'description' => 'Stay current with our announcements.',
        ],
    ],
]);

$category->meta['seo']['title'] ?? $category->name;

// Queryable
TaxonomyModel::where('meta->seo->title', '!=', null)->get();
```
