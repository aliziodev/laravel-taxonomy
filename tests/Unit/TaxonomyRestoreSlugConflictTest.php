<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;

uses(TestCase::class);

it('regenerates slug on restore if conflict', function () {
    config(['taxonomy.slugs.consider_trashed' => false]);
    config(['taxonomy.slugs.regenerate_on_restore' => true]);

    $t1 = Taxonomy::create([
        'name' => 'Category C',
        'type' => TaxonomyType::Category->value,
        'slug' => 'category-c',
    ]);

    $t1->delete();

    $t2 = Taxonomy::create([
        'name' => 'New Category C',
        'type' => TaxonomyType::Category->value,
        'slug' => 'category-c',
    ]);

    // Restoring t1 should auto-regenerate its slug to a unique variant
    $t1->restore();
    $t1->refresh();

    expect($t2->slug)->toBe('category-c');
    expect($t1->slug)->not->toBe('category-c');
    expect($t1->slug)->toStartWith('category-c');
});

it('respects consider_trashed=true (no reuse; conflict prevented up front)', function () {
    config(['taxonomy.slugs.consider_trashed' => true]);
    config(['taxonomy.slugs.regenerate_on_restore' => true]);

    $t1 = Taxonomy::create([
        'name' => 'Category D',
        'type' => TaxonomyType::Category->value,
        'slug' => 'category-d',
    ]);

    $t1->delete();

    // With consider_trashed=true, creating a record with the same slug is prevented
    $t2 = Taxonomy::create([
        'name' => 'New Category D',
        'type' => TaxonomyType::Category->value,
        // slug is not set → it will be generated and must be unique because t1 is considered existing
    ]);

    expect($t2->slug)->toStartWith('new-category-d');
    expect($t2->slug)->not->toBe('category-d');

    // Restoring t1 must keep its original slug without conflict
    $t1->restore();
    $t1->refresh();

    expect($t1->slug)->toBe('category-d');
});

it('throws DuplicateSlugException on restore conflict when regenerate_on_restore=false', function () {
    config(['taxonomy.slugs.consider_trashed' => false]);
    config(['taxonomy.slugs.regenerate_on_restore' => false]);

    $t1 = Taxonomy::create([
        'name' => 'Category E',
        'type' => TaxonomyType::Category->value,
        'slug' => 'category-e',
    ]);

    $t1->delete();

    $t2 = Taxonomy::create([
        'name' => 'New Category E',
        'type' => TaxonomyType::Category->value,
        'slug' => 'category-e',
    ]);

    expect(function () use ($t1) {
        $t1->restore();
    })->toThrow(DuplicateSlugException::class);
});
