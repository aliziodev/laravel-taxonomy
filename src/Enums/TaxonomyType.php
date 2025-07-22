<?php

namespace Aliziodev\LaravelTaxonomy\Enums;

/**
 * Taxonomy Type Enum.
 *
 * Defines the available taxonomy types that can be used throughout the application.
 * Each type represents a different categorization method for organizing content.
 *
 * @author Aliziodev
 *
 * @since 1.0.0
 */
enum TaxonomyType: string
{
    case Category = 'category';
    case Tag = 'tag';
    case Color = 'color';
    case Size = 'size';
    case Unit = 'unit';
    case Type = 'type';
    case Brand = 'brand';
    case Model = 'model';
    case Variant = 'variant';

    /**
     * Get all enum values as an array of strings.
     *
     * This method returns all the string values of the enum cases,
     * useful for validation, database seeding, or form options.
     *
     * @return array<int, string> Array of all enum values
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label for the taxonomy type.
     *
     * Returns a properly formatted, user-friendly label for display purposes.
     * This is useful for UI elements, form labels, and user-facing content.
     *
     * @return string The human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Category => 'Category',
            self::Tag => 'Tag',
            self::Color => 'Color',
            self::Size => 'Size',
            self::Unit => 'Unit',
            self::Type => 'Type',
            self::Brand => 'Brand',
            self::Model => 'Model',
            self::Variant => 'Variant',
        };
    }

    /**
     * Alias for the label() method.
     *
     * Provides an alternative method name for getting the human-readable label.
     * This method exists for consistency with other Laravel conventions.
     *
     * @return string The human-readable label
     */
    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Get all taxonomy types as an array of options.
     *
     * Returns an array of associative arrays, each containing 'value' and 'label' keys.
     * This format is particularly useful for generating form select options, API responses,
     * or any scenario where you need both the enum value and its display label.
     *
     * @return array<int, array{value: string, label: string}> Array of option arrays
     */
    public static function options(): array
    {
        return collect(self::cases())->map(function ($case) {
            return [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        })->toArray();
    }
}
