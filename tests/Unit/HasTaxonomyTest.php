<?php

namespace Tests\Unit;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class HasTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test model table
        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    #[Test]
    public function it_can_attach_taxonomies(): void
    {
        $model = Product::create(['name' => 'Test Model']);

        $category = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $model->attachTaxonomies($category);

        $this->assertTrue($model->hasTaxonomies($category));
        $this->assertCount(1, $model->taxonomies()->get());
    }

    #[Test]
    public function it_can_attach_multiple_taxonomies(): void
    {
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

        $this->assertTrue($model->hasTaxonomies($category1));
        $this->assertTrue($model->hasTaxonomies($category2));
        $this->assertCount(2, $model->taxonomies()->get());
    }

    #[Test]
    public function it_can_detach_taxonomies(): void
    {
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
        $this->assertCount(2, $model->taxonomies()->get());

        $model->detachTaxonomies($category1);
        $this->assertCount(1, $model->taxonomies()->get());
        $this->assertFalse($model->hasTaxonomies($category1));
        $this->assertTrue($model->hasTaxonomies($category2));
    }

    #[Test]
    public function it_can_detach_all_taxonomies(): void
    {
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
        $this->assertCount(2, $model->taxonomies()->get());

        $model->detachTaxonomies();
        $this->assertCount(0, $model->taxonomies()->get());
    }

    #[Test]
    public function it_can_sync_taxonomies(): void
    {
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
        $this->assertCount(2, $model->taxonomies()->get());

        // Sync with different taxonomies
        $model->syncTaxonomies([$category2, $category3]);

        // Refresh the model to get updated relations
        $model = $model->fresh();
        $this->assertNotNull($model);

        $this->assertCount(2, $model->taxonomies()->get());
        $this->assertFalse($model->hasTaxonomies($category1));
        $this->assertTrue($model->hasTaxonomies($category2));
        $this->assertTrue($model->hasTaxonomies($category3));
    }

    #[Test]
    public function it_can_toggle_taxonomies(): void
    {
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
        $this->assertTrue($model->hasTaxonomies($category1));
        $this->assertFalse($model->hasTaxonomies($category2));

        // Toggle both taxonomies
        $model->toggleTaxonomies([$category1, $category2]);

        // Refresh the model to get updated relations
        $model = $model->fresh();
        $this->assertNotNull($model);

        $this->assertFalse($model->hasTaxonomies($category1)); // Should be detached
        $this->assertTrue($model->hasTaxonomies($category2)); // Should be attached
    }

    #[Test]
    public function it_can_check_if_model_has_all_taxonomies(): void
    {
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

        $this->assertTrue($model->hasTaxonomies($category1));
        $this->assertFalse($model->hasTaxonomies($category2));
        $this->assertFalse($model->hasAllTaxonomies([$category1, $category2]));

        $model->attachTaxonomies($category2);
        $this->assertTrue($model->hasAllTaxonomies([$category1, $category2]));
    }

    #[Test]
    public function it_can_check_if_model_has_taxonomy_type(): void
    {
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

        $this->assertTrue($model->hasTaxonomyType(TaxonomyType::Category));
        $this->assertTrue($model->hasTaxonomyType(TaxonomyType::Tag));
        $this->assertFalse($model->hasTaxonomyType(TaxonomyType::Unit));
    }

    #[Test]
    public function it_can_get_taxonomies_of_type(): void
    {
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

        $this->assertCount(2, $categories);
        $this->assertCount(1, $tags);
    }

    #[Test]
    public function it_can_scope_models_with_any_taxonomies(): void
    {
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

        $this->assertCount(2, $results);
        $firstResult = $results->first();
        $this->assertNotNull($firstResult);
        $this->assertInstanceOf(Product::class, $firstResult);
        $this->assertEquals('Model 1', $firstResult->name);
    }

    #[Test]
    public function it_can_scope_models_with_all_taxonomies(): void
    {
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
        $this->assertCount(1, $models);
        $firstModel = $models->first();
        $this->assertNotNull($firstModel);
        $this->assertInstanceOf(Product::class, $firstModel);
        $this->assertEquals('Model 1', $firstModel->name);
    }

    #[Test]
    public function it_can_scope_models_with_taxonomy_type(): void
    {
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
        $this->assertCount(1, $models);
        $firstModel = $models->first();
        $this->assertNotNull($firstModel);
        $this->assertInstanceOf(Product::class, $firstModel);
        $this->assertEquals('Model 1', $firstModel->name);

        $models = Product::withTaxonomyType(TaxonomyType::Tag)->get();
        $this->assertCount(1, $models);
        $secondModel = $models->first();
        $this->assertNotNull($secondModel);
        $this->assertInstanceOf(Product::class, $secondModel);
        $this->assertEquals('Model 2', $secondModel->name);
    }

    #[Test]
    public function it_can_scope_models_with_taxonomy_slug(): void
    {
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
        $this->assertCount(1, $models);
        $firstModel = $models->first();
        $this->assertNotNull($firstModel);
        $this->assertInstanceOf(Product::class, $firstModel);
        $this->assertEquals('Model 1', $firstModel->name);

        // Test filtering by slug with type filter
        $models = Product::withTaxonomySlug('featured', TaxonomyType::Tag)->get();
        $this->assertCount(1, $models);
        $thirdModel = $models->first();
        $this->assertNotNull($thirdModel);
        $this->assertInstanceOf(Product::class, $thirdModel);
        $this->assertEquals('Model 3', $thirdModel->name);

        // Test filtering by non-existent slug
        $models = Product::withTaxonomySlug('non-existent')->get();
        $this->assertCount(0, $models);

        // Test filtering by slug with wrong type
        $models = Product::withTaxonomySlug('electronics', TaxonomyType::Tag)->get();
        $this->assertCount(0, $models);
    }

    #[Test]
    public function it_can_combine_taxonomy_type_and_slug_scopes(): void
    {
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

        $this->assertCount(1, $products);
        $product = $products->first();
        $this->assertNotNull($product);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Electronics Product', $product->name);

        // Test with different type - should find the tag version
        $products = Product::withTaxonomyType(TaxonomyType::Tag)
            ->withTaxonomySlug('electronics-tag')
            ->get();

        $this->assertCount(1, $products);
        $product = $products->first();
        $this->assertNotNull($product);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Tagged Product', $product->name);

        // Test with non-matching combination
        $products = Product::withTaxonomyType(TaxonomyType::Tag)
            ->withTaxonomySlug('books-category')
            ->get();

        $this->assertCount(0, $products);
    }
}
