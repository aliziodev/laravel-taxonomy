<?php

namespace Tests\Unit;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;

class HasTaxonomyScopesTest extends TestCase
{
    use RefreshDatabase;

    protected Taxonomy $category1;
    protected Taxonomy $category2;
    protected Taxonomy $tag1;
    protected Taxonomy $tag2;
    protected Product $product1;
    protected Product $product2;
    protected Product $product3;

    protected function setUp(): void
    {
        parent::setUp();

        // Create taxonomies
        $this->category1 = Taxonomy::create([
            'name' => 'Electronics',
            'type' => TaxonomyType::Category->value,
        ]);

        $this->category2 = Taxonomy::create([
            'name' => 'Books',
            'type' => TaxonomyType::Category->value,
        ]);

        $this->tag1 = Taxonomy::create([
            'name' => 'Featured',
            'type' => TaxonomyType::Tag->value,
        ]);

        $this->tag2 = Taxonomy::create([
            'name' => 'Sale',
            'type' => TaxonomyType::Tag->value,
        ]);

        // Create products
        $this->product1 = Product::create(['name' => 'Laptop']);
        $this->product2 = Product::create(['name' => 'Book']);
        $this->product3 = Product::create(['name' => 'Phone']);

        // Attach taxonomies to products
        $this->product1->attachTaxonomies([$this->category1, $this->tag1]);
        $this->product2->attachTaxonomies([$this->category2, $this->tag2]);
        $this->product3->attachTaxonomies([$this->category1, $this->tag2]);
    }

    #[Test]
    public function it_can_filter_by_taxonomy_id(): void
    {
        $products = Product::withTaxonomy($this->category1->id)->get();

        $this->assertCount(2, $products);
        $this->assertContains($this->product1->id, $products->pluck('id'));
        $this->assertContains($this->product3->id, $products->pluck('id'));
        $this->assertNotContains($this->product2->id, $products->pluck('id'));
    }

    #[Test]
    public function it_can_filter_by_taxonomy_instance(): void
    {
        $products = Product::withTaxonomy($this->category2)->get();

        $this->assertCount(1, $products);
        $this->assertContains($this->product2->id, $products->pluck('id'));
        $this->assertNotContains($this->product1->id, $products->pluck('id'));
        $this->assertNotContains($this->product3->id, $products->pluck('id'));
    }

    #[Test]
    public function it_can_exclude_taxonomies_by_ids(): void
    {
        $products = Product::withoutTaxonomies([$this->category1->id])->get();

        $this->assertCount(1, $products);
        $this->assertContains($this->product2->id, $products->pluck('id'));
        $this->assertNotContains($this->product1->id, $products->pluck('id'));
        $this->assertNotContains($this->product3->id, $products->pluck('id'));
    }

    #[Test]
    public function it_can_exclude_taxonomies_by_instances(): void
    {
        $products = Product::withoutTaxonomies([$this->tag1, $this->tag2])->get();

        $this->assertCount(0, $products);
    }

    #[Test]
    public function it_can_exclude_multiple_taxonomies(): void
    {
        $products = Product::withoutTaxonomies([$this->category1->id, $this->tag2->id])->get();

        $this->assertCount(0, $products);
    }

    #[Test]
    public function it_can_filter_by_multiple_taxonomy_criteria(): void
    {
        $filters = [
            TaxonomyType::Category->value => 'electronics',
            TaxonomyType::Tag->value => 'featured',
        ];

        $products = Product::filterByTaxonomies($filters)->get();

        $this->assertCount(1, $products);
        $this->assertContains($this->product1->id, $products->pluck('id'));
    }

    #[Test]
    public function it_can_filter_by_taxonomy_type_with_multiple_values(): void
    {
        $filters = [
            TaxonomyType::Tag->value => ['featured', 'sale'],
        ];

        $products = Product::filterByTaxonomies($filters)->get();

        $this->assertCount(3, $products);
        $this->assertContains($this->product1->id, $products->pluck('id'));
        $this->assertContains($this->product2->id, $products->pluck('id'));
        $this->assertContains($this->product3->id, $products->pluck('id'));
    }

    #[Test]
    public function it_can_filter_by_single_taxonomy_value(): void
    {
        $filters = [
            TaxonomyType::Category->value => 'books',
        ];

        $products = Product::filterByTaxonomies($filters)->get();

        $this->assertCount(1, $products);
        $this->assertContains($this->product2->id, $products->pluck('id'));
    }

    #[Test]
    public function it_returns_empty_result_when_no_matches_found(): void
    {
        $filters = [
            TaxonomyType::Category->value => 'nonexistent',
        ];

        $products = Product::filterByTaxonomies($filters)->get();

        $this->assertCount(0, $products);
    }

    #[Test]
    public function it_can_chain_multiple_scopes(): void
    {
        $products = Product::withTaxonomy($this->category1)
            ->withoutTaxonomies([$this->tag1])
            ->get();

        $this->assertCount(1, $products);
        $this->assertContains($this->product3->id, $products->pluck('id'));
        $this->assertNotContains($this->product1->id, $products->pluck('id'));
    }

    #[Test]
    public function it_can_combine_filter_by_taxonomies_with_other_scopes(): void
    {
        $filters = [
            TaxonomyType::Category->value => 'electronics',
        ];

        $products = Product::filterByTaxonomies($filters)
            ->withoutTaxonomies([$this->tag2])
            ->get();

        $this->assertCount(1, $products);
        $this->assertContains($this->product1->id, $products->pluck('id'));
        $this->assertNotContains($this->product3->id, $products->pluck('id'));
    }
}
