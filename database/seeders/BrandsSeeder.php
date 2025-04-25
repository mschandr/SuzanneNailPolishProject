<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BrandsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        DB::table('brands')->insert([
            ['name' => 'Holo Taco', 'total' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Mooncat', 'total' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'ILNP', 'total' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Painted Polish', 'total' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Rogue Lacquer', 'total' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Orly', 'total' => 0, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
