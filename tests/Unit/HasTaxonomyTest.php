<?php

namespace Tests\Unit;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;

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
    public function it_can_attach_taxonomies()
    {
        $model = TestModel::create(['name' => 'Test Model']);

        $category = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $model->attachTaxonomies($category);

        $this->assertTrue($model->hasTaxonomies($category));
        $this->assertCount(1, $model->taxonomies()->get());
    }

    #[Test]
    public function it_can_attach_multiple_taxonomies()
    {
        $model = TestModel::create(['name' => 'Test Model']);

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
    public function it_can_detach_taxonomies()
    {
        $model = TestModel::create(['name' => 'Test Model']);

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
    public function it_can_detach_all_taxonomies()
    {
        $model = TestModel::create(['name' => 'Test Model']);

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
    public function it_can_sync_taxonomies()
    {
        $model = TestModel::create(['name' => 'Test Model']);

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

        // Sync to a different set
        $model->syncTaxonomies([$category2->id, $category3->id]);

        // Refresh the model to get updated relations
        $model = $model->fresh();

        $this->assertCount(2, $model->taxonomies()->get());
        $this->assertFalse($model->hasTaxonomies($category1));
        $this->assertTrue($model->hasTaxonomies($category2));
        $this->assertTrue($model->hasTaxonomies($category3));
    }

    #[Test]
    public function it_can_toggle_taxonomies()
    {
        $model = TestModel::create(['name' => 'Test Model']);

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

        $this->assertFalse($model->hasTaxonomies($category1)); // Should be detached
        $this->assertTrue($model->hasTaxonomies($category2)); // Should be attached
    }

    #[Test]
    public function it_can_check_if_model_has_all_taxonomies()
    {
        $model = TestModel::create(['name' => 'Test Model']);

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
    public function it_can_check_if_model_has_taxonomy_type()
    {
        $model = TestModel::create(['name' => 'Test Model']);

        $category = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $tag = Taxonomy::create([
            'name' => 'Test Tag',
            'type' => TaxonomyType::Tag->value,
        ]);

        $model->attachTaxonomies($category);

        $this->assertTrue($model->hasTaxonomyType(TaxonomyType::Category));
        $this->assertFalse($model->hasTaxonomyType(TaxonomyType::Tag));
    }

    #[Test]
    public function it_can_get_taxonomies_of_type()
    {
        $model = TestModel::create(['name' => 'Test Model']);

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
    public function it_can_scope_models_with_any_taxonomies()
    {
        $model1 = TestModel::create(['name' => 'Model 1']);
        $model2 = TestModel::create(['name' => 'Model 2']);
        $model3 = TestModel::create(['name' => 'Model 3']);

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

        $models = TestModel::withAnyTaxonomies([$category1, $category2])->get();
        $this->assertCount(2, $models);

        $models = TestModel::withAnyTaxonomies($category1)->get();
        $this->assertCount(1, $models);
        $this->assertEquals('Model 1', $models->first()->name);
    }

    #[Test]
    public function it_can_scope_models_with_all_taxonomies()
    {
        $model1 = TestModel::create(['name' => 'Model 1']);
        $model2 = TestModel::create(['name' => 'Model 2']);

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

        $models = TestModel::withAllTaxonomies([$category1, $category2])->get();
        $this->assertCount(1, $models);
        $this->assertEquals('Model 1', $models->first()->name);
    }

    #[Test]
    public function it_can_scope_models_with_taxonomy_type()
    {
        $model1 = TestModel::create(['name' => 'Model 1']);
        $model2 = TestModel::create(['name' => 'Model 2']);
        $model3 = TestModel::create(['name' => 'Model 3']);

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

        $models = TestModel::withTaxonomyType(TaxonomyType::Category)->get();
        $this->assertCount(1, $models);
        $this->assertEquals('Model 1', $models->first()->name);

        $models = TestModel::withTaxonomyType(TaxonomyType::Tag)->get();
        $this->assertCount(1, $models);
        $this->assertEquals('Model 2', $models->first()->name);
    }
}

// Test model class for HasTaxonomy trait tests
class TestModel extends Model
{
    use HasTaxonomy;

    protected $fillable = ['name'];
}
