<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Import extends Model
{
    use HasUuids, Prunable;

    public const MAX_ROWS = 2000;

    public const MAX_KILOBYTES = 2048;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['applied_at' => 'datetime'];
    }

    /** An upload nobody applied is only a preview; it is cleared after a week. Applied imports are kept as history. */
    public function prunable(): Builder
    {
        return static::where('status', 'preview')->where('created_at', '<', now()->subDays(7));
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('line');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
