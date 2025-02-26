<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\WaterBill;
use App\Models\WaterRates;
use App\Models\WaterReading;
use App\Services\WaterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WaterReadingController extends Controller
{
    
    public $waterService;

    public function __construct(WaterService $waterService)
    {
        $this->waterService = $waterService;
    }

    public function index(Request $request) {

        if($request->ajax()) {
            $response = $this->waterService::locate($request->all());
            return response()->json($response);
        }

        return view('water-reading.index');
    }

    public function show(string $reference_no) {

        $data = $this->waterService::getBill($reference_no);

        return view('water-reading.show', compact('data'));

    }

    public function store(Request $request) {

        $payload = $request->all();

        $validator = Validator::make($payload, [
            'meter_no' => [
                'required',
                function ($attribute, $value, $fail) {
                    if (!DB::table('users')
                        ->where('meter_no', $value)
                        ->orWhere('contract_no', $value)
                        ->exists()) {
                        $fail('The water meter no. or water contract no. does not exist.');
                    }
                },
            ],
            'present_reading' => 'required|integer|gt:previous_reading',
        ]);

        if($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        DB::beginTransaction();

        try {
            
            $user = User::where('meter_no', $payload['meter_no'])
                ->orWhere('contract_no', $payload['meter_no'])
                ->first();

            $meter_no = $user->meter_no;
            $property_type_id = $user->property_type;

            $latest_reading = WaterReading::where('meter_no', $meter_no);

            if($latest_reading->count() > 0) {
                $latest_reading = $latest_reading->latest()->first();
                $previous_reading = $latest_reading->present_reading ?? 0;
            } else {
                $previous_reading = 0;
            }

            $consumption = $payload['present_reading'] - $previous_reading;

            $rate = WaterRates::where('cubic_from', '<=', $consumption)
                ->where('cubic_to', '>=', $consumption)
                ->where('property_types_id', $property_type_id)
                ->first()->rates ?? 0;


            if($rate == 0) {
                return redirect()->back()->withInput($payload)->with('alert', [
                    'status' => 'error',
                    'message' => "We've noticed that there's no water rate for this consumption"
                ]);
            }
        
            $water_bill = $rate * $consumption;

            $water = WaterReading::create([
                'meter_no' => $meter_no,
                'previous_reading' => $previous_reading,
                'present_reading' => $payload['present_reading'],
                'consumption' => $consumption,
                'rate' => $rate,
            ]);

            $bill = WaterBill::create([
                'water_reading_id' => $water->id,
                'reference_no' => 'REF-' . time(),
                'amount' => $water_bill,
            ]);

            DB::commit();

            return redirect()->route('water-reading.show', ['reference_no' => $bill->reference_no])->with('alert', [
                'status' => 'success',
                'message' => 'Water Bill Created'
            ]);

        } catch (\Exception $e) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => 'Error occured: ' . $e->getMessage()
            ]);
        }
        

    }

}
