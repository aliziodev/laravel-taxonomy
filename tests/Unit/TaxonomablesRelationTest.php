<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomable;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\CustomTaxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\Models\ProductWithCustomKey;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| taxonomables() reaches the pivot rows of every model class
|--------------------------------------------------------------------------
| morphedByMany() is bound to one class, so counting or filtering terms by
| usage across models meant querying the pivot table by hand.
*/

beforeEach(function () {
    Schema::create('products_custom_key', function (Blueprint $table) {
        $table->string('product_code')->primary();
        $table->string('name');
        $table->timestamps();
    });
});

function taxonomablesFixtures(): object
{
    $f = new stdClass;
    $f->used = Taxonomy::create(['name' => 'Used', 'type' => TaxonomyType::Tag]);
    $f->unused = Taxonomy::create(['name' => 'Unused', 'type' => TaxonomyType::Tag]);

    $f->product = Product::create(['name' => 'Phone']);
    $f->other = ProductWithCustomKey::create(['product_code' => 'P-1', 'name' => 'Case']);

    $f->product->attachTaxonomies($f->used);
    $f->other->attachTaxonomies($f->used);

    return $f;
}

it('counts the uses of a taxonomy across every model class', function () {
    $f = taxonomablesFixtures();

    $counts = Taxonomy::withCount('taxonomables')->orderBy('id')->pluck('taxonomables_count', 'name');

    expect($counts->all())->toBe(['Used' => 2, 'Unused' => 0]);
});

it('finds the taxonomies that nothing uses', function () {
    taxonomablesFixtures();

    expect(Taxonomy::whereDoesntHave('taxonomables')->pluck('name')->all())->toBe(['Unused']);
});

it('resolves the attached models of each class', function () {
    $f = taxonomablesFixtures();

    $models = $f->used->taxonomables()->with('taxonomable')->orderBy('id')->get()->pluck('taxonomable');

    expect($models)->toHaveCount(2)
        ->and($models->first())->toBeInstanceOf(Product::class)
        ->and($models->last())->toBeInstanceOf(ProductWithCustomKey::class);
});

it('belongs to the configured taxonomy model', function () {
    config(['taxonomy.model' => CustomTaxonomy::class]);
    $f = taxonomablesFixtures();

    $row = Taxonomable::query()->firstOrFail();

    expect($row->taxonomy)->toBeInstanceOf(CustomTaxonomy::class)
        ->and($row->taxonomy->is($f->used))->toBeTrue();
});

it('uses the configured pivot table', function () {
    config(['taxonomy.table_names.taxonomables' => 'custom_taxonomables']);

    expect((new Taxonomy)->taxonomables()->getRelated()->getTable())->toBe('custom_taxonomables');
});
