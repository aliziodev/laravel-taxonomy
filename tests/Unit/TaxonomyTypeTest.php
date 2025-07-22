<?php

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;
use Aliziodev\LaravelTaxonomy\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(TestCase::class, RefreshDatabase::class);

it('has correct enum values', function () {
    expect(TaxonomyType::Category->value)->toBe('category');
    expect(TaxonomyType::Tag->value)->toBe('tag');
    expect(TaxonomyType::Color->value)->toBe('color');
    expect(TaxonomyType::Size->value)->toBe('size');
    expect(TaxonomyType::Unit->value)->toBe('unit');
    expect(TaxonomyType::Type->value)->toBe('type');
    expect(TaxonomyType::Brand->value)->toBe('brand');
    expect(TaxonomyType::Model->value)->toBe('model');
    expect(TaxonomyType::Variant->value)->toBe('variant');
});

it('returns all enum values', function () {
    $values = TaxonomyType::values();

    expect($values)->toBeArray();
    expect($values)->toContain('category');
    expect($values)->toContain('tag');
    expect($values)->toContain('color');
    expect($values)->toContain('size');
    expect($values)->toContain('unit');
    expect($values)->toContain('type');
    expect($values)->toContain('brand');
    expect($values)->toContain('model');
    expect($values)->toContain('variant');
    expect($values)->toHaveCount(9);
});

it('returns correct labels', function () {
    expect(TaxonomyType::Category->label())->toBe('Category');
    expect(TaxonomyType::Tag->label())->toBe('Tag');
    expect(TaxonomyType::Color->label())->toBe('Color');
    expect(TaxonomyType::Size->label())->toBe('Size');
    expect(TaxonomyType::Unit->label())->toBe('Unit');
    expect(TaxonomyType::Type->label())->toBe('Type');
    expect(TaxonomyType::Brand->label())->toBe('Brand');
    expect(TaxonomyType::Model->label())->toBe('Model');
    expect(TaxonomyType::Variant->label())->toBe('Variant');
});

it('getLabel method works correctly', function () {
    expect(TaxonomyType::Category->getLabel())->toBe('Category');
    expect(TaxonomyType::Tag->getLabel())->toBe('Tag');
    expect(TaxonomyType::Color->getLabel())->toBe('Color');
});

it('returns correct options array', function () {
    $options = TaxonomyType::options();

    expect($options)->toBeArray();
    expect($options)->toHaveCount(9);

    // Check first option structure
    expect($options[0])->toHaveKeys(['value', 'label']);
    expect($options[0]['value'])->toBe('category');
    expect($options[0]['label'])->toBe('Category');

    // Check that all enum cases are included
    $values = array_column($options, 'value');
    $labels = array_column($options, 'label');

    expect($values)->toContain('category');
    expect($values)->toContain('tag');
    expect($values)->toContain('color');
    expect($values)->toContain('size');
    expect($values)->toContain('unit');
    expect($values)->toContain('type');
    expect($values)->toContain('brand');
    expect($values)->toContain('model');
    expect($values)->toContain('variant');

    expect($labels)->toContain('Category');
    expect($labels)->toContain('Tag');
    expect($labels)->toContain('Color');
    expect($labels)->toContain('Size');
    expect($labels)->toContain('Unit');
    expect($labels)->toContain('Type');
    expect($labels)->toContain('Brand');
    expect($labels)->toContain('Model');
    expect($labels)->toContain('Variant');
});

it('can be used in match expressions', function () {
    // Test that enum can be used in match expressions by testing the function
    $getMatchResult = function (TaxonomyType $type): string {
        return match ($type) {
            TaxonomyType::Category => 'category_matched',
            TaxonomyType::Tag => 'tag_matched',
            TaxonomyType::Color => 'color_matched',
            TaxonomyType::Size => 'size_matched',
            default => 'other_matched'
        };
    };

    expect($getMatchResult(TaxonomyType::Category))->toBe('category_matched');
    expect($getMatchResult(TaxonomyType::Tag))->toBe('tag_matched');
    expect($getMatchResult(TaxonomyType::Color))->toBe('color_matched');
    expect($getMatchResult(TaxonomyType::Size))->toBe('size_matched');
});

it('can be compared with other enum instances', function () {
    expect(TaxonomyType::Category)->toBe(TaxonomyType::Category);
    expect(TaxonomyType::Category)->not->toBe(TaxonomyType::Tag);
});

it('can be serialized to string', function () {
    expect(TaxonomyType::Category->value)->toBe('category');
    expect(TaxonomyType::Tag->value)->toBe('tag');
    expect(TaxonomyType::Color->value)->toBe('color');
    expect(TaxonomyType::Size->value)->toBe('size');
});

it('has all expected enum cases', function () {
    $cases = TaxonomyType::cases();
    $caseNames = array_map(fn ($case) => $case->name, $cases);

    expect($caseNames)->toContain('Category');
    expect($caseNames)->toContain('Tag');
    expect($caseNames)->toContain('Color');
    expect($caseNames)->toContain('Size');
    expect($caseNames)->toContain('Unit');
    expect($caseNames)->toContain('Type');
    expect($caseNames)->toContain('Brand');
    expect($caseNames)->toContain('Model');
    expect($caseNames)->toContain('Variant');
    expect($cases)->toHaveCount(9);
});
