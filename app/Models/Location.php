<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Location extends Model
{
    use HasUuids;

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        static::saving(function (Location $location) {
            $location->name = trim($location->name);
            $location->name_key = Str::lower($location->name);
        });
    }

    public function bottles(): HasMany
    {
        return $this->hasMany(Bottle::class);
    }
}
