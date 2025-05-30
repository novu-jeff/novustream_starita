<?php

namespace Database\Seeders;

use App\Models\PaymentBreakdown;
use App\Models\PaymentBreakdownPenalty;
use App\Models\PaymentServiceFee;
use App\Models\PaymentDiscount;
use App\Models\Ruling;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentBreakdownSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PaymentBreakdown::create([
            'name' => 'Sample',
            'type' => 'fixed',
            'amount' => 100, 
        ]);

        PaymentBreakdownPenalty::create([
            'due_from' => '1',
            'due_to' => '*',
            'amount_type' => 'percentage',
            'amount' => 0.15
        ]);

        PaymentServiceFee::create([
            'property_id' => 1,
            'amount' => 50,
        ]);

        PaymentDiscount::create([
            'name' => 'SC Discount',
            'eligible' => 'senior',
            'type' => 'percentage',
            'percentage_of' => 'basic_charge',
            'amount' => 0.05
        ]);

        Ruling::create([
            'due_date' => 30,
            'disconnection_date' => 27,
            'disconnection_rule' => 2,
        ]);
    }
}
