<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Polishes extends Model
{
    protected $table = 'polishes';
    protected $fillable = ['name', 'brand_id', 'location_id', 'shade', 'collection', 'release_date'];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function location()
    {
        return $this->belongsTo(Locations::class);
    }


}
