<?php

namespace Aliziodev\LaravelTaxonomy\Tests\Models;

use Aliziodev\LaravelTaxonomy\Traits\HasTaxonomy;
use Illuminate\Database\Eloquent\Model;

/**
 * Product model with a custom primary key for testing purposes.
 *
 * @property string $product_code
 * @property string $name
 */
class ProductWithCustomKey extends Model
{
    use HasTaxonomy;

    protected $table = 'products_custom_key';

    protected $primaryKey = 'product_code';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['product_code', 'name'];
}
