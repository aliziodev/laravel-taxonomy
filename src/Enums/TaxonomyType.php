<?php

namespace Aliziodev\LaravelTaxonomy\Enums;

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
     * Get all enum values.
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

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
}
