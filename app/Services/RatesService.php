<?php

namespace App\Services;

use App\Models\BaseRate;
use App\Models\Roles;
use App\Models\User;
use App\Models\Rates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RatesService {

    public function getBaseRates($property_type_id) {
        return BaseRate::where('property_type_id', $property_type_id)->orderBy('created_at', 'desc')->get();
    }

    public function getActiveBaseRate($property_type_id) {
        $baseRateQuery = BaseRate::where('property_type_id', $property_type_id)->latest()->first();
        $baseRate = $baseRateQuery->rate;
        return $baseRate;
    }

    public function getData($property_type = 1, ?int $id = null) {

        if(!is_null($id)) {
            return Rates::with('property_type')->where('id', $id)
                ->first() ?? null;
        }

        return Rates::where('property_types_id', $property_type)->with('property_type')->get();
    }
    public function create(array $payload) {
      
        $rates =  $this->getData($payload['property_type']);
        $highestCuM = $rates->isEmpty() ? 0 : $rates->max('cu_m');

        $cu_m = (int) $payload['cubic_meter'];

        for($highest = $highestCuM + 1; $highest <= $cu_m; $highest++)
        {
            if($highest <= 10) {
                $charge = 0;
            } else {
                $charge = $payload['charge'];
            }

            Rates::create([
                'property_types_id' => $payload['property_type'],
                'cu_m' => $highest,
                'charge' => $charge,
                'amount' => 0
            ]);
        }

        $data = $this->recomputeRates($payload['property_type']);

        return [
            'data' => $data,
            'status' => 'success',
            'message' => 'Water rate added.'
        ];
    }

    public function createBaseRate(array $payload) {
        BaseRate::create([
            'property_type_id' => $payload['property_type'],
            'rate' => $payload['rate']
        ]);
    }

    public function updateAllRates($property_type) {

        $rates = Rates::where('isActive', true)->get();
        foreach ($rates as $rate) {
                    
        }
    }

    public static function update(?int $id, array $payload) {

        DB::beginTransaction();

        try {
            
            $updateData = [
                'property_types_id' => $payload['property_type'],
                'cubic_from' => $payload['cubic_from'],
                'cubic_to' => $payload['cubic_to'],
                'rates' => $payload['rate']
            ];

            Rates::where('id', $id)->update($updateData);

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

    public static function delete(?int $id) {

        DB::beginTransaction();

        try {
            
            $data = Rates::where('id', $id)->first();
                
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

    public function updateCharge($payload)
    {
        $from = $payload['cubic_from'];
        $to = $payload['cubic_to'];

        $rates = Rates::where('property_types_id', $payload['property_type'])
            ->whereBetween('cu_m', [$from, $to])
            ->orWhereBetween('cu_m', [$from, $to])
            ->get();
        
        foreach ($rates as $rate) {
            $rate->charge = $payload['charge'];
            $rate->save();
        }
        
        return $rates;
    }

    public function recomputeRates($property_type) {

        $rates = Rates::where('property_types_id', $property_type)->get();
        $baseRate = $this->getActiveBaseRate($property_type);
       
        $totalAmount = $baseRate;
    
        foreach ($rates as $rate) {
            $charge = $rate->charge;
            $totalAmount += $charge;
    
            $rate->amount = $totalAmount;
            $rate->save();
        }

        DB::beginTransaction();
        try {

            DB::commit();

            return [
                'data' => $rates,
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
}