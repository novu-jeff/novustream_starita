<?php

namespace App\Services;

use App\Models\Zones;
use App\Models\BaseRate;
use App\Models\User;
use App\Models\Bill;
use App\Models\BillBreakdown;
use App\Models\BillDiscount;
use App\Models\Rates;
use App\Models\Reading;
use App\Models\UserAccounts;
use App\Models\SeniorDiscount;
use App\Models\Ruling;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use App\Models\PaymentDiscount;
use App\Models\PartialPayment;
use App\Models\Discount;
use App\Models\PaymentBreakdownPenalty;
use App\Models\InstallmentSchedule;

class MeterService {

    public $paymentBreakdownService;
    public $paymentServiceFee;

    public function __construct(PaymentBreakdownService $paymentBreakdownService) {
        $this->paymentBreakdownService = $paymentBreakdownService;
    }

    public function getReadUnread(string $monthYear)
    {
        $date = Carbon::parse($monthYear);

        $accounts = UserAccounts::with('user')->get();

        $readings = Reading::with('concessionaire.user')
            ->whereYear('created_at', $date->year)
            ->whereMonth('created_at', $date->month)
            ->get();

        $readData = $readings->map(function ($reading) {
            return [
                'account_no' => $reading->account_no,
                'name'       => $reading->concessionaire->user->name ?? 'N/A',
                'address'    => $reading->concessionaire->address ?? 'N/A',
                'meter_no'   => $reading->concessionaire->meter_serial_no ?? 'N/A',
            ];
        })->toArray();

        $readAccountNos = array_column($readData, 'account_no');

        $unreadData = $accounts->filter(function ($account) use ($readAccountNos) {
            return !in_array($account->account_no, $readAccountNos);
        })->map(function ($account) {
            return [
                'account_no' => $account->account_no,
                'name'       => $account->user->name ?? 'N/A',
                'address'    => $account->address ?? 'N/A',
                'meter_no'   => $account->meter_serial_no ?? 'N/A',
            ];
        })->values()->toArray();

        return [
            'read' => array_values($readData),
            'unread' => $unreadData,
        ];
    }

    public function getZones() {
        return Zones::select('zone', 'area')->get();
    }


    public function filterAccount(array $filter)
    {
        $query = UserAccounts::with('user');

        if (!empty($filter['zones']) && is_array($filter['zones'])) {

            $query->where(function ($q) use ($filter) {
                foreach ($filter['zones'] as $zone) {
                    $q->orWhere('account_no', 'like', $zone . '%');
                }
            });
        } elseif (
            !empty($filter['zone']) &&
            strtolower($filter['zone']) !== 'all'
        ) {
            $query->where('account_no', 'like', $filter['zone'] . '%');
        }

        if (
            isset($filter['senior_filter']) &&
            $filter['senior_filter'] === 'senior'
        ) {
            $query->whereIn('account_no', function ($q) {
                $q->select('account_no')
                    ->from('discount')
                    ->where('discount_type_id', 1);
            });
        }

        if (!empty($filter['search_by'])) {
            switch ($filter['search_by']) {
                case 'all':
                    if (!empty($filter['search'])) {
                        $query->where(function ($q) use ($filter) {
                            $q->where(
                                'account_no',
                                'like',
                                '%' . $filter['search'] . '%'
                            )
                            ->orWhere(
                                'meter_serial_no',
                                'like',
                                '%' . $filter['search'] . '%'
                            )
                            ->orWhereHas('user', function ($uq) use ($filter) {
                                $uq->where(
                                    'name',
                                    'like',
                                    '%' . $filter['search'] . '%'
                                );
                            });
                        });
                    }
                    break;

                case 'account_no':
                    if (!empty($filter['search'])) {
                        $query->where(
                            'account_no',
                            'like',
                            '%' . $filter['search'] . '%'
                        );
                    }
                    break;

                case 'meter_serial_no':
                    if (!empty($filter['search'])) {
                        $query->where(
                            'meter_serial_no',
                            'like',
                            '%' . $filter['search'] . '%'
                        );

                    }
                    break;

                case 'name':
                    if (!empty($filter['search'])) {
                        $searchParts = preg_split(
                            '/\s+/',
                            trim($filter['search'])
                        );
                        $query->whereHas('user', function ($q) use ($searchParts) {
                            foreach ($searchParts as $part) {
                                $q->whereRaw(
                                    "LOWER(name) LIKE ?",
                                    ['%' . strtolower($part) . '%']
                                );
                            }
                        });
                    }
                    break;

                case 'read':
                    $query->whereHas('readings');
                    break;

                case 'unread':
                    $query->whereDoesntHave('readings');
                    break;
            }
        }

        $total = $query->count();
        $limit = isset($filter['filter']) && is_numeric($filter['filter'])
            ? (int) $filter['filter']
            : 50;

        $data = $query
            ->orderByRaw("ISNULL(sequence_no) OR sequence_no = '' ASC")
            ->orderBy('sequence_no', 'ASC')
            ->limit($limit)
            ->get();

        return [
            'total' => $total,
            'data'  => $data
        ];
    }

    public function getPreviousReading($account_no) {

        $previous_reading = Reading::with('sc_discount', 'bill')
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

            $expired_date = null;

            $sc_discount_start = $previous_reading->sc_discount->effective_date ?? null;
            $sc_discount_end = $previous_reading->sc_discount->expired_date ?? null;

            if ($sc_discount_start && $sc_discount_end) {
                $billDate = Carbon::parse($suggestNextMonth);
                $scStartDate = Carbon::parse($sc_discount_start);
                $scEndDate = Carbon::parse($sc_discount_end);

                if($billDate->between($scStartDate, $scEndDate) && $billDate->diffInMonths($scEndDate, false) <= 1) {
                    $expired_date = Carbon::parse($scEndDate)->format('F d, Y');
                }
            }

            return [
                'previous_reading' => $previous_reading->present_reading ?? null,
                'suggestedNextMonth' => $suggestNextMonth,
                'sc_expired_date' => $expired_date
            ];
        }

        return [
            'previous_reading' => null,
            'suggestedNextMonth' => Carbon::now()->format('Y-m-d'),
            'sc_expired_date' => null
        ];
    }

    public function getReRead(string $reference_no) {

        $data = $this->getBill($reference_no);

        $client = $data['client'];
        $reading = $data['current_bill']['reading'];

        $expired_date = null;

        $sc_discount_start = $data['current_bill']['reading']['sc_discount']['effective_date'] ?? null;
        $sc_discount_end = $data['current_bill']['reading']['sc_discount']['expired_date'] ?? null;


        if ($sc_discount_start && $sc_discount_end) {
            $billDate = Carbon::parse($reading['created_at']);
            $scStartDate = Carbon::parse($sc_discount_start);
            $scEndDate = Carbon::parse($sc_discount_end);

            if($billDate->between($scStartDate, $scEndDate) && $billDate->diffInMonths($scEndDate, false) <= 1) {
                $expired_date = Carbon::parse($scEndDate)->format('F d, Y');
            }
        }

        $suggestedNextMonth = Carbon::parse($reading['created_at'])
            ->timezone('Asia/Manila')
            ->format('Y-m-d');

        $data = [
            'account_no' => $client['account_no'],
            'address' => $client['address'],
            'name' => $client['name'],
            'isHighConsumption' => $data['current_bill']['isHighConsumption'],
            'suggestedNextMonth' => $suggestedNextMonth,
            'sc_expired_date' => $expired_date
        ];

        $data = array_merge($data, $reading);

        return $data;
    }

    public function getAccount($meter_no) {
        return UserAccounts::with('user')->where('account_no', $meter_no ?? '')
        ->orWhere('meter_serial_no', $meter_no ?? '')
        ->first();
    }


    public static function getReport(string $zone = null, string $date = null, string $search = null)
    {
        $isAll = $zone === 'all';

        if (empty($zone) && empty($date) && empty($search)) {
            return Reading::with(['concessionaire.user', 'bill'])
                ->where('isReRead', false)
                ->whereHas('bill')
                ->get();
        }

        $readings = Reading::with(['concessionaire.user', 'bill'])
            ->when(empty($search), fn($q) => $q->where('isReRead', false)) // Exclude rereads for list view; include when searching for specific account
            ->whereHas('bill') // Only readings that have a bill (avoids missing ref in report when reading.reference_no is set but bill link is by reading_id)
            ->when(!empty($zone) && !$isAll, fn($q) =>
                $q->where('zone', 'like', "%$zone%")
            )
            ->when(!empty($date), function ($q) use ($date) {
                if (preg_match('/^\d{4}-\d{2}$/', $date)) {
                    [$year, $month] = explode('-', $date);
                    // Use bill_period_to (canonical billing/reading month) to include merged offline readings
                    $q->whereHas('bill', function ($bq) use ($year, $month) {
                        $bq->whereYear('bill_period_to', $year)
                            ->whereMonth('bill_period_to', $month);
                    });
                }
            })
            ->when(!empty($search), function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('account_no', 'like', "%$search%")
                    ->orWhereHas('concessionaire.user', fn($cq) =>
                        $cq->where('name', 'like', "%$search%")
                    );
                });
            })
            ->get();
        if ($isAll) {
            return $readings->values();
        }

        $grouped = $readings->groupBy(fn($r) => $r->zone ?? 'Unknown')
                            ->map(fn($zoneGroup) => $zoneGroup->values());

        return $grouped->values()->all();
    }



    public static function getData(?int $id = null) {

        if(!is_null($id)) {
            return Rates::with('property_types')->where('id', $id)
                ->first() ?? null;
        }

        return Rates::with('property_types')->get();

    }

    public static function getPayments(string $filter, string $zone = null, string $date = null, string $search = null, ?string $paymentMethod = null)
    {
        $isPaid = $filter === 'paid';

        $bills = Bill::with(['reading', 'client']) // Include client relationship
            ->where('isPaid', $isPaid)
            ->when($paymentMethod === 'walk-in', fn ($q) => $q->where('payment_method', 'cash'))
            ->when($paymentMethod === 'online', fn ($q) => $q->whereIn('payment_method', ['online', 'hitpay']))
            ->whereHas('reading', function ($query) use ($zone, $date) {
                $query->where('isReRead', false);

                if (!empty($zone) && $zone !== 'all') {
                    $query->where('zone', 'like', "%$zone%");
                }

                if (!empty($date)) {
                    [$year, $month] = explode('-', $date);
                    $query->whereYear('created_at', $year)
                        ->whereMonth('created_at', $month);
                }
            })
            ->when(!empty($search), function ($query) use ($search) {

            // Split user search into keywords
            $keywords = preg_split('/\s+/', strtolower($search));

            $query->where(function ($q) use ($keywords) {

                foreach ($keywords as $keyword) {
                    $keyword = trim($keyword);
                    if ($keyword === '') continue;

                    $q->where(function ($sub) use ($keyword) {
                        $sub->whereHas('reading', fn ($r) =>
                            $r->where('account_no', 'like', "%$keyword%")
                        )
                        ->orWhereHas('reading.concessionaire.user', function ($u) use ($keyword) {
                            $u->whereRaw('LOWER(name) LIKE ?', ["%$keyword%"]) // matches "Orge, Lucivil"

                            // ALSO match "Lucivil Orge"
                            ->orWhereRaw("
                                    LOWER(
                                        CONCAT(
                                            TRIM(SUBSTRING_INDEX(name, ',', -1)), ' ',
                                            TRIM(SUBSTRING_INDEX(name, ',', 1))
                                        )
                                    ) LIKE ?
                                ", ["%$keyword%"]);
                        });
                    });
                }
            });
        })->get();

        if ($zone === 'all') {
            if (!empty($date)) {
                return $bills->groupBy(fn($bill) => $bill->created_at->toDateString())
                            ->map(fn($group) => $group->values())
                            ->values()
                            ->all();
            }

            return $bills->values();
        }

        $grouped = $bills->groupBy(fn($bill) => $bill->reading->zone ?? 'Unknown')
            ->map(function ($groupedByZone) use ($date) {
                if (!empty($date)) {
                    return array_values(
                        $groupedByZone
                            ->groupBy(fn($bill) => $bill->created_at->toDateString())
                            ->map(fn($groupedByDate) => $groupedByDate->values())
                            ->values()
                            ->all()
                    );
                }

                return $groupedByZone->values();
            })->values()->all();

        return $grouped[0] ?? [];
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
            'reading' => $this->getPreviousReading($account->account_no)
        ];

    }

    public static function getBill(string $reference_no) {
        $current_bill = Bill::with('reading.sc_discount', 'breakdown', 'discount')
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
        $client = User::with(['accounts.sc_discount', 'accounts.property_types'])
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
                'property_types_id' => $payload['property_types_id'],
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

        if (is_null($ruling)) {
            return [
                'status' => 'error',
                'message' => "We've noticed that there's no ruling set. Please add first."
            ];
        }

        $other_deductions = $this->paymentBreakdownService::getData();
        $penalties = $this->paymentBreakdownService::getPenalty();

        if ((empty($other_deductions) || count($other_deductions) === 0)
            && (empty($penalties) || count($penalties) === 0)) {
            return [
                'status' => 'error',
                'message' => "We've noticed that there are no payment breakdowns or penalties set. Please add first."
            ];
        }

        $discounts = PaymentDiscount::all();
        if ($discounts->isEmpty()) {
            return [
                'status' => 'error',
                'message' => "We've noticed that there are no senior or franchise discounts. Please add first."
            ];
        }

        if (is_null($concessionaire)) {
            return [
                'status' => 'error',
                'message' => "We've noticed that there's no concessionaire with this account no."
            ];
        }

        $reference_no = $payload['reference_no'] ?? null;
        $reread_bill = $reference_no ? Bill::with('reading')->where('reference_no', $reference_no)->first() : null;
        if (!empty($payload['isReRead'])) {
            if ($reread_bill && $reread_bill->reading) {
                $reread_bill->reading->isReRead = true;
                $reread_bill->reading->save();
            }
        }

        $latest_reading = Reading::with('concessionaire.user', 'bill')
            ->where('isReRead', false)
            ->where('account_no', $payload['account_no'])
            ->latest()
            ->first();

        $previous_reading = isset($payload['previous_reading']) && $payload['previous_reading'] !== null && $payload['previous_reading'] !== ''
            ? (float) $payload['previous_reading']
            : (optional($latest_reading)->present_reading ?? 0);

        if (empty($payload['isReRead']) && $latest_reading) {
            $samePrev = (int) $latest_reading->previous_reading === (int) $previous_reading;
            $samePres = (int) $latest_reading->present_reading === (int) ($payload['present_reading'] ?? 0);
            $lastHadConsumption = (int) $latest_reading->present_reading !== (int) $latest_reading->previous_reading;
            if ($samePrev && $samePres && $lastHadConsumption) {
                return [
                    'status' => 'error',
                    'message' => 'A reading with the same previous and present values already exists for this account.',
                ];
            }
        }

        $isChangeSaved = optional($latest_reading)->bill->isChangeForAdvancePayment ?? false;

        $advances = $isChangeSaved ? (float) $latest_reading->bill->change ?? 0 : 0;
        $consumption = (float) $payload['present_reading'] - (float) $previous_reading;

        $base_rate = null;
        if (config('app.product') === 'novustream') {
            $rate = Rates::where('property_types_id', $payload['property_types_id'])
                ->where('cu_m', '<=', $consumption)
                ->orderByDesc('cu_m')
                ->value('amount');
        } else {
            $base_rate = BaseRate::where('property_types_id', $payload['property_types_id'])->value('rate') ?? 0;
            $rate = $base_rate * $consumption;
        }

        if ($rate == 0 || ($base_rate && $base_rate == 0)) {
            return [
                'status' => 'error',
                'message' => "We've noticed that there's no rate for this consumption"
            ];
        }

        $readingIds = Reading::where('account_no', trim($payload['account_no']))
            ->where('isReRead', 0)
            ->pluck('id');

        $forceZeroArrears = !empty($payload['force_zero_arrears']);

        // If the chronologically latest bill period is fully paid, do not carry older unpaid balances forward.
        $mostRecentBill = Bill::whereIn('reading_id', $readingIds)
            ->orderByDesc('bill_period_to')
            ->first();

        $skipLegacyArrears = !$forceZeroArrears
            && $mostRecentBill
            && (bool) $mostRecentBill->isPaid
            && !(bool) $mostRecentBill->isPartial;

        $latestUnpaidBill = null;
        $unpaidAmount = 0;
        $partialPaymentTotal = 0;

        if (!$forceZeroArrears && !$skipLegacyArrears) {
            $latestUnpaidBill = Bill::whereIn('reading_id', $readingIds)
                ->where('isInstallment', 0)
                ->where(function ($q) {
                    $q->where('isPaid', 0)
                        ->orWhere('isPartial', 1);
                })
                ->orderByDesc('bill_period_to')
                ->first();

            if ($latestUnpaidBill) {
                $unpaidAmount = (float) ($latestUnpaidBill->amount ?? 0);
                $partialPaymentTotal = $latestUnpaidBill->creditedPartialAmount();
                if ($latestUnpaidBill->reading_id) {
                    $fromTable = (float) PartialPayment::where('reading_id', $latestUnpaidBill->reading_id)
                        ->sum('partial_payment');
                    $partialPaymentTotal = max($partialPaymentTotal, $fromTable);
                }
            }
        }

        if ($forceZeroArrears) {
            $unpaidAmount = 0;
            $partialPaymentTotal = 0;
        }

        $remainingUnpaid = max($unpaidAmount - $partialPaymentTotal, 0);

        $installmentSchedule = InstallmentSchedule::where('is_paid', 0)
            ->whereHas('installment.bill.reading', function ($q) use ($payload) {
                $q->where('account_no', $payload['account_no']);
            })
            ->orderBy('month_no')
            ->first();

        if ($installmentSchedule) {
            $remainingUnpaid = (float) $installmentSchedule->amount;
        }

        $other_deductions = $this->paymentBreakdownService::getData();
        $deductions = [
            [
                'name' => 'Previous Balance',
                'amount' => $remainingUnpaid,
                'description' => ''
            ],
            [
                'name' => 'Basic Charge',
                'amount' => $rate,
                'description' => '',
            ],
        ];
        $total_amount = $rate + $remainingUnpaid;

        foreach ($other_deductions as $deduction) {
            if ($deduction->type == 'percentage') {
                $base_amount = ($deduction->percentage_of == 'basic_charge') ? $rate : $total_amount;
                $amount = $base_amount * ($deduction->amount);
                $deductions[] = [
                    'name' => $deduction->name,
                    'description' => $deduction->amount . '%',
                    'amount' => $amount
                ];
                $total_amount += $amount;
            } else {
                $deductions[] = [
                    'name' => $deduction->name,
                    'description' => '',
                    'amount' => $deduction->amount
                ];
                $total_amount += $deduction->amount;
            }
        }

        $total = collect($deductions)->sum('amount');
        $basic_charge = collect($deductions)->where('name', 'Basic Charge')->sum('amount');

        $appliedDiscounts = [];
        $totalDiscount = 0;
        $accountDiscountType = $concessionaire->discount_type ?? null;

        if ($accountDiscountType == 1) {
            $seniorDiscount = PaymentDiscount::where('eligible', 'senior')->first();
            if ($seniorDiscount) {
                // Determine base amount (basic_charge or total)
                $baseAmount = $basic_charge; // default to basic
                if ($seniorDiscount->percentage_of === 'total_amount') {
                    $baseAmount = $total;
                }

                $discountAmount = $seniorDiscount->type === 'fixed'
                    ? round(floatval($seniorDiscount->amount), 2)
                    : round($baseAmount * floatval($seniorDiscount->amount), 2);

                $appliedDiscounts[] = [
                    'name' => $seniorDiscount->name,
                    'amount' => $discountAmount,
                    'description' => $seniorDiscount->type === 'percentage' ? $seniorDiscount->amount . '%' : '',
                ];

                $totalDiscount += $discountAmount;
            }
        } elseif ($accountDiscountType == 2) {
            $franchiseDiscount = PaymentDiscount::where('eligible', 'franchise')->first();
            if ($franchiseDiscount) {
                // Determine base amount (basic_charge or total)
                $baseAmount = $basic_charge; // default to basic
                if ($franchiseDiscount->percentage_of === 'total_amount') {
                    $baseAmount = $total;
                }

                $discountAmount = $franchiseDiscount->type === 'fixed'
                    ? round(floatval($franchiseDiscount->amount), 2)
                    : round($baseAmount * floatval($franchiseDiscount->amount), 2);

                $appliedDiscounts[] = [
                    'name' => $franchiseDiscount->name,
                    'amount' => $discountAmount,
                    'description' => $franchiseDiscount->type === 'percentage' ? $franchiseDiscount->amount . '%' : '',
                ];

                $totalDiscount += $discountAmount;
            }
        }

        $total = collect($deductions)->sum('amount');
        $overall_total = $total;
        $arrears = collect($deductions)->firstWhere('name', 'Previous Balance')['amount'] ?? 0;

        $penaltyAmount = 0;
        $amount_after_due = 0;
        $hasPenalty = false;

        $dateNow = Carbon::parse($payload['date'])->format('Y-m-d');

        $penaltyExemption = \DB::table('penalty_exemptions')
            ->where('account_no', $payload['account_no'])
            ->where(function ($q) use ($dateNow) {
                $q->whereNull('effective_date')
                ->orWhere('effective_date', '<=', $dateNow);
            })
            ->where(function ($q) use ($dateNow) {
                $q->whereNull('expired_date')
                ->orWhere('expired_date', '>=', $dateNow);
            })
            ->first();

        $isPenaltyExempt = !is_null($penaltyExemption);

        if (!$installmentSchedule && !$isPenaltyExempt) {
            $penalties = $this->paymentBreakdownService::getPenalty();
            $amountPayable = $basic_charge - $totalDiscount;

            foreach ($penalties as $penalty) {
                if (strtolower($penalty->amount_type) === 'percentage') {
                    $penaltyAmount = $amountPayable * ($penalty->amount);
                } else {
                    $penaltyAmount = $penalty->amount;
                }

                $amount_after_due = $overall_total + $penaltyAmount + $remainingUnpaid;
                $hasPenalty = true;
            }
        } else {
            $penaltyAmount = 0;
            $amount_after_due = $overall_total;
            $hasPenalty = false;
        }

        $date = Carbon::parse($payload['date']);
        $days_due = $ruling->due_date;

        // Safely handle previous reading's bill
        $lastBillPeriodTo = optional(optional($latest_reading)->bill)->bill_period_to;

        if ($lastBillPeriodTo) {
            $lastReading = Carbon::parse($lastBillPeriodTo);
            $nextReading = $lastReading->addDays(1);
            $bill_period_from = $nextReading->format('Y-m-d H:i:s');
        } else {
            // No previous bill or reading — fallback to current date range
            $bill_period_from = $date->copy()->subDays($days_due)->format('Y-m-d H:i:s');
        }

        $bill_period_to = $date->copy()->format('Y-m-d H:i:s');
        $due_date = $date->copy()->addDays($days_due)->format('Y-m-d H:i:s');

        $isHighConsumption = $payload['is_high_consumption'] == 'yes';

        $billReferenceNo = $reference_no ?? $this->generateReferenceNo();

        $reading = [
            'zone' => explode('-', $payload['account_no'])[0] ?? null,
            'account_no' => $payload['account_no'],
            'previous_reading' => $previous_reading,
            'present_reading' => $payload['present_reading'],
            'consumption' => $consumption,
            'reader_name' => Auth::user()->name ?? 'OfflineReader',
            'created_at' => $bill_period_to,
            'updated_at' => $bill_period_to,
        ];
        if ($billReferenceNo) {
            $reading['reference_no'] = $billReferenceNo;
        }

        $finalTotal = $basic_charge + $remainingUnpaid;

        $finalAmount = round($basic_charge + $remainingUnpaid - $totalDiscount, 2);

        $finalAmountAfterDue = round($finalAmount + $penaltyAmount, 2);

        // if ($installmentSchedule) {

        //     $finalTotal = ($total - $partialPaymentTotal) + $remainingUnpaid;

        //     $finalAmount = ($overall_total - $partialPaymentTotal)
        //         + $remainingUnpaid
        //         + $penaltyAmount;

        //     $finalAmountAfterDue = ($overall_total - $partialPaymentTotal)
        //         + $remainingUnpaid
        //         + $penaltyAmount;

        // } else {

        //     $finalTotal = $basic_charge + $remainingUnpaid;

        //     $finalAmount = $basic_charge + $penaltyAmount + $remainingUnpaid;

        //     $finalAmountAfterDue = $basic_charge + $penaltyAmount + $remainingUnpaid;
        // }

        $payorName = optional($concessionaire->user)->name ?? null;

        $bill = [
            'reference_no' => $billReferenceNo,
            'bill_period_from' => $bill_period_from,
            'bill_period_to' => $bill_period_to,
            'previous_unpaid' => $remainingUnpaid,
            'total' => $finalTotal,
            'discount' => $totalDiscount,
            'penalty' => $penaltyAmount,
            'hasPenalty' => $hasPenalty,
            'advances' => $advances,
            'isChangeForAdvancePayment' => $isChangeSaved,
            'amount' => $finalAmountAfterDue,
            'amount_after_due' => $finalAmountAfterDue,
            'due_date' => $due_date,
            'isHighConsumption' => $isHighConsumption,
            'payor_name' => $payorName,
            'created_at' => $bill_period_to,
            'updated_at' => $bill_period_to,
        ];

        try {
            if ($billReferenceNo && $reference_no) {
                $readingModel = Reading::updateOrCreate(
                    ['reference_no' => $billReferenceNo],
                    $reading
                );
                $readingID = $readingModel->id;
            } else {
                $readingID = Reading::insertGetId($reading);
            }
            $bill['reading_id'] = $readingID;

            $existingBill = Bill::where('reference_no', $billReferenceNo)->first();
            if ($existingBill) {
                $existingBill->update(array_merge($bill, ['reading_id' => $readingID]));
                $billID = $existingBill->id;
                BillBreakdown::where('bill_id', $billID)->delete();
                BillDiscount::where('bill_id', $billID)->delete();
            } else {
                $billID = Bill::insertGetId($bill);
            }

            foreach ($deductions as $deduction) {
                BillBreakdown::insert([
                    'bill_id' => $billID,
                    'name' => $deduction['name'],
                    'description' => $deduction['description'],
                    'amount' => $deduction['amount'],
                    'created_at' => $bill_period_to,
                    'updated_at' => $bill_period_to,
                ]);
            }

            foreach ($appliedDiscounts as $discount) {
                BillDiscount::insert([
                    'bill_id' => $billID,
                    'name' => $discount['name'],
                    'description' => $discount['description'],
                    'amount' => $discount['amount'],
                    'created_at' => $bill_period_to,
                    'updated_at' => $bill_period_to,
                ]);
            }

            if (!empty($payload['isReRead']) && $reread_bill && $reread_bill->reading) {
                $reread_bill->reading->reread_reference_no = $billReferenceNo;
                $reread_bill->reading->save();
            }

        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        return [
            'status' => 'success',
            'bill' => array_merge($bill, ['id' => $billID]),
            'basic_charge' => $basic_charge,
            'reference_no' => $billReferenceNo,
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

    private function generateReferenceNo()
    {
        $prefix = env('REF_PREFIX', 'NST-STA');
        $technicianId = auth()->id() ?? '0';

        do {
            $time = time();
            $combined = "{$prefix}-0{$technicianId}-{$time}";

            $exists = Bill::where('reference_no', $combined)->exists();

            if ($exists) {
                sleep(1);
            }
        } while ($exists);

        return $combined;
    }

    public function getLatestReadingMonth()
    {
        return now()->format('Y-m');
    }

    /**
     * Apply same post-processing as ReadingController::store (discounts, penalty).
     * Used after create_breakdown in merge flow. Set $skipHitPayQr = true to avoid creating HitPay/QR for merge.
     */
    public function applyStorePostProcessingToBill(string $referenceNo, array $billData, float $basicCharge, float $consumption, string $account_no, array $payload = [], bool $skipHitPayQr = false): bool
    {
        try {
            $bill = Bill::where('reference_no', $referenceNo)->first();
            if (!$bill) {
                Log::warning('Merge: Bill not found for applyStorePostProcessingToBill', ['reference_no' => $referenceNo]);
                return false;
            }
            $account = $this->getAccount($account_no);
            if (!$account) {
                Log::warning('Merge: Account not found for applyStorePostProcessingToBill', ['account_no' => $account_no]);
                return false;
            }

            $currentDay = now()->day;
            $penaltyEntry = PaymentBreakdownPenalty::where('due_from', '<=', $currentDay)
                ->where('due_to', '>=', $currentDay)
                ->first();
            $penaltyAmount = 0;
            $totalDiscount = 0;
            $billAmountBeforePostProcessing = (float) ($billData['amount'] ?? $bill->amount ?? 0);
            $billAmountAfterDueBeforePostProcessing = (float) ($billData['amount_after_due'] ?? $bill->amount_after_due ?? $billAmountBeforePostProcessing);
            $existingPenalty = (float) ($billData['penalty'] ?? $bill->penalty ?? 0);
            $baseAmountBeforePenalty = $billAmountBeforePostProcessing;

            if (
                $existingPenalty > 0
                && abs($billAmountAfterDueBeforePostProcessing - $billAmountBeforePostProcessing) < 0.01
            ) {
                $baseAmountBeforePenalty = max($billAmountBeforePostProcessing - $existingPenalty, 0);
            }

            $hardcodedDiscounts = [
                '011-22-011450' => 0.02,
                '091-22-092230' => 0.05,
                '111-22-111720' => 0.02,
            ];
            $discountRecord = Discount::where('account_no', $account->account_no)->first();

            if (isset($hardcodedDiscounts[$account_no])) {
                $discountRate = $hardcodedDiscounts[$account_no];
                $hardcodedAmount = round($basicCharge * $discountRate, 2);
                BillDiscount::create([
                    'bill_id' => $bill->id,
                    'name' => 'Franchise Tax',
                    'description' => ($discountRate * 100) . '%',
                    'amount' => $hardcodedAmount,
                ]);
                $totalDiscount += $hardcodedAmount;
            }

            $ruling = DB::table('global_ruling')->first();
            $consumptionLimit = $ruling->snr_dc_rule ?? 0;

            if ($discountRecord && $discountRecord->discount_type_id == 1 && $consumption <= $consumptionLimit) {
                $seniorDiscount = PaymentDiscount::where('eligible', 'senior')->first();
                if ($seniorDiscount) {
                    $baseAmount = $seniorDiscount->percentage_of === 'basic_charge' ? $basicCharge : $baseAmountBeforePenalty;
                    $seniorAmount = $seniorDiscount->type === 'fixed'
                        ? round(floatval($seniorDiscount->amount), 2)
                        : round($baseAmount * floatval($seniorDiscount->amount), 2);
                    BillDiscount::create([
                        'bill_id' => $bill->id,
                        'name' => $seniorDiscount->name,
                        'description' => $seniorDiscount->type ?? null,
                        'amount' => $seniorAmount,
                    ]);
                    $totalDiscount += $seniorAmount;
                }
            }

            if ($discountRecord && $discountRecord->discount_type_id == 2) {
                $franchiseDiscount = PaymentDiscount::where('eligible', 'franchise')->first();
                if ($franchiseDiscount) {
                    $baseAmount = $franchiseDiscount->percentage_of === 'basic_charge' ? $basicCharge : $baseAmountBeforePenalty;
                    $franchiseAmount = $franchiseDiscount->type === 'fixed'
                        ? round(floatval($franchiseDiscount->amount), 2)
                        : round($baseAmount * floatval($franchiseDiscount->amount), 2);
                    BillDiscount::create([
                        'bill_id' => $bill->id,
                        'name' => $franchiseDiscount->name,
                        'description' => $franchiseDiscount->type ?? null,
                        'amount' => $franchiseAmount,
                    ]);
                    $totalDiscount += $franchiseAmount;
                }
            }

            $total = (float) ($billData['total'] ?? 0);
            $prevUnpaid = (float) ($billData['previous_unpaid'] ?? 0);
            $totalAmountPenalty = max($total - $prevUnpaid - $totalDiscount, 0);
            if ($penaltyEntry) {
                if ($penaltyEntry->amount_type === 'percentage') {
                    $penaltyAmount = $totalAmountPenalty * floatval($penaltyEntry->amount);
                } elseif ($penaltyEntry->amount_type === 'fixed') {
                    $penaltyAmount = floatval($penaltyEntry->amount);
                }
            }

            $penaltyExemptAccounts = [
                '011-22-011450', '031-22-030360', '031-22-030220', '011-22-011350', '081-22-082580',
                '081-22-082560', '081-22-082570', '101-22-102580', '081-22-080980', '111-22-111720',
                '091-22-092230', '061-22-060250', '071-22-073120', '111-22-110290', '111-22-111650',
            ];
            if (in_array($account_no, $penaltyExemptAccounts)) {
                $penaltyAmount = 0;
            }

            $amountDue = round(max($total - $totalDiscount, 0), 2);
            $amountAfterDue = round($amountDue + $penaltyAmount, 2);

            $bill->update([
                'penalty' => $penaltyAmount,
                'amount' => $amountAfterDue,
                'discount' => $totalDiscount,
                'amount_after_due' => $amountAfterDue,
                'hasPenalty' => $penaltyAmount > 0,
                'high_consumption_note' => $payload['high_consumption_note'] ?? null,
            ]);

            if (!$skipHitPayQr && !$bill->isPaid && empty($bill->hitpay_payment_id) && empty($bill->hitpay_reference)) {
                // Base amount (without penalty) for Novupay/HitPay so QR shows normal amount, not overdue
                $baseAmount = (float) $bill->amount - (float) ($bill->penalty ?? 0);
                $hitpayPayload = [
                    'reference_no' => $referenceNo,
                    'amount' => $baseAmount,
                    'payor' => $account->user->name ?? 'Sta. Rita Customer',
                    'email' => $account->user->email ?? null,
                    'account_no' => $account->account_no ?? '',
                ];
                $hitpayData = app(\App\Http\Controllers\PaymentController::class)
                    ->createHitpayPaymentRequest($referenceNo, $hitpayPayload);
                if ($hitpayData && (!empty($hitpayData['reference']) || !empty($hitpayData['id']))) {
                    $bill->update([
                        'hitpay_reference' => $hitpayData['reference'] ?? $hitpayData['reference_number'] ?? null,
                        'hitpay_payment_id' => $hitpayData['id'] ?? null,
                        'initiated_at' => now(),
                    ]);
                }
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Merge: applyStorePostProcessingToBill failed', [
                'reference_no' => $referenceNo,
                'account_no' => $account_no,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

}
