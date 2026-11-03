<?php

use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Tests\Models\UuidTaxonomy;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// The model hooks pass the taxonomy's own key to the slug checks. With a UUID
// key that is a string, and an int-typed parameter threw a TypeError on every
// slug change and every restore. See issue #18.
uses(TestCase::class);

beforeEach(function () {
    Schema::drop('taxonomables');
    Schema::drop('taxonomies');

    Schema::create('taxonomies', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->string('slug');
        $table->string('type')->index();
        $table->text('description')->nullable();
        $table->foreignUuid('parent_id')->nullable()->constrained('taxonomies');
        $table->integer('sort_order')->default(0);
        $table->unsignedInteger('lft')->nullable()->index();
        $table->unsignedInteger('rgt')->nullable()->index();
        $table->unsignedInteger('depth')->nullable()->index();
        $table->json('meta')->nullable();
        $table->timestamps();
        $table->softDeletes();

        $table->unique(['slug', 'type', 'deleted_at']);
    });
});

it('creates a taxonomy with a UUID key', function () {
    $taxonomy = UuidTaxonomy::create(['name' => 'Books', 'type' => 'category']);

    expect(Str::isUuid($taxonomy->getKey()))->toBeTrue()
        ->and($taxonomy->slug)->toBe('books');
});

it('changes the slug of a UUID-keyed taxonomy', function () {
    $taxonomy = UuidTaxonomy::create(['name' => 'Books', 'type' => 'category']);

    $taxonomy->update(['slug' => 'another-slug']);

    expect($taxonomy->fresh()?->slug)->toBe('another-slug');
});

it('rejects a slug taken by another UUID-keyed taxonomy of the same type', function () {
    UuidTaxonomy::create(['name' => 'Books', 'type' => 'category']);
    $music = UuidTaxonomy::create(['name' => 'Music', 'type' => 'category']);

    expect(fn () => $music->update(['slug' => 'books']))
        ->toThrow(DuplicateSlugException::class);
});

it('excludes the taxonomy itself when regenerating its slug on update', function () {
    config(['taxonomy.slugs.regenerate_on_update' => true]);

    $taxonomy = UuidTaxonomy::create(['name' => 'Books', 'type' => 'category']);

    // Same slug as before. Without the exclusion it would collide with itself
    // and come back as books-1.
    $taxonomy->update(['name' => 'BOOKS']);

    expect($taxonomy->fresh()?->slug)->toBe('books');
});

it('regenerates a conflicting slug when restoring a UUID-keyed taxonomy', function () {
    config(['taxonomy.slugs.consider_trashed' => false]);
    config(['taxonomy.slugs.regenerate_on_restore' => true]);

    $old = UuidTaxonomy::create(['name' => 'Books', 'type' => 'category']);
    $old->delete();

    UuidTaxonomy::create(['name' => 'Books', 'type' => 'category']);

    $old->restore();

    expect($old->fresh()?->slug)->toBe('books-1');
});

it('throws on a restore conflict for a UUID-keyed taxonomy when not regenerating', function () {
    config(['taxonomy.slugs.consider_trashed' => false]);
    config(['taxonomy.slugs.regenerate_on_restore' => false]);

    $old = UuidTaxonomy::create(['name' => 'Books', 'type' => 'category']);
    $old->delete();

    UuidTaxonomy::create(['name' => 'Books', 'type' => 'category']);

    expect(fn () => $old->restore())->toThrow(DuplicateSlugException::class);
});
