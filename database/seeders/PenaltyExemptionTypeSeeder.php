<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PenaltyExemptionTypeSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('penalty_exemption_type')->truncate();

        DB::table('penalty_exemption_type')->insert([
            [
                'id' => 1,
                'penalty_exemption_name' => 'Temporary',
            ],
            [
                'id' => 2,
                'penalty_exemption_name' => 'Permanent',
            ],
        ]);
    }
}
