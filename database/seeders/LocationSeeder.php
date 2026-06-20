<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        Location::firstOrCreate(['name' => 'Main Shop'], [
            'type' => 'shop',
            'is_default' => true,
        ]);

        Location::firstOrCreate(['name' => 'Warehouse'], [
            'type' => 'warehouse',
            'is_default' => false,
        ]);
    }
}
