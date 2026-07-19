<?php

use Aliziodev\LaravelTaxonomy\Console\Commands\RebuildNestedSetCommand;
use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\TaxonomyManager;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Pretends STDIN is a terminal so the confirmation prompt is reachable under
 * test, and answers it without needing a real one. Everything else, including
 * confirmRebuild() and the branch it drives, runs unchanged.
 */
class PromptableRebuildCommand extends RebuildNestedSetCommand
{
    public static bool $answer = false;

    protected function hasInteractiveStdin(): bool
    {
        return true;
    }

    /**
     * @param  string  $question
     * @param  bool  $default
     */
    public function confirm($question, $default = false): bool
    {
        return static::$answer;
    }
}

/**
 * A plain Arrayable that is not a Collection, to reach the Arrayable branch of
 * getTaxonomyIds().
 *
 * @implements Arrayable<int, int|string|Taxonomy>
 */
class ArrayableIds implements Arrayable
{
    /** @param array<int, int|string|Taxonomy> $ids */
    public function __construct(protected array $ids) {}

    /** @return array<int, int|string|Taxonomy> */
    public function toArray(): array
    {
        return $this->ids;
    }
}

/** Resolver used to exercise the config-driven cache scope. */
class ConfiguredCacheScope
{
    public function __invoke(): ?string
    {
        return 'from-config';
    }
}

afterEach(function () {
    TaxonomyManager::resolveCacheScopeUsing(null);
});

/*
|--------------------------------------------------------------------------
| Console command: the interactive prompt
|--------------------------------------------------------------------------
*/

it('cancels without rebuilding when the operator declines', function () {
    Taxonomy::create(['name' => 'A', 'type' => 'category']);
    Taxonomy::query()->update(['lft' => null, 'rgt' => null]);

    PromptableRebuildCommand::$answer = false;
    Artisan::registerCommand(new PromptableRebuildCommand);

    $exitCode = Artisan::call('taxonomy:rebuild-nested-set');

    // Declining is a choice, not a failure.
    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('Operation cancelled.')
        ->and(Taxonomy::first()->lft)->toBeNull();
});

it('rebuilds when the operator confirms', function () {
    Taxonomy::create(['name' => 'A', 'type' => 'category']);
    Taxonomy::query()->update(['lft' => null, 'rgt' => null]);

    PromptableRebuildCommand::$answer = true;
    Artisan::registerCommand(new PromptableRebuildCommand);

    $exitCode = Artisan::call('taxonomy:rebuild-nested-set');

    expect($exitCode)->toBe(0)
        ->and(Taxonomy::first()->lft)->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Nested set edge cases
|--------------------------------------------------------------------------
*/

it('returns early when a type has no rows to rebuild', function () {
    Taxonomy::rebuildNestedSet('a_type_with_no_rows');

    expect(Taxonomy::count())->toBe(0);
});

it('deletes a leaf whose nested set values were never populated', function () {
    $orphan = Taxonomy::create(['name' => 'Raw', 'type' => 'category']);

    // Rows written by raw SQL or an older import carry no lft/rgt. Deleting
    // one must not try to close a gap that does not exist.
    Taxonomy::withoutEvents(fn () => Taxonomy::where('id', $orphan->id)
        ->update(['lft' => null, 'rgt' => null]));

    $orphan->refresh()->delete();

    expect(Taxonomy::count())->toBe(0);
});

it('stops descendants() from looping on a cyclic parent chain', function () {
    $a = Taxonomy::create(['name' => 'A', 'type' => 'category']);
    $b = Taxonomy::create(['name' => 'B', 'type' => 'category', 'parent_id' => $a->id]);

    // Close the loop: A -> B -> A.
    Taxonomy::withoutEvents(fn () => Taxonomy::where('id', $a->id)->update(['parent_id' => $b->id]));

    // Walking down from A reaches B, then B's child is A again, which the
    // seen-set rejects instead of recursing forever.
    expect($a->descendants()->pluck('name')->all())->toBe(['B']);
});

it('stops flatTree() from looping on a cyclic parent chain', function () {
    $a = Taxonomy::create(['name' => 'A', 'type' => 'category']);
    $b = Taxonomy::create(['name' => 'B', 'type' => 'category', 'parent_id' => $a->id]);

    Taxonomy::withoutEvents(fn () => Taxonomy::where('id', $a->id)->update(['parent_id' => $b->id]));

    // Starting inside the cycle: A's children -> B, B's children -> A, and A
    // is already emitted.
    expect(Taxonomy::flatTree('category', $a->id)->pluck('name')->all())->toBe(['B', 'A']);
});

/*
|--------------------------------------------------------------------------
| Cache scope resolved from config
|--------------------------------------------------------------------------
*/

it('resolves the cache scope from the config class name', function () {
    config()->set('taxonomy.cache.scope', ConfiguredCacheScope::class);

    Taxonomy::create(['name' => 'Scoped', 'type' => 'category']);

    $manager = app(TaxonomyManager::class);

    expect($manager->getNestedTree(TaxonomyType::Category))->toHaveCount(1)
        ->and(cache()->has('taxonomy_scope_from-config_taxonomy_nested_tree_category'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Scopes given nothing to match
|--------------------------------------------------------------------------
*/

it('leaves the query untouched when withAllTaxonomies gets no ids', function () {
    Product::create(['name' => 'P', 'price' => 1]);

    expect(Product::withAllTaxonomies([])->count())->toBe(1);
});

it('leaves the query untouched when withAllTaxonomiesOfType gets no ids', function () {
    Product::create(['name' => 'P', 'price' => 1]);

    expect(Product::withAllTaxonomiesOfType(TaxonomyType::Tag, [])->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| getTaxonomyIds input shapes
|--------------------------------------------------------------------------
*/

it('accepts a Traversable of ids', function () {
    $product = Product::create(['name' => 'P', 'price' => 1]);
    $a = Taxonomy::create(['name' => 'A', 'type' => 'category']);

    $product->attachTaxonomies(new ArrayIterator([$a->id]));

    expect($product->taxonomies()->count())->toBe(1);
});

it('accepts an Arrayable of ids', function () {
    $product = Product::create(['name' => 'P', 'price' => 1]);
    $a = Taxonomy::create(['name' => 'A', 'type' => 'category']);

    $product->attachTaxonomies(new ArrayableIds([$a->id]));

    expect($product->taxonomies()->count())->toBe(1);
});
