<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Feature;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;

class RebuildNestedSetCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_can_rebuild_all_taxonomy_types(): void
    {
        // Create test taxonomies with broken nested set values
        $this->createTestTaxonomies();

        // Verify initial broken state
        $this->assertBrokenNestedSetValues();

        // Run rebuild command
        $exitCode = Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);

        // Assert command succeeded
        $this->assertEquals(0, $exitCode);

        // Verify nested set values are now correct
        $this->assertCorrectNestedSetValues();
    }

    #[Test]
    public function it_can_rebuild_specific_taxonomy_type(): void
    {
        // Create test taxonomies with broken nested set values
        $this->createTestTaxonomies();

        // Verify initial broken state
        $this->assertBrokenNestedSetValues();

        // Run rebuild command for categories only
        $exitCode = Artisan::call('taxonomy:rebuild-nested-set', [
            'type' => TaxonomyType::Category->value,
            '--force' => true,
        ]);

        // Assert command succeeded
        $this->assertEquals(0, $exitCode);

        // Verify only categories have correct nested set values
        $categories = Taxonomy::where('type', TaxonomyType::Category->value)->get();
        foreach ($categories as $category) {
            $this->assertNotNull($category->lft);
            $this->assertNotNull($category->rgt);
            $this->assertNotNull($category->depth);
            $this->assertGreaterThan($category->lft, $category->rgt);
        }

        // Tags should still have broken values
        $tags = Taxonomy::where('type', TaxonomyType::Tag->value)->get();
        $brokenTags = $tags->filter(fn ($tag) => is_null($tag->lft) || is_null($tag->rgt));
        $this->assertGreaterThan(0, $brokenTags->count());
    }

    #[Test]
    public function it_shows_confirmation_prompt_without_force_option(): void
    {
        // Create test taxonomies with broken nested set values
        $this->createTestTaxonomies();

        // Mock user input to decline
        $result = $this->artisan('taxonomy:rebuild-nested-set');
        $this->assertInstanceOf(\Illuminate\Testing\PendingCommand::class, $result);
        $result->expectsConfirmation('Do you want to continue?', 'no')
            ->expectsOutput('Operation cancelled.')
            ->assertExitCode(0);

        // Verify nested set values are still broken
        $this->assertBrokenNestedSetValues();
    }

    #[Test]
    public function it_accepts_confirmation_and_rebuilds(): void
    {
        // Create test taxonomies with broken nested set values
        $this->createTestTaxonomies();

        // Verify initial broken state
        $this->assertBrokenNestedSetValues();

        // Use Artisan::call with force flag to avoid confirmation prompt
        $exitCode = Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        // Verify nested set values are now correct
        $this->assertCorrectNestedSetValues();
    }

    #[Test]
    public function it_handles_invalid_taxonomy_type(): void
    {
        $result = $this->artisan('taxonomy:rebuild-nested-set', ['type' => 'invalid_type']);
        $this->assertInstanceOf(\Illuminate\Testing\PendingCommand::class, $result);
        $result->expectsOutput('Invalid taxonomy type: invalid_type')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_handles_empty_database(): void
    {
        // Clear all taxonomies
        Taxonomy::truncate();

        $result = $this->artisan('taxonomy:rebuild-nested-set', ['--force' => true]);
        $this->assertInstanceOf(\Illuminate\Testing\PendingCommand::class, $result);
        $result->expectsOutput('No taxonomies found to rebuild.')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_shows_progress_information(): void
    {
        // Create test taxonomies with broken nested set values
        $this->createTestTaxonomies();

        $result = $this->artisan('taxonomy:rebuild-nested-set', ['--force' => true]);
        $this->assertInstanceOf(\Illuminate\Testing\PendingCommand::class, $result);

        $result->expectsOutput('Starting nested set rebuild...')
            ->expectsOutputToContain('Rebuilding type:')
            ->expectsOutputToContain('Nested set rebuild completed in')
            ->assertExitCode(0);
    }

    #[Test]
    public function it_maintains_data_integrity_during_rebuild(): void
    {
        // Create test taxonomies with broken nested set values
        $this->createTestTaxonomies();

        // Store original data
        $originalCount = Taxonomy::count();
        $originalNames = Taxonomy::pluck('name')->sort()->values();

        // Run rebuild
        Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);

        // Verify data integrity
        $this->assertEquals($originalCount, Taxonomy::count());
        $this->assertEquals($originalNames, Taxonomy::pluck('name')->sort()->values());

        // Verify nested set integrity
        $this->assertNestedSetIntegrity();
    }

    #[Test]
    public function it_rebuilds_complex_hierarchies_correctly(): void
    {
        // Create a complex hierarchy
        $this->createComplexHierarchy();

        // Break nested set values
        Taxonomy::query()->update(['lft' => null, 'rgt' => null, 'depth' => null]);

        // Rebuild
        Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);

        // Verify complex hierarchy is correctly rebuilt
        $this->assertComplexHierarchyIntegrity();
    }

    /**
     * Create test taxonomies with intentionally broken nested set values.
     */
    protected function createTestTaxonomies(): void
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
    protected function assertBrokenNestedSetValues(): void
    {
        $brokenTaxonomies = Taxonomy::whereNull('lft')
            ->orWhereNull('rgt')
            ->orWhereNull('depth')
            ->count();

        $this->assertGreaterThan(0, $brokenTaxonomies, 'Expected some taxonomies to have broken nested set values');
    }

    /**
     * Assert that nested set values are correct.
     */
    protected function assertCorrectNestedSetValues(): void
    {
        $taxonomies = Taxonomy::all();

        foreach ($taxonomies as $taxonomy) {
            $this->assertNotNull($taxonomy->lft, "Taxonomy {$taxonomy->name} should have lft value");
            $this->assertNotNull($taxonomy->rgt, "Taxonomy {$taxonomy->name} should have rgt value");
            $this->assertNotNull($taxonomy->depth, "Taxonomy {$taxonomy->name} should have depth value");
            $this->assertGreaterThan($taxonomy->lft, $taxonomy->rgt, "Taxonomy {$taxonomy->name} rgt should be greater than lft");
        }
    }

    /**
     * Assert nested set integrity across the entire tree.
     */
    protected function assertNestedSetIntegrity(): void
    {
        $types = Taxonomy::select('type')->distinct()->pluck('type');

        foreach ($types as $type) {
            $taxonomies = Taxonomy::where('type', $type)
                ->orderBy('lft')
                ->get();

            // Check for gaps or overlaps in lft/rgt values
            $expectedLft = 1;
            foreach ($taxonomies->where('parent_id', null) as $root) {
                $this->assertNestedSetNodeIntegrity($root, $expectedLft);
                $expectedLft = $root->rgt + 1;
            }
        }
    }

    /**
     * Recursively check nested set integrity for a node and its descendants.
     */
    protected function assertNestedSetNodeIntegrity(Taxonomy $node, int &$expectedLft): void
    {
        $this->assertEquals($expectedLft, $node->lft, "Node {$node->name} should have lft = {$expectedLft}");
        ++$expectedLft;

        $children = Taxonomy::where('parent_id', $node->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {
            $this->assertEquals($node->depth + 1, $child->depth, "Child {$child->name} should have correct depth");
            $this->assertNestedSetNodeIntegrity($child, $expectedLft);
        }

        $this->assertEquals($expectedLft, $node->rgt, "Node {$node->name} should have rgt = {$expectedLft}");
        ++$expectedLft;
    }

    /**
     * Create a complex hierarchy for testing.
     */
    protected function createComplexHierarchy(): void
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
    protected function assertComplexHierarchyIntegrity(): void
    {
        // Check root node
        $root = Taxonomy::where('name', 'Root Category')->first();
        $this->assertNotNull($root);
        $this->assertEquals(0, $root->depth);
        $this->assertEquals(1, $root->lft);

        // Check that all descendants are properly nested within root
        $descendants = Taxonomy::where('lft', '>', $root->lft)
            ->where('rgt', '<', $root->rgt)
            ->count();

        $expectedDescendants = 3 + (3 * 2) + (3 * 2 * 2); // 3 + 6 + 12 = 21
        $this->assertEquals($expectedDescendants, $descendants);

        // Check depth consistency
        $level1Nodes = Taxonomy::where('depth', 1)->count();
        $level2Nodes = Taxonomy::where('depth', 2)->count();
        $level3Nodes = Taxonomy::where('depth', 3)->count();

        $this->assertEquals(3, $level1Nodes);
        $this->assertEquals(6, $level2Nodes);
        $this->assertEquals(12, $level3Nodes);
    }
}
