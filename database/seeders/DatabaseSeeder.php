<?php

namespace Database\Seeders;

use App\Models\ColorFamily;
use App\Models\Finish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $finishes = ['Creme', 'Shimmer', 'Glitter', 'Holographic', 'Matte', 'Jelly', 'Metallic', 'Duochrome', 'Magnetic', 'Thermal', 'Sheer', 'Flakie'];
        $colors = ['Red', 'Orange', 'Yellow', 'Green', 'Blue', 'Purple', 'Pink', 'Nude', 'Brown', 'Black', 'White', 'Gray', 'Clear', 'Multicolor'];

        foreach ($finishes as $name) {
            Finish::firstOrCreate(['name_key' => Str::lower($name)], ['name' => $name]);
        }
        foreach ($colors as $name) {
            ColorFamily::firstOrCreate(['name_key' => Str::lower($name)], ['name' => $name]);
        }
    }
}
