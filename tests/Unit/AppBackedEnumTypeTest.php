<?php

use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy as TaxonomyFacade;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Type parameters accept an application's own backed enum
|--------------------------------------------------------------------------
| Most methods were typed string|TaxonomyType, so an app that kept its types
| in its own enum had to pass ->value everywhere or hit a TypeError.
*/

enum AppCategoryType: string
{
    case Product = 'product_category';
    case Blog = 'blog_category';
}

enum AppLevelType: int
{
    case Gold = 1;
}

function appEnumFixtures(): object
{
    $f = new stdClass;
    $f->phones = Taxonomy::create(['name' => 'Phones', 'type' => AppCategoryType::Product]);
    $f->android = Taxonomy::create(['name' => 'Android', 'type' => AppCategoryType::Product, 'parent_id' => $f->phones->id]);
    $f->news = Taxonomy::create(['name' => 'News', 'type' => AppCategoryType::Blog]);

    $f->product = Product::create(['name' => 'Pixel']);
    $f->product->attachTaxonomiesOfType(AppCategoryType::Product, [$f->phones->id, $f->android->id, $f->news->id]);

    $f->other = Product::create(['name' => 'Unrelated']);

    return $f;
}

it('accepts an app enum in the model scopes and static helpers', function () {
    $f = appEnumFixtures();

    expect(Taxonomy::type(AppCategoryType::Product)->pluck('name')->sort()->values()->all())->toBe(['Android', 'Phones'])
        ->and(Taxonomy::findBySlug('phones', AppCategoryType::Product)?->is($f->phones))->toBeTrue()
        ->and(Taxonomy::findBySlug('phones', AppCategoryType::Blog))->toBeNull()
        ->and(Taxonomy::tree(AppCategoryType::Product)->pluck('name')->all())->toBe(['Phones'])
        ->and(Taxonomy::flatTree(AppCategoryType::Product)->pluck('name')->all())->toBe(['Phones', 'Android'])
        ->and(Taxonomy::getNestedTree(AppCategoryType::Product)->pluck('name')->all())->toBe(['Phones'])
        ->and(Taxonomy::slugExists('news', AppCategoryType::Blog))->toBeTrue();
});

it('accepts an app enum in the manager', function () {
    $f = appEnumFixtures();

    TaxonomyFacade::rebuildNestedSet(AppCategoryType::Product);
    TaxonomyFacade::clearCacheForType(AppCategoryType::Product);

    expect(TaxonomyFacade::findByType(AppCategoryType::Product)->pluck('name')->sort()->values()->all())->toBe(['Android', 'Phones'])
        ->and(TaxonomyFacade::findBySlug('news', AppCategoryType::Blog)?->is($f->news))->toBeTrue()
        ->and(TaxonomyFacade::exists('news', AppCategoryType::Blog))->toBeTrue()
        ->and(TaxonomyFacade::exists('news', AppCategoryType::Product))->toBeFalse()
        ->and(TaxonomyFacade::search('Pho', AppCategoryType::Product)->pluck('name')->all())->toBe(['Phones'])
        ->and(TaxonomyFacade::tree(AppCategoryType::Product)->pluck('name')->all())->toBe(['Phones'])
        ->and(TaxonomyFacade::flatTree(AppCategoryType::Product)->pluck('name')->all())->toBe(['Phones', 'Android'])
        ->and(TaxonomyFacade::getNestedTree(AppCategoryType::Product)->pluck('name')->all())->toBe(['Phones']);
});

it('accepts an app enum in the trait methods', function () {
    $f = appEnumFixtures();

    // attachTaxonomiesOfType() ignored the News id: it is not of that type.
    expect($f->product->taxonomiesOfType(AppCategoryType::Product)->pluck('name')->sort()->values()->all())->toBe(['Android', 'Phones'])
        ->and($f->product->hasTaxonomyType(AppCategoryType::Product))->toBeTrue()
        ->and($f->product->hasTaxonomyType(AppCategoryType::Blog))->toBeFalse()
        ->and($f->product->hasTaxonomiesOfType(AppCategoryType::Product, [$f->phones->id]))->toBeTrue()
        ->and($f->product->hasAllTaxonomiesOfType(AppCategoryType::Product, [$f->phones->id, $f->android->id]))->toBeTrue()
        ->and($f->product->getTaxonomyCountByType(AppCategoryType::Product))->toBe(2)
        ->and($f->product->getFirstTaxonomyOfType(AppCategoryType::Product))->not->toBeNull()
        ->and($f->product->getHierarchicalTaxonomies(AppCategoryType::Product)->pluck('name')->sort()->values()->all())->toBe(['Android', 'Phones'])
        ->and($f->product->getAncestorTaxonomies(AppCategoryType::Product)->pluck('name')->all())->toBe(['Phones']);

    $f->product->toggleTaxonomiesOfType(AppCategoryType::Product, [$f->android->id]);
    expect($f->product->getTaxonomyCountByType(AppCategoryType::Product))->toBe(1);

    $f->product->syncTaxonomiesOfType(AppCategoryType::Product, [$f->android->id]);
    expect($f->product->taxonomiesOfType(AppCategoryType::Product)->pluck('name')->all())->toBe(['Android']);

    $f->product->detachTaxonomiesOfType(AppCategoryType::Product);
    expect($f->product->hasTaxonomyType(AppCategoryType::Product))->toBeFalse();
});

it('accepts an app enum in the trait query scopes', function () {
    $f = appEnumFixtures();
    $f->other->attachTaxonomiesOfType(AppCategoryType::Product, [$f->android->id]);

    expect(Product::withTaxonomyType(AppCategoryType::Product)->count())->toBe(2)
        ->and(Product::withAnyTaxonomiesOfType(AppCategoryType::Product, [$f->phones->id])->pluck('name')->all())->toBe(['Pixel'])
        ->and(Product::withAllTaxonomiesOfType(AppCategoryType::Product, [$f->phones->id, $f->android->id])->pluck('name')->all())->toBe(['Pixel'])
        ->and(Product::withoutTaxonomiesOfType(AppCategoryType::Product, [$f->phones->id])->pluck('name')->all())->toBe(['Unrelated'])
        ->and(Product::withTaxonomySlug('phones', AppCategoryType::Product)->pluck('name')->all())->toBe(['Pixel'])
        ->and(Product::withTaxonomyAtDepth(1, AppCategoryType::Product)->count())->toBe(2)
        ->and(Product::orderByTaxonomyType(AppCategoryType::Product, 'desc')->value('products.name'))->toBe('Pixel');
});

it('stores an int-backed enum as a string', function () {
    $gold = Taxonomy::create(['name' => 'Gold', 'type' => AppLevelType::Gold]);

    // Before the reload as well: the in-memory value must match a fresh read.
    expect($gold->type)->toBe('1')
        ->and($gold->fresh()?->type)->toBe('1')
        ->and(Taxonomy::type(AppLevelType::Gold)->count())->toBe(1);

    Taxonomy::bulkCreate([['name' => 'Gold bulk', 'type' => AppLevelType::Gold]]);

    expect(Taxonomy::where('type', '1')->count())->toBe(2);
});

it('reports an app enum as its value on DuplicateSlugException', function () {
    expect((new DuplicateSlugException('phones', AppCategoryType::Product))->getType())->toBe('product_category')
        ->and((new DuplicateSlugException('gold', AppLevelType::Gold))->getType())->toBe('1');
});
