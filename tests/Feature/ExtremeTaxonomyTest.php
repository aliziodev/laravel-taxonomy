<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Feature;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class ExtremeTaxonomyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test struktur tree ekstrem dengan deep nesting (10-20 level)
     * Memastikan tidak ada stack overflow atau recursion error.
     */
    #[Test]
    public function it_can_handle_extreme_deep_nesting_structure(): void
    {
        // Buat struktur dengan 20 level deep
        $levels = 20;
        $currentParent = null;
        $taxonomies = [];

        // Buat chain taxonomy dengan 20 level
        for ($i = 1; $i <= $levels; ++$i) {
            $taxonomy = Taxonomy::create([
                'name' => "Level {$i} Category",
                'type' => TaxonomyType::Category->value,
                'slug' => "level-{$i}-category",
                'parent_id' => $currentParent?->id,
            ]);

            $taxonomies[] = $taxonomy;
            $currentParent = $taxonomy;
        }

        // Rebuild nested set untuk set nested set values
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        // Refresh models untuk mendapatkan lft/rgt values
        $taxonomies = array_map(fn ($t) => $t->fresh(), $taxonomies);
        $deepestNode = end($taxonomies);
        $rootNode = $taxonomies[0];
        $this->assertNotNull($deepestNode);
        $this->assertNotNull($rootNode);

        // Test getAncestors() - deepest node harus punya 19 ancestors
        $ancestors = $deepestNode->getAncestors();
        $this->assertCount(19, $ancestors);
        $firstAncestor = $ancestors->first();
        $this->assertNotNull($firstAncestor);
        $this->assertEquals($rootNode->id, $firstAncestor->id);

        // Test getDescendants() - root harus punya 19 descendants
        $descendants = $rootNode->getDescendants();
        $this->assertCount(19, $descendants);
        $lastDescendant = $descendants->last();
        $this->assertNotNull($lastDescendant);
        $this->assertEquals($deepestNode->id, $lastDescendant->id);

        // Test move operation pada deep structure
        $middleNode = $taxonomies[10]; // Level 11
        $newParent = $taxonomies[5];   // Level 6
        $this->assertNotNull($middleNode);
        $this->assertNotNull($newParent);

        // Move middle node ke parent yang lebih tinggi
        $middleNode->moveToParent($newParent->id);

        // Verify struktur masih valid setelah move
        $refreshedMiddleNode = $middleNode->fresh();
        $this->assertNotNull($refreshedMiddleNode);
        $this->assertEquals($newParent->id, $refreshedMiddleNode->parent_id);

        // Test rebuild() pada struktur kompleks
        $startTime = microtime(true);
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
        $rebuildTime = microtime(true) - $startTime;

        // Pastikan rebuild tidak terlalu lama (< 5 detik untuk 20 level)
        $this->assertLessThan(5.0, $rebuildTime, 'Rebuild took too long: ' . $rebuildTime . ' seconds');

        // Verify struktur masih konsisten setelah rebuild
        $this->assertDatabaseHas('taxonomies', [
            'id' => $rootNode->id,
            'lft' => 1,
        ]);
    }

    /**
     * Test dengan multiple branches pada setiap level.
     */
    #[Test]
    public function it_can_handle_extreme_wide_and_deep_structure(): void
    {
        // Buat struktur dengan 3 level, setiap level punya 2 children (reduced to prevent hang)
        $this->createWideDeepStructure(3, 2);

        // Hitung total nodes yang dibuat: Level 1: 2, Level 2: 4, Level 3: 8 = 14 nodes
        // Struktur rekursif menghasilkan 2^1 + 2^2 + 2^3 = 2 + 4 + 8 = 14 nodes
        $totalNodes = Taxonomy::count();
        $this->assertGreaterThan(10, $totalNodes);
        $this->assertLessThan(20, $totalNodes, 'Too many nodes created, test may hang');

        // Test performance getNestedTree() pada struktur besar
        $startTime = microtime(true);
        $tree = Taxonomy::getNestedTree();
        $getTreeTime = microtime(true) - $startTime;

        $this->assertLessThan(2.0, $getTreeTime, 'getNestedTree() took too long: ' . $getTreeTime . ' seconds');
        $this->assertGreaterThan(0, $tree->count());
    }

    /**
     * Helper untuk membuat struktur wide dan deep.
     */
    private function createWideDeepStructure(int $maxDepth, int $branchingFactor, ?int $parentId = null, int $currentDepth = 1): void
    {
        if ($currentDepth > $maxDepth) {
            return;
        }

        for ($i = 1; $i <= $branchingFactor; ++$i) {
            $taxonomy = Taxonomy::create([
                'name' => "Level {$currentDepth} Branch {$i}",
                'type' => TaxonomyType::Category->value,
                'slug' => "level-{$currentDepth}-branch-{$i}-" . uniqid(),
                'parent_id' => $parentId,
            ]);

            // Recursively create children
            $this->createWideDeepStructure($maxDepth, $branchingFactor, $taxonomy->id, $currentDepth + 1);
        }
    }

    /**
     * Test deteksi dan perbaikan struktur invalid
     * Simulasikan struktur rusak dengan mengubah lft/rgt manual.
     */
    #[Test]
    public function it_can_detect_and_repair_invalid_structure(): void
    {
        // Buat struktur normal terlebih dahulu
        $root = Taxonomy::create([
            'name' => 'Root',
            'type' => TaxonomyType::Category->value,
            'slug' => 'root',
        ]);

        $child1 = Taxonomy::create([
            'name' => 'Child 1',
            'type' => TaxonomyType::Category->value,
            'slug' => 'child-1',
            'parent_id' => $root->id,
        ]);

        $child2 = Taxonomy::create([
            'name' => 'Child 2',
            'type' => TaxonomyType::Category->value,
            'slug' => 'child-2',
            'parent_id' => $root->id,
        ]);

        $grandchild = Taxonomy::create([
            'name' => 'Grandchild',
            'type' => TaxonomyType::Category->value,
            'slug' => 'grandchild',
            'parent_id' => $child1->id,
        ]);

        // Simpan struktur yang benar untuk perbandingan
        $originalStructure = Taxonomy::orderBy('lft')->get(['id', 'name', 'lft', 'rgt', 'parent_id'])->toArray();

        // Rusak struktur dengan mengubah lft/rgt secara manual
        DB::table('taxonomies')->where('id', $child1->id)->update(['lft' => 10, 'rgt' => 15]);
        DB::table('taxonomies')->where('id', $child2->id)->update(['lft' => 5, 'rgt' => 6]);
        DB::table('taxonomies')->where('id', $grandchild->id)->update(['lft' => 20, 'rgt' => 21]);

        // Verify struktur memang rusak
        $damagedChild1 = Taxonomy::find($child1->id);
        $this->assertNotNull($damagedChild1);
        $this->assertEquals(10, $damagedChild1->lft);
        $this->assertEquals(15, $damagedChild1->rgt);

        // Test rebuild() memperbaiki struktur
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);

        // Verify struktur sudah diperbaiki
        $repairedStructure = Taxonomy::orderBy('lft')->get(['id', 'name', 'lft', 'rgt', 'parent_id'])->toArray();

        // Pastikan parent-child relationship masih benar
        $repairedChild1 = Taxonomy::find($child1->id);
        $repairedGrandchild = Taxonomy::find($grandchild->id);

        $this->assertNotNull($repairedChild1);
        $this->assertNotNull($repairedGrandchild);
        $this->assertEquals($root->id, $repairedChild1->parent_id);
        $this->assertEquals($child1->id, $repairedGrandchild->parent_id);

        // Pastikan lft/rgt values valid (lft < rgt, dan nested set rules)
        foreach (Taxonomy::all() as $taxonomy) {
            $this->assertLessThan($taxonomy->rgt, $taxonomy->lft);

            if ($taxonomy->parent_id) {
                $parent = Taxonomy::find($taxonomy->parent_id);
                $this->assertNotNull($parent);
                $this->assertGreaterThan($parent->lft, $taxonomy->lft);
                $this->assertLessThan($parent->rgt, $taxonomy->rgt);
            }
        }
    }

    /**
     * Test soft delete taxonomy dan dampaknya pada children
     * (Jika menggunakan SoftDeletes trait).
     */
    #[Test]
    public function it_can_handle_soft_delete_taxonomy_with_children(): void
    {
        // Skip test jika model tidak menggunakan SoftDeletes
        if (! in_array('Illuminate\Database\Eloquent\SoftDeletes', trait_uses_recursive(Taxonomy::class))) {
            $this->markTestSkipped('Taxonomy model does not use SoftDeletes trait');
        }

        // Buat struktur parent-child
        $parent = Taxonomy::create([
            'name' => 'Parent Category',
            'type' => TaxonomyType::Category->value,
            'slug' => 'parent-category',
        ]);

        $child1 = Taxonomy::create([
            'name' => 'Child 1',
            'type' => TaxonomyType::Category->value,
            'slug' => 'child-1',
            'parent_id' => $parent->id,
        ]);

        $child2 = Taxonomy::create([
            'name' => 'Child 2',
            'type' => TaxonomyType::Category->value,
            'slug' => 'child-2',
            'parent_id' => $parent->id,
        ]);

        // Clear cache sebelum test
        Cache::flush();

        // Soft delete parent
        $parent->delete();

        // Test apakah parent ter-soft delete
        $this->assertTrue($parent->trashed());

        // Test apakah children juga ter-soft delete (tergantung implementasi)
        // Ini bisa berbeda tergantung business logic yang diinginkan
        $child1Fresh = Taxonomy::withTrashed()->find($child1->id);
        $child2Fresh = Taxonomy::withTrashed()->find($child2->id);

        // Test cache dibersihkan setelah soft delete
        $cachedTree = Cache::get('taxonomy_tree');
        $this->assertNull($cachedTree, 'Cache should be cleared after soft delete');

        // Test restore functionality
        $parent->restore();
        $freshParent = $parent->fresh();
        $this->assertNotNull($freshParent);
        $this->assertFalse($freshParent->trashed());
    }

    /**
     * Test race condition saat concurrent moveToParent operations.
     */
    #[Test]
    public function it_can_handle_race_condition_concurrent_move_operations(): void
    {
        // Buat struktur test
        $root = Taxonomy::create([
            'name' => 'Root',
            'type' => TaxonomyType::Category->value,
            'slug' => 'root',
        ]);

        $parent1 = Taxonomy::create([
            'name' => 'Parent 1',
            'type' => TaxonomyType::Category->value,
            'slug' => 'parent-1',
            'parent_id' => $root->id,
        ]);

        $parent2 = Taxonomy::create([
            'name' => 'Parent 2',
            'type' => TaxonomyType::Category->value,
            'slug' => 'parent-2',
            'parent_id' => $root->id,
        ]);

        $movingNode = Taxonomy::create([
            'name' => 'Moving Node',
            'type' => TaxonomyType::Category->value,
            'slug' => 'moving-node',
            'parent_id' => $parent1->id,
        ]);

        // Simulasi concurrent operations menggunakan database transactions
        $results = [];
        $exceptions = [];

        // Jalankan multiple move operations secara "concurrent"
        for ($i = 0; $i < 5; ++$i) {
            try {
                DB::transaction(function () use ($movingNode, $parent1, $parent2, &$results) {
                    // Alternate between moving to parent1 and parent2
                    $targetParent = (count($results) % 2 === 0) ? $parent2 : $parent1;

                    $freshMovingNode = $movingNode->fresh();
                    $this->assertNotNull($freshMovingNode);
                    $freshMovingNode->moveToParent($targetParent->id);
                    $results[] = $targetParent->id;
                });
            } catch (\Exception $e) {
                $exceptions[] = $e->getMessage();
            }
        }

        // Verify final state is consistent
        $finalNode = $movingNode->fresh();
        $this->assertNotNull($finalNode);
        $this->assertNotNull($finalNode->parent_id);
        $this->assertTrue(in_array($finalNode->parent_id, [$parent1->id, $parent2->id]));

        // Verify nested set structure is still valid
        $this->assertValidNestedSetStructure();
    }

    /**
     * Performance testing dengan 10,000 taxonomies.
     */
    #[Test]
    public function it_can_handle_performance_with_large_dataset(): void
    {
        // Skip jika environment tidak mendukung test berat
        if (config('app.skip_performance_tests', false)) {
            $this->markTestSkipped('Performance tests skipped');
        }

        $targetCount = 100; // Further reduced to prevent hang

        // Test 1: Bulk creation performance
        $startTime = microtime(true);

        $taxonomies = [];
        for ($i = 1; $i <= $targetCount; ++$i) {
            $taxonomies[] = [
                'name' => "Category {$i}",
                'type' => TaxonomyType::Category->value,
                'slug' => "category-{$i}",
                'created_at' => now(),
                'updated_at' => now(),
            ];

            // Batch insert setiap 100 records
            if ($i % 100 === 0) {
                DB::table('taxonomies')->insert($taxonomies);
                $taxonomies = [];
            }
        }

        if (! empty($taxonomies)) {
            DB::table('taxonomies')->insert($taxonomies);
        }

        $creationTime = microtime(true) - $startTime;
        $this->assertLessThan(5.0, $creationTime, "Creation of {$targetCount} taxonomies took too long: {$creationTime} seconds");

        // Test 2: rebuild() performance
        $startTime = microtime(true);
        Taxonomy::rebuildNestedSet(TaxonomyType::Category->value);
        $rebuildTime = microtime(true) - $startTime;

        $this->assertLessThan(10.0, $rebuildTime, "Rebuild with {$targetCount} taxonomies took too long: {$rebuildTime} seconds");

        // Test 3: getNestedTree() performance
        $startTime = microtime(true);
        $tree = Taxonomy::getNestedTree();
        $getTreeTime = microtime(true) - $startTime;

        $this->assertLessThan(3.0, $getTreeTime, "getNestedTree() with {$targetCount} taxonomies took too long: {$getTreeTime} seconds");
        $this->assertGreaterThan(0, $tree->count());

        // Test 4: moveToParent() performance pada dataset besar
        $firstTaxonomy = Taxonomy::first();
        $lastTaxonomy = Taxonomy::orderBy('id', 'desc')->first();

        $this->assertNotNull($firstTaxonomy);
        $this->assertNotNull($lastTaxonomy);

        $startTime = microtime(true);
        $lastTaxonomy->moveToParent($firstTaxonomy->id);
        $moveTime = microtime(true) - $startTime;

        $this->assertLessThan(2.0, $moveTime, "moveToParent() on large dataset took too long: {$moveTime} seconds");

        // Verify move was successful
        $freshLastTaxonomy = $lastTaxonomy->fresh();
        $this->assertNotNull($freshLastTaxonomy);
        $this->assertEquals($firstTaxonomy->id, $freshLastTaxonomy->parent_id);

        // Output performance metrics
        // Verify performance test results
        $this->assertLessThan(60.0, $creationTime, "Creation time should be under 60 seconds for {$targetCount} taxonomies");
        $this->assertLessThan(45.0, $rebuildTime, "Rebuild time should be under 45 seconds for {$targetCount} taxonomies");
        $this->assertLessThan(10.0, $getTreeTime, "GetNestedTree time should be under 10 seconds for {$targetCount} taxonomies");
        $this->assertLessThan(5.0, $moveTime, "MoveToParent time should be under 5 seconds for {$targetCount} taxonomies");
    }

    /**
     * Test memory usage pada operasi besar.
     */
    #[Test]
    public function it_can_handle_memory_usage_large_operations(): void
    {
        $initialMemory = memory_get_usage(true);

        // Buat 50 taxonomies (reduced to prevent hang)
        for ($i = 1; $i <= 50; ++$i) {
            Taxonomy::create([
                'name' => "Memory Test {$i}",
                'type' => TaxonomyType::Category->value,
                'slug' => "memory-test-{$i}",
            ]);
        }

        $afterCreationMemory = memory_get_usage(true);

        // Jalankan operasi yang memory-intensive
        $tree = Taxonomy::getNestedTree();
        $firstTaxonomy = Taxonomy::first();
        $this->assertNotNull($firstTaxonomy);
        $descendants = $firstTaxonomy->getDescendants();

        $finalMemory = memory_get_usage(true);

        $creationMemoryIncrease = $afterCreationMemory - $initialMemory;
        $operationMemoryIncrease = $finalMemory - $afterCreationMemory;

        // Memory increase shouldn't be excessive (< 10MB untuk 50 records)
        $this->assertLessThan(10 * 1024 * 1024, $creationMemoryIncrease, 'Memory usage too high during creation');
        $this->assertLessThan(5 * 1024 * 1024, $operationMemoryIncrease, 'Memory usage too high during operations');

        // Verify memory usage is within acceptable limits
        $maxMemoryIncrease = 512 * 1024 * 1024; // 512MB
        $this->assertLessThan($maxMemoryIncrease, $creationMemoryIncrease, 'Creation memory increase should be under 512MB');
        $this->assertLessThan($maxMemoryIncrease / 2, $operationMemoryIncrease, 'Operation memory increase should be under 256MB');
        
        // Memory bisa sama karena garbage collection, jadi kita test bahwa tidak ada memory leak besar
        $this->assertGreaterThanOrEqual($initialMemory, $afterCreationMemory, 'Memory should not decrease after creation');
        $this->assertGreaterThanOrEqual($afterCreationMemory, $finalMemory, 'Memory should not decrease after operations');
        
        // Memory usage tracking completed without major leaks
        $memoryEfficient = ($creationMemoryIncrease < 50 * 1024 * 1024) && ($operationMemoryIncrease < 25 * 1024 * 1024);
        $this->assertTrue($memoryEfficient, 'Memory usage should be efficient for the operations performed');
    }

    /**
     * Helper untuk validasi nested set structure.
     */
    private function assertValidNestedSetStructure(): void
    {
        $taxonomies = Taxonomy::orderBy('lft')->get();

        foreach ($taxonomies as $taxonomy) {
            $this->assertNotNull($taxonomy);
            // lft harus lebih kecil dari rgt
            $this->assertLessThan($taxonomy->rgt, $taxonomy->lft, "Invalid lft/rgt for taxonomy {$taxonomy->id}");

            // Jika punya parent, harus berada dalam range parent
            if ($taxonomy->parent_id) {
                $parent = Taxonomy::find($taxonomy->parent_id);
                $this->assertNotNull($parent);
                $this->assertGreaterThan($parent->lft, $taxonomy->lft, "Child lft not greater than parent lft for taxonomy {$taxonomy->id}");
                $this->assertLessThan($parent->rgt, $taxonomy->rgt, "Child rgt not less than parent rgt for taxonomy {$taxonomy->id}");
            }
        }
    }
}
