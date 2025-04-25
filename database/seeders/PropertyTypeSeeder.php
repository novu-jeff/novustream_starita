<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PropertyType;
use App\Models\PropertyTypes;

class PropertyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if(config('app.product') === 'novustream') {
            $propertyTypes = [
                ['name' => 'residential'],
                ['name' => 'government'],
                ['name' => 'commercial and industrial'],
                ['name' => 'semi-commercial'],
                ['name' => 'commercial'],
            ];
        } else {
            $propertyTypes = [
                ['name' => 'residential'],
                ['name' => 'semi-commercial'],
                ['name' => 'commercial'],
            ];
        }
        

        foreach ($propertyTypes as $type) {
            PropertyTypes::updateOrCreate(['name' => $type['name']], $type);
        }
    }
}
