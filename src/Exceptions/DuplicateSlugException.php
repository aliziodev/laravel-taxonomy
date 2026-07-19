<?php

namespace Aliziodev\LaravelTaxonomy\Exceptions;

use Aliziodev\LaravelTaxonomy\Enums\TaxonomyType;

class DuplicateSlugException extends TaxonomyException
{
    /**
     * The slug that collided.
     */
    protected string $slug;

    /**
     * The taxonomy type the collision occurred in, if known.
     */
    protected ?string $type;

    /**
     * Create a new duplicate slug exception.
     *
     * $type accepts a TaxonomyType because the package accepts enums wherever
     * a type is expected; passing one used to raise a TypeError here instead
     * of the intended exception.
     *
     * @param  string  $slug  The duplicate slug
     * @param  string|TaxonomyType|null  $type  The taxonomy type (optional)
     * @param  string|null  $message  Custom error message (optional)
     */
    public function __construct(string $slug, string|TaxonomyType|null $type = null, ?string $message = null)
    {
        $this->slug = $slug;
        $this->type = $type instanceof TaxonomyType ? $type->value : $type;

        if ($message === null) {
            $message = $this->type
                ? "The slug '{$slug}' already exists for type '{$this->type}'. Please provide a unique slug within this taxonomy type."
                : "The slug '{$slug}' already exists. Please provide a unique slug.";
        }

        parent::__construct($message);
    }

    /**
     * Get the slug that collided, without parsing the message.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the taxonomy type the collision occurred in, if known.
     */
    public function getType(): ?string
    {
        return $this->type;
    }
}
