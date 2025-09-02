<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PropertyTypes;
use App\Models\BaseRate;
use App\Models\Rates;

class PropertyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (config('app.product') === 'novustream') {
            $propertyTypes = [
                [
                    'name' => 'Residential 1/2"', 'rate' => 200,
                    '0-10' => 0, '11-20' => 22, '21-30' => 24.25, '31-40' => 26.75, '41-50' => 29.5, '51-60' => 32.5
                ],
                [
                    'name' => 'Residential 3/4"', 'rate' => 320,
                    '0-10' => 0, '11-20' => 22, '21-30' => 24.25, '31-40' => 26.75, '41-50' => 29.5, '51-60' => 32.5
                ],
                [
                    'name' => 'Residential 1 1/2"', 'rate' => 1600,
                    '0-10' => 0, '11-20' => 22, '21-30' => 24.25, '31-40' => 26.75, '41-50' => 29.5, '51-60' => 32.5
                ],
                [
                    'name' => 'Residential 2"', 'rate' => 4000,
                    '0-10' => 0, '11-20' => 22, '21-30' => 24.25, '31-40' => 26.75, '41-50' => 29.5, '51-60' => 32.5
                ],
                [
                    'name' => 'Residential 4"', 'rate' => 14400,
                    '0-10' => 0, '11-20' => 22, '21-30' => 24.25, '31-40' => 26.75, '41-50' => 29.5, '51-60' => 32.5
                ],
                [
                    'name' => 'Government 1/2"', 'rate' => 200,
                    '0-10' => 0, '11-20' => 22, '21-30' => 24.25, '31-40' => 26.75, '41-50' => 29.5, '51-60' => 32.5
                ],
                [
                    'name' => 'Commercial/Industrial 1/2"', 'rate' => 400,
                    '0-10' => 0, '11-20' => 44, '21-30' => 48.5, '31-40' => 53.5, '41-50' => 59, '51-60' => 65
                ],
                [
                    'name' => 'Commercial/Industrial 1"', 'rate' => 1280,
                    '0-10' => 0, '11-20' => 44, '21-30' => 48.5, '31-40' => 53.5, '41-50' => 59, '51-60' => 65
                ],
                [
                    'name' => 'Commercial/Industrial 2"', 'rate' => 8000,
                    '0-10' => 0, '11-20' => 44, '21-30' => 48.5, '31-40' => 53.5, '41-50' => 59, '51-60' => 65
                ],
                [
                    'name' => 'Commercial/Industrial 3"', 'rate' => 14400,
                    '0-10' => 0, '11-20' => 44, '21-30' => 48.5, '31-40' => 53.5, '41-50' => 59, '51-60' => 65
                ],
                [
                    'name' => 'Commercial A 1/2"', 'rate' => 350,
                    '0-10' => 0, '11-20' => 38.5, '21-30' => 42.44, '31-40' => 46.81, '41-50' => 51.63, '51-60' => 56.88
                ],
                [
                    'name' => 'Commercial C 3/4"', 'rate' => 400,
                    '0-10' => 0, '11-20' => 27.5, '21-30' => 30.31, '31-40' => 33.44, '41-50' => 36.88, '51-60' => 40.63
                ],
                [
                    'name' => 'Commercial C 1"', 'rate' => 800,
                    '0-10' => 0, '11-20' => 27.5, '21-30' => 30.31, '31-40' => 33.44, '41-50' => 36.88, '51-60' => 40.63
                ],
                [
                    'name' => 'Commercial C 2"', 'rate' => 5000,
                    '0-10' => 0, '11-20' => 27.5, '21-30' => 30.31, '31-40' => 33.44, '41-50' => 36.88, '51-60' => 40.63
                ],
            ];

            foreach ($propertyTypes as $index => $type) {
    $property = PropertyTypes::updateOrCreate(['name' => $type['name']], [
        'name' => $type['name'],
        // Auto-generate unique rate_code starting at 201
        'rate_code' => $type['rate_code'] ?? (200 + $index + 1),
    ]);

    $base_rate = BaseRate::updateOrCreate(
        ['property_type_id' => $property->id],
        ['rate' => $type['rate']]
    );

    foreach ($type as $key => $value) {
        if (str_contains($key, '-')) {
            $this->compute($property, $base_rate, $key, $value);
        }
    }
}

        } else {
            $propertyTypes = [
                ['name' => 'residential', 'rate_code' => 101, 'rate' => 10.3454],
                ['name' => 'semi-commercial', 'rate_code' => 102, 'rate' => 9.3713],
                ['name' => 'commercial', 'rate_code' => 103, 'rate' => 7.7647],
            ];

            foreach ($propertyTypes as $index => $type) {
    $property = PropertyTypes::updateOrCreate(['name' => $type['name']], [
        'name' => $type['name'],
        // Auto-generate unique rate_code starting at 201
        'rate_code' => $type['rate_code'] ?? (200 + $index + 1),
    ]);

    $base_rate = BaseRate::updateOrCreate(
        ['property_type_id' => $property->id],
        ['rate' => $type['rate']]
    );

    foreach ($type as $key => $value) {
        if (str_contains($key, '-')) {
            $this->compute($property, $base_rate, $key, $value);
        }
    }
}

        }
    }

    public function compute($property, $base_rate, $range, $cum_charge)
    {
        [$from, $to] = explode('-', $range);

        if($from === '0') {
            for ($i = (int)$from; $i <= (int)$to; $i++) {

                $amount = $base_rate->rate;

                Rates::updateOrCreate([
                    'property_types_id' => $property->id,
                    'cu_m' => $i,
                ], [
                    'charge' => $cum_charge,
                    'amount' => $amount
                ]);
            }
        } else {
            for ($i = (int)$from; $i <= (int)$to; $i++) {
                $prevAmount = Rates::where('property_types_id', $property->id)
                    ->where('cu_m', $i - 1)
                    ->value('amount') ?? 0;

                $amount = $prevAmount + $cum_charge;

                Rates::updateOrCreate([
                    'property_types_id' => $property->id,
                    'cu_m' => $i,
                ], [
                    'charge' => $cum_charge,
                    'amount' => $amount
                ]);
            }
        }
    }
}
