<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bill;
use App\Models\Reading;
use App\Models\ConcessionerAccount;

class BillSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = ConcessionerAccount::all();

        foreach ($accounts as $account) {
            // Create reading
            $reading = Reading::create([
                'zone'                => $account->zone,
                'account_no'          => $account->account_no,
                'previous_reading'    => rand(50, 99),
                'present_reading'     => rand(100, 500),
                'consumption'         => rand(10, 100),
                'reader_name'         => fake()->name(),
                'isReRead'            => (bool)rand(0, 1),
                'reread_reference_no' => strtoupper(uniqid('RR')),
                'created_at'          => now()->subDays(rand(1, 30)),
                'updated_at'          => now(),
            ]);

            // Create bill linked to concessioner account
            Bill::create([
                'reading_id'            => $reading->id,
                'reference_no'          => 'BILL-' . strtoupper(uniqid()),
                'bill_period_from'      => now()->subMonth(),
                'bill_period_to'        => now(),
                'amount'                => rand(500, 2000),
                'amount_after_due'      => rand(2000, 3000),
                'amount_paid'           => 0,
                'isPaid'                => false,
                'hasPenalty'            => false,
                'hasDisconnection'      => false,
                'hasDisconnected'       => false,
                'created_at'            => now(),
                'updated_at'            => now(),
            ]);
        }
    }
}
