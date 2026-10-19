<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasUuids;

    public const REASONS = [
        'inappropriate' => 'Inappropriate or offensive',
        'not_nails' => 'Not about nails or polish',
        'spam' => 'Spam or advertising',
        'privacy' => 'Shows someone without their consent',
        'copyright' => 'Someone else\'s photo',
        'other' => 'Something else',
    ];

    // manicure_id, reporter_id and status are set by the app.
    protected $fillable = ['reason', 'details'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    public function manicure(): BelongsTo
    {
        return $this->belongsTo(Manicure::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
