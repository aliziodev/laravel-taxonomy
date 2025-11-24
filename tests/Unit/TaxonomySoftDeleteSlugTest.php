<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;

uses(TestCase::class);

it('allows same slug after soft delete (custom slug)', function () {
    $t1 = Taxonomy::create([
        'name' => 'Category A',
        'slug' => 'category-a',
        'type' => TaxonomyType::Category->value,
    ]);

    $t1->delete();

    $t2 = Taxonomy::create([
        'name' => 'New Category A',
        'slug' => 'category-a',
        'type' => TaxonomyType::Category->value,
    ]);

    expect($t2->slug)->toBe('category-a');
    expect($t2->id)->not->toBe($t1->id);
});

it('generates same slug after soft delete (auto generate)', function () {
    $t1 = Taxonomy::create([
        'name' => 'Category B',
        'type' => TaxonomyType::Category->value,
    ]);

    expect($t1->slug)->toBe('category-b');

    $t1->delete();

    $t2 = Taxonomy::create([
        'name' => 'Category B',
        'type' => TaxonomyType::Category->value,
    ]);

    expect($t2->slug)->toBe('category-b');
});
