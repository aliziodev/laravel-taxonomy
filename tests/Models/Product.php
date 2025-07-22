<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Models;

use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $name
 */
class Product extends Model
{
    use HasTaxonomy;

    protected $fillable = ['name', 'description'];

    protected $table = 'products';
}
