<?php

namespace App\Http\Controllers;

use App\Imports\PreviousBillingImport;
use App\Models\Bill;
use App\Services\GenerateService;
use App\Services\MeterService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Yajra\DataTables\Facades\DataTables;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Maatwebsite\Excel\Excel as ExcelFormat;
use App\Models\PaymentBreakdownPenalty;
use App\Models\PartialPayment;
use App\Models\BillDiscount;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{

    public $meterService;
    public $generateService;

    public function __construct(MeterService $meterService,
        GenerateService $generateService) {
        $this->meterService = $meterService;
        $this->generateService = $generateService;
    }

    public function index(Request $request)
{
    $filter = $request->filter ?? 'unpaid';

    if (!in_array($filter, ['unpaid', 'paid'], true)) {
        return redirect()->route('payments.index', ['filter' => 'unpaid']);
    }

    $zones = $this->meterService->getZones();
    $zone  = $request->zone ?? 'all';

    $paymentMethod = $request->payment_method ?? 'all';
    if (!in_array($paymentMethod, ['all', 'walk-in', 'online'], true)) {
        $paymentMethod = 'all';
    }

    $entries  = $request->entries ?? 10;
    $search   = trim($request->search ?? '');
    $date     = $request->date ?? $this->meterService->getLatestReadingMonth();

    $startDate = \Carbon\Carbon::parse($date)->startOfMonth();
    $endDate   = \Carbon\Carbon::parse($date)->endOfMonth();

    /*
    |--------------------------------------------------------------------------
    | BASE QUERY
    |--------------------------------------------------------------------------
    */

    $query = \App\Models\Bill::query()
        ->with(['reading.concessionaire.user'])
        ->whereBetween('bill_period_to', [$startDate, $endDate]);

    /*
    |--------------------------------------------------------------------------
    | FILTER: Paid / Unpaid
    |--------------------------------------------------------------------------
    */

    if ($filter === 'paid') {
    $query->where('isPaid', 1);
} else {
    $query->where('isPaid', 0);
}

    /*
    |--------------------------------------------------------------------------
    | FILTER: Zone
    |--------------------------------------------------------------------------
    */

    if ($zone !== 'all') {
        $query->whereHas('reading.concessionaire', function ($q) use ($zone) {
            $q->where('zone', $zone);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER: Payment Method
    |--------------------------------------------------------------------------
    */

    if ($paymentMethod !== 'all') {
        $query->where('payment_method', $paymentMethod);
    }

    /*
    |--------------------------------------------------------------------------
    | SMART SEARCH (FAST TOKEN SEARCH)
    |--------------------------------------------------------------------------
    */

    if (!empty($search)) {
        $tokens = preg_split('/\s+/', strtolower($search));

        $query->where(function ($q) use ($tokens, $search) {

            // Reference number
            $q->where('reference_no', 'like', "%{$search}%")

              ->orWhereHas('reading', function ($rq) use ($tokens, $search) {
                  $rq->where('account_no', 'like', "%{$search}%")
                     ->orWhereHas('concessionaire.user', function ($uq) use ($tokens) {
                         foreach ($tokens as $token) {
                             $uq->where('name', 'like', "%{$token}%");
                         }
                     });
              });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | DATABASE PAGINATION
    |--------------------------------------------------------------------------
    */

    $data = $query
        ->orderByDesc('created_at')
        ->paginate($entries)
        ->withQueryString();

    return view(
    'payments.index',
        compact(
            'data',
            'entries',
            'filter',
            'zones',
            'zone',
            'date',
            'paymentMethod'
        )
    )->with('toSearch', $search);
}


    public function upload(Request $request)
    {
        if ($request->getMethod() !== 'POST') {
            return view('payments.upload');
        }

        if (!$request->hasFile('file')) {
            return response()->json([
                'status' => 'error',
                'message' => 'No file uploaded.',
            ]);
        }

        $file = $request->file('file');

        if (
            !$file->isValid() ||
            $file->getClientOriginalExtension() !== 'xlsx' ||
            $file->getMimeType() !== 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Excel (.xlsx) files are allowed.',
            ]);
        }

        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheetNames = $spreadsheet->getSheetNames();

        $expectedHeaders = [
            'reference_no', 'account_no', 'billing_from', 'billing_to',
            'previous_reading', 'present_reading', 'consumption', 'penalty',
            'unpaid', 'arrears', 'current_bill', 'amount_paid',
            'date_paid', 'due_date', 'payor_name', 'payment_reference_no',
        ];

        $allMessages = [];
        $importedSheets = [];

        $headingData = (new HeadingRowImport(2))->toArray($file);

        $normalizeHeader = function ($header) {
            $h = (string)$header;
            $h = trim($h);
            $h = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $h));
            $h = preg_replace('/_+/', '_', $h);
            $h = trim($h, '_');
            return $h;
        };

        foreach ($sheetNames as $index => $sheetName) {
            $rawHeadersRow = $headingData[$index][0] ?? [];

            $normalizedHeaders = [];
            foreach ($rawHeadersRow as $h) {
                $n = $normalizeHeader($h);
                if (!empty($n)) {
                    $normalizedHeaders[] = $n;
                }
            }

            if (empty($normalizedHeaders) && !empty($headingData[$index])) {
                foreach ($headingData[$index] as $possibleRow) {
                    if (!empty($possibleRow) && is_array($possibleRow)) {
                        foreach ($possibleRow as $h) {
                            $n = $normalizeHeader($h);
                            if (!empty($n)) {
                                $normalizedHeaders[] = $n;
                            }
                        }
                        if (!empty($normalizedHeaders)) break;
                    }
                }
            }

            $missing = array_values(array_diff($expectedHeaders, $normalizedHeaders));

            if (!empty($missing)) {
                $allMessages[] = [
                    'sheet' => $sheetName,
                    'status' => 'error',
                    'message' => 'Missing headers in sheet.',
                    'missing_headers' => $missing,
                ];
                continue;
            }

            try {
                $importInstance = new PreviousBillingImport($sheetName);

                Excel::import(new class($importInstance, $sheetName) implements \Maatwebsite\Excel\Concerns\WithMultipleSheets {
                    private $importInstance;
                    private $sheetName;

                    public function __construct($importInstance, $sheetName)
                    {
                        $this->importInstance = $importInstance;
                        $this->sheetName = $sheetName;
                    }

                    public function sheets(): array
                    {
                        return [$this->sheetName => $this->importInstance];
                    }
                }, $file);

                $importedSheets[] = $sheetName;

                $failures = $importInstance->failures();
                $failureErrors = [];

                if ($failures->isNotEmpty()) {
                    foreach ($failures as $failure) {
                        $row = $failure->row();
                        foreach ($failure->errors() as $error) {
                            $failureErrors[] = "Row [$row]: $error";
                        }
                    }
                }

                $skippedRows = $importInstance->getSkippedRows();
                $rowCount = $importInstance->getRowCounter();
                $totalImported = max($rowCount - 2 - count($failureErrors) - count($skippedRows), 0);

                if (!empty($failureErrors) || !empty($skippedRows)) {
                    $message = [];
                    if (!empty($failureErrors)) {
                        $message[] = count($failureErrors) . ' skipped due to validation';
                    }
                    if (!empty($skippedRows)) {
                        $message[] = count($skippedRows) . ' skipped due to logic checks';
                    }

                    $allMessages[] = [
                        'sheet' => $sheetName,
                        'status' => 'warning',
                        'message' => "Total of <b>(".number_format($totalImported, 0).")</b> records partially imported. <br>" . implode(', ', $message),
                        'errors' => array_merge($failureErrors, $skippedRows),
                    ];
                } else {
                    $allMessages[] = [
                        'sheet' => $sheetName,
                        'status' => 'success',
                        'message' => "Total of <b>(".number_format($totalImported, 0).")</b> records imported successfully.",
                    ];
                }

            } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                $failures = $e->failures();
                $messages = [];

                foreach ($failures as $failure) {
                    $row = $failure->row();
                    foreach ($failure->errors() as $error) {
                        $messages[] = "Row [$row]: $error";
                    }
                }

                $allMessages[] = [
                    'sheet' => $sheetName,
                    'status' => 'error',
                    'message' => 'Validation errors found during import.',
                    'errors' => $messages,
                ];
            } catch (\Exception $e) {
                $allMessages[] = [
                    'sheet' => $sheetName,
                    'status' => 'error',
                    'message' => 'An error occurred: ' . $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'status' => 'completed',
            'imported' => $importedSheets,
            'messages' => $allMessages,
        ]);
    }


    public function pay(Request $request, string $reference_no)
{
    if ($request->getMethod() == 'POST') {
        $payload = $request->all();

        switch ($payload['payment_type']) {
            case 'cash':
                return $this->processCashPayment($reference_no, $payload);
            case 'online':
                return $this->processOnlinePayment($reference_no, $payload);
        }
    }

    $data = $this->meterService::getBill($reference_no);

    if (isset($data['status']) && $data['status'] === 'error') {
        return redirect()->back()->with('alert', [
            'status' => 'error',
            'message' => $data['message']
        ]);
    }

    // ⚙️ Validate reading
    $currentBill = $data['current_bill'] ?? null;
    if (!$currentBill || !isset($currentBill['reading_id'])) {
        return redirect()->back()->with('alert', [
            'status' => 'error',
            'message' => 'No reading found for this bill.'
        ]);
    }

    $reading = \App\Models\Reading::find($currentBill['reading_id']);
    if (!$reading) {
        return redirect()->back()->with('alert', [
            'status' => 'error',
            'message' => 'Reading not found.'
        ]);
    }

    // 🔹 Deduct previous partial payment (important new part)
    if (!empty($currentBill['isPartial']) && $currentBill['isPartial'] == 1) {
        $partialAmount = floatval($currentBill['partial_payment'] ?? 0);
        $remaining = floatval($currentBill['total'] ?? 0);

        if ($remaining < 0) $remaining = 0;

        // Update total due shown to user
        $data['current_bill']['total'] = $remaining;
    }

    // 🧾 Compute arrears stack
    $arrearsStack = collect();
    $previousUnpaid = (float)($currentBill['previous_unpaid'] ?? 0);
    if ($previousUnpaid > 0) {
        $arrearsMonth = \Carbon\Carbon::parse($currentBill['bill_period_to'])
            ->subMonth()
            ->format('F');
        $arrearsStack[$arrearsMonth] = $previousUnpaid;
    }

    // 🧮 Use dynamic penalty computation
    $amount = (float)($data['current_bill']['total'] ?? 0);
    $amount_afterDue = (float)($currentBill['amount_after_due'] ?? 0);
    $discount = (float)($currentBill['discount']['amount'] ?? 0);
    $tax = (float)($currentBill['tax'] ?? 0);
    $currentDay = now()->day;

    $penaltyEntry = \App\Models\PaymentBreakdownPenalty::where('due_from', '<=', $currentDay)
        ->where('due_to', '>=', $currentDay)
        ->first();

    $assumedPenalty = 0;
    $assumedAmountAfterDue = $amount_afterDue;

    if ($penaltyEntry) {
        $penaltyBase = $amount - $discount;
        if ($penaltyEntry->amount_type === 'percentage') {
            $assumedPenalty = $penaltyBase * floatval($penaltyEntry->amount);
        } elseif ($penaltyEntry->amount_type === 'fixed') {
            $assumedPenalty = floatval($penaltyEntry->amount);
        }
    } else {
        $assumedPenalty = $amount * 0.10; // fallback
    }

    $assumedAmountAfterDue = $amount + $previousUnpaid + $tax - $discount;

    $data['current_bill']['assumed_penalty'] = $assumedPenalty;
    $data['current_bill']['assumed_amount_after_due'] = $assumedAmountAfterDue;

    // 💰 Add service fees
    $hitpay_fee = 20;
    $novupay_fee = 10;
    $additional_service_fee = $hitpay_fee + $novupay_fee;
    $final_amount = $assumedAmountAfterDue + $additional_service_fee;

    // 🧾 Build payment payload
    $paymentPayload = [
        'reference_no' => $reference_no,
        'amount' => $final_amount,
        'customer' => [
            'name' => $data['client']['name'] ?? '',
            'account_no' => $data['client']['account_no'] ?? '',
            'address' => $data['client']['address'] ?? '',
        ],
    ];

    $hitpayCompletedId = $data['current_bill']['hitpay_payment_id']
        ?? $data['current_bill']['hitpay_reference']
        ?? null;
    if (!empty($data['current_bill']['isPaid']) && !empty($hitpayCompletedId)) {
        $url = self::buildHitpayCompletedUrl($hitpayCompletedId);
    } else {
        // 🔹 Generate HitPay checkout link (your logic)
        $hitpayData = app(\App\Http\Controllers\PaymentController::class)
            ->createHitpayPaymentRequest($reference_no, $paymentPayload);

        $url = $hitpayData['url'] ?? env('NOVUPAY_URL') . '/payment/merchants/' . $reference_no;
    }

    $qr_code = $this->generateService::qr_code($url, 80);

    $user = \App\Models\User::whereHas('accounts', function ($q) use ($data) {
        $q->where('account_no', $data['client']['account_no']);
    })->first();

    return view('payments.pay', compact('data', 'reference_no', 'qr_code', 'arrearsStack', 'user'));
}


    private function calculateTotalDue(array $currentBillData, ?array $payload = null, float $fullArrears = 0): array
    {
        // 1. amount -> total
        $currentBill = (float) ($currentBillData['total'] ?? 0);
        $arrears = $fullArrears ?: (float) ($currentBillData['previous_unpaid'] ?? 0);
        $penalty = (float) ($currentBillData['penalty'] ?? 0);

        $discount = 0;
        if (isset($payload['discount'])) {
            $discount = (float) $payload['discount'];
        } elseif (isset($currentBillData['discount'])) {
            if (is_array($currentBillData['discount'])) {
                $discount = collect($currentBillData['discount'])->sum('amount');
            } else {
                $discount = (float) $currentBillData['discount'];
            }
        }

        $advancePayment = (float) ($currentBillData['advances'] ?? 0);
        $partialPayment = (float) ($currentBillData['partial_payment'] ?? 0);
        $dueDatePenalty = 0;
        $dueDate = $currentBillData['due_date'] ?? null;
        $tax = (float) ($currentBillData['tax'] ?? 0);

        $dueDate = isset($currentBillData['due_date'])
            ? \Carbon\Carbon::parse(trim($currentBillData['due_date']))
                ->timezone('Asia/Manila')
                ->startOfDay()
            : null;

        $today = \Carbon\Carbon::today('Asia/Manila');

        if ($dueDate && $today->gt($dueDate)) {
            $dueDatePenalty = (float) $penalty;
        }

        $userDiscount = $currentBillData['reading']['account_no'];
            if (in_array($userDiscount, ['011-12-010740'])) {
                $temporaryDiscount = 0.25;
            } else {
                $temporaryDiscount = null;
            }

        // 2. removed arrears
        $totalDue = $currentBill - $discount + $dueDatePenalty - $advancePayment - $partialPayment;
        $temporaryDiscounts = $totalDue * $temporaryDiscount;
        $totalDue = $totalDue - $temporaryDiscounts;
        $totalDue = max(0, round($totalDue, 2));

        return [
            'total_due' => $totalDue,
            'breakdown' => [
                'current_bill' => $currentBill,
                'arrears' => $arrears,
                //'previous_penalty' => $prevPenalty, // optional
                'discount' => $discount,
                'advance_payment' => $advancePayment,
                'due_date_penalty' => $dueDatePenalty,
                'tax' => $tax,
            ],
        ];
    }



        private function getBill(string $reference_no, $payload = null, bool $strictAmount = false)
    {
        $data = $this->meterService::getBill($reference_no);

        if (!$data || !isset($data['current_bill'])) {
            return ['error' => 'Bill not found'];
        }

        $readingId = $data['current_bill']['reading_id'] ?? null;

        if (!$readingId) {
            return ['error' => 'No reading found for this bill.'];
        }

        $reading = \App\Models\Reading::find($readingId);
        if (!$reading) {
            return ['error' => 'Reading not found.'];
        }

        $accountNo = $reading->account_no;

        $unpaidBills = Bill::whereHas('reading', function($query) use ($accountNo) {
            $query->where('account_no', $accountNo);
        })
        ->where('isPaid', 0)
        ->orderBy('bill_period_from')
        ->get();

        $fullArrears = $unpaidBills->sum(fn($b) => $b->previous_unpaid);

        $totalDueResult = $this->calculateTotalDue($data['current_bill'], $payload);
        $totalDue = $totalDueResult['total_due'];
        $breakdown = $totalDueResult['breakdown'];

        if ($strictAmount && $payload) {
            $validator = Validator::make($payload, [
                'payment_amount' => 'required|numeric|gte:' . $totalDue,
            ], [
                'payment_amount.gte' => 'Cash payment is insufficient. Total due is PHP ' . number_format($totalDue, 2)
            ]);

            if ($validator->fails()) {
                return ['error' => $validator->errors()->first()];
            }
        }

        $data['current_bill']['assumed_amount_after_due'] = $totalDue;
        $data['current_bill']['breakdown'] = $breakdown;
        $data['current_bill']['previous_unpaid'] = $fullArrears;

        return [
            'data' => $data,
            'total_due' => $totalDue,
            'breakdown' => $breakdown,
        ];
    }

    public function applyDiscount(Request $request, $reference_no)
    {
        $request->validate([
            'appliedDiscount' => 'required|numeric|min:0|max:100'
        ]);

        $bill = Bill::where('reference_no', $reference_no)
                    ->where('isPaid', 0)
                    ->firstOrFail();

        if ($bill->discount > 0) {
            return back()->with('alert', [
                'status' => 'error',
                'message' => 'Discount already applied.'
            ]);
        }

        $percentage = (float) $request->appliedDiscount;
        $baseAmount = (float) $bill->amount;

        $computedDiscount = round(($baseAmount * $percentage) / 100, 2);

        if ($computedDiscount > $baseAmount) {
            $computedDiscount = $baseAmount;
        }

        \DB::transaction(function () use ($bill, $percentage, $computedDiscount) {

            $bill->discount         = $computedDiscount;
            $bill->penalty          = 0;
            $bill->amount           = $bill->total - $computedDiscount;
            $bill->amount_after_due = $bill->total - $computedDiscount;
            $bill->save();

            BillDiscount::create([
                'bill_id'     => $bill->id,
                'name'        => $percentage . '% discount',
                'description' => 'percentage',
                'amount'      => $computedDiscount,
            ]);
        });

        return back()->with('alert', [
            'status' => 'success',
            'message' => 'Discount applied successfully.'
        ]);
    }


    public function processCashPayment(string $reference_no, array $payload)
    {
        $result = $this->getBill($reference_no, $payload, false);

        if (isset($result['error'])) {
            return back()->with('alert', [
                'status' => 'error',
                'message' => $result['error']
            ]);
        }

        $data = $result['data'];
        $currentBill = Bill::find($data['current_bill']['id']);

        if (!$currentBill) {
            return back()->with('alert', [
                'status' => 'error',
                'message' => 'Bill not found.'
            ]);
        }

        $now = now();
        $paymentAmount  = round((float) $payload['payment_amount'], 2);
        $payArrearsOnly = !empty($payload['pay_arrears_only']);

        $account_no = optional($currentBill->reading)->account_no;

        $unpaidBills = Bill::whereHas('reading', function ($q) use ($account_no) {
                $q->where('account_no', $account_no);
            })
            ->where('isPaid', 0)
            ->orderBy('bill_period_from', 'asc')
            ->get();

        if ($unpaidBills->isEmpty()) {
            return back()->with('alert', [
                'status' => 'error',
                'message' => 'No unpaid bills found.'
            ]);
        }

        $remaining = $paymentAmount;

        if ($payArrearsOnly) {

        $arrearsAmount = round((float)$currentBill->previous_unpaid, 2);

        if ($paymentAmount < $arrearsAmount) {
            return back()->with('alert', [
                'status' => 'error',
                'message' => 'Payment does not fully cover arrears.'
            ]);
        }

        Bill::whereHas('reading', function ($q) use ($account_no) {
                $q->where('account_no', $account_no);
            })
            ->where('id', '<>', $currentBill->id)
            ->where('isPaid', 0)
            ->update([
                'isPaid'        => 1,
                'isPartial'     => 0,
                'amount_paid'   => DB::raw('amount_after_due'),
                'date_paid'     => now(),
                'payment_method'=> 'cash',
            ]);

        $currentBill->update([
            'isPartial'         => 1,
            'partial_payment'   => $paymentAmount,
        ]);

        return back()->with('alert', [
            'status'  => 'success',
            'message' => 'Arrears successfully paid.'
        ]);
    }

    $dueDate = \Carbon\Carbon::parse($currentBill->due_date);
    $isOverdue = now()->greaterThan($dueDate);

    $collectible = $isOverdue
        ? (float)$currentBill->amount_after_due
        : (float)$currentBill->total;

    $balance = round(
        $collectible - (float)$currentBill->amount_paid,
        2
    );

    if ($paymentAmount < $balance) {
        return back()->with('alert', [
            'status'  => 'error',
            'message' => 'Full settlement required. Total payable is PHP ' .
                        number_format($balance, 2)
        ]);
    }

    Bill::whereHas('reading', function ($q) use ($account_no) {
            $q->where('account_no', $account_no);
        })
        ->where('isPaid', 0)
        ->update([
            'isPaid'        => 1,
            'isPartial'     => 0,
            'amount_paid'   => DB::raw('amount_after_due'),
            'date_paid'     => now(),
            'payment_method'=> 'cash',
        ]);

        return back()->with('alert', [
            'status'  => 'success',
            'message' => 'Payment applied successfully.'
        ]);
    }

    public function processOnlinePaymentOld(string $reference_no, array $payload)
    {
        $result = $this->getBill($reference_no, $payload, false);

        if (isset($result['error'])) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $result['error']
            ]);
        }

        $url = env('NOVUPAY_URL') . '/payment/merchants/' . $reference_no;

        return redirect()->route('payments.pay', ['reference_no' => $reference_no])->with('alert', [
            'status' => 'success',
            'payment_request' => true,
            'redirect' => $url,
        ]);
    }

    public function processOnlinePayment(string $reference_no, array $payload)
    {
        // 🔹 Call your existing helper method
        $hitpayData = $this->createHitpayPaymentRequest($reference_no, $payload);

        // 🔹 Handle error from HitPay
        if (!$hitpayData || empty($hitpayData['url'])) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => 'Failed to generate HitPay payment request.',
            ]);
        }

        // 🔹 Update Bill record (optional, if you want to track the HitPay ID)
        $bill = \App\Models\Bill::where('reference_no', $reference_no)->first();
        if ($bill) {
            $bill->update([
                'payment_method' => 'online',
                'initiated_at' => now(),
                'hitpay_reference' => $hitpayData['reference'] ?? $hitpayData['reference_number'] ?? null,
                'hitpay_payment_id' => $hitpayData['id'] ?? null,
            ]);
        }

        // 🔹 Redirect with success message + payment link
        return redirect()->back()->with('alert', [
            'status' => 'success',
            'payment_request' => true,
            'redirect' => $hitpayData['url'],
        ]);
    }



    public function createHitpayPaymentRequest(string $reference_no, array $payload): ?array
    {
        try {
            $existingBill = Bill::where('reference_no', $reference_no)->first();
            if ($existingBill && !empty($existingBill->hitpay_payment_id)) {
                return [
                    'id' => $existingBill->hitpay_payment_id,
                    'url' => self::buildHitpayCheckoutUrl($existingBill->hitpay_payment_id),
                    'reference' => $existingBill->hitpay_reference,
                    'reference_number' => $existingBill->hitpay_reference,
                ];
            }

            $result = $this->getBill($reference_no, $payload, false);

            if (isset($result['error'])) {
                \Log::error('HitPay error: ' . $result['error']);
                return null;
            }

            $billData = $result['data']['current_bill'] ?? null;
            // dd($billData);
            if (!$billData) {
                \Log::error('Missing bill data for HitPay', ['reference_no' => $reference_no]);
                return null;
            }
            $days_before_due = 15;
            if (!empty($billData['due_date'])) {
                try {
                    $due_date = Carbon::parse($billData['due_date']);
                } catch (\Exception $e) {
                    $due_date = Carbon::now()->addDays($days_before_due);
                }
            } else {
                $due_date = Carbon::now()->addDays($days_before_due);
            }
            if ($due_date->isPast()) {
                $due_date = Carbon::now()->addDay();
            }
            $due_date = $due_date->endOfDay()->format('Y-m-d H:i:s');


            // dd($billData);
            $rateCode = $result['data']['client']['rate_code'] ?? null;
            $amount = (float) $billData['total'];
            $discount = !empty($billData['discount'][0]['amount'])
                ? (float) $billData['discount'][0]['amount']
                : 0;

            $amount = $amount - $discount;

            // dd($amount, $discount);

            $payor = $result['data']['client']['name'] ?? ($payload['payor'] ?? 'Sta. Rita Customer');
            $email = $result['data']['client']['email'] ?? ($payload['email'] ?? 'srwdsystem2023@gmail.com');
            $account_no = $result['data']['client']['account_no'] ?? ($payload['account_no'] ?? '000000');

            // ⚙️ Default payment methods (include QRPH if allowed)
            // $paymentMethods = ["gcash","gcash_qr","qrph_netbank","upay_bayd","upay_ecpy","upay_instapay","upay_online","upay_pchc","upay_plwn","xpay_card"];
            $qrph_fee  = $amount <= 2000 ? 20 : ($amount * 0.01);
            $gcash_fee = $amount * 0.023;

            $novupay_fee = 25;
            if(Str::contains($rateCode, '12')){
                $novupay_fee = 10;
            }

            // Select fee based on amount
            if ($amount < 800) {
                $selected_fee = $gcash_fee;
                $paymentMethods = ['gcash'];
            } else {
                $selected_fee = $qrph_fee;
                $paymentMethods = ['qrph_netbank', 'upay_online'];
            }

            $hitpay_fee = round($selected_fee, 1);
            $additional_service_fee = $hitpay_fee + $novupay_fee;

            $final_amount = round($amount + $additional_service_fee, 2);
            // 🧾 Purpose formatting
            $purpose = "Amount Due: PHP {$amount}\nConvenience Fee: PHP {$additional_service_fee}\nAccount #: {$account_no}";

            $hitpayPayload = [
                'amount' => $final_amount,
                'currency' => 'PHP',
                'email' => $email,
                'purpose' => $purpose,
                'reference_number' => $reference_no,
                'redirect_url' => env('HITPAY_REDIRECT_URL'),
                'webhook' => env('HITPAY_WEBHOOK_URL'),
                'send_email' => true,
                'send_sms' => true,
                'name' => $payor,
                'add_admin_fee' => true,
                'admin_fee' => '15.00',
                'payment_methods' => array_values($paymentMethods),
                'expiry_date' => $due_date,
            ];

            // dd($hitpayPayload);

            $response = \Http::withHeaders([
                'X-BUSINESS-API-KEY' => env('HITPAY_API_KEY'),
            ])->post(env('HITPAY_API_URL') . '/payment-requests', $hitpayPayload);

            // dd($response->body());
            if ($response->failed()) {
                \Log::error('HitPay API request failed', ['body' => $response->body()]);
                return null;
            }

            $data = $response->json();
            // dd($data);
            return [
                'id' => $data['id'] ?? null,
                'url' => $data['url'] ?? null,
                'reference' => $data['reference'] ?? null,
                'reference_number' => $data['reference_number'] ?? null,
            ];
        } catch (\Exception $e) {
            \Log::error('createHitpayPaymentRequest exception: ' . $e->getMessage());
            return null;
        }
    }




    public function handleRedirect(Request $request)
    {
        \Log::info('HitPay redirect received in PaymentController', $request->all());

        $status = strtolower($request->query('status'));
        $hitpay_reference = $request->query('reference');

        if (!$hitpay_reference || !$status) {
            abort(404, 'Invalid payment reference.');
        }

        // ✅ Step 1: If already paid locally (walk-in), show paid receipt
        // HitPay redirect sends payment-request ID (UUID) as "reference" – match by hitpay_reference, reference_no, or hitpay_payment_id
        $localBill = \App\Models\Bill::where('hitpay_reference', $hitpay_reference)
            ->orWhere('reference_no', $hitpay_reference)
            ->orWhere('hitpay_payment_id', $hitpay_reference)
            ->first();

        if ($localBill && $localBill->isPaid) {
            return view('payments.status', [
                'payload' => [
                    'title' => 'Payment Successful',
                    'message' => 'Payment was already marked as paid.',
                    'reference_no' => $localBill->reference_no ?? $hitpay_reference,
                    'status' => 'paid',
                    'amount' => number_format((float) ($localBill->amount_paid ?? $localBill->amount ?? 0), 2),
                    'date_paid' => $localBill->date_paid ?? now()->format('M d, Y H:i:s'),
                    'payment_id' => $localBill->hitpay_payment_id
                        ?? $localBill->payment_id
                        ?? null,
                ]
            ]);
        }

        // ✅ Step 2: Verify payment details with HitPay
        $response = \Http::withHeaders([
            'X-BUSINESS-API-KEY' => env('HITPAY_API_KEY'),
        ])->get(env('HITPAY_API_URL') . "/payment-requests/{$hitpay_reference}");

        if ($response->failed()) {
            \Log::error('HitPay verify API failed', ['reference' => $hitpay_reference]);
            return view('payments.status', [
                'payload' => [
                    'title' => 'Payment Verification Failed',
                    'message' => 'Unable to verify payment from HitPay.',
                    'reference_no' => $hitpay_reference,
                    'status' => 'failed',
                    'amount' => '0.00',
                    'date_paid' => now()->format('M d, Y h:i A'),
                ]
            ]);
        }

        $payment = $response->json();
        \Log::info('HitPay verified payment', $payment);

        $status = strtolower($payment['status'] ?? $status);
        $pendingStatuses = ['pending', 'processing', 'open', 'initiated'];
        if (in_array($status, $pendingStatuses, true)) {
            $status = 'pending';
        }
        $reference_number = $payment['reference_number'] ?? null;
        $amount = (float) ($payment['amount'] ?? 0);
        $payor = $payment['name'] ?? 'Unknown';
        $paymentId = $payment['payment_id'] ?? $payment['id'] ?? null;

        // ✅ Step 2: Find bill (redirect "reference" is payment-request ID; also match by our reference_number from API response)
        $bill = \App\Models\Bill::where('hitpay_reference', $hitpay_reference)
            ->orWhere('hitpay_payment_id', $hitpay_reference)
            ->orWhere('reference_no', $reference_number)
            ->first();

        $days_before_due = 15;
        $due_date = !empty($bill['due_date'])
            ? Carbon::parse($bill['due_date'])->format('M d, Y H:i:s')
            : Carbon::now()->addDays($days_before_due)->format('M d, Y H:i:s');


        if (!$bill) {
            \Log::warning("HitPay verify: Bill not found for {$hitpay_reference}");
            return view('payments.status', [
                'payload' => [
                    'title' => 'Bill Not Found',
                    'message' => 'Payment verified, but no matching bill found.',
                    'reference_no' => $hitpay_reference,
                    'status' => 'error',
                    'amount' => $amount,
                    'date_paid' => now()->format('M d, Y h:i A'),
                ]
            ]);
        }

        // ✅ Step 3: Mark bill as paid if successful
        if (in_array($status, ['completed', 'succeeded', 'success'], true)) {
            $bill->update([
                'isPaid' => 1,
                'amount_paid' => $amount,
                'payor_name' => $payor,
                'date_paid' => now(),
                'payment_method' => 'online',
            ]);
            \Log::info('HitPay redirect: bill marked as paid', [
                'bill_id' => $bill->id,
                'reference_no' => $bill->reference_no,
                'hitpay_reference' => $hitpay_reference,
                'amount' => $amount,
            ]);

            return view('payments.status', [
                'payload' => [
                    'title' => 'Payment Successful',
                    'message' => 'Your payment was verified and marked as paid.',
                    'reference_no' => $bill->reference_no,
                    'status' => $status,
                    'amount' => number_format($amount, 2),
                    'date_paid' => now()->format('M d, Y H:i:s'),
                    'payment_id' => $paymentId ?? uniqid('PAY-'),
                ]
            ]);
        } elseif ($status === 'pending' && !$bill->isPaid) {
            $pendingUpdate = [
                'isPaid' => 0,
            ];
            if (empty($bill->hitpay_reference)) {
                $pendingUpdate['hitpay_reference'] = $hitpay_reference;
            }
            if (!empty($paymentId) && empty($bill->hitpay_payment_id)) {
                $pendingUpdate['hitpay_payment_id'] = $paymentId;
            }
            if (empty($bill->initiated_at)) {
                $pendingUpdate['initiated_at'] = now();
            }
            if (empty($bill->payment_method)) {
                $pendingUpdate['payment_method'] = 'online';
            }
            $bill->update($pendingUpdate);
        }

        // ❌ Step 4: Failed or canceled
        return view('payments.status', [
            'payload' => [
                'title' => 'Payment Failed',
                'message' => 'Your payment was not completed or was canceled.',
                'reference_no' => $bill->reference_no,
                'status' => $status,
                'amount' => number_format($amount, 2),
                'date_paid' => now()->format('M d, Y H:i:s'),
                'payment_id' => $payment['id'] ?? uniqid('PAY-'),
                'expires_at' => $due_date ?? null,
            ]
        ]);
    }

    private function deleteHitpayPaymentRequest(Bill $bill): void
    {
        $paymentRequestId = $bill->hitpay_payment_id ?? null;
        if (empty($paymentRequestId)) {
            \Log::info('HitPay delete skipped (missing hitpay_payment_id)', [
                'bill_id' => $bill->id,
                'reference_no' => $bill->reference_no ?? null,
                'hitpay_reference' => $bill->hitpay_reference ?? null,
            ]);
            return;
        }

        try {
            $response = \Http::withHeaders([
                'X-BUSINESS-API-KEY' => env('HITPAY_API_KEY'),
            ])->delete(env('HITPAY_API_URL') . "/payment-requests/{$paymentRequestId}");

            if ($response->failed()) {
                \Log::warning('HitPay delete payment request failed', [
                    'bill_id' => $bill->id,
                    'hitpay_payment_id' => $paymentRequestId,
                    'body' => $response->body(),
                ]);
            } else {
                \Log::info('HitPay payment request deleted', [
                    'bill_id' => $bill->id,
                    'hitpay_payment_id' => $paymentRequestId,
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('HitPay delete payment request error: ' . $e->getMessage(), [
                'bill_id' => $bill->id,
                'hitpay_payment_id' => $paymentRequestId,
            ]);
        }
    }

    public static function buildHitpayCompletedUrl(?string $paymentRequestId): ?string
    {
        if (empty($paymentRequestId)) {
            return null;
        }

        $slug = trim((string) env('HITPAY_BUSINESS_SLUG', ''));
        if ($slug !== '') {
            $slug = ltrim($slug, '@');
            return "https://securecheckout.hit-pay.com/payment-request/@{$slug}/{$paymentRequestId}/completed";
        }

        return "https://securecheckout.hit-pay.com/payment-request/{$paymentRequestId}/completed";
    }

    public static function buildHitpayCheckoutUrl(?string $paymentRequestId): ?string
    {
        if (empty($paymentRequestId)) {
            return null;
        }

        $slug = trim((string) env('HITPAY_BUSINESS_SLUG', ''));
        if ($slug !== '') {
            $slug = ltrim($slug, '@');
            return "https://securecheckout.hit-pay.com/payment-request/@{$slug}/{$paymentRequestId}/checkout";
        }

        return "https://securecheckout.hit-pay.com/payment-request/{$paymentRequestId}/checkout";
    }

    /**
     * HitPay webhook: update bill to paid when payment completes.
     * HitPay may send reference_number (our ref) or payment-request id – look up by both.
     */
    public function hitpayWebhook(Request $request)
    {
        $payload = $request->all();
        Log::info('HitPay Webhook received', $payload);

        $reference_number = $payload['reference_number'] ?? $payload['reference_no'] ?? null;
        $payment_request_id = $payload['payment_request_id'] ?? $payload['id'] ?? null;
        $payment_status = strtolower($payload['status'] ?? '');
        $payment_amount = (float) ($payload['amount'] ?? 0);
        $payor = $payload['customer']['name'] ?? $payload['name'] ?? 'Unknown';

        if (empty($reference_number) && empty($payment_request_id)) {
            Log::warning('HitPay webhook: missing reference_number and id');
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
        }

        $existingBill = Bill::query()
            ->where(function ($q) use ($reference_number, $payment_request_id) {
                if (!empty($reference_number)) {
                    $q->where('reference_no', $reference_number)
                      ->orWhere('hitpay_reference', $reference_number)
                      ->orWhere('hitpay_payment_id', $reference_number);
                }
                if (!empty($payment_request_id)) {
                    $q->orWhere('hitpay_payment_id', $payment_request_id)
                      ->orWhere('hitpay_reference', $payment_request_id);
                }
            })
            ->orderBy('id')
            ->first();

        if (!$existingBill) {
            Log::warning('HitPay webhook: bill not found', ['reference_number' => $reference_number, 'id' => $payment_request_id]);
            return response()->json(['status' => 'error', 'message' => 'Bill not found'], 404);
        }

        if ($existingBill->isPaid) {
            Log::info('HitPay webhook ignored; bill already paid', ['reference_no' => $existingBill->reference_no]);
            return response()->json(['status' => 'ignored', 'message' => 'Bill already paid'], 200);
        }

        if (!in_array($payment_status, ['completed', 'succeeded', 'success'], true)) {
            Log::info('HitPay webhook ignored; status not completed', ['status' => $payment_status]);
            return response()->json(['status' => 'ignored', 'message' => 'Payment not completed'], 200);
        }

        try {
            $existingBill->update([
                'isPaid' => 1,
                'amount_paid' => $payment_amount > 0 ? $payment_amount : $existingBill->amount,
                'payor_name' => $payor,
                'date_paid' => now(),
                'payment_method' => 'online',
            ]);
            Log::info('HitPay webhook: bill marked as paid', [
                'bill_id' => $existingBill->id,
                'reference_no' => $existingBill->reference_no,
            ]);
            return response()->json(['status' => 'success', 'message' => 'Bill updated'], 200);
        } catch (\Exception $e) {
            Log::error('HitPay webhook exception: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Server error'], 500);
        }
    }

    public function datatable($query) {
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('account_no', function($row) {
                return $row->reading->account_no ?? 'N/A';
            })
            ->editColumn('billing_period', function ($row) {
                return ($row->bill_period_from && $row->bill_period_to)
                    ? Carbon::parse($row->bill_period_from)->format('M d, Y') . ' TO ' . Carbon::parse($row->bill_period_to)->format('M d, Y')
                    : 'N/A';
            })
            ->editColumn('bill_date', function ($row) {
                return !empty($row->bill_period_to)
                    ? Carbon::parse($row->bill_period_to)->format('M d, Y')
                    : 'N/A';
            })
            ->editColumn('amount', function ($row) {
                return '₱' . number_format((float)($row->amount ?? 0), 2);
            })
            ->editColumn('due_date', function ($row) {
                return !empty($row->due_date)
                    ? Carbon::parse($row->due_date)->format('M d, Y')
                    : 'N/A';
            })
            ->editColumn('status', function ($row) {
                return $row->isPaid
                    ? '<div class="alert alert-primary mb-0 py-1 px-2 text-center">Paid</div>'
                    : '<div class="alert alert-danger mb-0 py-1 px-2 text-center">Unpaid</div>';
            })
            ->addColumn('actions', function ($row) {
                if(!$row->isPaid) {
                    return '
                    <div class="d-flex align-items-center gap-2">
                        <a href="' . route('payments.pay', ['reference_no' => $row->reference_no]) . '"
                            class="btn btn-primary text-white text-uppercase fw-bold">
                            <i class="bx bx-credit-card-alt" ></i>
                        </a>
                    </div>';
                } else {
                    return
                    '<div class="d-flex align-items-center gap-2">
                        <a target="_blank" href="' . route('reading.show', $row->reference_no) . '"
                            class="btn btn-primary text-white text-uppercase fw-bold"
                            id="show-btn" data-id="' . e($row->id) . '">
                            <i class="bx bx-receipt"></i>
                        </a>
                    </div>';
                }
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }

}
