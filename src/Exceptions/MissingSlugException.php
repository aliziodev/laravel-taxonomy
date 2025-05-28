<?php

namespace Aliziodev\LaravelTaxonomy\Exceptions;

class MissingSlugException extends TaxonomyException
{
    /**
     * Create a new missing slug exception.
     *
     * @param string $message
     * @return void
     */
    public function __construct(string $message = "Slug is required when automatic slug generation is disabled")
    {
        parent::__construct($message);
    }
}