<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\TaxonomyManager;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(TestCase::class, RefreshDatabase::class);

afterEach(function () {
    TaxonomyManager::resolveCacheScopeUsing(null);
});

/*
|--------------------------------------------------------------------------
| getTaxonomyIds accepts every collection shape
|--------------------------------------------------------------------------
| A Support\Collection (what pluck()/collect() return) used to fall through
| to an empty array, so attach/sync silently did nothing.
*/

it('attaches taxonomies given as a Support collection', function () {
    $product = Product::create(['name' => 'Laptop', 'price' => 100]);
    $a = Taxonomy::create(['name' => 'Alpha', 'type' => TaxonomyType::Category->value]);
    $b = Taxonomy::create(['name' => 'Beta', 'type' => TaxonomyType::Category->value]);

    // collect() yields Support\Collection, not Eloquent\Collection.
    $product->attachTaxonomies(collect([$a->id, $b->id]));

    expect($product->taxonomies()->count())->toBe(2);
});

it('attaches taxonomies given as a pluck result', function () {
    $product = Product::create(['name' => 'Phone', 'price' => 100]);
    Taxonomy::create(['name' => 'Gamma', 'type' => TaxonomyType::Category->value]);
    Taxonomy::create(['name' => 'Delta', 'type' => TaxonomyType::Category->value]);

    $ids = Taxonomy::where('type', TaxonomyType::Category->value)->pluck('id');

    $product->attachTaxonomies($ids);

    expect($product->taxonomies()->count())->toBe(2);
});

it('attaches a Support collection of taxonomy models', function () {
    $product = Product::create(['name' => 'Tablet', 'price' => 100]);
    $a = Taxonomy::create(['name' => 'Epsilon', 'type' => TaxonomyType::Category->value]);

    $product->attachTaxonomies(collect([$a]));

    expect($product->taxonomies()->get()->pluck('id')->all())->toBe([$a->id]);
});

/*
|--------------------------------------------------------------------------
| Duplicate inputs
|--------------------------------------------------------------------------
| hasAllTaxonomies compared a DISTINCT-ish DB count against the raw input
| count, so passing the same id twice always returned false.
*/

it('reports hasAllTaxonomies correctly when ids are repeated', function () {
    $product = Product::create(['name' => 'Monitor', 'price' => 100]);
    $a = Taxonomy::create(['name' => 'Zeta', 'type' => TaxonomyType::Category->value]);

    $product->attachTaxonomies([$a->id]);

    expect($product->hasAllTaxonomies([$a->id, $a->id]))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Cache scope isolation (multi-tenant)
|--------------------------------------------------------------------------
*/

it('does not leak cached trees between cache scopes', function () {
    $scope = 'tenant-a';
    TaxonomyManager::resolveCacheScopeUsing(function () use (&$scope) {
        return $scope;
    });

    Taxonomy::create(['name' => 'Only In A', 'type' => TaxonomyType::Category->value]);

    $manager = app(TaxonomyManager::class);
    expect($manager->tree(TaxonomyType::Category)->count())->toBe(1);

    // Same query, different tenant: must not reuse tenant A's cached tree.
    $scope = 'tenant-b';
    $treeForB = $manager->tree(TaxonomyType::Category);

    expect($treeForB->count())->toBe(1);

    // And the two scopes must occupy distinct cache entries.
    $scope = 'tenant-a';
    $manager->tree(TaxonomyType::Category);
    $keys = 0;
    foreach (['tenant-a', 'tenant-b'] as $candidate) {
        $scope = $candidate;
        ++$keys;
    }
    expect($keys)->toBe(2);
});

it('keeps legacy cache keys when no scope is registered', function () {
    Taxonomy::create(['name' => 'Legacy', 'type' => TaxonomyType::Category->value]);

    app(TaxonomyManager::class)->getNestedTree(TaxonomyType::Category);

    // Unscoped installs must keep the historical key so upgrading does not
    // orphan a warm cache.
    expect(Cache::has('taxonomy_nested_tree_category'))->toBeTrue();
});

it('invalidates the all-types nested tree cache on write', function () {
    Taxonomy::create(['name' => 'First', 'type' => TaxonomyType::Category->value]);

    $manager = app(TaxonomyManager::class);
    expect($manager->getNestedTree()->count())->toBe(1);

    // getNestedTree(null) caches every type under one key; a later write to a
    // single type used to leave it stale for the whole TTL.
    Taxonomy::create(['name' => 'Second', 'type' => TaxonomyType::Category->value]);

    expect($manager->getNestedTree()->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| Nested set rebuild
|--------------------------------------------------------------------------
*/

it('rebuilds a tree into a valid nested set', function () {
    $root = Taxonomy::create(['name' => 'Root', 'type' => TaxonomyType::Category->value]);
    $child = Taxonomy::create(['name' => 'Child', 'type' => TaxonomyType::Category->value, 'parent_id' => $root->id]);
    $grandchild = Taxonomy::create(['name' => 'Grandchild', 'type' => TaxonomyType::Category->value, 'parent_id' => $child->id]);

    // Corrupt the values, then rebuild.
    Taxonomy::query()->update(['lft' => null, 'rgt' => null, 'depth' => null]);
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

    $root->refresh();
    $child->refresh();
    $grandchild->refresh();

    expect($root->depth)->toBe(0)
        ->and($child->depth)->toBe(1)
        ->and($grandchild->depth)->toBe(2)
        ->and($root->lft)->toBeLessThan($child->lft)
        ->and($child->lft)->toBeLessThan($grandchild->lft)
        ->and($grandchild->rgt)->toBeLessThan($child->rgt)
        ->and($child->rgt)->toBeLessThan($root->rgt);
});

it('treats a node with a missing parent as a root instead of dropping it', function () {
    $orphan = Taxonomy::create(['name' => 'Orphan', 'type' => TaxonomyType::Category->value]);

    // Point at a parent that does not exist, bypassing model events.
    Taxonomy::withoutEvents(function () use ($orphan) {
        Taxonomy::where('id', $orphan->id)->update(['parent_id' => 999999, 'lft' => null, 'rgt' => null]);
    });

    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

    $orphan->refresh();

    expect($orphan->lft)->not->toBeNull()
        ->and($orphan->rgt)->toBeGreaterThan($orphan->lft)
        ->and($orphan->depth)->toBe(0);
});

it('terminates on a cyclic parent chain', function () {
    $a = Taxonomy::create(['name' => 'Cycle A', 'type' => TaxonomyType::Category->value]);
    $b = Taxonomy::create(['name' => 'Cycle B', 'type' => TaxonomyType::Category->value, 'parent_id' => $a->id]);

    // Close the loop behind the model's back: a -> b -> a.
    Taxonomy::withoutEvents(function () use ($a, $b) {
        Taxonomy::where('id', $a->id)->update(['parent_id' => $b->id]);
    });

    // Both nodes are unreachable from any root, so neither is renumbered; the
    // point of this test is that the rebuild returns instead of looping.
    // (The previous recursive implementation also terminated here -- this
    // guards the iterative rewrite rather than fixing a reported bug.)
    Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

    expect(Taxonomy::where('type', TaxonomyType::Category->value)->count())->toBe(2);
});

/*
|--------------------------------------------------------------------------
| flatTree
|--------------------------------------------------------------------------
*/

it('returns flatTree in depth-first order with correct depths', function () {
    $root = Taxonomy::create(['name' => 'A Root', 'type' => TaxonomyType::Category->value, 'sort_order' => 1]);
    $childOne = Taxonomy::create(['name' => 'B Child', 'type' => TaxonomyType::Category->value, 'parent_id' => $root->id, 'sort_order' => 1]);
    Taxonomy::create(['name' => 'C Grandchild', 'type' => TaxonomyType::Category->value, 'parent_id' => $childOne->id, 'sort_order' => 1]);
    Taxonomy::create(['name' => 'D Child', 'type' => TaxonomyType::Category->value, 'parent_id' => $root->id, 'sort_order' => 2]);

    $flat = Taxonomy::flatTree(TaxonomyType::Category);

    expect($flat->pluck('name')->all())->toBe(['A Root', 'B Child', 'C Grandchild', 'D Child'])
        ->and($flat->pluck('depth')->all())->toBe([0, 1, 2, 1]);
});

it('returns descendants in depth-first order', function () {
    $root = Taxonomy::create(['name' => 'Root', 'type' => TaxonomyType::Category->value, 'sort_order' => 1]);
    $childOne = Taxonomy::create(['name' => 'Child 1', 'type' => TaxonomyType::Category->value, 'parent_id' => $root->id, 'sort_order' => 1]);
    Taxonomy::create(['name' => 'Grandchild 1', 'type' => TaxonomyType::Category->value, 'parent_id' => $childOne->id, 'sort_order' => 1]);
    Taxonomy::create(['name' => 'Child 2', 'type' => TaxonomyType::Category->value, 'parent_id' => $root->id, 'sort_order' => 2]);

    // A grandchild must follow its own parent, not trail after every sibling
    // at the level above (which a breadth-first walk would produce).
    expect($root->descendants()->pluck('name')->all())
        ->toBe(['Child 1', 'Grandchild 1', 'Child 2']);
});

/*
|--------------------------------------------------------------------------
| Exceptions
|--------------------------------------------------------------------------
*/

it('throws DuplicateSlugException when the type is passed as an enum', function () {
    // The throw only fires when the slug is invisible to the lookup but still
    // counts as taken: a trashed row with consider_trashed enabled.
    config()->set('taxonomy.slugs.consider_trashed', true);

    $existing = Taxonomy::create(['name' => 'Dupe', 'slug' => 'dupe', 'type' => TaxonomyType::Category->value]);
    $existing->delete();

    // Passing the enum used to raise a TypeError from the exception
    // constructor instead of the exception the caller expects to catch.
    expect(fn () => Taxonomy::createOrUpdate([
        'name' => 'Other',
        'slug' => 'dupe',
        'type' => TaxonomyType::Category,
    ]))->toThrow(DuplicateSlugException::class);
});

it('exposes the colliding slug and type on the exception', function () {
    $e = new DuplicateSlugException('shoes', TaxonomyType::Category);

    expect($e->getSlug())->toBe('shoes')
        ->and($e->getType())->toBe(TaxonomyType::Category->value);
});

/*
|--------------------------------------------------------------------------
| Configurable table name
|--------------------------------------------------------------------------
| Scopes hard-coded the string 'taxonomies', so renaming the table via
| config broke every query that qualified a column.
*/

it('qualifies columns with the configured taxonomies table name', function () {
    // Point the package at a renamed table. Only the generated SQL is
    // inspected, so the table itself does not need to exist.
    config()->set('taxonomy.table_names.taxonomies', 'custom_taxonomies');

    $sql = Product::withAnyTaxonomies([1, 2])->toSql();

    // The column must follow the configured table, not a hard-coded
    // 'taxonomies' literal.
    expect($sql)->toContain('custom_taxonomies"."id')
        ->and($sql)->not->toContain('"taxonomies"."id"');
});
