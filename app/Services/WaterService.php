<?php

namespace App\Services;

use App\Models\User;
use App\Models\WaterBill;
use App\Models\WaterRates;
use App\Models\WaterReading;
use Illuminate\Support\Facades\DB;
use PDO;

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

    public static function getPayments(?int $reference_no = null, bool $isPaid = false) {
        
        $query = WaterBill::with('reading')->where('isPaid', $isPaid);
    
        if (!is_null($reference_no)) {
            $query->where('reference_no', $reference_no);
        }
    
        return $query->get();
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

    public static function getBill(string $reference_no)
    {
        // Fetch the current bill with reading details
        $current_bill = WaterBill::with('reading')->where('reference_no', $reference_no)->first();
    
        if (!$current_bill) {
            return null;
        }
    
        // Get meter number from current bill
        $meter_no = optional($current_bill->reading)->meter_no;
    
        // Fetch the client details
        $client = User::with('property_types')->where('meter_no', $meter_no)->first();
    
        // Fetch the previous paid payment
        $previous_payment = WaterBill::with('reading.user')
            ->where('isPaid', true)
            ->whereHas('reading.user', function ($query) use ($meter_no) {
                $query->where('meter_no', $meter_no);
            })
            ->latest()
            ->first();
    
        // Prepare base query for unpaid bills
        $unpaidQuery = WaterBill::with('reading')
            ->where('isPaid', false)
            ->whereHas('reading', function ($query) use ($meter_no) {
                $query->where('meter_no', $meter_no);
            });
    
        // Fetch the latest unpaid payment (active payment)
        $active_payment = (clone $unpaidQuery)
            ->latest()
            ->select('reference_no')
            ->first();
    
        // Fetch other unpaid bills excluding the current reference number
        $unpaid_bills = (clone $unpaidQuery)
            ->where('reference_no', '!=', $reference_no)
            ->get();
    
        // Ensure active_payment is null if it matches the current reference_no
        if ($active_payment && $active_payment->reference_no == $reference_no) {
            $active_payment = null;
        }
    
        return [
            'client' => $client,
            'current_bill' => $current_bill,
            'previous_payment' => $previous_payment,
            'active_payment' => $active_payment,
            'unpaid_bills' => $unpaid_bills,
        ];
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