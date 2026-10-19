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

    public const MODE_HAND = 'hand';

    public const MODE_NAILS = 'nails';

    /** Nail key => label, in display order (left hand then right, thumb to pinky). */
    public const NAILS = [
        'left_thumb' => 'Left thumb', 'left_index' => 'Left index', 'left_middle' => 'Left middle',
        'left_ring' => 'Left ring', 'left_pinky' => 'Left pinky',
        'right_thumb' => 'Right thumb', 'right_index' => 'Right index', 'right_middle' => 'Right middle',
        'right_ring' => 'Right ring', 'right_pinky' => 'Right pinky',
    ];

    // user_id is set by the app. Visibility is validated in the controller.
    protected $fillable = ['worn_on', 'caption', 'visibility'];

    protected function casts(): array
    {
        return ['worn_on' => 'date', 'hidden_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(ManicurePhoto::class)->orderBy('position');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** Users who hearted this Manicure. */
    public function hearts(): HasMany
    {
        return $this->hasMany(Heart::class);
    }

    /**
     * The layers that count right now: the whole-hand list in whole-hand mode, the nail lists in per-nail mode.
     * The other mode's list is kept but dormant, so switching modes never loses anything.
     *
     * @return \Illuminate\Support\Collection<int, ManicurePolish>
     */
    public function activeLayers()
    {
        $nails = $this->polish_mode === self::MODE_NAILS;

        return $this->layers->filter(fn ($layer) => ($layer->target === ManicurePolish::HAND) !== $nails)->values();
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

    /** Publicly visible right now: set to public, not hidden by the Admin, and the owner is not suspended. */
    public function isPublic(): bool
    {
        return $this->hidden_at === null
            && ! $this->user->isSuspended()
            && $this->effectiveVisibility() === self::PUBLIC;
    }

    /**
     * The Admin may review and act on a Manicure only while it has an open report or is hidden. Once a report
     * is dismissed the Manicure goes back to being the owner's alone: a past report is not standing access.
     */
    public function isModeratable(): bool
    {
        return $this->hidden_at !== null || $this->reports()->where('status', 'open')->exists();
    }

    /** Owners can't change a hidden Manicure (the Admin reviewed the content as it was); they can still delete it. */
    public function abortIfLockedForEditing(): void
    {
        abort_if($this->hidden_at !== null, 403, 'This manicure was hidden by a moderator, so it can no longer be edited. You can still delete it.');
    }

    /**
     * The owner always sees their own. Everyone else sees it only while it is public. The Admin additionally
     * sees one with an open report or that is hidden (to review it), but never other people's private Manicures.
     */
    public function visibleTo(?User $viewer): bool
    {
        if ($viewer !== null && $viewer->id === $this->user_id) {
            return true;
        }

        if ($viewer?->is_admin && $this->isModeratable()) {
            return true;
        }

        return $this->isPublic();
    }

    /** Public means: set to public (or following a public default), not hidden, and the owner not suspended. */
    public function scopePubliclyVisible($query)
    {
        return $query
            ->whereNull('manicures.hidden_at')
            ->whereIn('manicures.user_id', User::select('id')->whereNull('suspended_at'))
            ->where(function ($q) {
                $q->where('manicures.visibility', self::PUBLIC)
                    ->orWhere(function ($q) {
                        $q->whereNull('manicures.visibility')
                            ->whereIn('manicures.user_id', User::select('id')->where('manicure_default_visibility', self::PUBLIC));
                    });
            });
    }
}
