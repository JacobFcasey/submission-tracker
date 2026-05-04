<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MunicipalitiesSeeder extends Seeder
{
    public function run(): void
    {
        $municipalities = [
            ['name' => 'City of Tshwane', 'province' => 'Gauteng', 'code' => 'COT'],
            ['name' => 'Ekurhuleni', 'province' => 'Gauteng', 'code' => 'EKU'],
            ['name' => 'Mogale City', 'province' => 'Gauteng', 'code' => 'MOG'],
            ['name' => 'Emfuleni', 'province' => 'Gauteng', 'code' => 'EMF'],
            ['name' => 'Merafong City', 'province' => 'Gauteng', 'code' => 'MER'],
            ['name' => 'Rand West City', 'province' => 'Gauteng', 'code' => 'RWC'],
            ['name' => 'City of Johannesburg', 'province' => 'Gauteng', 'code' => 'COJ'],

            // Alias code used in the Excel "Area Code" column
            ['name' => 'City of Johannesburg', 'province' => 'Gauteng', 'code' => 'JHB'],

            ['name' => 'West Coast District Municipality', 'province' => 'Western Cape', 'code' => 'WCDM'],
            ['name' => 'George', 'province' => 'Western Cape', 'code' => 'GEO'],
        ];

        foreach ($municipalities as $municipality) {
            DB::table('municipalities')->updateOrInsert(
                ['code' => $municipality['code']],
                $municipality
            );
        }
    }
}
