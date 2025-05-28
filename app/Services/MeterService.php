<?php

namespace App\Services;

use App\Models\BaseRate;
use App\Models\User;
use App\Models\Bill;
use App\Models\Rates;
use App\Models\Reading;
use App\Models\UserAccounts;
use App\Models\Ruling;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PDO;

class MeterService {

    public $paymentBreakdownService;
    public $paymentServiceFee;

    public function __construct(PaymentBreakdownService $paymentBreakdownService) {
        $this->paymentBreakdownService = $paymentBreakdownService;
    }

    public function getAccount($meter_no)
    {
        return  UserAccounts::with('user')->where('account_no', $meter_no ?? '')
        ->orWhere('meter_serial_no', $meter_no ?? '')
        ->first();
    }

    public static function getReport(?string $date = null) {

        if(!is_null($date)) {
            return Reading::whereDate('created_at', $date)->get();
        }

        return Reading::with('bill')->get();

    }

    public static function getData(?int $id = null) {

        if(!is_null($id)) {
            return Rates::with('property_types')->where('id', $id)
                ->first() ?? null;
        }

        return Rates::with('property_types')->get();

    }

    public static function getPayments(?int $reference_no = null, bool $isPaid = false) {
        
        $query = Bill::with('reading')
            ->where('isPaid', $isPaid);
    
        if (!is_null($reference_no)) {
            $query->where('reference_no', $reference_no);
        }
    
        return $query->get();
    }

    public function locate(array $payload)
    {    
        $account = $this->getAccount($payload['meter_no']);

        if (!$account) {
            return [
                'status' => 'error',
                'message' => 'No client found'
            ];
        }
    
        $previous_reading = Reading::where('account_no', $account->account_no)
            ->latest()
            ->first() ?? [];
    
        return [
            'status' => 'success',
            'account' => $account,
            'reading' => $previous_reading
        ];
    }

    public static function getBill(string $reference_no) {

        // Fetch the current bill with reading details
        $current_bill = Bill::with('reading', 'breakdown')->where('reference_no', $reference_no)->first();    

        if (!$current_bill) {
            return [
                'status' => 'error',
                'message' => 'No bill found'
            ];
        }

        // Get meter number from current bill
        $account_no = optional($current_bill->reading)->account_no;
    
        $client = User::with(['accounts.property_types'])
                ->whereHas('accounts', function ($query) use ($account_no) {
                    $query->where('account_no', $account_no);
                })
                ->first();

        $previous_payment = DB::table('bill')
            ->leftJoin('readings', 'bill.reading_id', 'readings.id')
            ->where('readings.account_no', $account_no)
            ->where('bill.isPaid', true)
            ->select('bill.*')
            ->orderBy('bill.created_at', 'desc')
            ->first();
        
        // Prepare base query for unpaid bills
        $unpaidQuery = Bill::with('reading')
            ->where('isPaid', false)
            ->whereHas('reading', function ($query) use ($account_no) {
                $query->where('account_no', $account_no);
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

        if (is_null($client)) {
            return [
                'status' => 'error',
                'message' => 'No Concessionaire found for this transaction'
            ];
        }

        $filteredAccounts = collect($client->accounts)
            ->where('account_no', $account_no)
            ->values();

        $filteredAccountArray = optional($filteredAccounts->first())->toArray() ?? [];
        $client = array_merge($filteredAccountArray, $client->toArray());

        unset($client['accounts']);
        
        return [
            'client' => $client,
            'current_bill' => $current_bill,
            'previous_payment' => $previous_payment,
            'active_payment' => $active_payment,
            'unpaid_bills' => $unpaid_bills,
        ];
    }    

    public static function getBills(?string $number = null, bool $isAll = false) {

        $query = Bill::with(['reading', 'breakdown'])->orderBy('created_at', 'desc');

        if (!is_null($number)) {
            $account = UserAccounts::where('meter_serial_no', $number)
                ->orWhere('account_no', $number)
                ->first();

            if ($account) {
                $query->whereHas('reading', function ($q) use ($account) {
                    $q->where('account_no', $account->account_no);
                });
            }
        }

        return $isAll ? $query->get()->toArray() : optional($query->first())->toArray();
    }
    
    public static function create(array $payload) {

        DB::beginTransaction();
        try {

            Rates::create([
                'property_types_id' => $payload['property_type'],
                'cubic_from' => $payload['cubic_from'],
                'cubic_to' => $payload['cubic_to'],
                'rates' => $payload['rate']
            ]);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Rate added.'
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

            Rates::where('id', $id)->update($updateData);

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Rate  updated.'
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
            
            $data = Rates::where('id', $id)->first();
                
            $data->delete();

            DB::commit();

            return [
                'status' => 'success',
                'message' => 'Rate deleted.'
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

        $ruling = Ruling::first();
        $concessionaire = UserAccounts::with('user')->where('account_no', $payload['account_no'])->first();

        if(is_null($ruling)) {
            return [
                'status' => 'error',
                'message' => "We've noticed that there's no ruling set. Please add first."
            ];
        }

        if(is_null($concessionaire)) {
             return [
                'status' => 'error',
                'message' => "We've noticed that there's no concessionaire with this account no."
            ];
        }


        $latest_reading = Reading::with('concessionaire.user', 'bill')
            ->where('account_no', $payload['account_no'])
            ->latest()
            ->first();

        $previous_reading = optional($latest_reading)->present_reading ?? 0;

        $consumption = (float) $payload['present_reading'] - (float) $previous_reading;
    
        $base_rate = null;

        if(config('app.product') === 'novustream') {
            # novustream
            $rate = Rates::where('cu_m', $consumption)
                ->where('property_types_id', $payload['property_type_id'])
                ->value('amount') ?? 0;
        } else {
            # novusurge
           $base_rate = BaseRate::where('property_type_id', $payload['property_type_id'])
                ->value('rate') ?? 0;
            $rate = $base_rate  *  $consumption;
        }
        
        if ($rate == 0 || $base_rate && $base_rate == 0) {
            return [
                'status' => 'error',
                'message' => "We've noticed that there's no rate for this consumption"
            ];
        }

        $unpaidAmount = Bill::with('reading')
            ->where('isPaid', false)
            ->whereNotNull('amount')
            ->whereHas('reading', function ($query) use ($payload) {
                $query->where('account_no', $payload['account_no']);
            })->whereNotNull('amount')
            ->sum('amount') ?? 0;

        $total_amount = $unpaidAmount + $rate;
    
        $other_deductions = $this->paymentBreakdownService::getData();
        $discounts = $this->paymentBreakdownService::getDiscounts();
        $service_fees = $this->paymentBreakdownService::getServiceFee();

        $deductions = [
            [
                'name' => 'Previous Balance',
                'amount' => $unpaidAmount,
                'description' => ''
            ],
            [
                'name' => 'Basic Charge',
                'amount' => $rate,
                'description' => '',
            ],
        ];
    
        // deductions
        foreach ($other_deductions as $deduction) {
            if ($deduction->type == 'percentage') {
                $base_amount = ($deduction->percentage_of == 'basic_charge') ? $rate : $total_amount;
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

        // service fee
        foreach ($service_fees as $fee) {
            if ($fee->property_id == $payload['property_type_id']) {
                $deductions[] = [
                    'name' => 'System Fee',
                    'amount' => $fee->amount,
                    'description' => '',
                ];
            }
        }    

        $isSeniorCitizen = $concessionaire->user->senior_citizen_no ?? null;
        $isPWD = $concessionaire->user->pwd_no ?? null;
        $total = collect($deductions)->sum('amount');

        // discounts
        $appliedDiscounts = [];

        foreach ($discounts as $discount) {
            
            $discountAmount = 0;

            $isEligible = (
                ($discount->eligible === 'senior' && $isSeniorCitizen) ||
                ($discount->eligible === 'pwd' && $isPWD)
            );

            if (!$isEligible) {
                continue;
            }

            if (strtolower($discount->type) === 'percentage') {
                $discountAmount = $total * ($discount->amount / 100);
            } else {
                $discountAmount = $discount->amount;
            }

            $overall_total = $total - $discountAmount;

            $appliedDiscounts[] = [
                'name' => $discount->name,
                'amount' => $discountAmount,
                'description' => '', 
            ];
        }


        $reading = [
            'account_no' => $payload['account_no'],
            'previous_reading' => $previous_reading,
            'present_reading' => $payload['present_reading'],
            'consumption' => $consumption,
            'rate' => $rate,
            'reader_name' => Auth::user()->name
        ];

        $days_due = $ruling->due_date;

        $bill_period_from = Carbon::now()->subMonth()->format('Y-m-d H:i:s');
        $bill_period_to = Carbon::now()->format('Y-m-d H:i:s');
        $due_date = Carbon::now()->addDays($days_due)->format('Y-m-d H:i:s');

        $bill = [
            'reference_no' => $this->generateReferenceNo(),
            'bill_period_from' => $bill_period_from,
            'bill_period_to' => $bill_period_to,
            'previous_unpaid' => $unpaidAmount,
            'total' => $total,
            'discount' => $discountAmount,
            'amount' => $overall_total,
            'due_date' => $due_date,
        ];

        $prev_consumption = $this->previousConsumption($payload['account_no']) ?? [];

        return [
            'status' => 'success',
            'previous_consumptions' => $prev_consumption,
            'reading' => $reading,
            'deductions' => $deductions,
            'discounts' => $appliedDiscounts,
            'bill' => $bill,
        ];

    }

    private function previousConsumption(string $account_no) {
        $readings = Reading::select(
                DB::raw('MONTH(created_at) as month_number'),
                DB::raw('MONTHNAME(created_at) as month_name'),
                DB::raw('SUM(consumption) as total_consumption')
            )
            ->where('account_no', $account_no) 
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month_number', 'month_name')
            ->orderBy('month_number')
            ->get();

        $prev_consumption = $readings->map(function ($reading) {
            return [
                'month' => $reading->month_name,
                'value' => (int) $reading->total_consumption
            ];
        })->values()->toArray();
    }

    private function generateReferenceNo() {

        $prefix = env('REF_PREFIX');

        do {
            $time = time();
            $combined = $prefix . '-' . $time;
            $exists = Bill::where('reference_no', $combined)
                ->exists();

            if ($exists) {
                sleep(1);
            }
            
        } while ($exists);

        return $combined;
    }


}