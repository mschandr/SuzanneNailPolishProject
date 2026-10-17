<?php

namespace App\Models;

use App\Models\Concerns\BumpsCatalogVersion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Brand extends Model
{
    use BumpsCatalogVersion, HasUuids;

    protected $fillable = ['name'];

    protected static function booted(): void
    {
        static::saving(function (Brand $brand) {
            $brand->name = trim($brand->name);
            $brand->name_key = Str::lower($brand->name);

            if (! $brand->slug) {
                $base = Str::slug($brand->name) ?: 'brand';
                $slug = $base;
                for ($i = 2; static::where('slug', $slug)->exists(); $i++) {
                    $slug = "{$base}-{$i}";
                }
                $brand->slug = $slug;
            }
        });
    }

    public function polishes(): HasMany
    {
        return $this->hasMany(Polish::class);
    }
}
