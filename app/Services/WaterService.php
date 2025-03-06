<?php

namespace App\Services;

use App\Models\PaymentServiceFee;
use App\Models\User;
use App\Models\WaterBill;
use App\Models\WaterRates;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PDO;

class WaterService {


    public $paymentBreakdownService;
    public $paymentServiceFee;

    public function __construct(PaymentBreakdownService $paymentBreakdownService) {
        $this->paymentBreakdownService = $paymentBreakdownService;
    }

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

    public static function getBill(string $reference_no) {

        // Fetch the current bill with reading details
        $current_bill = WaterBill::with('reading', 'breakdown')->where('reference_no', $reference_no)->first();
    
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

    public function create_breakdown(array $payload) {

        $latest_reading = WaterReading::with('bill')->where('meter_no', $payload['meter_no'])->latest()->first();
        $previous_reading = optional($latest_reading)->present_reading ?? 0;

        $consumption = (float) $payload['present_reading'] - (float) $previous_reading;
    
        $rate = WaterRates::where('cubic_from', '<=', $consumption)
            ->where('cubic_to', '>=', $consumption)
            ->where('property_types_id', $payload['property_type_id'])
            ->value('rates') ?? 0;
    
        if ($rate == 0) {
            return [
                'status' => 'error',
                'message' => "We've noticed that there's no water rate for this consumption"
            ];
        }
    
        $unpaidAmount = WaterBill::where('isPaid', false)->whereNotNull('amount')->sum('amount') ?? 0;
        $basic_charge = $rate * $consumption;
        $total_amount = $unpaidAmount + $basic_charge;
    
        $other_deductions = $this->paymentBreakdownService::getData();
        $penalty_deductions = $this->paymentBreakdownService::getPenalty();
        $service_fees = $this->paymentBreakdownService::getServiceFee();


        $deductions = [
            [
                'name' => 'Previous Balance',
                'amount' => $unpaidAmount,
                'description' => ''
            ],
            [
                'name' => 'Basic Charge',
                'amount' => $basic_charge,
                'description' => '',
            ],
        ];
    
        // Process Other Deductions
        foreach ($other_deductions as $deduction) {
            if ($deduction->type == 'percentage') {
                $base_amount = ($deduction->percentage_of == 'basic_charge') ? $basic_charge : $total_amount;
                $amount = $base_amount * ($deduction->amount / 100); 
    
                $deductions[] = [
                    'name' => $deduction->name,
                    'description' => $deduction->amount . '%',
                    'amount' => $amount
                ];
            } else {
                $deductions[] = [
                    'name' => $deduction->name,
                    'description' => '',
                    'amount' => $deduction->amount
                ];
            }
        }


        // Process Penalty

        if(!is_null($latest_reading)) {
            
            $current_timestamp = Carbon::now();
            $due_timestamp = Carbon::parse($latest_reading->bill->due_date) ?? null;

            if($current_timestamp->gt($due_timestamp)) {

                $due_count = $current_timestamp->diff($due_timestamp)->days;
    
                $amount = 0;
    
                foreach ($penalty_deductions as $penalty) {
                    if ($due_count >= $penalty->due_from && $due_count <= $penalty->due_to) {
                        $amount = $penalty->amount;
                        break; 
                    }
                }
    
                $deductions[] = [
                    'name' => 'Penalty',
                    'description' => '',
                    'amount' => $amount
                ];
    
            }
        }
        
    
        // Process Service Fees
        foreach ($service_fees as $fee) {
            if ($fee->property_id == $payload['property_type_id']) {
                $deductions[] = [
                    'name' => 'System Fee',
                    'amount' => $fee->amount,
                    'description' => '',
                ];
            }
        }    

        $reading = [
            'meter_no' => $payload['meter_no'],
            'previous_reading' => $previous_reading,
            'present_reading' => $payload['present_reading'],
            'consumption' => $consumption,
            'rate' => $rate,
        ];

        $bill_period_from = Carbon::now()->subMonth()->format('Y-m-d H:i:s');
        $bill_period_to = Carbon::now()->format('Y-m-d H:i:s');
        $due_date = Carbon::now()->addDays(14)->format('Y-m-d H:i:s');
        $overall_total = collect($deductions)->sum('amount');

        $bill = [
            'reference_no' => 'REF-' . time(),
            'bill_period_from' => $bill_period_from,
            'bill_period_to' => $bill_period_to,
            'previous_unpaid' => $unpaidAmount,
            'amount' => $overall_total,
            'due_date' => $due_date,
        ];

        return [
            'status' => 'success',
            'reading' => $reading,
            'deductions' => $deductions,
            'bill' => $bill
        ];

    }
    

}