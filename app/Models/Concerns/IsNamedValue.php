<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * An Admin-managed list value (Finish, Color family) with a unique,
 * case-insensitive name.
 */
trait IsNamedValue
{
    use BumpsCatalogVersion, HasUuids;

    protected static function bootIsNamedValue(): void
    {
        static::saving(function ($model) {
            $model->name = trim($model->name);
            $model->name_key = Str::lower($model->name);
        });
    }
}
