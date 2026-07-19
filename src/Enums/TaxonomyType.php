<?php

namespace Aliziodev\LaravelTaxonomy\Enums;

use Illuminate\Support\Str;

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
        // Every arm of the previous match returned the value title-cased, so
        // adding a case meant either repeating that or hitting an
        // UnhandledMatchError at runtime. Str::headline gives the same result
        // for all existing cases and a sensible one for multi-word values
        // ('product_type' becomes 'Product Type').
        return Str::headline($this->value);
    }

    /**
     * Human-readable label, named to match Filament's HasLabel contract.
     *
     * This is not redundant with label(). Filament resolves enum labels through
     * `Filament\Support\Contracts\HasLabel`, which requires `getLabel(): ?string`.
     * Returning `string` satisfies that (a return type may be narrowed), so an
     * application enum can implement the contract with no glue code:
     *
     *     enum ProductType: string implements HasLabel
     *     {
     *         case Category = 'category';
     *
     *         public function getLabel(): string
     *         {
     *             return TaxonomyType::from($this->value)->getLabel();
     *         }
     *     }
     *
     * Filament is not a dependency of this package; the method simply keeps the
     * shape its contract expects. Prefer label() when you are not bridging to
     * Filament.
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
        return array_map(fn (self $case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ], self::cases());
    }
}
