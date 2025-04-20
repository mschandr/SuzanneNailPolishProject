<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('locations')->insert([
            ['name' => 'Holo Taco rainbow basket'],
            ['name' => 'Holo Taco regular basket'],
            ['name' => 'Holo Taco Cremes'],
            ['name' => 'The pink basket'],
            ['name' => 'The Mooncat basket'],
        ]);
    }
}
