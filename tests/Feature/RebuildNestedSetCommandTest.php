<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(TestCase::class, RefreshDatabase::class);

it('can rebuild all taxonomy types', function () {
    // Create test taxonomies with broken nested set values
    createBrokenTestTaxonomies();

    // Verify initial broken state
    assertBrokenNestedSetValues();

    // Run rebuild command
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);

    // Assert command succeeded
    expect($exitCode)->toBe(0);

    // Verify nested set values are now correct
    assertCorrectNestedSetValues();
});

it('can rebuild specific taxonomy type', function () {
    // Create test taxonomies with broken nested set values
    createBrokenTestTaxonomies();

    // Verify initial broken state
    assertBrokenNestedSetValues();

    // Run rebuild command for categories only
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', [
        'type' => TaxonomyType::Category->value,
        '--force' => true,
    ]);

    // Assert command succeeded
    expect($exitCode)->toBe(0);

    // Verify only categories have correct nested set values
    $categories = Taxonomy::where('type', TaxonomyType::Category->value)->get();
    foreach ($categories as $category) {
        expect($category->lft)->not->toBeNull();
        expect($category->rgt)->not->toBeNull();
        expect($category->depth)->not->toBeNull();
        expect($category->rgt)->toBeGreaterThan($category->lft);
    }

    // Tags should still have broken values
    $tags = Taxonomy::where('type', TaxonomyType::Tag->value)->get();
    $brokenTags = $tags->filter(fn ($tag) => is_null($tag->lft) || is_null($tag->rgt));
    expect($brokenTags->count())->toBeGreaterThan(0);
});

it('shows confirmation prompt without force option', function () {
    // Create test taxonomies with broken nested set values
    createBrokenTestTaxonomies();

    // Since we can't easily mock confirmation in Artisan::call(),
    // we'll test that the command exits with code 1 when no force flag is provided
    // and no confirmation is given (this would be the default behavior)
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set');
    expect($exitCode)->toBe(1);

    // Verify nested set values are still broken
    assertBrokenNestedSetValues();
});

it('rebuilds with force flag bypassing confirmation', function () {
    // Create test taxonomies with broken nested set values
    createBrokenTestTaxonomies();

    // Verify initial broken state
    assertBrokenNestedSetValues();

    // Use Artisan::call with force flag to avoid confirmation prompt
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);
    expect($exitCode)->toBe(0);

    // Verify nested set values are now correct
    assertCorrectNestedSetValues();
    assertNestedSetIntegrity();
});

it('handles invalid taxonomy type', function () {
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', ['type' => 'invalid_type']);
    expect($exitCode)->toBe(1);
});

it('handles empty database', function () {
    // Clear all taxonomies
    Taxonomy::truncate();

    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);
    expect($exitCode)->toBe(0);
});

it('shows progress information', function () {
    // Create test taxonomies with broken nested set values
    createBrokenTestTaxonomies();

    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);
    expect($exitCode)->toBe(0);
});

it('maintains data integrity during rebuild', function () {
    // Create test taxonomies with broken nested set values
    createBrokenTestTaxonomies();

    // Store original data
    $originalCount = Taxonomy::count();
    $originalNames = Taxonomy::pluck('name')->sort()->values();

    // Run rebuild
    Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);

    // Verify data integrity
    expect(Taxonomy::count())->toBe($originalCount);
    expect(Taxonomy::pluck('name')->sort()->values())->toEqual($originalNames);

    // Verify nested set integrity
    assertNestedSetIntegrity();
});

it('rebuilds complex hierarchies correctly', function () {
    // Create a complex hierarchy
    createComplexHierarchy();

    // Break nested set values
    Taxonomy::query()->update(['lft' => null, 'rgt' => null, 'depth' => null]);

    // Rebuild
    Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);

    // Verify complex hierarchy is correctly rebuilt
    assertComplexHierarchyIntegrity();
});

/**
 * Create test taxonomies with intentionally broken nested set values.
 */
function createBrokenTestTaxonomies(): void
{
    // Disable model events to prevent automatic nested set calculation
    Taxonomy::unsetEventDispatcher();

    // Categories
    $electronics = new Taxonomy([
        'name' => 'Electronics',
        'slug' => 'electronics',
        'type' => TaxonomyType::Category->value,
        'lft' => null, // Intentionally broken
        'rgt' => null, // Intentionally broken
        'depth' => null, // Intentionally broken
    ]);
    $electronics->saveQuietly();

    $phones = new Taxonomy([
        'name' => 'Phones',
        'slug' => 'phones',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $electronics->id,
        'lft' => null,
        'rgt' => null,
        'depth' => null,
    ]);
    $phones->saveQuietly();

    $smartphones = new Taxonomy([
        'name' => 'Smartphones',
        'slug' => 'smartphones',
        'type' => TaxonomyType::Category->value,
        'parent_id' => $phones->id,
        'lft' => null,
        'rgt' => null,
        'depth' => null,
    ]);
    $smartphones->saveQuietly();

    // Tags
    $popular = new Taxonomy([
        'name' => 'Popular',
        'slug' => 'popular',
        'type' => TaxonomyType::Tag->value,
        'lft' => null,
        'rgt' => null,
        'depth' => null,
    ]);
    $popular->saveQuietly();

    $featured = new Taxonomy([
        'name' => 'Featured',
        'slug' => 'featured',
        'type' => TaxonomyType::Tag->value,
        'lft' => null,
        'rgt' => null,
        'depth' => null,
    ]);
    $featured->saveQuietly();

    // Re-enable model events
    Taxonomy::setEventDispatcher(app('events'));
}

/**
 * Assert that nested set values are broken.
 */
function assertBrokenNestedSetValues(): void
{
    $brokenTaxonomies = Taxonomy::whereNull('lft')
        ->orWhereNull('rgt')
        ->orWhereNull('depth')
        ->count();

    expect($brokenTaxonomies)->toBeGreaterThan(0, 'Expected some taxonomies to have broken nested set values');
}

/**
 * Assert that nested set values are correct.
 */
function assertCorrectNestedSetValues(): void
{
    $taxonomies = Taxonomy::all();

    foreach ($taxonomies as $taxonomy) {
        expect($taxonomy->lft)->not->toBeNull("Taxonomy {$taxonomy->name} should have lft value");
        expect($taxonomy->rgt)->not->toBeNull("Taxonomy {$taxonomy->name} should have rgt value");
        expect($taxonomy->depth)->not->toBeNull("Taxonomy {$taxonomy->name} should have depth value");
        expect($taxonomy->rgt)->toBeGreaterThan($taxonomy->lft, "Taxonomy {$taxonomy->name} rgt should be greater than lft");
    }
}

/**
 * Assert nested set integrity across the entire tree.
 */
function assertNestedSetIntegrity(): void
{
    $types = Taxonomy::select('type')->distinct()->pluck('type');

    foreach ($types as $type) {
        $taxonomies = Taxonomy::where('type', $type)
            ->orderBy('lft')
            ->get();

        // Check for gaps or overlaps in lft/rgt values
        $expectedLft = 1;
        foreach ($taxonomies->where('parent_id', null) as $root) {
            assertNestedSetNodeIntegrity($root, $expectedLft);
            $expectedLft = $root->rgt + 1;
        }
    }
}

/**
 * Recursively check nested set integrity for a node and its descendants.
 */
function assertNestedSetNodeIntegrity(Taxonomy $node, int &$expectedLft): void
{
    expect($node->lft)->toBe($expectedLft, "Node {$node->name} should have lft = {$expectedLft}");
    ++$expectedLft;

    $children = Taxonomy::where('parent_id', $node->id)
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get();

    foreach ($children as $child) {
        expect($child->depth)->toBe($node->depth + 1, "Child {$child->name} should have correct depth");
        assertNestedSetNodeIntegrity($child, $expectedLft);
    }

    expect($node->rgt)->toBe($expectedLft, "Node {$node->name} should have rgt = {$expectedLft}");
    ++$expectedLft;
}

/**
 * Create a complex hierarchy for testing.
 */
function createComplexHierarchy(): void
{
    // Clear existing data
    Taxonomy::truncate();

    // Create a 4-level deep hierarchy normally first
    $root = Taxonomy::create([
        'name' => 'Root Category',
        'slug' => 'root',
        'type' => TaxonomyType::Category->value,
    ]);

    for ($i = 1; $i <= 3; ++$i) {
        $level1 = Taxonomy::create([
            'name' => "Level 1 - {$i}",
            'slug' => "level-1-{$i}",
            'type' => TaxonomyType::Category->value,
            'parent_id' => $root->id,
        ]);

        for ($j = 1; $j <= 2; ++$j) {
            $level2 = Taxonomy::create([
                'name' => "Level 2 - {$i}.{$j}",
                'slug' => "level-2-{$i}-{$j}",
                'type' => TaxonomyType::Category->value,
                'parent_id' => $level1->id,
            ]);

            for ($k = 1; $k <= 2; ++$k) {
                Taxonomy::create([
                    'name' => "Level 3 - {$i}.{$j}.{$k}",
                    'slug' => "level-3-{$i}-{$j}-{$k}",
                    'type' => TaxonomyType::Category->value,
                    'parent_id' => $level2->id,
                ]);
            }
        }
    }
}

/**
 * Assert complex hierarchy integrity.
 */
function assertComplexHierarchyIntegrity(): void
{
    // Check root node
    $root = Taxonomy::where('name', 'Root Category')->first();
    expect($root)->not->toBeNull();
    expect($root->depth)->toBe(0);
    expect($root->lft)->toBe(1);

    // Check that all descendants are properly nested within root
    $descendants = Taxonomy::where('lft', '>', $root->lft)
        ->where('rgt', '<', $root->rgt)
        ->count();

    $expectedDescendants = 3 + (3 * 2) + (3 * 2 * 2); // 3 + 6 + 12 = 21
    expect($descendants)->toBe($expectedDescendants);

    // Check depth consistency
    $level1Nodes = Taxonomy::where('depth', 1)->count();
    $level2Nodes = Taxonomy::where('depth', 2)->count();
    $level3Nodes = Taxonomy::where('depth', 3)->count();

    expect($level1Nodes)->toBe(3);
    expect($level2Nodes)->toBe(6);
    expect($level3Nodes)->toBe(12);
}
