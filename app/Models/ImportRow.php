<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    // import_id is set by the relationship; 'line' etc. are written by the importer only.

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
