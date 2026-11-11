<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Tests\Models\CustomTaxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['taxonomy.model' => CustomTaxonomy::class]);
});

/*
|--------------------------------------------------------------------------
| The tree relations keep the configured model
|--------------------------------------------------------------------------
| parent() and children() used self::class, so walking the tree of a custom
| model hydrated the package's base model instead.
*/

it('hydrates the children with the configured model', function () {
    $root = CustomTaxonomy::create(['name' => 'Root', 'type' => TaxonomyType::Category->value]);
    CustomTaxonomy::create(['name' => 'Child', 'type' => TaxonomyType::Category->value, 'parent_id' => $root->id]);

    expect($root->children)->toHaveCount(1)
        ->and($root->children->first())->toBeInstanceOf(CustomTaxonomy::class);
});

it('hydrates the parent with the configured model', function () {
    $root = CustomTaxonomy::create(['name' => 'Root', 'type' => TaxonomyType::Category->value]);
    $child = CustomTaxonomy::create(['name' => 'Child', 'type' => TaxonomyType::Category->value, 'parent_id' => $root->id]);

    expect($child->parent)->toBeInstanceOf(CustomTaxonomy::class);
});

it('builds the tree with the configured model', function () {
    $root = CustomTaxonomy::create(['name' => 'Root', 'type' => TaxonomyType::Category->value]);
    CustomTaxonomy::create(['name' => 'Child', 'type' => TaxonomyType::Category->value, 'parent_id' => $root->id]);

    $tree = CustomTaxonomy::tree(TaxonomyType::Category);

    expect($tree->first()->children->first())->toBeInstanceOf(CustomTaxonomy::class);
});
