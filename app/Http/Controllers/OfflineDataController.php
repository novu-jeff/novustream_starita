<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserAccounts;
use App\Models\Zones;
use App\Models\Rates;
use App\Models\PropertyTypes;
use App\Models\PaymentDiscount;
use App\Models\PaymentBreakdownPenalty;
use App\Models\Reading;
use App\Models\Bill;

class OfflineDataController extends Controller
{
    public function download(Request $request)
    {
        
        // STATIC TOKEN AUTHENTICATION
        // $token = $request->header('X-API-KEY');

        // if ($token !== config('app.offline_api_key')) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        $user = \App\Models\User::first();


        // if (!$user) {
        //     return response()->json(['error' => 'Unauthenticated'], 401);
        // }

        // if (!in_array($user->user_type, ['technician', 'admin'])) {
        //     return response()->json(['error' => 'Unauthorized'], 403);
        // }

        // ✅ Determine technician’s assigned zones
        $zoneIds = $user->zone_assigned ? explode(',', $user->zone_assigned) : [];
        $zones = Zones::whereIn('id', $zoneIds)->pluck('zone');

        // ✅ Fetch accounts with latest reading + related user
        $accounts = UserAccounts::with([
                'user',
                'readings' => function ($q) {
                    $q->latest()->limit(1);
                }
            ])
            ->when($zones->isNotEmpty(), function ($query) use ($zones) {
                $query->where(function ($q) use ($zones) {
                    foreach ($zones as $zone) {
                        $q->orWhere('account_no', 'like', "{$zone}%");
                    }
                });
            })
            ->get();
        
        // dd($accounts);

        // ✅ Compute unpaid + previous_reading BEFORE mapping to array
        $previousReadings = [];

        foreach ($accounts as $acc) {
            $latest = Reading::where('account_no', $acc->account_no)
                ->latest('created_at')
                ->first();

            $previousReadings[$acc->account_no] = [
                'present_reading' => $latest->present_reading ?? 0,
                'created_at'      => $latest->created_at ?? null,
            ];
        }


        // ✅ Now transform to clean arrays for frontend
        $accounts = $accounts->map(function ($acc) use ($previousReadings) {
            // dd([
            //     'prop_type_raw' => $acc->property_type,
            //     'from_id_relation' => $acc->property_types?->toArray(),
            //     'from_name_relation' => $acc->property_types_by_name?->toArray(),
            // ]);
        // dd($acc);


            // \Log::info('[DEBUG OFFLINE] Account structure:', $acc->toArray());
            $unpaid = Bill::whereHas('reading', function ($q) use ($acc) {
                    $q->where('account_no', $acc->account_no);
                })
                ->where('isPaid', false)
                ->sum('amount');

            
            
            return [
                'account_no'       => $acc->account_no,
                'name'             => $acc->user->name ?? 'N/A',
                'address'          => $acc->address,
                'meter_serial_no'  => $acc->meter_serial_no,
                'zone'             => $acc->zone,
                'property_type_id' => $acc->property_types_by_name->id ?? null,
                'discount_type'    => $acc->discount->discount_type_id ?? 0,
                'previous_reading' => $previousReadings[$acc->account_no]['present_reading'] ?? 0,
                'unpaid_amount'    => $unpaid,
                'created_at'      => $previousReadings[$acc->account_no]['created_at'] ?? null,
                'sequence_no'     => $acc->sequence_no ?? null,
            ];

        })->sortBy(function ($account) {
            // Sort by sequence_no, treating null as last
            return $account['sequence_no'] ?? PHP_INT_MAX;
        })->values();

        // ✅ Rates, Property Types, Discounts, Penalties
        $rates         = Rates::select('property_types_id', 'cu_m', 'amount')->get();
        $propertyTypes = PropertyTypes::select('id', 'name')->get();
        $discounts     = PaymentDiscount::select('eligible', 'type', 'amount', 'percentage_of')->get();
        $penalties     = PaymentBreakdownPenalty::select('due_from', 'due_to', 'amount_type', 'amount')->get();

        // ✅ Final data payload
        $data = [
            'accounts'       => $accounts,
            'rates'          => $rates,
            'property_types' => $propertyTypes,
            'discounts'      => $discounts,
            'penalties'      => $penalties,
        ];

        return response()->json($data);
    }
}