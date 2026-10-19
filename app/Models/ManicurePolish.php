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

    protected $table = 'manicure_polishes'; // Laravel's pluralizer treats "polish" as uncountable

    // manicure_id, position and a validated bottle_id are set by the app.
    protected $fillable = [];

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
