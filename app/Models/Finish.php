<?php

namespace App\Models;

use App\Models\Concerns\IsNamedValue;
use Illuminate\Database\Eloquent\Model;

class Finish extends Model
{
    use IsNamedValue;

    protected $fillable = ['name'];
}
