<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Models;

use Aliziodev\LaravelTaxonomy\Models\Taxonomy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

/**
 * Taxonomy keyed by UUID, as an app does when it swaps the shipped
 * migration's `id()` for `uuid('id')->primary()`.
 */
class UuidTaxonomy extends Taxonomy
{
    use HasUuids;
}
