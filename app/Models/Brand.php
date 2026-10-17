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

    /** Brands with at least one public Polish; a brand that exists only for Private Polishes is not public. */
    public function scopeWithPublicPolishes($query)
    {
        return $query->whereHas('polishes', fn ($q) => $q->public());
    }

    /** A brand only matters to cached public pages once it has a public Polish. */
    public function affectsPublicCatalog(): bool
    {
        return $this->exists && $this->polishes()->public()->exists();
    }

    public function polishes(): HasMany
    {
        return $this->hasMany(Polish::class);
    }
}
