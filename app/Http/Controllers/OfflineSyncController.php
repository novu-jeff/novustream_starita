<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reading;

class OfflineSyncController extends Controller
{
    // store single reading when online
    public function store(Request $request)
    {
        $data = $request->validate([
            'account_no'        => 'required|string',
            'previous_reading'  => 'nullable|numeric',
            'present_reading'   => 'nullable|numeric',
            'consumption'       => 'nullable|numeric',
            'reader_name'       => 'nullable|string',
            'zone'              => 'nullable|string',
            'reference_no'      => 'nullable|string|unique:readings,reference_no',
        ]);

        $reading = Reading::create($data);

        return response()->json(['status' => 'stored', 'reading_id' => $reading->id]);
    }

    // bulk sync (called when back online)
    public function sync(Request $request)
    {
        $readings = $request->input('readings', []);
        foreach ($readings as $r) {
            Reading::updateOrCreate(
                ['reference_no' => $r['reference_no']],
                [
                    'account_no'        => $r['account_no'],
                    'previous_reading'  => $r['previous_reading'],
                    'present_reading'   => $r['present_reading'],
                    'consumption'       => $r['consumption'],
                    'reader_name'       => $r['reader_name'] ?? 'OfflineReader',
                    'zone'              => $r['zone'] ?? null,
                ]
            );
        }

        return response()->json(['status' => 'synced']);
    }
}