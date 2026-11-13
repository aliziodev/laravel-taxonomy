<?php

namespace Aliziodev\LaravelTaxonomy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A row of the taxonomables pivot: one taxonomy attached to one model.
 *
 * It exists so a taxonomy can reach its pivot rows whatever model they point
 * to, which morphedByMany() cannot do because it is bound to a single class.
 *
 * @property int $id
 * @property int $taxonomy_id
 * @property string $taxonomable_type
 * @property int|string $taxonomable_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Taxonomy $taxonomy
 * @property-read Model $taxonomable
 */
class Taxonomable extends Model
{
    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return config('taxonomy.table_names.taxonomables', 'taxonomables');
    }

    /**
     * Get the attached taxonomy.
     *
     * @return BelongsTo<Taxonomy, $this>
     */
    public function taxonomy(): BelongsTo
    {
        /** @var class-string<Taxonomy> $related */
        $related = config('taxonomy.model', Taxonomy::class);

        return $this->belongsTo($related, 'taxonomy_id');
    }

    /**
     * Get the model the taxonomy is attached to.
     *
     * @return MorphTo<Model, $this>
     */
    public function taxonomable(): MorphTo
    {
        return $this->morphTo();
    }
}
