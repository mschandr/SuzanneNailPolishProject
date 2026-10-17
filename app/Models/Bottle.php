<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bottle extends Model
{
    use HasUuids;

    public const OWNED = 'owned';

    /** Non-owned Bottles stay as history. */
    public const STATUSES = ['owned', 'used_up', 'given_away', 'discarded'];

    // user_id and status are set by the app, never mass-assigned from a request.
    protected $fillable = ['polish_id', 'location_id'];

    protected $attributes = ['status' => self::OWNED];

    public function polish(): BelongsTo
    {
        return $this->belongsTo(Polish::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
