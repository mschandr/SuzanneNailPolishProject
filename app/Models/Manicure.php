<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manicure extends Model
{
    use HasUuids;

    public const PUBLIC = 'public';

    public const PRIVATE = 'private';

    public const MAX_PHOTOS = 4;

    // user_id is set by the app. Visibility is validated in the controller.
    protected $fillable = ['worn_on', 'caption', 'visibility'];

    protected function casts(): array
    {
        return ['worn_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ManicurePhoto::class)->orderBy('position');
    }

    /** The ordered Polishes worn, base to top. */
    public function layers(): HasMany
    {
        return $this->hasMany(ManicurePolish::class)->orderBy('position');
    }

    /** What anyone but the owner can see: its own setting, else the owner's current account default. */
    public function effectiveVisibility(): string
    {
        return $this->visibility ?? $this->user->manicure_default_visibility;
    }

    public function isPublic(): bool
    {
        return $this->effectiveVisibility() === self::PUBLIC;
    }

    public function visibleTo(?User $viewer): bool
    {
        return $this->isPublic() || ($viewer !== null && $viewer->id === $this->user_id);
    }

    /** Public means: set to public, or unset while the owner's default is public. */
    public function scopePubliclyVisible($query)
    {
        return $query->where(function ($q) {
            $q->where('manicures.visibility', self::PUBLIC)
                ->orWhere(function ($q) {
                    $q->whereNull('manicures.visibility')
                        ->whereIn('manicures.user_id', User::select('id')->where('manicure_default_visibility', self::PUBLIC));
                });
        });
    }
}
