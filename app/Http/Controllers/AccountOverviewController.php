<?php

namespace App\Http\Controllers;

use App\Services\ClientService;
use App\Services\UserService;
use App\Services\WaterService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Yajra\DataTables\Facades\DataTables;

class AccountOverviewController extends Controller
{

    public $clientService;
    public $waterService;

    public function __construct(ClientService $clientService, WaterService $waterService) {

        $this->middleware(function ($request, $next) {
            $method = $request->route()->getActionMethod(); // Use request object
    
            if (!in_array($method, ['show'])) {
                if (!Gate::any(['client'])) {
                    abort(403, 'Unauthorized');
                }
            }
    
            return $next($request);
        });
        
        $this->clientService = $clientService;
        $this->waterService = $waterService;
    }

    public function index()
    {

        $id = Auth::user()->id;

        $data = $this->clientService::getData($id);
        
        $statement = $this->waterService::getBills($data->meter_no ?? '') ?? [];

        return view('account-overview.index', compact('data', 'statement'));
    }

    public function show() {

        $id = Auth::user()->id;

        $data = $this->clientService::getData($id);
        $statement = $this->waterService::getBills($data->meter_no ?? '', true);

        if(request()->ajax()) {
            return $this->datatable($statement);
        }

        return view('account-overview.show', compact('data'));

    }

    public function datatable($query)
    {
        return DataTables::of($query)
            ->addIndexColumn()
            ->editColumn('billing_period', function ($row) {
                return ($row->bill_period_from && $row->bill_period_to)
                    ? Carbon::parse($row->bill_period_from)->format('F d, Y') . ' TO ' . Carbon::parse($row->bill_period_to)->format('F d, Y')
                    : 'N/A';
            })
            ->editColumn('bill_date', function ($row) {
                return $row->bill_period_to ? Carbon::parse($row->bill_period_to)->format('F d, Y') : 'N/A';
            })
            ->editColumn('amount', function ($row) {
                return '₱' . number_format($row->amount ?? 0, 2);
            })
            ->editColumn('due_date', function ($row) {
                return $row->due_date ? Carbon::parse($row->due_date)->format('F d, Y') : 'N/A';
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
                        <a target="_blank" href="' . e(route('water-reading.show', $reference_no)) . '" 
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
