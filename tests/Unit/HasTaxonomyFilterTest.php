<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Database\Eloquent\Collection;

uses(TestCase::class);

describe('scopeFilterByTaxonomies', function () {
    it('filters by exclude criteria', function () {
        $category1 = Taxonomy::create([
            'name' => 'Electronics',
            'type' => TaxonomyType::Category->value,
        ]);

        $category2 = Taxonomy::create([
            'name' => 'Books',
            'type' => TaxonomyType::Category->value,
        ]);

        $product1 = Product::create(['name' => 'Laptop']);
        $product2 = Product::create(['name' => 'Novel']);
        $product3 = Product::create(['name' => 'Tablet']);

        $product1->attachTaxonomies([$category1->id]);
        $product2->attachTaxonomies([$category2->id]);
        // product3 has no taxonomies

        $filters = ['exclude' => [$category1->id]];
        $results = Product::filterByTaxonomies($filters)->get();

        expect($results->pluck('id'))->toContain($product2->id, $product3->id);
        expect($results->pluck('id'))->not->toContain($product1->id);
    });

    it('filters by include criteria', function () {
        $category1 = Taxonomy::create([
            'name' => 'Electronics',
            'type' => TaxonomyType::Category->value,
        ]);

        $category2 = Taxonomy::create([
            'name' => 'Books',
            'type' => TaxonomyType::Category->value,
        ]);

        $product1 = Product::create(['name' => 'Laptop']);
        $product2 = Product::create(['name' => 'Novel']);
        $product3 = Product::create(['name' => 'Tablet']);

        $product1->attachTaxonomies([$category1->id]);
        $product2->attachTaxonomies([$category2->id]);
        // product3 has no taxonomies

        $filters = ['include' => [$category1->id]];
        $results = Product::filterByTaxonomies($filters)->get();

        expect($results->pluck('id'))->toContain($product1->id);
        expect($results->pluck('id'))->not->toContain($product2->id, $product3->id);
    });

    it('filters by specific taxonomy type with array values using OR logic', function () {
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

        $clothing = Taxonomy::create([
            'name' => 'Clothing',
            'slug' => 'clothing',
            'type' => TaxonomyType::Category->value,
        ]);

        $product1 = Product::create(['name' => 'Laptop']);
        $product2 = Product::create(['name' => 'Novel']);
        $product3 = Product::create(['name' => 'Shirt']);
        $product4 = Product::create(['name' => 'Tablet']);

        $product1->attachTaxonomies([$electronics->id]);
        $product2->attachTaxonomies([$books->id]);
        $product3->attachTaxonomies([$clothing->id]);
        // product4 has no taxonomies

        $filters = [TaxonomyType::Category->value => ['electronics', 'books']];
        $results = Product::filterByTaxonomies($filters)->get();

        expect($results->pluck('id'))->toContain($product1->id, $product2->id);
        expect($results->pluck('id'))->not->toContain($product3->id, $product4->id);
    });

    it('filters by specific taxonomy type with string value', function () {
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

        $product1 = Product::create(['name' => 'Laptop']);
        $product2 = Product::create(['name' => 'Novel']);

        $product1->attachTaxonomies([$electronics->id]);
        $product2->attachTaxonomies([$books->id]);

        $filters = [TaxonomyType::Category->value => 'electronics'];
        $results = Product::filterByTaxonomies($filters)->get();

        expect($results->pluck('id'))->toContain($product1->id);
        expect($results->pluck('id'))->not->toContain($product2->id);
    });

    it('filters by taxonomy ID when value is numeric', function () {
        $category = Taxonomy::create([
            'name' => 'Electronics',
            'type' => TaxonomyType::Category->value,
        ]);

        $product1 = Product::create(['name' => 'Laptop']);
        $product2 = Product::create(['name' => 'Novel']);

        $product1->attachTaxonomies([$category->id]);
        // product2 has no taxonomies

        $filters = ['category' => $category->id];
        $results = Product::filterByTaxonomies($filters)->get();

        expect($results->pluck('id'))->toContain($product1->id);
        expect($results->pluck('id'))->not->toContain($product2->id);
    });

    it('handles empty filter values gracefully', function () {
        $product1 = Product::create(['name' => 'Laptop']);
        $product2 = Product::create(['name' => 'Novel']);

        $filters = [
            'exclude' => [],
            'include' => null,
            'category' => '',
        ];

        $results = Product::filterByTaxonomies($filters)->get();

        expect($results->pluck('id'))->toContain($product1->id, $product2->id);
    });

    it('combines multiple filter criteria', function () {
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

        $product1 = Product::create(['name' => 'Laptop']);
        $product2 = Product::create(['name' => 'Novel']);
        $product3 = Product::create(['name' => 'Featured Laptop']);

        $product1->attachTaxonomies([$electronics->id]);
        $product2->attachTaxonomies([$books->id]);
        $product3->attachTaxonomies([$electronics->id, $featured->id]);

        $filters = [
            'include' => [$electronics->id],
            TaxonomyType::Tag->value => 'featured',
        ];

        $results = Product::filterByTaxonomies($filters)->get();

        expect($results->pluck('id'))->toContain($product3->id);
        expect($results->pluck('id'))->not->toContain($product1->id, $product2->id);
    });

    it('uses custom relationship name', function () {
        $category = Taxonomy::create([
            'name' => 'Electronics',
            'type' => TaxonomyType::Category->value,
        ]);

        $product = Product::create(['name' => 'Laptop']);
        $product->attachTaxonomies([$category->id]);

        $filters = ['include' => [$category->id]];
        $results = Product::filterByTaxonomies($filters, 'taxonomable')->get();

        expect($results->pluck('id'))->toContain($product->id);
    });
});

describe('getTaxonomyIds', function () {
    it('returns empty array for null input', function () {
        $product = new Product;
        $reflection = new ReflectionClass($product);
        $method = $reflection->getMethod('getTaxonomyIds');
        $method->setAccessible(true);

        $result = $method->invoke($product, null);

        expect($result)->toBe([]);
    });

    it('returns array with single numeric value', function () {
        $product = new Product;
        $reflection = new ReflectionClass($product);
        $method = $reflection->getMethod('getTaxonomyIds');
        $method->setAccessible(true);

        $result = $method->invoke($product, 123);

        expect($result)->toBe([123]);
    });

    it('returns array with single string value', function () {
        $product = new Product;
        $reflection = new ReflectionClass($product);
        $method = $reflection->getMethod('getTaxonomyIds');
        $method->setAccessible(true);

        $result = $method->invoke($product, 'test-slug');

        expect($result)->toBe(['test-slug']);
    });

    it('returns array with taxonomy ID from Taxonomy instance', function () {
        $taxonomy = Taxonomy::create([
            'name' => 'Test Category',
            'type' => TaxonomyType::Category->value,
        ]);

        $product = new Product;
        $reflection = new ReflectionClass($product);
        $method = $reflection->getMethod('getTaxonomyIds');
        $method->setAccessible(true);

        $result = $method->invoke($product, $taxonomy);

        expect($result)->toBe([$taxonomy->id]);
    });

    it('returns array of IDs from array of mixed values', function () {
        $taxonomy1 = Taxonomy::create([
            'name' => 'Category 1',
            'type' => TaxonomyType::Category->value,
        ]);

        $taxonomy2 = Taxonomy::create([
            'name' => 'Category 2',
            'type' => TaxonomyType::Category->value,
        ]);

        $input = [$taxonomy1, 123, 'slug', $taxonomy2];

        $product = new Product;
        $reflection = new ReflectionClass($product);
        $method = $reflection->getMethod('getTaxonomyIds');
        $method->setAccessible(true);

        $result = $method->invoke($product, $input);

        expect($result)->toBe([$taxonomy1->id, 123, 'slug', $taxonomy2->id]);
    });

    it('returns array of IDs from Collection of taxonomies', function () {
        $taxonomy1 = Taxonomy::create([
            'name' => 'Category 1',
            'type' => TaxonomyType::Category->value,
        ]);

        $taxonomy2 = Taxonomy::create([
            'name' => 'Category 2',
            'type' => TaxonomyType::Category->value,
        ]);

        $collection = new Collection([$taxonomy1, $taxonomy2]);

        $product = new Product;
        $reflection = new ReflectionClass($product);
        $method = $reflection->getMethod('getTaxonomyIds');
        $method->setAccessible(true);

        $result = $method->invoke($product, $collection);

        expect($result)->toBe([$taxonomy1->id, $taxonomy2->id]);
    });

    it('returns empty array for unsupported input type', function () {
        $product = new Product;
        $reflection = new ReflectionClass($product);
        $method = $reflection->getMethod('getTaxonomyIds');
        $method->setAccessible(true);

        $result = $method->invoke($product, new stdClass);

        expect($result)->toBe([]);
    });

    it('handles empty array input', function () {
        $product = new Product;
        $reflection = new ReflectionClass($product);
        $method = $reflection->getMethod('getTaxonomyIds');
        $method->setAccessible(true);

        $result = $method->invoke($product, []);

        expect($result)->toBe([]);
    });

    it('handles empty Collection input', function () {
        $collection = new Collection([]);

        $product = new Product;
        $reflection = new ReflectionClass($product);
        $method = $reflection->getMethod('getTaxonomyIds');
        $method->setAccessible(true);

        $result = $method->invoke($product, $collection);

        expect($result)->toBe([]);
    });
});
