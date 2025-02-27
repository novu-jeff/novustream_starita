<?php

namespace App\Services;

use App\Models\User;
use App\Models\WaterBill;
use App\Models\WaterRates;
use App\Models\WaterReading;
use Illuminate\Support\Facades\DB;

class WaterService {

    public static function getReport(string $date = null) {

        if(!is_null($date)) {
            return WaterReading::whereDate('created_at', $date)->get();
        }

        return WaterReading::with('bill')->get();

    }

    public static function getData(int $id = null) {

        if(!is_null($id)) {
            return WaterRates::with('property_types')->where('id', $id)
                ->first() ?? null;
        }

        return WaterRates::with('property_types')->get();

    }

    public static function locate(array $payload)
    {
        $client = User::where('meter_no', $payload['meter_no'] ?? '')
            ->orWhere('contract_no', $payload['meter_no'] ?? '')
            ->first();
    
        if (!$client) {
            return [
                'status' => 'error',
                'message' => 'No client found'
            ];
        }
    
        $previous_reading = WaterReading::where('meter_no', $client->meter_no)
            ->latest()
            ->first() ?? [];
    
        return [
            'status' => 'success',
            'client' => $client,
            'reading' => $previous_reading
        ];
    }

    public static function getBill(string $reference_no) {

        $current_bill = WaterBill::with('reading')->where('reference_no', $reference_no)
            ->first() ?? [];

        if(!$current_bill) {
            return null;
        }

        $client = User::with('property_types')->where('meter_no', $current_bill->reading->meter_no)->first();

        $previous_payment = WaterBill::with('reading.user')->where('isPaid', true)
            ->whereHas('reading.user', function($query) use ($current_bill) {
                return $query->meter_no = $current_bill->reading->user->meter_no;
            })->latest()->first();

        $data = [
            'client' => $client,
            'current_bill' => $current_bill,
            'previous_payment' => $previous_payment
        ];
        
        return $data;
    }


    public static function getBills(string $meter_no, bool $isAll = false) {

        if($isAll) {
            return WaterBill::with('reading')
                ->whereHas('reading', function($query) use ($meter_no) {
                    return $query->where('meter_no', $meter_no);
                })->orderByDesc('created_at')
                ->get();
        }

        return WaterBill::with('reading')
            ->whereHas('reading', function($query) use ($meter_no) {
                return $query->where('meter_no', $meter_no);
            })->latest()
            ->first();

    }
    

    public static function create(array $payload) {

        DB::beginTransaction();
        try {

            WaterRates::create([
                'property_types_id' => $payload['property_type'],
                'cubic_from' => $payload['cubic_from'],
                'cubic_to' => $payload['cubic_to'],
                'rates' => $payload['rate']
            ]);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Water rate added.'
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
                'property_types_id' => $payload['property_type'],
                'cubic_from' => $payload['cubic_from'],
                'cubic_to' => $payload['cubic_to'],
                'rates' => $payload['rate']
            ];

            WaterRates::where('id', $id)->update($updateData);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Water rate  updated.'
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
            
            $data = WaterRates::where('id', $id)->first();
                
            $data->delete();

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Water rate deleted.'
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