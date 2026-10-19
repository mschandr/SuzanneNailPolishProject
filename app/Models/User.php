<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['display_name', 'email', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $attributes = ['is_admin' => false];

    // signup_method is set by the app, never by mass assignment.

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    public function bottles(): HasMany
    {
        return $this->hasMany(Bottle::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function privatePolishes(): HasMany
    {
        return $this->hasMany(Polish::class);
    }

    /** The id of this User's Location of that name, creating it if needed (names ignore case). */
    public function locationIdNamed(string $name): string
    {
        $name = trim($name);
        $find = fn () => $this->locations()->where('name_key', Str::lower($name))->value('id');

        if ($id = $find()) {
            return $id;
        }

        try {
            return $this->locations()->create(['name' => $name])->id;
        } catch (UniqueConstraintViolationException) {
            // A concurrent identical request created it first.
            return $find();
        }
    }
}
