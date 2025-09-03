<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ConcessionerAccount;
use App\Models\PropertyTypes;

class ConcessionerAccountSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing accounts
        ConcessionerAccount::truncate();

        $users = User::all();
        $propertyTypes = PropertyTypes::pluck('name')->toArray(); // Fetch all property type names

        // Zones mapping
        $zones = [
            101 => 'CABAMBANGAN',
            201 => 'SAN VICENTE',
            301 => 'STA. INES',
            302 => 'SAN GUILLELRMO',
            303 => 'TINAJERO',
            401 => 'CABETICAN',
            501 => 'STA. BARBARA',
            601 => 'CABALANTIAN',
            602 => 'WESTVILLE',
            701 => 'SAN ISIDRO',
            702 => 'SOLANDA',
            801 => 'BANLIC',
            802 => 'SPLENDEROSA-LA HACIENDA',
            803 => 'LA TIERRA',
            804 => 'MACABACLE',
            805 => 'PARULOG',
            806 => 'SAN ANTONIO',
        ];

        // Track sequence number per zone
        $zoneCounters = [];
        foreach ($zones as $zoneNumber => $area) {
            $lastAccount = ConcessionerAccount::where('zone', $zoneNumber)
                                ->orderByDesc('id')
                                ->first();

            // Start from 1 if no previous account
            $zoneCounters[$zoneNumber] = $lastAccount ? intval(substr($lastAccount->account_no, -5)) + 1 : 1;
        }

        $propertyTypeCount = count($propertyTypes);
        $userIndex = 0;

        foreach ($users as $user) {
            // Cycle through zones
            $zoneNumber = array_keys($zones)[$user->id % count($zones)];
            $zoneArea = $zones[$zoneNumber];

            // Format account number
            $seq = str_pad($zoneCounters[$zoneNumber], 5, '0', STR_PAD_LEFT);
            $accountNo = "{$zoneNumber}-12-{$seq}";

            // Pick property type (even distribution)
            $propertyType = $propertyTypes[$userIndex % $propertyTypeCount];
            $userIndex++;

            // Increment zone counter
            $zoneCounters[$zoneNumber]++;

            ConcessionerAccount::create([
                'user_id' => $user->id,
                'zone' => $zoneNumber,
                'account_no' => $accountNo,
                'address' => $zoneArea,
                'property_type' => $propertyType,
                'rate_code' => rand(1, 5),
                'status' => 'Active',
                'meter_brand' => 'Brand ' . rand(1, 3),
                'meter_serial_no' => strtoupper(fake()->bothify('SN####')),
                'sc_no' => 'SC-' . rand(1000, 9999),
                'date_connected' => now()->subDays(rand(10, 300)),
                'sequence_no' => rand(1, 100),
                'meter_type' => 'Type-' . rand(1, 3),
                'meter_wire' => 'Wire-' . rand(1, 3),
                'meter_form' => 'Form-' . rand(1, 3),
                'meter_class' => 'Class-' . rand(1, 3),
                'lat_long' => fake()->latitude() . ', ' . fake()->longitude(),
                'isErcSealed' => (bool)rand(0, 1),
                'inspection_image' => 'default.jpg',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
