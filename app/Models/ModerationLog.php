<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModerationLog extends Model
{
    use HasUuids;

    protected $table = 'moderation_log';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public static function record(User $admin, string $action, string $targetType, ?string $targetId, string $summary, ?string $note = null): self
    {
        $entry = new self;
        $entry->forceFill([
            'admin_id' => $admin->id, 'action' => $action, 'target_type' => $targetType,
            'target_id' => $targetId, 'summary' => mb_substr($summary, 0, 500), 'note' => $note ? mb_substr($note, 0, 300) : null,
            'created_at' => now(),
        ])->save();

        return $entry;
    }
}
