<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class InviteRequest extends Model
{
    use HasUuids;

    protected $fillable = ['email'];

    protected function casts(): array
    {
        return ['invited_at' => 'datetime'];
    }
}
