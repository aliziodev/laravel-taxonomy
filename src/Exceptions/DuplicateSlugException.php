<?php

namespace Aliziodev\LaravelTaxonomy\Exceptions;

class DuplicateSlugException extends TaxonomyException
{
    /**
     * Create a new duplicate slug exception.
     *
     * @param string $slug
     * @param string $message
     * @return void
     */
    public function __construct(string $slug, string $message = null)
    {
        $message = $message ?? "The slug '{$slug}' already exists. Please provide a unique slug.";
        parent::__construct($message);
    }
}