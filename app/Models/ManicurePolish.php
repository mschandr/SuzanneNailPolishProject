<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One layer in a Manicure's ordered list of Polishes (base coat, color, top coat...). */
class ManicurePolish extends Model
{
    use HasUuids;

    public const MAX_LAYERS = 10;

    /** The target of the whole-hand list; every other target is a nail key (see Manicure::NAILS). */
    public const HAND = 'hand';

    protected $table = 'manicure_polishes'; // Laravel's pluralizer treats "polish" as uncountable

    // manicure_id, position and a validated bottle_id are set by the app.
    protected $fillable = [];

    /**
     * Restrict a layers query (run inside a `whereHas('layers', ...)` on manicures) to layers that count in the
     * Manicure's current mode, so a dormant list from the other mode never matches a search.
     */
    public static function onlyActive($query)
    {
        // A hand-list layer counts in hand mode, a nail-list layer in any other (nails) mode. This is the SQL twin of
        // Manicure::activeLayers(); keep the two in step.
        $hand = self::HAND;
        $handMode = Manicure::MODE_HAND;

        return $query->whereRaw("(manicure_polishes.target = '{$hand}') = (manicures.polish_mode = '{$handMode}')");
    }

    public function manicure(): BelongsTo
    {
        return $this->belongsTo(Manicure::class);
    }

    public function polish(): BelongsTo
    {
        return $this->belongsTo(Polish::class);
    }

    public function bottle(): BelongsTo
    {
        return $this->belongsTo(Bottle::class);
    }
}
