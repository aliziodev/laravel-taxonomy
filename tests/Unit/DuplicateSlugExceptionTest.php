<?php

use Aliziodev\LaravelTaxonomy\Exceptions\DuplicateSlugException;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;

uses(TestCase::class);

it('creates exception with slug and type', function () {
    $exception = new DuplicateSlugException('test-slug', 'category');

    expect($exception->getMessage())->toBe(
        "The slug 'test-slug' already exists for type 'category'. Please provide a unique slug within this taxonomy type."
    );
});

it('creates exception with slug only', function () {
    $exception = new DuplicateSlugException('test-slug');

    expect($exception->getMessage())->toBe(
        "The slug 'test-slug' already exists. Please provide a unique slug."
    );
});

it('creates exception with slug and null type', function () {
    $exception = new DuplicateSlugException('test-slug', null);

    expect($exception->getMessage())->toBe(
        "The slug 'test-slug' already exists. Please provide a unique slug."
    );
});

it('creates exception with custom message', function () {
    $customMessage = 'Custom error message for duplicate slug';
    $exception = new DuplicateSlugException('test-slug', 'category', $customMessage);

    expect($exception->getMessage())->toBe($customMessage);
});

it('creates exception with custom message overriding default', function () {
    $customMessage = 'This is a custom message';
    $exception = new DuplicateSlugException('test-slug', null, $customMessage);

    expect($exception->getMessage())->toBe($customMessage);
});

it('handles empty slug gracefully', function () {
    $exception = new DuplicateSlugException('', 'category');

    expect($exception->getMessage())->toBe(
        "The slug '' already exists for type 'category'. Please provide a unique slug within this taxonomy type."
    );
});

it('handles special characters in slug', function () {
    $exception = new DuplicateSlugException('test-slug-with-special-chars!@#', 'category');

    expect($exception->getMessage())->toBe(
        "The slug 'test-slug-with-special-chars!@#' already exists for type 'category'. Please provide a unique slug within this taxonomy type."
    );
});

it('handles special characters in type', function () {
    $exception = new DuplicateSlugException('test-slug', 'category-with-special!@#');

    expect($exception->getMessage())->toBe(
        "The slug 'test-slug' already exists for type 'category-with-special!@#'. Please provide a unique slug within this taxonomy type."
    );
});

it('is instance of Exception', function () {
    $exception = new DuplicateSlugException('test-slug', 'category');

    expect($exception)->toBeInstanceOf(Exception::class);
});

it('preserves exception hierarchy', function () {
    $exception = new DuplicateSlugException('test-slug', 'category');

    expect($exception)->toBeInstanceOf(Throwable::class);
    expect($exception)->toBeInstanceOf(Exception::class);
    expect($exception)->toBeInstanceOf(DuplicateSlugException::class);
});
