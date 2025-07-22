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

it('can attach taxonomies', function () {
    $model = Product::create(['name' => 'Test Model']);

    $category = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomies($category);

    expect($model->hasTaxonomies($category))->toBeTrue();
    expect($model->taxonomies()->get())->toHaveCount(1);
});

it('can attach multiple taxonomies', function () {
    $model = Product::create(['name' => 'Test Model']);

    $category1 = Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomies([$category1->id, $category2->id]);

    expect($model->hasTaxonomies($category1))->toBeTrue();
    expect($model->hasTaxonomies($category2))->toBeTrue();
    expect($model->taxonomies()->get())->toHaveCount(2);
});

it('can detach taxonomies', function () {
    $model = Product::create(['name' => 'Test Model']);

    $category1 = Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomies([$category1, $category2]);
    expect($model->taxonomies()->get())->toHaveCount(2);

    $model->detachTaxonomies($category1);
    expect($model->taxonomies()->get())->toHaveCount(1);
    expect($model->hasTaxonomies($category1))->toBeFalse();
    expect($model->hasTaxonomies($category2))->toBeTrue();
});

it('can detach all taxonomies', function () {
    $model = Product::create(['name' => 'Test Model']);

    $category1 = Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomies([$category1, $category2]);
    expect($model->taxonomies()->get())->toHaveCount(2);

    $model->detachTaxonomies();
    expect($model->taxonomies()->get())->toHaveCount(0);
});

it('can sync taxonomies', function () {
    $model = Product::create(['name' => 'Test Model']);

    $category1 = Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    $category3 = Taxonomy::create([
        'name' => 'Category 3',
        'type' => TaxonomyType::Category->value,
    ]);

    // Attach initial taxonomies
    $model->attachTaxonomies([$category1, $category2]);
    expect($model->taxonomies()->get())->toHaveCount(2);

    // Sync with different taxonomies
    $model->syncTaxonomies([$category2, $category3]);

    // Refresh the model to get updated relations
    $model = $model->fresh();
    expect($model)->not->toBeNull();

    expect($model->taxonomies()->get())->toHaveCount(2);
    expect($model->hasTaxonomies($category1))->toBeFalse();
    expect($model->hasTaxonomies($category2))->toBeTrue();
    expect($model->hasTaxonomies($category3))->toBeTrue();
});

it('can toggle taxonomies', function () {
    $model = Product::create(['name' => 'Test Model']);

    $category1 = Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    // Attach initial taxonomy
    $model->attachTaxonomies($category1);
    expect($model->hasTaxonomies($category1))->toBeTrue();
    expect($model->hasTaxonomies($category2))->toBeFalse();

    // Toggle both taxonomies
    $model->toggleTaxonomies([$category1, $category2]);

    // Refresh the model to get updated relations
    $model = $model->fresh();
    expect($model)->not->toBeNull();

    expect($model->hasTaxonomies($category1))->toBeFalse(); // Should be detached
    expect($model->hasTaxonomies($category2))->toBeTrue(); // Should be attached
});

it('can check if model has all taxonomies', function () {
    $model = Product::create(['name' => 'Test Model']);

    $category1 = Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    $model->attachTaxonomies($category1);

    expect($model->hasTaxonomies($category1))->toBeTrue();
    expect($model->hasTaxonomies($category2))->toBeFalse();
    expect($model->hasAllTaxonomies([$category1, $category2]))->toBeFalse();

    $model->attachTaxonomies($category2);
    expect($model->hasAllTaxonomies([$category1, $category2]))->toBeTrue();
});

it('can check if model has taxonomy type', function () {
    $model = Product::create(['name' => 'Test Model']);

    $category = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Test Tag',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model->attachTaxonomies([$category, $tag]);

    expect($model->hasTaxonomyType(TaxonomyType::Category))->toBeTrue();
    expect($model->hasTaxonomyType(TaxonomyType::Tag))->toBeTrue();
    expect($model->hasTaxonomyType(TaxonomyType::Unit))->toBeFalse();
});

it('can get taxonomies of type', function () {
    $model = Product::create(['name' => 'Test Model']);

    $category1 = Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Test Tag',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model->attachTaxonomies([$category1, $category2, $tag]);

    $categories = $model->taxonomiesOfType(TaxonomyType::Category);
    $tags = $model->taxonomiesOfType(TaxonomyType::Tag);

    expect($categories)->toHaveCount(2);
    expect($tags)->toHaveCount(1);
});

it('can scope models with any taxonomies', function () {
    $model1 = Product::create(['name' => 'Model 1']);
    $model2 = Product::create(['name' => 'Model 2']);
    $model3 = Product::create(['name' => 'Model 3']);

    $category1 = Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    $model1->attachTaxonomies($category1);
    $model2->attachTaxonomies($category2);
    // model3 has no taxonomies

    $results = Product::withAnyTaxonomies([$category1, $category2])->get();

    expect($results)->toHaveCount(2);
    $firstResult = $results->first();
    expect($firstResult)->not->toBeNull();
    expect($firstResult)->toBeInstanceOf(Product::class);
    expect($firstResult->name)->toBe('Model 1');
});

it('can scope models with all taxonomies', function () {
    $model1 = Product::create(['name' => 'Model 1']);
    $model2 = Product::create(['name' => 'Model 2']);

    $category1 = Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    $category2 = Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    $model1->attachTaxonomies([$category1, $category2]);
    $model2->attachTaxonomies($category1);

    $models = Product::withAllTaxonomies([$category1, $category2])->get();
    expect($models)->toHaveCount(1);
    $firstModel = $models->first();
    expect($firstModel)->not->toBeNull();
    expect($firstModel)->toBeInstanceOf(Product::class);
    expect($firstModel->name)->toBe('Model 1');
});

it('can scope models with taxonomy type', function () {
    $model1 = Product::create(['name' => 'Model 1']);
    $model2 = Product::create(['name' => 'Model 2']);
    $model3 = Product::create(['name' => 'Model 3']);

    $category = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Test Tag',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model1->attachTaxonomies($category);
    $model2->attachTaxonomies($tag);

    $models = Product::withTaxonomyType(TaxonomyType::Category)->get();
    expect($models)->toHaveCount(1);
    $firstModel = $models->first();
    expect($firstModel)->not->toBeNull();
    expect($firstModel)->toBeInstanceOf(Product::class);
    expect($firstModel->name)->toBe('Model 1');

    $models = Product::withTaxonomyType(TaxonomyType::Tag)->get();
    expect($models)->toHaveCount(1);
    $secondModel = $models->first();
    expect($secondModel)->not->toBeNull();
    expect($secondModel)->toBeInstanceOf(Product::class);
    expect($secondModel->name)->toBe('Model 2');
});

it('can scope models with taxonomy slug', function () {
    $model1 = Product::create(['name' => 'Model 1']);
    $model2 = Product::create(['name' => 'Model 2']);
    $model3 = Product::create(['name' => 'Model 3']);

    $electronics = Taxonomy::create([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'type' => TaxonomyType::Category->value,
    ]);

    $books = Taxonomy::create([
        'name' => 'Books',
        'slug' => 'books',
        'type' => TaxonomyType::Category->value,
    ]);

    $featured = Taxonomy::create([
        'name' => 'Featured',
        'slug' => 'featured',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model1->attachTaxonomies($electronics);
    $model2->attachTaxonomies($books);
    $model3->attachTaxonomies($featured);

    // Test filtering by slug only
    $models = Product::withTaxonomySlug('electronics')->get();
    expect($models)->toHaveCount(1);
    $firstModel = $models->first();
    expect($firstModel)->not->toBeNull();
    expect($firstModel)->toBeInstanceOf(Product::class);
    expect($firstModel->name)->toBe('Model 1');

    // Test filtering by slug with type filter
    $models = Product::withTaxonomySlug('featured', TaxonomyType::Tag)->get();
    expect($models)->toHaveCount(1);
    $thirdModel = $models->first();
    expect($thirdModel)->not->toBeNull();
    expect($thirdModel)->toBeInstanceOf(Product::class);
    expect($thirdModel->name)->toBe('Model 3');

    // Test filtering by non-existent slug
    $models = Product::withTaxonomySlug('non-existent')->get();
    expect($models)->toHaveCount(0);

    // Test filtering by slug with wrong type
    $models = Product::withTaxonomySlug('electronics', TaxonomyType::Tag)->get();
    expect($models)->toHaveCount(0);
});

it('can combine taxonomy type and slug scopes', function () {
    $model1 = Product::create(['name' => 'Electronics Product']);
    $model2 = Product::create(['name' => 'Book Product']);
    $model3 = Product::create(['name' => 'Tagged Product']);

    $electronics = Taxonomy::create([
        'name' => 'Electronics Category',
        'slug' => 'electronics-category',
        'type' => TaxonomyType::Category->value,
    ]);

    $books = Taxonomy::create([
        'name' => 'Books Category',
        'slug' => 'books-category',
        'type' => TaxonomyType::Category->value,
    ]);

    $electronicsTag = Taxonomy::create([
        'name' => 'Electronics Tag',
        'slug' => 'electronics-tag',
        'type' => TaxonomyType::Tag->value,
    ]);

    $model1->attachTaxonomies($electronics);
    $model2->attachTaxonomies($books);
    $model3->attachTaxonomies($electronicsTag);

    // Test combining withTaxonomyType and withTaxonomySlug
    // Find products in electronics category (not tag)
    $products = Product::withTaxonomyType(TaxonomyType::Category)
        ->withTaxonomySlug('electronics-category')
        ->get();

    expect($products)->toHaveCount(1);
    $product = $products->first();
    expect($product)->not->toBeNull();
    expect($product)->toBeInstanceOf(Product::class);
    expect($product->name)->toBe('Electronics Product');

    // Test with different type - should find the tag version
    $products = Product::withTaxonomyType(TaxonomyType::Tag)
        ->withTaxonomySlug('electronics-tag')
        ->get();

    expect($products)->toHaveCount(1);
    $product = $products->first();
    expect($product)->not->toBeNull();
    expect($product)->toBeInstanceOf(Product::class);
    expect($product->name)->toBe('Tagged Product');

    // Test with non-matching combination
    $products = Product::withTaxonomyType(TaxonomyType::Tag)
        ->withTaxonomySlug('books-category')
        ->get();

    expect($products)->toHaveCount(0);
});

it('detaches taxonomies when model is force deleted', function () {
    $model = Product::create(['name' => 'Test Product']);

    $category = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    $tag = Taxonomy::create([
        'name' => 'Test Tag',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Attach taxonomies to the model
    $model->attachTaxonomies([$category->id, $tag->id]);

    // Verify taxonomies are attached
    expect($model->taxonomies()->count())->toBe(2);
    expect($model->hasTaxonomies([$category, $tag]))->toBeTrue();

    // Force delete the model
    $model->forceDelete();

    // Verify taxonomies are detached from the deleted model
    // Check the pivot table directly since the model is deleted
    $pivotCount = \Illuminate\Support\Facades\DB::table('taxonomables')
        ->where('taxonomable_type', Product::class)
        ->where('taxonomable_id', $model->id)
        ->count();

    expect($pivotCount)->toBe(0);
});

it('detaches taxonomies when model is soft deleted with force delete', function () {
    // Create a model that supports soft deletes
    $testModelClass = new class extends \Illuminate\Database\Eloquent\Model
    {
        use \Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
        use \Illuminate\Database\Eloquent\SoftDeletes;

        protected $table = 'test_models';
        protected $fillable = ['name'];
        protected $primaryKey = 'id';
        public $incrementing = true;
        protected $keyType = 'int';
    };

    // Create the model instance
    $testModel = $testModelClass::create(['name' => 'Test Model']);

    // Ensure the model has a valid ID
    expect($testModel->getKey())->not->toBeNull();
    expect($testModel->getKey())->toBeGreaterThan(0);

    $category = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    // Attach taxonomy to the model
    $testModel->attachTaxonomies($category);

    // Verify taxonomy is attached
    expect($testModel->taxonomies()->count())->toBe(1);

    // Soft delete first
    $testModel->delete();

    // Verify taxonomy is still attached after soft delete
    $pivotCount = \Illuminate\Support\Facades\DB::table('taxonomables')
        ->where('taxonomable_type', get_class($testModel))
        ->where('taxonomable_id', $testModel->getKey())
        ->count();

    expect($pivotCount)->toBe(1);

    // Force delete the soft deleted model
    $testModel->forceDelete();

    // Verify taxonomy is detached after force delete
    $pivotCountAfterForceDelete = \Illuminate\Support\Facades\DB::table('taxonomables')
        ->where('taxonomable_type', get_class($testModel))
        ->where('taxonomable_id', $testModel->getKey())
        ->count();

    expect($pivotCountAfterForceDelete)->toBe(0);
});
