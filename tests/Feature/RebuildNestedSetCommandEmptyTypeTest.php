<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

uses(TestCase::class);

it('handles empty type when no taxonomies found for specific type', function () {
    // Create taxonomies of one type only
    Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    Taxonomy::create([
        'name' => 'Category 2',
        'type' => TaxonomyType::Category->value,
    ]);

    // Verify we have categories but no tags
    expect(Taxonomy::where('type', TaxonomyType::Category->value)->count())->toBe(2);
    expect(Taxonomy::where('type', TaxonomyType::Tag->value)->count())->toBe(0);

    // Run rebuild for tag type (which has no taxonomies)
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', [
        'type' => TaxonomyType::Tag->value,
        '--force' => true,
    ]);

    // Should succeed with exit code 0 (command handles empty types gracefully)
    expect($exitCode)->toBe(0);

    // Verify output contains "No taxonomies found" message
    $output = Artisan::output();
    expect($output)->toContain('No taxonomies found for type: ' . TaxonomyType::Tag->value);
    expect($output)->toContain('Rebuilding type: ' . TaxonomyType::Tag->value);
});

it('handles multiple types during full rebuild with only existing types', function () {
    // Create taxonomies of both types
    Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    Taxonomy::create([
        'name' => 'Tag 1',
        'type' => TaxonomyType::Tag->value,
    ]);

    // Verify we have both types
    expect(Taxonomy::where('type', TaxonomyType::Category->value)->count())->toBe(1);
    expect(Taxonomy::where('type', TaxonomyType::Tag->value)->count())->toBe(1);

    // Run full rebuild (all types)
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', ['--force' => true]);

    // Should succeed with exit code 0
    expect($exitCode)->toBe(0);

    // Verify output contains messages for both types
    $output = Artisan::output();
    expect($output)->toContain('Rebuilding type: ' . TaxonomyType::Category->value);
    expect($output)->toContain('Rebuilding type: ' . TaxonomyType::Tag->value);
    expect($output)->toContain('Rebuilt 1 taxonomies'); // For both types
});

it('shows correct message when specific type has no taxonomies', function () {
    // Ensure database is clean
    Taxonomy::truncate();

    // Create taxonomies of category type only
    Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    // Verify setup
    expect(Taxonomy::where('type', TaxonomyType::Category->value)->count())->toBe(1);
    expect(Taxonomy::where('type', TaxonomyType::Tag->value)->count())->toBe(0);

    // Test rebuilding empty tag type
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', [
        'type' => TaxonomyType::Tag->value,
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Rebuilding type: ' . TaxonomyType::Tag->value);
    expect($output)->toContain('No taxonomies found for type: ' . TaxonomyType::Tag->value);
    expect($output)->not->toContain('Rebuilt');
});

it('handles invalid custom taxonomy type', function () {
    // Create taxonomies of standard types
    Taxonomy::create([
        'name' => 'Category 1',
        'type' => TaxonomyType::Category->value,
    ]);

    // Try to rebuild a custom type that doesn't exist in enum
    $customType = 'invalid_custom_type';

    // Verify no taxonomies exist for custom type
    expect(Taxonomy::where('type', $customType)->count())->toBe(0);

    // Run rebuild for invalid custom type
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', [
        'type' => $customType,
        '--force' => true,
    ]);

    // Should fail with exit code 1 because type is invalid
    expect($exitCode)->toBe(1);

    // Verify output contains error message
    $output = Artisan::output();
    expect($output)->toContain('Invalid taxonomy type: ' . $customType);
    expect($output)->toContain('Available types:');
});

it('returns early when no taxonomies found for type without processing', function () {
    // Create taxonomies of one type
    $category = Taxonomy::create([
        'name' => 'Test Category',
        'type' => TaxonomyType::Category->value,
    ]);

    // Store original nested set values
    $originalLft = $category->lft;
    $originalRgt = $category->rgt;

    // Run rebuild for empty tag type
    $exitCode = Artisan::call('taxonomy:rebuild-nested-set', [
        'type' => TaxonomyType::Tag->value,
        '--force' => true,
    ]);

    expect($exitCode)->toBe(0);

    // Verify that category taxonomy was not affected
    $category->refresh();
    expect($category->lft)->toBe($originalLft);
    expect($category->rgt)->toBe($originalRgt);

    // Verify output shows early return
    $output = Artisan::output();
    expect($output)->toContain('No taxonomies found for type: ' . TaxonomyType::Tag->value);
    expect($output)->not->toContain('Rebuilt 0 taxonomies'); // Should not reach rebuild logic
});
