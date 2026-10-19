<?php

namespace App\Models;

use App\Models\Concerns\BumpsCatalogVersion;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Polish extends Model
{
    use BumpsCatalogVersion, HasUuids;

    /** The filesystem disk swatch photos are stored on. */
    public const SWATCH_DISK = 'public';

    // Laravel's pluralizer treats "polish" as uncountable.
    protected $table = 'polishes';

    // user_id (privacy) is set by the app, never mass-assigned from a request.
    protected $fillable = ['brand_id', 'name', 'collection', 'color_family_id', 'vendor_url', 'swatch_path'];

    protected function casts(): array
    {
        return ['promotion_requested_at' => 'datetime'];
    }

    /** The picture to show: an uploaded swatch if there is one, else an https link from an import. */
    public function swatchSrc(): ?string
    {
        if ($this->swatch_path) {
            return Storage::disk(self::SWATCH_DISK)->url($this->swatch_path);
        }

        return $this->swatch_url && str_starts_with($this->swatch_url, 'https://') ? $this->swatch_url : null;
    }

    public function isPrivate(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Whether the viewer may see this Polish: public ones, a Private one's owner, and the Admin
     * only for a Private one its owner has suggested (so the Admin can review it).
     */
    public function visibleTo(?User $user): bool
    {
        if (! $this->isPrivate()) {
            return true;
        }

        return $user !== null && (
            $user->id === $this->user_id
            || ($user->is_admin && $this->promotion_requested_at !== null)
        );
    }

    /** Whether the User may keep a Bottle of it: public Polishes and their own Private ones. */
    public function usableBy(User $user): bool
    {
        return ! $this->isPrivate() || $this->user_id === $user->id;
    }

    public function scopePublic($query)
    {
        return $query->whereNull($query->getModel()->getTable().'.user_id');
    }

    /** Only changes to public Polishes (or ones that were public) affect cached Catalog pages. */
    public function affectsPublicCatalog(): bool
    {
        if ($this->user_id === null) {
            return true;
        }

        // A freshly created private row has no "original"; only an update of a previously public row counts.
        return ! $this->wasRecentlyCreated && $this->getOriginal('user_id') === null;
    }

    protected static function booted(): void
    {
        static::saving(function (Polish $polish) {
            $polish->name = trim($polish->name);
            $polish->name_key = Str::lower($polish->name);
        });
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
