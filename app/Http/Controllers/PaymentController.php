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
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\HeadingRowImport;
use Yajra\DataTables\Facades\DataTables;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Illuminate\Support\Facades\Http;

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

        $filter = $request->filter ?? '';

        if (!in_array($filter, ['unpaid', 'paid'], true)) {
            return redirect()->route('payments.index', ['filter' => 'unpaid']);
        }

        $zones = $this->meterService->getZones();
        $zone = $request->zone ?? 'all';

        $entries = $request->entries ?? 10;
        $toSearch = $request->search ?? '';
        $date = $request->date ?? $this->meterService->getLatestReadingMonth();

        $collection = collect($this->meterService::getPayments($filter, $zone, $date, $toSearch))
            ->flatten(2);

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $collection->slice(($currentPage - 1) * $entries, $entries)->values();

        $data = new LengthAwarePaginator(
            $currentItems,
            $collection->count(),
            $entries,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('payments.index', compact('data', 'entries', 'filter', 'zones', 'zone', 'date', 'toSearch'));
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


    public function pay(Request $request, string $reference_no) {

        if($request->getMethod() == 'POST') {
            $payload = $request->all();

            switch($payload['payment_type']) {
                case 'cash':
                    return $this->processCashPayment($reference_no, $payload);
                case 'online':
                    return $this->processOnlinePayment($reference_no, $payload);
            }

        }

        $data = $this->meterService::getBill($reference_no);

        if(isset($data['status']) && $data['status'] == 'error') {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $data['message']
            ]);
        }

        if (!is_null($data['active_payment'])
            && $data['active_payment']['reference_no'] !== $reference_no) {
            $alert = [
                'status' => 'warning',
                'message' => 'This account has another active payment. Showing requested bill anyway.'
            ];
        }

        $url = env('NOVUPAY_URL') . '/payment/merchants/' . $reference_no;

        $qr_code = $this->generateService::qr_code($url, 80);

        $amount = $data['current_bill']['amount'] ?? 0;
        $assumed_penalty = $amount * 0.15;
        $assumed_amount_after_due = $amount + $assumed_penalty;

        $data['current_bill']['assumed_penalty'] = $assumed_penalty;
        $data['current_bill']['assumed_amount_after_due'] = $assumed_amount_after_due;
        $arrearsStack = collect($data['arrearsStack'] ?? []);
        return view('payments.pay', compact('data', 'reference_no', 'qr_code', 'arrearsStack'));
    }

    private function getBill(string $reference_no, $payload = null, bool $strictAmount = false)
    {
        $data = $this->meterService::getBill($reference_no);

        if (!$data || !isset($data['current_bill'])) {
            return ['error' => 'Bill not found'];
        }

        $total = (float) $data['current_bill']['amount'] + (float) $data['current_bill']['penalty'];

        if($strictAmount) {
            $validator = Validator::make($payload, [
                'payment_amount' => 'required|gte:' . $total
            ], [
                'payment_amount.gte' => 'Cash payment is insufficient'
            ]);

            if ($validator->fails()) {
                return ['error' => $validator->errors()->first()];
            }
        }

        return ['data' => $data];
    }

    public function processCashPayment(string $reference_no, array $payload) {

        $result = $this->getBill($reference_no, $payload, true);

        if (isset($result['error'])) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $result['error']
            ]);
        }

        $data = $result['data'];
        $now = Carbon::now()->format('Y-m-d H:i:s');

        $amount = (float) $data['current_bill']['amount'] + (float) $data['current_bill']['penalty'];
        $change = (float) $payload['payment_amount'] - $amount;
        $forAdvancePayment = isset($payload['for_advances']) && $payload['for_advances'];

        $saveChange = ($change != 0 && $forAdvancePayment);

        $currentBill = Bill::find($data['current_bill']['id']);

        if ($currentBill) {
            $currentBill->update([
                'isPaid' => true,
                'amount_paid' => $payload['payment_amount'],
                'change' => $change,
                'payor_name' => $payload['payor'],
                'date_paid' => $now,
                'isChangeForAdvancePayment' => $saveChange,
                'payment_method' => 'cash',
            ]);
        }

        if (!empty($data['unpaid_bills'])) {
            foreach ($data['unpaid_bills'] as $unpaid_bill) {
                $unpaidBill = Bill::find($unpaid_bill['id']);
                if ($unpaidBill) {
                    $unpaidBill->update([
                        'payor_name' => $payload['payor'],
                        'date_paid' => $now,
                        'isPaid' => true,
                        'amount_paid' => $payload['payment_amount'],
                        'change' => $change,
                        'paid_by_reference_no' => $reference_no,
                    ]);
                }
            }
        }

        return redirect()->back()->with('alert', [
            'status' => 'success',
            'message' => 'Bill has been paid'
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
        // Step 1: Retrieve bill details
        $result = $this->getBill($reference_no, $payload, false);

        if (isset($result['error'])) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $result['error']
            ]);
        }

        // var_dump($result); exit;
        $billData = $result['data']['current_bill'] ?? null;

        if (!$billData) {
            return back()->with('alert', [
                'status' => 'error',
                'message' => 'Missing bill data.'
            ]);
        }


        // Step 2: Prepare HitPay payload
        $amount = number_format(
            (float)$billData['amount'] + (float)$billData['penalty'],
            2,
            '.',
            ''
        );

        $payor = $result['data']['client']['name'] ?? ($payload['payor'] ?? 'Customer');
        $email = $result['data']['client']['email'] ?? ($payload['email'] ?? 'jeff@novulutions.com');
        $account_no = $result['data']['client']['account_no']
            ?? ($payload['account_no'] ?? '000000');
        // dd($result);
        $hitpayPayload = [
            'amount' => $amount,
            'currency' => 'PHP',
            'email' => $email,
            'purpose' => 'Sta. Rita Water District. Payment for Account # -  ' . $account_no,
            'reference_number' => $reference_no,
            'redirect_url' => env('HITPAY_REDIRECT_URL'),
            // 'redirect_url' => route('payments.redirect', ['reference' => $reference_no, 'status' => 'pending']),
            'webhook' => env('HITPAY_WEBHOOK_URL'),
            'send_email' => true,
            'send_sms' => true,
            'name' => $payor,
            'add_admin_fee' => true,
            'admin_fee' => '15.00', // Optional fixed admin fee
            // 'generate_qr' => true,
            // 'payment_methods' => ['qrph_netbank'],
        ];

        // Step 3: Send request to HitPay API
        $response = Http::withHeaders([
            'X-BUSINESS-API-KEY' => env('HITPAY_API_KEY'),
        ])->post(env('HITPAY_API_URL') . '/payment-requests', $hitpayPayload);

        if ($response->failed()) {
            $error = $response->json('message') ?? 'Failed to create HitPay payment.';
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $error,
            ]);
        }

        $hitpayData = $response->json();

        // Step 4: Redirect to HitPay checkout page
        return redirect()->back()->with('alert', [
            'status' => 'success',
            'payment_request' => true,
            'redirect' => $hitpayData['url']
        ]);

    }

    public function createHitpayPaymentRequest(string $reference_no, array $payload): ?array
    {
        try {
            $result = $this->getBill($reference_no, $payload, false);

            if (isset($result['error'])) {
                \Log::error('HitPay error: ' . $result['error']);
                return null;
            }

            $billData = $result['data']['current_bill'] ?? null;
            if (!$billData) {
                \Log::error('Missing bill data for HitPay', ['reference_no' => $reference_no]);
                return null;
            }

            $amount = number_format(
                (float)$billData['amount'] + (float)$billData['penalty'],
                2,
                '.',
                ''
            );

            $payor = $result['data']['client']['name'] ?? ($payload['payor'] ?? 'Customer');
            $email = $result['data']['client']['email'] ?? ($payload['email'] ?? 'jeff@novulutions.com');
            $account_no = $result['data']['client']['account_no'] ?? ($payload['account_no'] ?? '000000');

            $hitpayPayload = [
                'amount' => $amount,
                'currency' => 'PHP',
                'email' => $email,
                'purpose' => 'Sta. Rita Water District. Payment for Account # - ' . $account_no,
                'reference_number' => $reference_no,
                'redirect_url' => env('HITPAY_REDIRECT_URL'),
                'webhook' => env('HITPAY_WEBHOOK_URL'),
                'send_email' => true,
                'send_sms' => true,
                'name' => $payor,
                'add_admin_fee' => true,
                'admin_fee' => '15.00',
            ];

            $response = \Http::withHeaders([
                'X-BUSINESS-API-KEY' => env('HITPAY_API_KEY'),
            ])->post(env('HITPAY_API_URL') . '/payment-requests', $hitpayPayload);

            if ($response->failed()) {
                \Log::error('HitPay API request failed', ['body' => $response->body()]);
                return null;
            }

            $data = $response->json();
            return [
                'id' => $data['id'] ?? null,
                'url' => $data['url'] ?? null,
            ];
        } catch (\Exception $e) {
            \Log::error('createHitpayPaymentRequest exception: ' . $e->getMessage());
            return null;
        }
    }




    public function handleRedirect(Request $request)
    {
        $reference = $request->query('reference');
        $status = $request->query('status');

        if (!$reference || !$status) {
            abort(404, 'Invalid payment reference.');
        }

        // You can show a custom page or redirect based on status
        if ($status === 'completed' || $status === 'success') {
            return view('payments.success', [
                'reference' => $reference,
                'message' => 'Your payment was successful!'
            ]);
        }

        if ($status === 'failed' || $status === 'canceled') {
            return view('payments.failed', [
                'reference' => $reference,
                'message' => 'Your payment was canceled or failed. Please try again.'
            ]);
        }

        abort(404, 'Unknown payment status.');
    }


    public function createHitPayPayment(Request $request)
    {
        $reference_no = $request->input('reference_no');
        $amount = $request->input('amount');

        $response = Http::withHeaders([
            'X-BUSINESS-API-KEY' => env('HITPAY_API_KEY'),
            'Content-Type' => 'application/json',
        ])->post(env('HITPAY_API_URL') . '/payment-requests', [
            'amount' => $amount,
            'currency' => 'PHP',
            'reference_number' => $reference_no,
            'redirect_url' => env('HITPAY_REDIRECT_URL'),
            'webhook' => env('HITPAY_WEBHOOK_URL'),
            'name' => 'Bill Payment #' . $reference_no,
            'email' => $request->input('email', 'customer@example.com'),
        ]);

        if ($response->failed()) {
            return back()->with('alert', [
                'status' => 'error',
                'message' => 'Failed to create payment request. Please try again.'
            ]);
        }

        $data = $response->json();
        return response()->json($data);
    }

    public function hitpayCallback(Request $request)
    {
        // HitPay redirects here after payment
        $reference_no = $request->input('reference_number');
        $status = $request->input('status'); // 'completed', 'failed', etc.

        // Update your DB or bill status here
        // Example:
        // Bill::where('reference_no', $reference_no)->update(['status' => $status]);

        return redirect()->route('payments.pay', ['reference_no' => $reference_no])
            ->with('alert', [
                'status' => $status === 'completed' ? 'success' : 'error',
                'message' => "Payment {$status}"
            ]);
    }

    public function hitpayWebhook(Request $request)
    {
        // optional: verify signature before processing
        $payload = $request->all();

        if (isset($payload['reference_number'], $payload['status'])) {
            // Bill::where('reference_no', $payload['reference_number'])
            //     ->update(['status' => $payload['status']]);
        }

        return response()->json(['success' => true]);
    }



    public function callback(Request $request, string $reference_no)
    {
        $payload = $request->all();
        $bill = $this->meterService->getBill($reference_no);

        if ($bill) {
            $now = Carbon::now()->format('Y-m-d H:i:s');

            $currentBill = Bill::find($bill['current_bill']['id']);
            if ($currentBill) {
                $currentBill->update([
                    'isPaid' => true,
                    'amount_paid' => $payload['amount'],
                    'date_paid' => $now,
                    'payment_method' => 'online',
                ]);
            }

            // Update unpaid bills if needed
            if (!empty($bill['unpaid_bills'])) {
                foreach ($bill['unpaid_bills'] as $unpaid_bill) {
                    $unpaidBill = Bill::find($unpaid_bill['id']);
                    if ($unpaidBill) {
                        $unpaidBill->update([
                            'isPaid' => true,
                            'amount_paid' => $payload['amount'],
                            'date_paid' => $now,
                            'paid_by_reference_no' => $reference_no,
                            'payment_method' => 'online',
                        ]);
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Payment successful'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Payment not found'
        ], 404);
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
