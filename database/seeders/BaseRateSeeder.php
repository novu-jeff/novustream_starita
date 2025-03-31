<?php

namespace Database\Seeders;

use App\Models\BaseRate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BaseRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        if(config('app.product') === 'novustream') {
            BaseRate::updateOrCreate(
                ['property_type_id' => 1],
                [
                    'rate' => 160.00,
                ]
            );
    
            BaseRate::updateOrCreate(
                ['property_type_id' => 2],
                [
                    'rate' => 280.00,
                ]
            );
    
            BaseRate::updateOrCreate(
                ['property_type_id' => 3],
                [
                    'rate' => 320.00,
                ]
            );

        } else  {
            BaseRate::updateOrCreate(
                ['property_type_id' => 1],
                [
                    'rate' => 10.3454,
                ]
            );
    
            BaseRate::updateOrCreate(
                ['property_type_id' => 2],
                [
                    'rate' => 9.3713,
                ]
            );
    
            BaseRate::updateOrCreate(
                ['property_type_id' => 3],
                [
                    'rate' => 7.7647,
                ]
            );
            
        }
     
    }
}
