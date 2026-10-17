<?php

namespace App\Models;

use App\Models\Concerns\BumpsCatalogVersion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Polish extends Model
{
    use BumpsCatalogVersion, HasUuids;

    // Laravel's pluralizer treats "polish" as uncountable.
    protected $table = 'polishes';

    protected $fillable = ['brand_id', 'name', 'collection', 'color_family_id', 'vendor_url', 'swatch_path'];

    protected static function booted(): void
    {
        static::saving(function (Polish $polish) {
            $polish->name = trim($polish->name);
            $polish->name_key = Str::lower($polish->name);
        });
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function colorFamily(): BelongsTo
    {
        return $this->belongsTo(ColorFamily::class);
    }

    public function finishes(): BelongsToMany
    {
        return $this->belongsToMany(Finish::class, 'finish_polish');
    }

    /** /<brand>/<uuid>/<color-name>. The UUID decides identity; the slugs only read well. */
    public function url(): string
    {
        return '/'.$this->brand->slug.'/'.$this->id.'/'.(Str::slug($this->name) ?: 'polish');
    }
}
