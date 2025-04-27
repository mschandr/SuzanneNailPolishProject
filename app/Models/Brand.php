<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    protected $table = 'Brands';
    protected $fillable = ['name', 'total'];

    public function polishes()
    {
        return $this->hasMany(Polishes::class);
    }
}
