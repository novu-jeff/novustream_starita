<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CallbackController extends Controller
{
    public function save(Request $request) {

        $payload = $request->all();

        if(!isset($payload['reference_no']) || !isset($payload['payment_id'])) {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => 'reference_no and payment_id is required'
                ]);
        }

        $record = Bill::where('reference_no', $payload['reference_no'])
            ->first();

        if(!$record) {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => 'reference_no ' . $payload['reference_no'] . ' does not exists'
                ]);
        }

        if($record->isPaid) {
            return response()
                ->json([
                    'status' => 'error',
                    'message' => 'reference_no ' . $payload['reference_no'] . ' is already paid'
                ]);
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');

        Bill::where('reference_no', $payload['reference_no'])
            ->update([
                'payment_id' => $payload['payment_id'],
                'isPaid' => true,
                'date_paid' => $now,
            ]);

        return response()->json([
            'status' => 'success',
            'message' => 'updated payment info'
        ]);

    }
}
