<?php

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Support\Facades\DB;

// No RefreshDatabase here: it wraps each test in a transaction, and SQLite
// ignores PRAGMA foreign_keys inside one. Every test boots a fresh :memory:
// database anyway. Foreign keys are switched on so the parent_id constraint
// behaves as it does on MySQL and PostgreSQL.
uses(TestCase::class);

beforeEach(function () {
    DB::statement('PRAGMA foreign_keys = ON');
});

/**
 * Build the tree every test starts from.
 *
 * Root
 * ├── A
 * │   ├── A1
 * │   └── A2
 * └── B
 *     └── B1
 */
function deleteTestTree(): object
{
    $root = Taxonomy::create(['name' => 'Root', 'type' => 'category']);
    $a = Taxonomy::create(['name' => 'A', 'type' => 'category', 'parent_id' => $root->id]);
    $a1 = Taxonomy::create(['name' => 'A1', 'type' => 'category', 'parent_id' => $a->id]);
    $a2 = Taxonomy::create(['name' => 'A2', 'type' => 'category', 'parent_id' => $a->id]);
    $b = Taxonomy::create(['name' => 'B', 'type' => 'category', 'parent_id' => $root->id]);
    $b1 = Taxonomy::create(['name' => 'B1', 'type' => 'category', 'parent_id' => $b->id]);

    // Later inserts widen earlier rows in the database; reload every bound.
    return (object) array_map(
        fn (Taxonomy $node) => $node->refresh(),
        compact('root', 'a', 'a1', 'a2', 'b', 'b1'),
    );
}

/**
 * @return array<int, string>
 */
function deleteTestPositions(): array
{
    return Taxonomy::where('type', 'category')
        ->orderBy('id')
        ->get()
        ->mapWithKeys(fn (Taxonomy $node) => [$node->id => "{$node->lft}-{$node->rgt}@{$node->depth}"])
        ->all();
}

/**
 * The stored nested set must be exactly what a full rebuild would produce:
 * no overlaps, no gaps, no stale depths.
 */
function expectCanonicalNestedSet(): void
{
    $stored = deleteTestPositions();

    Taxonomy::rebuildNestedSet('category');

    expect($stored)->toBe(deleteTestPositions());
}

it('keeps the nested set intact when force-deleting a soft-deleted leaf', function () {
    $tree = deleteTestTree();

    $tree->a1->delete();
    // The trashed row still carries the lft/rgt it had before the soft
    // delete closed its gap. Closing that gap a second time shifted live
    // nodes out from under their parents.
    $tree->a1->forceDelete();

    expectCanonicalNestedSet();
    expect($tree->a->refresh()->getDescendants()->pluck('name')->all())->toBe(['A2']);
});

it('keeps the nested set intact when deleting leaves loaded together', function (string $method) {
    $tree = deleteTestTree();

    // Every delete shifts the rows after it, so all but the first instance
    // in this collection are stale by the time they are deleted.
    Taxonomy::where('type', 'category')
        ->whereDoesntHave('children')
        ->get()
        ->each(fn (Taxonomy $leaf) => $leaf->{$method}());

    expectCanonicalNestedSet();
    expect($tree->root->refresh()->getDescendants()->pluck('name')->all())->toBe(['A', 'B']);
})->with(['delete', 'forceDelete']);

it('leaves no gap when deleting a node that has children', function (string $method) {
    $tree = deleteTestTree();

    $tree->a->{$method}();

    expectCanonicalNestedSet();
    expect($tree->a1->refresh()->parent_id)->toBe($tree->root->id)
        ->and($tree->a1->depth)->toBe(1)
        ->and($tree->root->refresh()->getDescendants()->pluck('name')->all())->toBe(['A1', 'A2', 'B', 'B1']);
})->with(['delete', 'forceDelete']);

it('force-deletes a parent whose child is already trashed', function () {
    $tree = deleteTestTree();

    $tree->b1->delete();
    // The trashed child still referenced its parent, so the parent_id
    // foreign key rejected this.
    $tree->b->forceDelete();

    expect(Taxonomy::withTrashed()->find($tree->b->id))->toBeNull()
        ->and(Taxonomy::withTrashed()->find($tree->b1->id)?->parent_id)->toBe($tree->root->id);
});

it('restores a trashed child under the parent its siblings moved to', function () {
    $tree = deleteTestTree();

    $tree->a1->delete();
    $tree->a->delete();
    $tree->a1->restore();

    expect($tree->a1->refresh()->parent_id)->toBe($tree->root->id)
        ->and($tree->a1->depth)->toBe(1);
    expectCanonicalNestedSet();
});

it('detaches a force-deleted taxonomy from its models', function () {
    $tree = deleteTestTree();
    $product = Product::create(['name' => 'Phone']);
    $product->attachTaxonomies([$tree->a1, $tree->a2]);

    $tree->a1->delete();
    // A soft-deleted term keeps its pivot row but no longer shows up.
    expect($product->taxonomies()->pluck('name')->all())->toBe(['A2'])
        ->and(DB::table('taxonomables')->count())->toBe(2);

    $tree->a1->forceDelete();

    expect(DB::table('taxonomables')->pluck('taxonomy_id')->all())->toBe([$tree->a2->id]);
});
