<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // Create a test model table
    Schema::create('test_models', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->timestamps();
        $table->softDeletes();
    });
});

it('can attach taxonomies of specific type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Attach categories
    $model->attachTaxonomiesOfType(TaxonomyType::Category, [$category1->id, $category2->id]);

    expect($model->taxonomiesOfType(TaxonomyType::Category))->toHaveCount(2);
    expect($model->hasTaxonomies($category1))->toBeTrue();
    expect($model->hasTaxonomies($category2))->toBeTrue();
    expect($model->hasTaxonomies($tag))->toBeFalse();
});

it('ignores taxonomies of different type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Try to attach tag as category - should be ignored
    $model->attachTaxonomiesOfType(TaxonomyType::Category, [$category->id, $tag->id]);

    expect($model->taxonomiesOfType(TaxonomyType::Category))->toHaveCount(1);
    expect($model->hasTaxonomies($category))->toBeTrue();
    expect($model->hasTaxonomies($tag))->toBeFalse();
});

it('handles empty array gracefully', function () {
    $model = Product::create(['name' => 'Test Product']);

    $model->attachTaxonomiesOfType(TaxonomyType::Category, []);

    expect($model->taxonomiesOfType(TaxonomyType::Category))->toHaveCount(0);
});

it('can detach specific taxonomies of type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model->attachTaxonomies([$category1, $category2, $tag]);

    // Detach only one category
    $model->detachTaxonomiesOfType(TaxonomyType::Category, [$category1->id]);

    expect($model->hasTaxonomies($category1))->toBeFalse();
    expect($model->hasTaxonomies($category2))->toBeTrue();
    expect($model->hasTaxonomies($tag))->toBeTrue();
});

it('can detach all taxonomies of type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model->attachTaxonomies([$category1, $category2, $tag]);

    // Detach all categories
    $model->detachTaxonomiesOfType(TaxonomyType::Category);

    expect($model->hasTaxonomies($category1))->toBeFalse();
    expect($model->hasTaxonomies($category2))->toBeFalse();
    expect($model->hasTaxonomies($tag))->toBeTrue();
});

it('handles non-existent taxonomies gracefully', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomies($category);

    // Try to detach non-existent taxonomy
    $model->detachTaxonomiesOfType(TaxonomyType::Category, [999]);

    expect($model->hasTaxonomies($category))->toBeTrue();
});

it('can sync taxonomies of specific type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $category3 = Taxonomy::create([
        'name' => 'Laptops',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Initially attach some taxonomies
    $model->attachTaxonomies([$category1, $tag]);

    // Sync categories - should replace category1 with category2 and category3
    $model->syncTaxonomiesOfType(TaxonomyType::Category, [$category2->id, $category3->id]);

    expect($model->hasTaxonomies($category1))->toBeFalse();
    expect($model->hasTaxonomies($category2))->toBeTrue();
    expect($model->hasTaxonomies($category3))->toBeTrue();
    expect($model->hasTaxonomies($tag))->toBeTrue(); // Tag should remain
});

it('ignores taxonomies of different type during sync', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Try to sync categories with a tag - tag should be ignored
    $model->syncTaxonomiesOfType(TaxonomyType::Category, [$category->id, $tag->id]);

    expect($model->taxonomiesOfType(TaxonomyType::Category))->toHaveCount(1);
    expect($model->hasTaxonomies($category))->toBeTrue();
    expect($model->hasTaxonomies($tag))->toBeFalse();
});

it('can toggle taxonomies of specific type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    // Initially attach category1
    $model->attachTaxonomies($category1);

    // Toggle both categories - should detach category1 and attach category2
    $model->toggleTaxonomiesOfType(TaxonomyType::Category, [$category1->id, $category2->id]);

    expect($model->hasTaxonomies($category1))->toBeFalse();
    expect($model->hasTaxonomies($category2))->toBeTrue();
});

it('ignores taxonomies of different type during toggle', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Try to toggle categories with a tag - tag should be ignored
    $model->toggleTaxonomiesOfType(TaxonomyType::Category, [$category->id, $tag->id]);

    expect($model->hasTaxonomies($category))->toBeTrue();
    expect($model->hasTaxonomies($tag))->toBeFalse();
});

it('returns true when model has any of the given taxonomies of type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomies($category1);

    expect($model->hasTaxonomiesOfType(TaxonomyType::Category, [$category1->id, $category2->id]))->toBeTrue();
    expect($model->hasTaxonomiesOfType(TaxonomyType::Category, [$category2->id]))->toBeFalse();
});

it('returns false for taxonomies attach of different type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model->attachTaxonomies($tag);

    expect($model->hasTaxonomiesOfType(TaxonomyType::Category, [$tag->id]))->toBeFalse();
});

it('returns true when model has all given taxonomies of type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomies([$category1, $category2]);

    expect($model->hasAllTaxonomiesOfType(TaxonomyType::Category, [$category1->id, $category2->id]))->toBeTrue();
    expect($model->hasAllTaxonomiesOfType(TaxonomyType::Category, [$category1->id]))->toBeTrue();
});

it('returns false when model missing some taxonomies', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomies($category1);

    expect($model->hasAllTaxonomiesOfType(TaxonomyType::Category, [$category1->id, $category2->id]))->toBeFalse();
});

it('returns false for taxonomies of different type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model->attachTaxonomies($tag);

    expect($model->hasAllTaxonomiesOfType(TaxonomyType::Category, [$tag->id]))->toBeFalse();
});

it('returns correct count of taxonomies by type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model->attachTaxonomies([$category1, $category2, $tag]);

    expect($model->getTaxonomyCountByType(TaxonomyType::Category))->toBe(2);
    expect($model->getTaxonomyCountByType(TaxonomyType::Tag))->toBe(1);
    expect($model->getTaxonomyCountByType('nonexistent'))->toBe(0);
});

it('returns first taxonomy of specified type', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model->attachTaxonomies([$category1, $category2, $tag]);

    $firstCategory = $model->getFirstTaxonomyOfType(TaxonomyType::Category);
    $firstTag = $model->getFirstTaxonomyOfType(TaxonomyType::Tag);

    expect($firstCategory)->toBeInstanceOf(Taxonomy::class);
    expect($firstCategory->type)->toBe(TaxonomyType::Category->value);
    expect($firstTag)->toBeInstanceOf(Taxonomy::class);
    expect($firstTag->type)->toBe(TaxonomyType::Tag->value);
});

it('returns null when no taxonomy of type exists', function () {
    $model = Product::create(['name' => 'Test Product']);

    expect($model->getFirstTaxonomyOfType(TaxonomyType::Category))->toBeNull();
});

it('can filter models with any taxonomies of specific type', function () {
    // Setup test data
    $model1 = Product::create(['name' => 'Product 1']);
    $model2 = Product::create(['name' => 'Product 2']);
    $model3 = Product::create(['name' => 'Product 3']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag1 = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Setup relationships
    $model1->attachTaxonomies([$category1, $tag1]);
    $model2->attachTaxonomies([$category1, $category2]);
    $model3->attachTaxonomies([$tag1]);

    $results = Product::withAnyTaxonomiesOfType(
        TaxonomyType::Category,
        [$category1->id, $category2->id]
    )->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('id'))->toContain($model1->id, $model2->id);
    expect($results->pluck('id'))->not->toContain($model3->id);
});

it('returns empty when no models have taxonomies of type', function () {
    $nonExistentCategory = Taxonomy::create([
        'name' => 'Non-existent',
        'type' => TaxonomyType::Category->value,
    ]);

    $results = Product::withAnyTaxonomiesOfType(
        TaxonomyType::Category,
        [$nonExistentCategory->id]
    )->get();

    expect($results)->toHaveCount(0);
});

it('filters models with all of the given taxonomies of type', function () {
    // Setup test data
    $model1 = Product::create(['name' => 'Product 1']);
    $model2 = Product::create(['name' => 'Product 2']);
    $model3 = Product::create(['name' => 'Product 3']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag1 = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Setup relationships
    $model1->attachTaxonomies([$category1, $tag1]);
    $model2->attachTaxonomies([$category1, $category2]);
    $model3->attachTaxonomies([$tag1]);

    $results = Product::withAllTaxonomiesOfType(
        TaxonomyType::Category,
        [$category1->id, $category2->id]
    )->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($model2->id);
});

it('filters models with single taxonomy of type', function () {
    // Setup test data
    $model1 = Product::create(['name' => 'Product 1']);
    $model2 = Product::create(['name' => 'Product 2']);
    $model3 = Product::create(['name' => 'Product 3']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag1 = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Setup relationships
    $model1->attachTaxonomies([$category1, $tag1]);
    $model2->attachTaxonomies([$category1]);
    $model3->attachTaxonomies([$tag1]);

    $results = Product::withAllTaxonomiesOfType(
        TaxonomyType::Category,
        [$category1->id]
    )->get();

    expect($results)->toHaveCount(2);
    expect($results->pluck('id'))->toContain($model1->id, $model2->id);
});

it('excludes models with given taxonomies of type', function () {
    // Setup test data
    $model1 = Product::create(['name' => 'Product 1']);
    $model2 = Product::create(['name' => 'Product 2']);
    $model3 = Product::create(['name' => 'Product 3']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag1 = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Setup relationships
    $model1->attachTaxonomies([$category1, $tag1]);
    $model2->attachTaxonomies([$category1]);
    $model3->attachTaxonomies([$tag1]);

    $results = Product::withoutTaxonomiesOfType(
        TaxonomyType::Category,
        [$category1->id]
    )->get();

    expect($results)->toHaveCount(1);
    expect($results->first()->id)->toBe($model3->id);
});

it('includes all models when excluding non-existent taxonomies', function () {
    // Setup test data
    $model1 = Product::create(['name' => 'Product 1']);
    $model2 = Product::create(['name' => 'Product 2']);
    $model3 = Product::create(['name' => 'Product 3']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag1 = Taxonomy::create([
        'name' => 'Popular',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Setup relationships
    $model1->attachTaxonomies([$category1, $tag1]);
    $model2->attachTaxonomies([$category1]);
    $model3->attachTaxonomies([$tag1]);

    $nonExistentCategory = Taxonomy::create([
        'name' => 'Non-existent',
        'type' => TaxonomyType::Category->value,
    ]);

    $results = Product::withoutTaxonomiesOfType(
        TaxonomyType::Category,
        [$nonExistentCategory->id]
    )->get();

    expect($results)->toHaveCount(3);
});

it('orders models by taxonomy name ascending', function () {
    // Setup test data
    $model1 = Product::create(['name' => 'Product 1']);
    $model2 = Product::create(['name' => 'Product 2']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    // Setup relationships
    $model1->attachTaxonomies([$category1]);
    $model2->attachTaxonomies([$category2]);

    $results = Product::orderByTaxonomyType(TaxonomyType::Category, 'asc', 'name')->get();

    // Should be ordered by taxonomy name: Computers, Electronics
    expect($results)->toHaveCount(2);
    expect($results->first()->id)->toBe($model2->id); // Computers comes first
});

it('orders models by taxonomy name descending', function () {
    // Setup test data
    $model1 = Product::create(['name' => 'Product 1']);
    $model2 = Product::create(['name' => 'Product 2']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    // Setup relationships
    $model1->attachTaxonomies([$category1]);
    $model2->attachTaxonomies([$category2]);

    $results = Product::orderByTaxonomyType(TaxonomyType::Category, 'desc', 'name')->get();

    expect($results)->toHaveCount(2);
    expect($results->first()->id)->toBe($model1->id); // Electronics comes first in desc order
});

it('can order models by taxonomy type', function () {
    // Setup test data
    $model1 = Product::create(['name' => 'Product 1']);
    $model2 = Product::create(['name' => 'Product 2']);
    $model3 = Product::create(['name' => 'Product 3']);

    $category1 = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Computers',
        'type' => TaxonomyType::Category->value,
    ]);

    $category3 = Taxonomy::create([
        'name' => 'Books',
        'type' => TaxonomyType::Category->value,
    ]);

    // Setup relationships - all models have categories
    $model1->attachTaxonomies([$category1]);
    $model2->attachTaxonomies([$category2]);
    $model3->attachTaxonomies([$category3]);

    $results = Product::orderByTaxonomyType(TaxonomyType::Category)->get();

    expect($results)->toHaveCount(3);
    // Should be ordered by taxonomy name: Books, Computers, Electronics
    expect($results->first()->id)->toBe($model3->id); // Books comes first
    expect($results->last()->id)->toBe($model1->id); // Electronics comes last
});

it('handles string taxonomy type parameters', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomiesOfType('category', [$category->id]);

    expect($model->hasTaxonomiesOfType('category', [$category->id]))->toBeTrue();
    expect($model->getTaxonomyCountByType('category'))->toBe(1);
});

it('handles empty taxonomy arrays gracefully', function () {
    $model = Product::create(['name' => 'Test Product']);

    expect(fn () => $model->attachTaxonomiesOfType(TaxonomyType::Category, []))->not->toThrow(Exception::class);
    expect(fn () => $model->detachTaxonomiesOfType(TaxonomyType::Category, []))->not->toThrow(Exception::class);
    expect(fn () => $model->syncTaxonomiesOfType(TaxonomyType::Category, []))->not->toThrow(Exception::class);
    expect(fn () => $model->toggleTaxonomiesOfType(TaxonomyType::Category, []))->not->toThrow(Exception::class);
});

it('handles non-existent taxonomy IDs gracefully', function () {
    $model = Product::create(['name' => 'Test Product']);

    expect(fn () => $model->attachTaxonomiesOfType(TaxonomyType::Category, [999, 1000]))->not->toThrow(Exception::class);
    expect($model->hasTaxonomiesOfType(TaxonomyType::Category, [999]))->toBeFalse();
    expect($model->hasAllTaxonomiesOfType(TaxonomyType::Category, [999]))->toBeFalse();
});

it('handles mixed valid and invalid taxonomy IDs', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category = Taxonomy::create([
        'name' => 'Electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomiesOfType(TaxonomyType::Category, [$category->id, 999]);

    expect($model->hasTaxonomies($category))->toBeTrue();
    expect($model->getTaxonomyCountByType(TaxonomyType::Category))->toBe(1);
});
