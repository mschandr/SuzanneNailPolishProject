<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Worn extends Model
{
    protected $table = 'worn';
    protected $fillable = ['worn_on', 'notes'];
}
