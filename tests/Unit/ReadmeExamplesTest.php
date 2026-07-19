<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy as TaxonomyModel;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

/*
 * Every example in README.md is exercised here. The previous README documented
 * a `models` relation, attaching by slug and an instance-level
 * rebuildNestedSet() -- none of which existed. This file exists so that cannot
 * happen again silently.
 */
function readmeFixtures(): object
{
    $f = new stdClass;
    $f->electronics = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category,
        'meta' => ['icon' => 'devices', 'color' => '#3498db', 'featured' => true],
    ]);

    $f->phones = Taxonomy::create([
        'name' => 'Smartphones',
        'type' => TaxonomyType::Category,
        'parent_id' => $f->electronics->id,
    ]);

    $f->sale = Taxonomy::create(['name' => 'Sale', 'type' => TaxonomyType::Tag]);

    $f->product = Product::create(['name' => 'Phone', 'price' => 100]);

    return $f;
}

it('runs the quick start example', function () {
    $f = readmeFixtures();

    $f->product->attachTaxonomies([$f->phones->id]);

    expect($f->product->taxonomies)->toHaveCount(1)
        ->and(Product::withTaxonomySlug('smartphones')->count())->toBe(1);
});

it('exposes every facade method the README lists', function () {
    $f = readmeFixtures();

    expect(Taxonomy::find($f->phones->id))->not->toBeNull()
        ->and(Taxonomy::findMany([$f->phones->id]))->toHaveCount(1)
        ->and(Taxonomy::findBySlug('smartphones', TaxonomyType::Category))->not->toBeNull()
        ->and(Taxonomy::findByType(TaxonomyType::Category))->toHaveCount(2)
        ->and(Taxonomy::findByParent($f->electronics->id))->toHaveCount(1)
        ->and(Taxonomy::search('phone', TaxonomyType::Category))->toHaveCount(1)
        ->and(Taxonomy::exists('smartphones', TaxonomyType::Category))->toBeTrue()
        ->and(Taxonomy::getTypes())->not->toBeEmpty()
        ->and(Taxonomy::tree(TaxonomyType::Category))->toHaveCount(1)
        ->and(Taxonomy::flatTree(TaxonomyType::Category))->toHaveCount(2)
        ->and(Taxonomy::getNestedTree(TaxonomyType::Category))->toHaveCount(1)
        ->and(Taxonomy::getDescendants($f->electronics->id))->toHaveCount(1)
        ->and(Taxonomy::getAncestors($f->phones->id))->toHaveCount(1);

    Taxonomy::createOrUpdate(['name' => 'Electronics', 'type' => 'category']);
    Taxonomy::rebuildNestedSet('category');
    Taxonomy::clearCacheForType(TaxonomyType::Category);
    Taxonomy::moveToParent($f->phones->id, null);

    expect($f->phones->fresh()->parent_id)->toBeNull();
});

it('exposes every model scope the README lists', function () {
    $f = readmeFixtures();

    expect(TaxonomyModel::type(TaxonomyType::Category)->ordered()->get())->toHaveCount(2)
        ->and(TaxonomyModel::root()->get())->toHaveCount(2)
        ->and(TaxonomyModel::roots()->get())->toHaveCount(2)
        ->and(TaxonomyModel::atDepth(1)->get())->toHaveCount(1)
        ->and(TaxonomyModel::nestedSetOrder()->get())->toHaveCount(3);
});

it('runs the attach, sync, detach and toggle examples', function () {
    $f = readmeFixtures();

    $f->product->attachTaxonomies([$f->phones->id, $f->sale->id]);
    expect($f->product->taxonomies()->count())->toBe(2);

    $f->product->syncTaxonomies([$f->phones->id]);
    expect($f->product->taxonomies()->count())->toBe(1);

    $f->product->toggleTaxonomies([$f->sale->id]);
    expect($f->product->taxonomies()->count())->toBe(2);

    $f->product->detachTaxonomies([$f->sale->id]);
    expect($f->product->taxonomies()->count())->toBe(1);

    $f->product->detachTaxonomies();
    expect($f->product->taxonomies()->count())->toBe(0);
});

it('accepts a model, an array and any collection', function () {
    $f = readmeFixtures();

    $f->product->attachTaxonomies($f->phones);
    $f->product->attachTaxonomies(collect([$f->sale]));
    $f->product->attachTaxonomies(TaxonomyModel::type('category')->pluck('id'));

    expect($f->product->taxonomies()->count())->toBe(3);
});

it('runs the read and check examples', function () {
    $f = readmeFixtures();

    $f->product->attachTaxonomies([$f->phones->id, $f->sale->id]);

    expect($f->product->taxonomiesOfType(TaxonomyType::Category))->toHaveCount(1)
        ->and($f->product->getFirstTaxonomyOfType(TaxonomyType::Category))->not->toBeNull()
        ->and($f->product->getTaxonomyCountByType(TaxonomyType::Tag))->toBe(1)
        ->and($f->product->hasTaxonomies([$f->phones->id]))->toBeTrue()
        ->and($f->product->hasAllTaxonomies([$f->phones->id, $f->sale->id]))->toBeTrue()
        ->and($f->product->hasTaxonomyType(TaxonomyType::Category))->toBeTrue();
});

it('runs the type-specific examples and leaves other types alone', function () {
    $f = readmeFixtures();

    $f->product->attachTaxonomies([$f->sale->id]);

    $f->product->syncTaxonomiesOfType(TaxonomyType::Category, [$f->phones->id]);

    // The tag survives a category-scoped sync.
    expect($f->product->taxonomies()->count())->toBe(2);

    $f->product->attachTaxonomiesOfType(TaxonomyType::Tag, [$f->sale->id]);
    expect($f->product->hasTaxonomiesOfType(TaxonomyType::Tag, [$f->sale->id]))->toBeTrue()
        ->and($f->product->hasAllTaxonomiesOfType(TaxonomyType::Tag, [$f->sale->id]))->toBeTrue();

    $f->product->toggleTaxonomiesOfType(TaxonomyType::Tag, [$f->sale->id]);
    $f->product->detachTaxonomiesOfType(TaxonomyType::Tag);

    expect($f->product->taxonomiesOfType(TaxonomyType::Tag))->toHaveCount(0);
});

it('runs every query scope the README lists', function () {
    $f = readmeFixtures();

    $f->product->attachTaxonomies([$f->phones->id, $f->sale->id]);
    $ids = [$f->phones->id];

    expect(Product::withTaxonomy($ids)->count())->toBe(1)
        ->and(Product::withAnyTaxonomies($ids)->count())->toBe(1)
        ->and(Product::withAllTaxonomies([$f->phones->id, $f->sale->id])->count())->toBe(1)
        ->and(Product::withoutTaxonomies($ids)->count())->toBe(0)
        ->and(Product::withTaxonomyType(TaxonomyType::Category)->count())->toBe(1)
        ->and(Product::withTaxonomySlug('smartphones', TaxonomyType::Category)->count())->toBe(1)
        ->and(Product::withAnyTaxonomiesOfType(TaxonomyType::Tag, [$f->sale->id])->count())->toBe(1)
        ->and(Product::withAllTaxonomiesOfType(TaxonomyType::Tag, [$f->sale->id])->count())->toBe(1)
        ->and(Product::withoutTaxonomiesOfType(TaxonomyType::Tag, [$f->sale->id])->count())->toBe(0)
        ->and(Product::withTaxonomyHierarchy($f->electronics->id)->count())->toBe(1)
        ->and(Product::withTaxonomyAtDepth(1, TaxonomyType::Category)->count())->toBe(1)
        ->and(Product::orderByTaxonomyType(TaxonomyType::Category, 'asc', 'name')->count())->toBe(1);

    // Chained scopes AND together.
    expect(
        Product::withTaxonomySlug('smartphones', TaxonomyType::Category)
            ->withAnyTaxonomiesOfType(TaxonomyType::Tag, [$f->sale->id])
            ->count()
    )->toBe(1);
});

it('runs the filterByTaxonomies example', function () {
    $f = readmeFixtures();

    $f->product->attachTaxonomies([$f->phones->id]);

    expect(Product::filterByTaxonomies(['category' => 'smartphones'])->count())->toBe(1)
        ->and(Product::filterByTaxonomies(['exclude' => [$f->phones->id]])->count())->toBe(0);

    $red = Taxonomy::create(['name' => 'Red', 'type' => 'color']);
    $f->product->attachTaxonomies([$red->id]);

    expect(Product::filterByTaxonomies(['color' => ['red', 'blue']])->count())->toBe(1);
});

it('runs every hierarchy example', function () {
    $f = readmeFixtures();

    // Adding a child widens the parent's rgt in the database, so an instance
    // loaded beforehand holds stale nested-set bounds. The README documents
    // this: refresh before calling getDescendants()/getAncestors().
    $f->electronics->refresh();

    expect($f->phones->parent)->not->toBeNull()
        ->and($f->electronics->children)->toHaveCount(1)
        ->and($f->phones->ancestors())->toHaveCount(1)
        ->and($f->electronics->descendants())->toHaveCount(1)
        ->and($f->phones->getAncestors())->toHaveCount(1)
        ->and($f->electronics->getDescendants())->toHaveCount(1)
        ->and($f->electronics->getSiblings())->toHaveCount(0)
        ->and($f->electronics->getChildren())->toHaveCount(1)
        ->and($f->electronics->isAncestorOf($f->phones))->toBeTrue()
        ->and($f->phones->isDescendantOf($f->electronics))->toBeTrue()
        ->and($f->electronics->getLevel())->toBe(0)
        ->and($f->phones->path)->toBe('Electronics > Smartphones')
        ->and($f->phones->full_slug)->toBe('electronics/smartphones');
});

it('exposes children_nested and tree_depth on getNestedTree', function () {
    $f = readmeFixtures();

    $tree = Taxonomy::getNestedTree(TaxonomyType::Category);
    $root = $tree->first();

    expect($root->tree_depth)->toBe(0)
        ->and($root->children_nested)->toHaveCount(1);
});

it('rejects a circular move', function () {
    $f = readmeFixtures();

    expect(fn () => $f->electronics->moveToParent($f->phones->id))
        ->toThrow(Exception::class);
});

it('accepts custom string types without registration', function () {
    $f = readmeFixtures();

    Taxonomy::create(['name' => 'Winter', 'type' => 'season']);
    $f->product->attachTaxonomies([Taxonomy::findBySlug('winter', 'season')->id]);

    expect(Product::withTaxonomyType('season')->count())->toBe(1);
});

it('runs the enum helper examples', function () {
    $f = readmeFixtures();

    expect(TaxonomyType::values())->toContain('category')
        ->and(TaxonomyType::options()[0])->toHaveKeys(['value', 'label'])
        ->and(TaxonomyType::Category->label())->toBe('Category')
        ->and(TaxonomyType::Category->getLabel())->toBe('Category');
});

it('runs the metadata examples', function () {
    $f = readmeFixtures();

    expect($f->electronics->meta['icon'])->toBe('devices')
        ->and(TaxonomyModel::where('meta->featured', true)->count())->toBe(1);

    TaxonomyModel::where('id', $f->sale->id)->update(['meta' => json_encode(['tags' => ['sale']])]);
    expect(TaxonomyModel::whereJsonContains('meta->tags', 'sale')->count())->toBe(1);
});

it('runs the slug examples', function () {
    $f = readmeFixtures();

    $a = Taxonomy::create(['name' => 'Gadgets', 'type' => TaxonomyType::Category]);
    $b = Taxonomy::create(['name' => 'Gadgets', 'type' => TaxonomyType::Category]);
    $c = Taxonomy::create(['name' => 'Gadgets', 'slug' => 'custom', 'type' => 'category']);

    expect($a->slug)->toBe('gadgets')
        ->and($b->slug)->toBe('gadgets-1')
        ->and($c->slug)->toBe('custom');

    // Same slug across different types is allowed.
    Taxonomy::create(['name' => 'Featured', 'type' => TaxonomyType::Category]);
    Taxonomy::create(['name' => 'Featured', 'type' => TaxonomyType::Tag]);

    expect(TaxonomyModel::where('slug', 'featured')->count())->toBe(2);
});

it('exposes the slug and type on DuplicateSlugException', function () {
    Taxonomy::create(['name' => 'Taken', 'slug' => 'taken', 'type' => 'category']);

    // getSlug()/getType() are asserted in TaxonomyRegressionTest; here we only
    // need the README's claim that a duplicate slug raises this exception.
    expect(fn () => Taxonomy::create(['name' => 'Other', 'slug' => 'taken', 'type' => 'category']))
        ->toThrow(DuplicateSlugException::class);
});
