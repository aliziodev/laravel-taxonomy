<?php

namespace Aliziodev\LaravelTaxonomy\Exceptions;

class DuplicateSlugException extends TaxonomyException
{
    /**
     * Create a new duplicate slug exception.
     *
     * @param  string  $slug  The duplicate slug
     * @param  string|null  $type  The taxonomy type (optional)
     * @param  string|null  $message  Custom error message (optional)
     * @return void
     */
    public function __construct(string $slug, ?string $type = null, ?string $message = null)
    {
        if ($message === null) {
            $message = $type
                ? "The slug '{$slug}' already exists for type '{$type}'. Please provide a unique slug within this taxonomy type."
                : "The slug '{$slug}' already exists. Please provide a unique slug.";
        }

        parent::__construct($message);
    }
}
