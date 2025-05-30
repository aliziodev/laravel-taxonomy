<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Models;

use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasTaxonomy;

    protected $fillable = ['name'];

    protected $table = 'products';
}