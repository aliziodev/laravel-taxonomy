<?php

use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('allows same slug for different types', function () {
    // Create taxonomies with same slug but different types
    $category = Taxonomy::create([
        'name' => 'Technology',
        'slug' => 'technology',
        'type' => 'category',
    ]);

    $tag = Taxonomy::create([
        'name' => 'Technology',
        'slug' => 'technology',
        'type' => 'tag',
    ]);

    expect($category->slug)->toBe('technology');
    expect($tag->slug)->toBe('technology');
    expect($category->type)->toBe('category');
    expect($tag->type)->toBe('tag');
});

it('prevents duplicate slug within same type', function () {
    // Create first taxonomy
    Taxonomy::create([
        'name' => 'Technology',
        'slug' => 'technology',
        'type' => 'category',
    ]);

    // Try to create second taxonomy with same slug and same type
    expect(function () {
        Taxonomy::create([
            'name' => 'Technology Duplicate',
            'slug' => 'technology',
            'type' => 'category',
        ]);
    })->toThrow(DuplicateSlugException::class);
});

it('generates unique slug within type scope', function () {
    // Create first taxonomy
    $first = Taxonomy::create([
        'name' => 'Technology',
        'type' => 'category',
    ]);

    // Create second taxonomy with same name within same type
    $second = Taxonomy::create([
        'name' => 'Technology',
        'type' => 'category',
    ]);

    // Create third taxonomy with same name in different type
    $third = Taxonomy::create([
        'name' => 'Technology',
        'type' => 'tag',
    ]);

    expect($first->slug)->toBe('technology');
    expect($second->slug)->toBe('technology-1');
    expect($third->slug)->toBe('technology'); // Can be same because different type
});

it('checks slug existence within type scope', function () {
    // Create taxonomy
    Taxonomy::create([
        'name' => 'Technology',
        'slug' => 'technology',
        'type' => 'category',
    ]);

    // Check slug exists within same type
    expect(Taxonomy::slugExists('technology', 'category'))->toBeTrue();

    // Check slug exists within different type
    expect(Taxonomy::slugExists('technology', 'tag'))->toBeFalse();
});

it('finds taxonomy by slug and type correctly', function () {
    // Create two taxonomies with same slug but different types
    $category = Taxonomy::create([
        'name' => 'Technology',
        'slug' => 'technology',
        'type' => 'category',
    ]);

    $tag = Taxonomy::create([
        'name' => 'Technology',
        'slug' => 'technology',
        'type' => 'tag',
    ]);

    // Test findBySlug method
    $foundCategory = Taxonomy::findBySlug('technology', 'category');
    $foundTag = Taxonomy::findBySlug('technology', 'tag');

    expect($foundCategory->id)->toBe($category->id);
    expect($foundTag->id)->toBe($tag->id);
    expect($foundCategory->type)->toBe('category');
    expect($foundTag->type)->toBe('tag');
});

it('updates slug correctly within type scope', function () {
    // Create taxonomy
    $taxonomy = Taxonomy::create([
        'name' => 'Technology',
        'type' => 'category',
    ]);

    // Create another taxonomy with slug we will try to use
    Taxonomy::create([
        'name' => 'Science',
        'slug' => 'science',
        'type' => 'category',
    ]);

    // Create taxonomy with same slug in different type
    Taxonomy::create([
        'name' => 'Science',
        'slug' => 'science',
        'type' => 'tag',
    ]);

    // Update first taxonomy with slug that already exists in same type
    expect(function () use ($taxonomy) {
        $taxonomy->update(['slug' => 'science']);
    })->toThrow(DuplicateSlugException::class);

    // But can update with slug that exists in different type
    $taxonomy->update(['slug' => 'science-new']);
    expect($taxonomy->fresh()->slug)->toBe('science-new');
});
