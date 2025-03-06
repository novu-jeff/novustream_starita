<?php

namespace App\Services;

use App\Models\PaymentBreakdown;
use App\Models\PaymentBreakdownPenalty;
use App\Models\PaymentServiceFee;
use App\Models\User;
use App\Models\WaterBill;
use App\Models\WaterRates;
use App\Models\WaterReading;
use Illuminate\Support\Facades\DB;

class PaymentBreakdownService {

    public static function getData(int $id = null) {

        if(!is_null($id)) {
            return PaymentBreakdown::where('id', $id)
                ->first() ?? null;
        }

        return PaymentBreakdown::get();

    }

    public static function getPenalty(int $id = null) {

        if(!is_null($id)) {
            return PaymentBreakdownPenalty::where('id', $id)
                ->first() ?? null;
        }

        return PaymentBreakdownPenalty::get();

    }

    public static function getServiceFee(int $id = null) {

        if(!is_null($id)) {
            return PaymentServiceFee::with('property')->where('id', $id)
                ->first() ?? null;
        }

        return PaymentServiceFee::with('property')->get();

    }


    public static function create(array $payload) {

        DB::beginTransaction();

        try {

            if($payload['action'] == 'regular') {
                PaymentBreakdown::create([
                    'name' => $payload['name'],
                    'type' => $payload['type'],
                    'percentage_of' => $payload['percentage_of'],
                    'amount' => $payload['amount'],                
                ]);
            }

            if ($payload['action'] === 'penalty') {
                $penalty = array_map(
                    fn($from, $to, $amount) => compact('from', 'to', 'amount'),
                    $payload['penalty']['from'] ?? [],
                    $payload['penalty']['to'] ?? [],
                    $payload['penalty']['amount'] ?? []
                );
            
                // Properly delete all existing records
                PaymentBreakdownPenalty::query()->delete();
            
                foreach ($penalty as $item) {
                    PaymentBreakdownPenalty::updateOrCreate(
                        [
                            'due_from' => $item['from'],
                            'due_to' => $item['to'],
                        ],
                        [
                            'amount' => $item['amount'],
                        ]
                    );
                }
            }

            if ($payload['action'] === 'service-fee') {
                
                $service_fee = array_map(
                    fn($property_type, $amount) => compact('property_type', 'amount'),
                    $payload['service_fee']['property_type'] ?? [],
                    $payload['service_fee']['amount'] ?? []
                );
                            
                PaymentServiceFee::query()->delete();
            
                foreach ($service_fee as $item) {
                    PaymentServiceFee::updateOrCreate(
                        [
                            'property_id' => $item['property_type'],
                            'amount' => $item['amount'],
                        ],
                        [
                            'property_id' => $item['property_type'],
                        ]
                    );
                }
            }

            DB::commit();

            return [
                'status' => 'success',
                'message' => (($payload['action'] == 'service-fee'))
                    ? 'Service fee updated' 
                    : (($payload['action'] == 'regular') 
                        ? 'Payment breakdown added' 
                        : 'Payment penalty updated'),

            ];

        } catch (\Exception $e) {
            
            DB::rollBack();

            return [
                'status' => 'error',
                'message' => 'Error occured: ' . $e->getMessage()
            ];
        }

    }

    public static function update(int $id, array $payload) {

        DB::beginTransaction();

        try {
            
            $updateData = [
                'name' => $payload['name'],
                'type' => $payload['type'],
                'amount' => $payload['amount'], 
            ];

            PaymentBreakdown::where('id', $id)->update($updateData);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Payment breakdown  updated.'
            ];

        } catch (\Exception $e) {
            
            DB::rollBack();

            return [
                'status' => 'error',
                'message' => 'Error occured: ' . $e->getMessage()
            ];
        }

    }

    public static function delete(int $id) {

        DB::beginTransaction();

        try {
            
            $data = PaymentBreakdown::where('id', $id)->first();
                
            $data->delete();

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Payment breakdown deleted.'
            ];

        } catch (\Exception $e) {
            
            DB::rollBack();

            return [
                'status' => 'error',
                'message' => 'Error occured: ' . $e->getMessage()
            ];
        }

    }


}