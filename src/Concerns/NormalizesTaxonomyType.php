<?php

namespace Aliziodev\LaravelTaxonomy\Concerns;

/**
 * Normalises a taxonomy type to the string stored in the `type` column.
 *
 * Shared so the model, the manager, the trait and the exceptions all accept
 * the same input (a string, the package's TaxonomyType or an application's
 * own backed enum) instead of each carrying their own copy of this check.
 */
trait NormalizesTaxonomyType
{
    /**
     * Get the string value of a taxonomy type.
     *
     * The value is cast because an int-backed enum is a backed enum too, and
     * the `type` column holds strings.
     *
     * @return ($type is null ? null : string)
     */
    protected static function normalizeTaxonomyType(string|\BackedEnum|null $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return (string) ($type instanceof \BackedEnum ? $type->value : $type);
    }
}
