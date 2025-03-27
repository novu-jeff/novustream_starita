<?php

namespace App\Http\Controllers;

use App\Services\ClientService;
use App\Services\GenerateService;
use App\Services\MeterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Yajra\DataTables\Facades\DataTables;

class AccountOverviewController extends Controller
{

    public $clientService;
    public $meterService;
    public $generateService;

    public function __construct(ClientService $clientService, MeterService $meterService, GenerateService $generateService) {
        $this->clientService = $clientService;
        $this->meterService = $meterService;
        $this->generateService = $generateService;
    }

    public function index()
    {

        $my = Auth::user()->load('property_types');

        $id = $my->id;

        $data = $this->clientService::getData($id);
        
        $statement = $this->meterService::getBills($data->meter_serial_no ?? '') ?? [];

        return view('account-overview.index', compact('my', 'data', 'statement'));
    }

    public function bills(?string $reference_no = null) {

        $id = Auth::user()->id;

        if(!is_null($reference_no)) {
            
            $data = $this->meterService::getBill($reference_no);

            if(is_null($data)) {
                return redirect()->route('reading.index')->with('alert', [
                    'status' => 'error',
                    'message' => 'Bill Not Found'
                ]);
            }

            $url = route('account-overview.bills.reference_no', ['reference_no' => $reference_no]);

            $qr_code = $this->generateService::qr_code($url, 80);

            $isViewBill = true;

            return view('account-overview.bill', compact('isViewBill', 'data', 'reference_no', 'qr_code'));
        }

        $data = $this->clientService::getData($id);
        $statement = $this->meterService::getBills($data->meter_serial_no ?? '', true);

        if(request()->ajax()) {
            return $this->datatable($statement);
        }

        $isViewBill = false;

        return view('account-overview.bill', compact('isViewBill', 'data'));

    }

    public function datatable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('billing_period', function ($row) {
                return ($row->bill_period_from && $row->bill_period_to)
                    ? Carbon::parse($row->bill_period_from)->format('M d, Y') . ' TO ' . Carbon::parse($row->bill_period_to)->format('M d, Y')
                    : 'N/A';
            })
            ->editColumn('bill_date', function ($row) {
                return $row->bill_period_to ? Carbon::parse($row->bill_period_to)->format('M d, Y') : 'N/A';
            })
            ->editColumn('amount', function ($row) {
                return '₱' . number_format($row->amount ?? 0, 2);
            })
            ->editColumn('due_date', function ($row) {
                return $row->due_date ? Carbon::parse($row->due_date)->format('M d, Y') : 'N/A';
            })
            ->editColumn('status', function ($row) {
                return $row->isPaid
                    ? '<div class="alert alert-primary mb-0 py-1 px-2 text-center">Paid</div>'
                    : '<div class="alert alert-danger mb-0 py-1 px-2 text-center">Unpaid</div>';
            })
            ->addColumn('actions', function ($row) {
                $reference_no = $row->reference_no ?? null;
    
                if ($reference_no) {
                    return '<div class="d-flex align-items-center gap-2">
                        <a href="' . e(route('account-overview.bills.reference_no', $reference_no)) . '" 
                            class="btn btn-primary text-white text-uppercase fw-bold" 
                            id="show-btn" data-id="' . e($row->id) . '">
                            <i class="bx bx-receipt"></i>
                        </a>
                    </div>';
                }
                return '<span class="text-muted">No Reference</span>';
            })
            ->rawColumns(['status', 'actions'])
            ->make(true);
    }
    

}
