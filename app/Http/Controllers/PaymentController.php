<?php

namespace App\Http\Controllers;

use App\Services\GenerateService;
use App\Services\WaterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\Facades\DataTables;

class PaymentController extends Controller
{

    public $waterService;
    public $generateService;

    public function __construct(WaterService $waterService, GenerateService $generateService) {
        $this->waterService = $waterService;
        $this->generateService = $generateService;
    }

    public function index() {
        return redirect()->route('payments.show', ['payment' => 'unpaid']);
    }

    public function show(string $filter) {
        if (!in_array($filter, ['unpaid', 'paid'], true)) {
            return redirect()->route('payments.index');
        }
    
        $fil = $filter === 'paid';
    
        $data = $this->waterService::getPayments(null, $fil);
    
        if (request()->ajax()) {
            return $this->datatable($data);
        }
    
        return view('payments.index', compact('data', 'filter'));
    }

    public function pay(Request $request, string $reference_no) {

        if($request->getMethod() == 'POST') {
            $payload = $request->all();
            $this->processPayment($reference_no, $payload);    
        }

        $data = $this->waterService::getBill($reference_no);

        if(!$data) {
            return redirect()->route('payments.index');
        }

        if(!is_null($data['active_payment'])) {
            return redirect()->route('payments.pay', ['reference_no' => $data['active_payment']->reference_no]);
        }

        $url = route('water-reading.show', ['reference_no' => $reference_no]);

        $qr_code = $this->generateService::qr_code($url, 60);

        return view('payments.pay', compact('data', 'reference_no', 'qr_code'));

    }

    public function processPayment(string $reference_no, array $payload) {

        $data = $this->waterService::getBill($reference_no);

        $total = $data['current_bill']->amount;

        $validator = Validator::make($payload, [
            'payment_amount' => 'required|gte:' . $total
        ], [
            'payment_amount.gte' => 'Insufficient Payment Amount'
        ]);

        if($validator->fails()) {
            return redirect()->back()->with('alert', [
                'status' => 'error',
                'message' => $validator->errors()->first()
            ]);
        }

        $now = Carbon::now()->format('y-m-d H:i:s');

        $data['current_bill']->isPaid = true;
        $data['current_bill']->payor_name = $payload['payor'];
        $data['current_bill']->date_paid = $now;
        $data['current_bill']->save();

        if(!empty($data['unpaid_bills'])) {
            foreach($data['unpaid_bills'] as $unpaid_bills) {
                $unpaid_bills->payor_name = $payload['payor'];
                $unpaid_bills->date_paid = $now;
                $unpaid_bills->isPaid = true;
                $unpaid_bills->paid_by_reference_no = $reference_no;
                $unpaid_bills->save();
            }
        }
        return redirect()->back()->with('alert', [
            'status' => 'success',
            'message' => 'Water Bill Paid'
        ]);

    }

    public function datatable($query) {
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('billing_period', function ($row) {
                return ($row->bill_period_from && $row->bill_period_to) 
                    ? Carbon::parse($row->bill_period_from)->format('F d, Y') . ' TO ' . Carbon::parse($row->bill_period_to)->format('F d, Y')
                    : 'N/A';
            })
            ->editColumn('bill_date', function ($row) {
                return !empty($row->bill_period_to) 
                    ? Carbon::parse($row->bill_period_to)->format('F d, Y') 
                    : 'N/A';
            })
            ->editColumn('amount', function ($row) {
                return '₱' . number_format((float)($row->amount ?? 0), 2);
            })
            ->editColumn('due_date', function ($row) {
                return !empty($row->due_date) 
                    ? Carbon::parse($row->due_date)->format('F d, Y') 
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
                        <a target="_blank" href="' . route('water-reading.show', $row->reference_no) . '" 
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
