<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        $zones = [
            ['zone' => '101', 'area' => 'CABAMBANGAN'],
            ['zone' => '201', 'area' => 'SAN VICENTE'],
            ['zone' => '301', 'area' => 'STA. INES'],
            ['zone' => '302', 'area' => 'SAN GUILLELRMO'],
            ['zone' => '303', 'area' => 'TINAJERO'],
            ['zone' => '401', 'area' => 'CABETICAN'],
            ['zone' => '501', 'area' => 'STA. BARBARA'],
            ['zone' => '601', 'area' => 'CABALANTIAN'],
            ['zone' => '602', 'area' => 'WESTVILLE'],
            ['zone' => '701', 'area' => 'SAN ISIDRO'],
            ['zone' => '702', 'area' => 'SOLANDA'],
            ['zone' => '801', 'area' => 'BANLIC'],
            ['zone' => '802', 'area' => 'SPLENDEROSA-LA HACIENDA'],
            ['zone' => '803', 'area' => 'LA TIERRA'],
            ['zone' => '804', 'area' => 'MACABACLE'],
            ['zone' => '805', 'area' => 'PARULOG'],
            ['zone' => '806', 'area' => 'SAN ANTONIO'],
        ];

        foreach ($zones as &$zone) {
            $zone['created_at'] = Carbon::now();
            $zone['updated_at'] = Carbon::now();
        }

        DB::table('zones')->insert($zones);
    }
}
