<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class MagicLink extends Model
{
    use HasUuids, Prunable;

    protected $fillable = ['email', 'token_hash', 'expires_at'];

    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }
}
