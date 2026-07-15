<?php

namespace Database\Seeders;

use App\Models\TripCity;
use Illuminate\Database\Seeder;

class TripCitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'Barcelona', 'country' => 'Spain',    'days' => 3],
            ['name' => 'Seville',   'country' => 'Spain',    'days' => 1],
            ['name' => 'Málaga',    'country' => 'Spain',    'days' => 2],
            ['name' => 'Lagos',     'country' => 'Portugal', 'days' => 2],
            ['name' => 'Lisbon',    'country' => 'Portugal', 'days' => 2],
            ['name' => 'Porto',     'country' => 'Portugal', 'days' => 1],
        ];

        foreach ($cities as $city) {
            TripCity::firstOrCreate(['name' => $city['name']], $city);
        }
    }
}
