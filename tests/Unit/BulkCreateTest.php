<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Exceptions\MissingSlugException;
use Aliziodev\LaravelTaxonomy\Facades\Taxonomy as TaxonomyFacade;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(TestCase::class, RefreshDatabase::class);

it('inserts rows and returns the count', function () {
    $count = Taxonomy::bulkCreate([
        ['name' => 'Electronics', 'type' => 'category'],
        ['name' => 'Books', 'type' => 'category'],
    ]);

    expect($count)->toBe(2)
        ->and(Taxonomy::count())->toBe(2);
});

it('returns zero for an empty set without touching the database', function () {
    expect(Taxonomy::bulkCreate([]))->toBe(0)
        ->and(Taxonomy::count())->toBe(0);
});

it('generates slugs from names', function () {
    Taxonomy::bulkCreate([['name' => 'Home Appliances', 'type' => 'category']]);

    expect(Taxonomy::first()->slug)->toBe('home-appliances');
});

it('de-duplicates slugs within the same batch', function () {
    Taxonomy::bulkCreate([
        ['name' => 'Sale', 'type' => 'tag'],
        ['name' => 'Sale', 'type' => 'tag'],
        ['name' => 'Sale', 'type' => 'tag'],
    ]);

    expect(Taxonomy::orderBy('id')->pluck('slug')->all())
        ->toBe(['sale', 'sale-1', 'sale-2']);
});

it('de-duplicates against slugs already in the table', function () {
    Taxonomy::create(['name' => 'Sale', 'type' => 'tag']);

    Taxonomy::bulkCreate([['name' => 'Sale', 'type' => 'tag']]);

    expect(Taxonomy::orderBy('id')->pluck('slug')->all())->toBe(['sale', 'sale-1']);
});

it('allows the same slug across different types', function () {
    Taxonomy::bulkCreate([
        ['name' => 'Featured', 'type' => 'category'],
        ['name' => 'Featured', 'type' => 'tag'],
    ]);

    expect(Taxonomy::where('slug', 'featured')->count())->toBe(2);
});

it('accepts an explicit slug', function () {
    Taxonomy::bulkCreate([['name' => 'Electronics', 'slug' => 'electro', 'type' => 'category']]);

    expect(Taxonomy::first()->slug)->toBe('electro');
});

it('rejects an explicit slug that is already taken', function () {
    Taxonomy::create(['name' => 'Taken', 'slug' => 'taken', 'type' => 'category']);

    expect(fn () => Taxonomy::bulkCreate([
        ['name' => 'Other', 'slug' => 'taken', 'type' => 'category'],
    ]))->toThrow(DuplicateSlugException::class);
});

it('rejects a duplicate explicit slug inside one batch', function () {
    expect(fn () => Taxonomy::bulkCreate([
        ['name' => 'One', 'slug' => 'dupe', 'type' => 'category'],
        ['name' => 'Two', 'slug' => 'dupe', 'type' => 'category'],
    ]))->toThrow(DuplicateSlugException::class);
});

it('treats an empty slug as absent', function () {
    Taxonomy::bulkCreate([['name' => 'Electronics', 'slug' => '', 'type' => 'category']]);

    expect(Taxonomy::first()->slug)->toBe('electronics');
});

it('throws when slug generation is disabled and no slug is given', function () {
    config()->set('taxonomy.slugs.generate', false);

    expect(fn () => Taxonomy::bulkCreate([['name' => 'Electronics', 'type' => 'category']]))
        ->toThrow(MissingSlugException::class);
});

it('accepts an explicit slug when generation is disabled', function () {
    config()->set('taxonomy.slugs.generate', false);

    Taxonomy::bulkCreate([['name' => 'Electronics', 'slug' => 'electro', 'type' => 'category']]);

    expect(Taxonomy::first()->slug)->toBe('electro');
});

it('requires a type on every row', function () {
    expect(fn () => Taxonomy::bulkCreate([['name' => 'No type']]))
        ->toThrow(InvalidArgumentException::class, 'must declare a type');
});

it('requires a name on every row', function () {
    expect(fn () => Taxonomy::bulkCreate([['type' => 'category']]))
        ->toThrow(InvalidArgumentException::class, 'must declare a name');
});

it('accepts a TaxonomyType enum for the type', function () {
    Taxonomy::bulkCreate([['name' => 'Electronics', 'type' => TaxonomyType::Category]]);

    expect(Taxonomy::first()->type)->toBe('category');
});

it('stores description, sort_order and meta', function () {
    Taxonomy::bulkCreate([[
        'name' => 'Electronics',
        'type' => 'category',
        'description' => 'All devices',
        'sort_order' => 7,
        'meta' => ['icon' => 'devices', 'featured' => true],
    ]]);

    $row = Taxonomy::first();

    expect($row->description)->toBe('All devices')
        ->and($row->sort_order)->toBe(7)
        ->and($row->meta)->toBe(['icon' => 'devices', 'featured' => true]);
});

it('honours explicit timestamps', function () {
    Taxonomy::bulkCreate([[
        'name' => 'Old',
        'type' => 'category',
        'created_at' => '2020-01-01 00:00:00',
        'updated_at' => '2020-01-02 00:00:00',
    ]]);

    expect(Taxonomy::first()->created_at->format('Y-m-d'))->toBe('2020-01-01');
});

it('builds a valid nested set for a hierarchy', function () {
    $root = Taxonomy::create(['name' => 'Root', 'type' => 'category']);

    Taxonomy::bulkCreate([
        ['name' => 'Child A', 'type' => 'category', 'parent_id' => $root->id, 'sort_order' => 1],
        ['name' => 'Child B', 'type' => 'category', 'parent_id' => $root->id, 'sort_order' => 2],
    ]);

    $root->refresh();
    $a = Taxonomy::where('slug', 'child-a')->first();

    expect($root->depth)->toBe(0)
        ->and($a->depth)->toBe(1)
        ->and($root->lft)->toBeLessThan($a->lft)
        ->and($root->rgt)->toBeGreaterThan($a->rgt)
        ->and($root->getDescendants())->toHaveCount(2);
});

it('numbers every type it touches', function () {
    Taxonomy::bulkCreate([
        ['name' => 'Cat', 'type' => 'category'],
        ['name' => 'Tag', 'type' => 'tag'],
    ]);

    expect(Taxonomy::whereNull('lft')->count())->toBe(0)
        ->and(Taxonomy::where('type', 'tag')->first()->depth)->toBe(0);
});

it('invalidates cached trees', function () {
    Taxonomy::create(['name' => 'First', 'type' => 'category']);

    expect(TaxonomyFacade::getNestedTree(TaxonomyType::Category))->toHaveCount(1);
    expect(Cache::has('taxonomy_nested_tree_category'))->toBeTrue();

    Taxonomy::bulkCreate([['name' => 'Second', 'type' => 'category']]);

    expect(TaxonomyFacade::getNestedTree(TaxonomyType::Category))->toHaveCount(2);
});

it('writes in chunks', function () {
    $rows = [];
    for ($i = 1; $i <= 25; ++$i) {
        $rows[] = ['name' => "Item {$i}", 'type' => 'category'];
    }

    expect(Taxonomy::bulkCreate($rows, 10))->toBe(25)
        ->and(Taxonomy::count())->toBe(25);
});

it('clamps a non-positive chunk size instead of failing', function () {
    expect(Taxonomy::bulkCreate([['name' => 'A', 'type' => 'category']], 0))->toBe(1);
});

it('accepts any iterable, not just an array', function () {
    $generator = (function () {
        yield ['name' => 'Streamed', 'type' => 'category'];
    })();

    expect(Taxonomy::bulkCreate($generator))->toBe(1);
});

it('is reachable through the facade', function () {
    expect(TaxonomyFacade::bulkCreate([['name' => 'Via facade', 'type' => 'category']]))->toBe(1)
        ->and(Taxonomy::count())->toBe(1);
});

it('does not fire model events', function () {
    $fired = 0;
    Taxonomy::creating(function () use (&$fired) {
        ++$fired;
    });

    Taxonomy::bulkCreate([['name' => 'Silent', 'type' => 'category']]);

    // Documented trade-off: observers are bypassed for speed.
    expect($fired)->toBe(0)
        ->and(Taxonomy::count())->toBe(1);
});

it('considers trashed slugs so a restore cannot collide', function () {
    $trashed = Taxonomy::create(['name' => 'Gone', 'type' => 'category']);
    $trashed->delete();

    Taxonomy::bulkCreate([['name' => 'Gone', 'type' => 'category']]);

    // The live row must not reuse the trashed slug, otherwise restoring the
    // old one would hit the unique index.
    expect(Taxonomy::first()->slug)->toBe('gone-1');
});
