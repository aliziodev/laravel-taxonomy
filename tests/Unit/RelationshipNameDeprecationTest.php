<?php

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Aliziodev\LaravelTaxonomy\Tests\Models\Product;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Capture E_USER_DEPRECATED raised while running $callback.
 *
 * @return array<int, string>
 */
function captureDeprecations(callable $callback): array
{
    $captured = [];

    set_error_handler(function (int $severity, string $message) use (&$captured) {
        if ($severity === E_USER_DEPRECATED) {
            $captured[] = $message;
        }

        return true;
    });

    try {
        $callback();
    } catch (Throwable) {
        // The call may still fail (the pivot columns do not exist); the
        // deprecation is raised before that and is what we are asserting on.
    } finally {
        restore_error_handler();
    }

    return $captured;
}

it('does not warn for the default relationship name', function () {
    $product = Product::create(['name' => 'P', 'price' => 1]);
    $t = Taxonomy::create(['name' => 'C', 'type' => 'category']);

    $messages = captureDeprecations(function () use ($product, $t) {
        $product->attachTaxonomies([$t->id]);
        $product->taxonomies()->get();
        Product::withAnyTaxonomies([$t->id])->get();
    });

    expect($messages)->toBeEmpty();
});

it('warns when a custom relationship name reaches a scope', function () {
    $t = Taxonomy::create(['name' => 'C', 'type' => 'category']);

    // Scopes cannot honour $name at all, so this is the most misleading case:
    // it used to be accepted and silently ignored.
    $messages = captureDeprecations(function () use ($t) {
        Product::withAnyTaxonomies([$t->id], 'scope_probe_name')->get();
    });

    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain('scope_probe_name')
        ->and($messages[0])->toContain('3.0');
});

it('warns only once per relationship name', function () {
    $t = Taxonomy::create(['name' => 'C', 'type' => 'category']);

    $messages = captureDeprecations(function () use ($t) {
        foreach (range(1, 5) as $ignored) {
            Product::withAnyTaxonomies([$t->id], 'repeated_name')->get();
        }
    });

    // A loop must not flood the log.
    expect($messages)->toHaveCount(1);
});
