<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('locations')->insert([
            ['name' => 'Kitchen counters', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Under the bed: Holo taco special', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Under the bed: Holo taco non-cremes', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Under the bed: Holo Taco cremes', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Under the bed: pinks', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Under the bed: Odd ones', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Under the bed: Mooncat', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Under the bed: Specialty polishes', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'China cabinet: Big white container', 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'China cabinet: smaller container', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
