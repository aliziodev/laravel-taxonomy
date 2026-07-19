<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Covers the patterns in docs/en/. Product stands in for both Product and
 * Article; the taxonomy API is identical.
 */
function docsFixtures(): object
{
    $f = new stdClass;
    $f->electronics = Taxonomy::create(['name' => 'Electronics', 'type' => TaxonomyType::Category]);
    $f->phones = Taxonomy::create([
        'name' => 'Smartphones',
        'type' => TaxonomyType::Category,
        'parent_id' => $f->electronics->id,
    ]);
    $f->android = Taxonomy::create([
        'name' => 'Android',
        'type' => TaxonomyType::Category,
        'parent_id' => $f->phones->id,
    ]);

    $f->red = Taxonomy::create(['name' => 'Red', 'type' => 'color', 'meta' => ['hex' => '#ef4444']]);
    $f->bestseller = Taxonomy::create(['name' => 'Bestseller', 'type' => TaxonomyType::Tag]);

    $f->product = Product::create(['name' => 'Pixel', 'price' => 100]);

    return $f;
}

it('assigns per type without disturbing other types', function () {
    $f = docsFixtures();

    $f->product->syncTaxonomiesOfType(TaxonomyType::Category, [$f->android->id]);
    $f->product->syncTaxonomiesOfType('color', [$f->red->id]);
    $f->product->attachTaxonomiesOfType(TaxonomyType::Tag, [$f->bestseller->id]);

    expect($f->product->taxonomies()->count())->toBe(3);

    // Re-syncing categories leaves colour and tag alone.
    $f->product->syncTaxonomiesOfType(TaxonomyType::Category, [$f->phones->id]);

    expect($f->product->taxonomies()->count())->toBe(3)
        ->and($f->product->taxonomiesOfType('color'))->toHaveCount(1);
});

it('browses a category including its descendants', function () {
    $f = docsFixtures();

    $f->product->attachTaxonomies([$f->android->id]);

    // Android sits two levels under Electronics.
    expect(Product::withTaxonomyHierarchy($f->phones->id)->count())->toBe(1)
        ->and(Product::withTaxonomy([$f->phones->id])->count())->toBe(0);
});

it('runs the faceted filter examples', function () {
    $f = docsFixtures();

    $f->product->attachTaxonomies([$f->android->id, $f->red->id]);

    expect(Product::filterByTaxonomies(['category' => 'android'])->count())->toBe(1)
        ->and(Product::filterByTaxonomies(['color' => ['red', 'blue']])->count())->toBe(1);

    expect(
        Product::withTaxonomySlug('android', TaxonomyType::Category)
            ->withAnyTaxonomiesOfType('color', [$f->red->id])
            ->withoutTaxonomiesOfType(TaxonomyType::Tag, [$f->bestseller->id])
            ->count()
    )->toBe(1);
});

it('builds a breadcrumb trail', function () {
    $f = docsFixtures();

    expect($f->android->path)->toBe('Electronics > Smartphones > Android');

    $trail = $f->android->ancestors()->reverse()->push($f->android);

    expect($trail->pluck('name')->all())->toBe(['Electronics', 'Smartphones', 'Android']);
});

it('lists top level categories', function () {
    $f = docsFixtures();

    expect(TaxonomyModel::type(TaxonomyType::Category)->root()->ordered()->get())->toHaveCount(1);
});

it('reuses a term via createOrUpdate when tags arrive as names', function () {
    $f = docsFixtures();

    $ids = collect(['Laravel', 'PHP', 'Laravel'])
        ->map(fn (string $name) => Taxonomy::createOrUpdate([
            'name' => $name,
            'type' => TaxonomyType::Tag->value,
        ])->id);

    $f->product->syncTaxonomiesOfType(TaxonomyType::Tag, $ids);

    // 'Laravel' twice must not create two terms.
    expect(TaxonomyModel::where('type', 'tag')->where('slug', 'laravel')->count())->toBe(1)
        ->and($f->product->taxonomiesOfType(TaxonomyType::Tag))->toHaveCount(2);
});

it('finds related records sharing a tag', function () {
    $f = docsFixtures();

    $other = Product::create(['name' => 'Other', 'price' => 50]);
    $f->product->attachTaxonomiesOfType(TaxonomyType::Tag, [$f->bestseller->id]);
    $other->attachTaxonomiesOfType(TaxonomyType::Tag, [$f->bestseller->id]);

    $tagIds = $f->product->taxonomiesOfType(TaxonomyType::Tag)->pluck('id');

    $related = Product::withAnyTaxonomiesOfType(TaxonomyType::Tag, $tagIds)
        ->whereKeyNot($f->product->getKey())
        ->get();

    expect($related->pluck('id')->all())->toBe([$other->id]);
});

it('counts usage over the pivot table', function () {
    $f = docsFixtures();

    $f->product->attachTaxonomies([$f->android->id, $f->red->id]);

    $usage = DB::table(config('taxonomy.table_names.taxonomables'))
        ->where('taxonomable_type', (new Product)->getMorphClass())
        ->selectRaw('taxonomy_id, count(*) as total')
        ->groupBy('taxonomy_id')
        ->pluck('total', 'taxonomy_id');

    expect($usage[$f->android->id])->toBe(1)
        ->and($usage->has($f->bestseller->id))->toBeFalse();
});

it('queries nested meta used for SEO', function () {
    $f = docsFixtures();

    Taxonomy::create([
        'name' => 'News',
        'type' => TaxonomyType::Category,
        'meta' => ['seo' => ['title' => 'Latest News', 'description' => 'Updates.']],
    ]);

    $news = Taxonomy::findBySlug('news', TaxonomyType::Category);

    expect($news->meta['seo']['title'])->toBe('Latest News')
        ->and(TaxonomyModel::where('meta->seo->title', '!=', null)->count())->toBe(1);
});
