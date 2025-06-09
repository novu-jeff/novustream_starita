<?php

namespace App\Services;

use App\Models\BaseRate;
use App\Models\User;
use App\Models\Bill;
use App\Models\BillBreakdown;
use App\Models\BillDiscount;
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

    public function getZones() {
        $zones = UserAccounts::select('account_no')->distinct()->pluck('account_no');

        $grouped = $zones->map(function ($accountNo) {
            $zone = explode('-', $accountNo)[0] ?? 'unknown';
            return [
                'zone' => $zone,
                'account_no' => $accountNo,
            ];
        })->groupBy('zone');

        return array_keys($grouped->toArray()) ?? [];
    }

    public function filterAccount(array $filter) {
        // Start query with eager loading 'user' relationship
        $query = UserAccounts::with('user');

        // Apply zone filter if provided and not 'all'
        if (!empty($filter['zone']) && strtolower($filter['zone']) !== 'all') {
            $query->where('account_no', 'like', $filter['zone'] . '%');
        }

        // Apply search filters if 'search' and 'search_by' are provided
        if (!empty($filter['search']) && !empty($filter['search_by'])) {
            switch ($filter['search_by']) {
                case 'all':
                    $query->where(function ($q) use ($filter) {
                        $q->where('account_no', 'like', '%' . $filter['search'] . '%')
                        ->orWhere('meter_serial_no', 'like', '%' . $filter['search'] . '%')
                        ->orWhereHas('user', function ($uq) use ($filter) {
                            $uq->where('name', 'like', '%' . $filter['search'] . '%');
                        });
                    });
                    break;

                case 'account_no':
                    $query->where('account_no', $filter['search']);
                    break;

                case 'meter_serial_no':
                    $query->where('meter_serial_no', $filter['search']);
                    break;

                case 'name':
                    $query->whereHas('user', function ($q) use ($filter) {
                        $q->where('name', $filter['search']);
                    });
                break;
            }
        }

        // Determine limit from 'filter' param (defaults to 50 if invalid)
        $limit = (isset($filter['filter']) && is_numeric($filter['filter'])) 
            ? (int) $filter['filter'] 
            : 50;

        // Get results limited by $limit
        return $query->limit($limit)->get()->toArray();
    }

    public function getPreviousReading($account_no) {
        $previous_reading = Reading::with('bill')
            ->where('account_no', $account_no)
            ->latest()
            ->first();

        if ($previous_reading) {
        
            $suggestNextMonth = optional($previous_reading->bill)->bill_period_to;

            if ($suggestNextMonth) {
                $suggestNextMonth = Carbon::parse($suggestNextMonth)
                    ->addMonth(1)
                    ->format('Y-m-d');
            } else {
                $suggestNextMonth = null;
            }

            return [
                'previous_reading' => $previous_reading->present_reading ?? null,
                'suggestedNextMonth' => $suggestNextMonth,
            ];
        }

        return [
            'previous_reading' => null,
            'suggestedNextMonth' => null,
        ];
    }

    public function checkConsumption($account_no, $current_reading)
    {
        $previousReading = Reading::with('bill')
            ->where('account_no', $account_no)
            ->latest()
            ->first();

        if (!$previousReading) {
            return [
                'status' => false,
                'message' => 'No previous reading found.'
            ];
        }

        $previousReadingDate = Carbon::parse($previousReading->created_at)->format('Y-m-d');
        
        $previousConsumptions = self::previousConsumption($account_no, $previousReadingDate);
        $previousValues = collect($previousConsumptions)->pluck('value')->filter(fn($v) => $v > 0)->values();

        if ($previousValues->isEmpty()) {
            return [
                'status' => false,
                'message' => 'Insufficient non-zero consumption data.'
            ];
        }

        $averageConsumption = $previousValues->avg();

        $multiplier = env('AVR_CONSUMP_PERCENTAGE') / 100;
        $threshold = $averageConsumption * (1 + $multiplier);

        $previousReadingValue = $previousReading->present_reading ?? 0;
        $currentConsumption = $current_reading - $previousReadingValue;

        $isHighConsumption = $currentConsumption > $threshold;

        return [
            'status' => true,
            'high_consumption' => $isHighConsumption,
            'current_consumption' => $currentConsumption,
            'average' => $averageConsumption,
            'threshold' => $threshold,
            'percentage_increase' => $multiplier * 100,
            'data_points_used' => $previousValues->count(),
        ];
    }


    public function getAccount($meter_no) {
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

    public function locate(array $payload) {    

        $account = $this->getAccount($payload['meter_no']);

        if (!$account) {
            return [
                'status' => 'error',
                'message' => 'No client found'
            ];
        }
    
        return [
            'status' => 'success',
            'account' => $account,
            'reading' => $previous_reading
        ];
    }

    public static function getBill(string $reference_no) {

        // Fetch the current bill with reading details
        $current_bill = Bill::with('reading', 'breakdown', 'discount')
            ->where('reference_no', $reference_no)
            ->first();    

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
        
        $bill_period_from = $current_bill->bill_period_from;

        $previousConsumption = self::previousConsumption($account_no, $bill_period_from);
        
        unset($client['accounts']);
        
        return [
            'client' => $client,
            'current_bill' => $current_bill->toArray() ?? [],
            'previous_payment' => $previous_payment,
            'active_payment' => $active_payment ? $active_payment->toArray() : null,
            'unpaid_bills' => $unpaid_bills->toArray() ?? [],
            'previousConsumption' => $previousConsumption
        ];
    }    

    public static function getBills(?string $number = null, bool $isAll = false, bool $isPaid = false) {

        $query = Bill::with(['reading', 'breakdown'])
            ->where('isPaid', $isPaid);
            
        if ($number) {
            
            $account = UserAccounts::where('account_no', $number)
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
                $amount = $base_amount * ($deduction->amount); 
    
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
        $basic_charge = collect($deductions)
            ->where('name', 'Basic Charge')
            ->sum('amount');
            
        // discounts
        $appliedDiscounts = [];
        $discountAmount = 0;

        foreach ($discounts as $discount) {
            
            $isEligible = (
                ($discount->eligible === 'senior' && $isSeniorCitizen) ||
                ($discount->eligible === 'pwd' && $isPWD)
            );

            if (!$isEligible) {
                continue;
            }

            if (strtolower($discount->type) === 'percentage') {
                $discountAmount = $basic_charge * ($discount->amount);
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

        $overall_total = $discountAmount == 0 ? $total : $overall_total;
        $arrears = collect($deductions)
            ->firstWhere('name', 'Previous Balance')['amount'] ?? 0;

        // penalty
        $penaltyAmount = 0;
        $amount_after_due = 0;
        $hasPenalty = false;

        if($unpaidAmount != 0) {

            $penalties = $this->paymentBreakdownService::getPenalty();
            $amountPayable = $total - $arrears - $discountAmount;

            foreach ($penalties as $penalty) {

                if (strtolower($penalty->amount_type) === 'percentage') {
                    $penaltyAmount = $amountPayable * ($penalty->amount);
                } else {
                    $penaltyAmount = $penalty->amount;
                }

                $amount_after_due = $overall_total + $penaltyAmount;
                $hasPenalty = true;
            }
        }

        $date = $payload['date'];
        $days_due = $ruling->due_date;

        if($latest_reading) {
            $lastReading = Carbon::parse($latest_reading->bill->bill_period_to);
            $nextReading = $lastReading->addDays(1);
            $bill_period_from = $nextReading->format('Y-m-d H:i:s');
            $bill_period_to = $nextReading->addDays($days_due)->format('Y-m-d H:i:s');
        } else {
            $bill_period_from = $date->copy()->subDays($days_due)->format('Y-m-d H:i:s');
            $bill_period_to = $date->copy()->format('Y-m-d H:i:s');
        }
       
        $due_date = $date->copy()->addDays($days_due)->format('Y-m-d H:i:s');

        $reading = [
            'account_no' => $payload['account_no'],
            'previous_reading' => $previous_reading,
            'present_reading' => $payload['present_reading'],
            'consumption' => $consumption,
            'reader_name' => Auth::user()->name,
            'created_at' => $bill_period_to,
            'updated_at' => $bill_period_to,
        ];

        $bill = [
            'reference_no' => $this->generateReferenceNo(),
            'bill_period_from' => $bill_period_from,
            'bill_period_to' => $bill_period_to,
            'previous_unpaid' => $unpaidAmount,
            'total' => $total,
            'discount' => $discountAmount,
            'penalty' => $penaltyAmount,
            'hasPenalty' => $hasPenalty,
            'amount' => $overall_total,
            'amount_after_due' => $amount_after_due,
            'due_date' => $due_date,
            'created_at' => $bill_period_to,
            'updated_at' => $bill_period_to,
        ];

        try {
            
            $readingID = Reading::insertGetId($reading);

            $bill['reading_id'] = $readingID;

            $billID = Bill::insertGetId($bill);

            foreach($deductions as $deduction) {
                BillBreakdown::insert([
                    'bill_id' => $billID,
                    'name' => $deduction['name'],
                    'description' => $deduction['description'],
                    'amount' => $deduction['amount'],
                    'created_at' => $bill_period_to,
                    'updated_at' => $bill_period_to,
                ]);
            }

            foreach($appliedDiscounts as $discount) {
                BillDiscount::insert([
                    'bill_id' => $billID,
                    'name' => $discount['name'],
                    'description' => $discount['description'],
                    'amount' => $discount['amount'],
                    'created_at' => $bill_period_to,
                    'updated_at' => $bill_period_to,
                ]);
            }

        } catch (\Exeception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage
            ];
        }

        return [
            'status' => 'success',
            'bill' => $bill,
        ];
    }

    private static function previousConsumption(string $account_no, string $bill_period_from) {
        
        $billDate = Carbon::parse($bill_period_from);

        $targetMonths = collect();
        for ($i = 1; $i <= 6; $i++) {
            $date = $billDate->copy()->subMonths($i);
            $targetMonths->push([
                'month' => $date->format('M'),
                'month_number' => $date->month,
                'year' => $date->year,
                'value' => 0
            ]);
        }

        $start = $billDate->copy()->subMonths(6)->startOfMonth();
        $end = $billDate->copy()->subMonth()->endOfMonth();

        $readings = Reading::select(
                DB::raw('MONTH(created_at) as month_number'),
                DB::raw('YEAR(created_at) as year_number'),
                'consumption'
            )
            ->where('account_no', $account_no)
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->unique(fn($item) => $item->year_number . '-' . $item->month_number);

        $result = $targetMonths->map(function ($month) use ($readings) {
            $reading = $readings->first(function ($r) use ($month) {
                return $r->month_number == $month['month_number'] &&
                    $r->year_number == $month['year'];
            });

            return [
                'month' => $month['month'],
                'year' => $month['year'],
                'value' => $reading ? (int) $reading->consumption : 0
            ];
        });

        return $result->toArray();
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