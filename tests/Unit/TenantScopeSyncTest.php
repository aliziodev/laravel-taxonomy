<?php

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Mirrors the multi-tenant setup from issue #15: a global scope that hides
 * every row not belonging to the active tenant.
 */
class TenantScopedTaxonomy extends Taxonomy
{
    public static ?int $activeTenant = null;

    protected static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('tenant', function ($query) {
            if (static::$activeTenant !== null) {
                $query->where('meta->tenant_id', static::$activeTenant);
            }
        });
    }
}

beforeEach(function () {
    TenantScopedTaxonomy::$activeTenant = null;
    config()->set('taxonomy.model', TenantScopedTaxonomy::class);
});

afterEach(function () {
    TenantScopedTaxonomy::$activeTenant = null;
});

it('syncs taxonomies that a tenant global scope hides', function () {
    // A taxonomy shared across tenants: the tenant scope filters it out.
    $shared = TenantScopedTaxonomy::create([
        'name' => 'Shared Zone',
        'type' => 'category',
        'meta' => ['tenant_id' => null],
    ]);

    $owned = TenantScopedTaxonomy::create([
        'name' => 'Tenant Zone',
        'type' => 'category',
        'meta' => ['tenant_id' => 1],
    ]);

    $product = Product::create(['name' => 'Post', 'price' => 1]);

    TenantScopedTaxonomy::$activeTenant = 1;

    // The caller explicitly selected both IDs. The internal type check used to
    // re-filter them through the tenant scope, so the shared one was dropped
    // without any error -- exactly the reported symptom.
    $product->syncTaxonomiesOfType('category', [$shared->id, $owned->id]);

    $attached = $product->taxonomies()->withoutGlobalScopes()->pluck('taxonomies.id')->all();

    expect($attached)->toHaveCount(2)
        ->and($attached)->toContain($shared->id)
        ->and($attached)->toContain($owned->id);
});

it('still refuses to attach a taxonomy of the wrong type', function () {
    $category = TenantScopedTaxonomy::create(['name' => 'Cat', 'type' => 'category']);
    $tag = TenantScopedTaxonomy::create(['name' => 'Tag', 'type' => 'tag']);

    $product = Product::create(['name' => 'Post', 'price' => 1]);

    $product->syncTaxonomiesOfType('category', [$category->id, $tag->id]);

    $attached = $product->taxonomies()->withoutGlobalScopes()->pluck('taxonomies.id')->all();

    expect($attached)->toBe([$category->id]);
});

it('still refuses to attach a soft-deleted taxonomy', function () {
    $live = TenantScopedTaxonomy::create(['name' => 'Live', 'type' => 'category']);
    $trashed = TenantScopedTaxonomy::create(['name' => 'Trashed', 'type' => 'category']);
    $trashed->delete();

    $product = Product::create(['name' => 'Post', 'price' => 1]);

    // Bypassing global scopes must not also resurrect trashed rows.
    $product->syncTaxonomiesOfType('category', [$live->id, $trashed->id]);

    $attached = $product->taxonomies()->withoutGlobalScopes()->pluck('taxonomies.id')->all();

    expect($attached)->toBe([$live->id]);
});
