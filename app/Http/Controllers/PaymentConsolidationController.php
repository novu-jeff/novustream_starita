<?php

namespace App\Http\Controllers;

use App\Exports\CashPaymentConsolidationExport;
use App\Services\CashPaymentConsolidationQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;

class PaymentConsolidationController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Gate::any(['admin', 'cashier'])) {
                abort(403, 'Unauthorized');
            }

            return $next($request);
        });
    }

    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $data = CashPaymentConsolidationQuery::build(
            $filters['period'],
            $filters['search'],
            $filters['from_date'],
            $filters['to_date']
        )
            ->paginate(10)
            ->withQueryString();

        return view('payments.consolidation', [
            'data' => $data,
            'cashier' => auth()->user()->name ?? 'NA',
            ...$filters,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $filename = 'cash-payment-consolidation-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(
            new CashPaymentConsolidationExport(
                $filters['period'],
                $filters['search'],
                $filters['from_date'],
                $filters['to_date']
            ),
            $filename
        );
    }

    public function receipt(int $billId)
    {
        $receipt = CashPaymentConsolidationQuery::findReceipt($billId);
        abort_if(!$receipt, 404);

        return view('payments.consolidation-receipt', [
            'receipt' => $receipt,
            'cashier' => auth()->user()->name ?? 'NA',
            'systemFee' => (float) config('payments.system_fee', 10),
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['sometimes', 'required', 'in:today,week,month,custom'],
            'search' => ['nullable', 'string', 'max:100'],
            'from_date' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom', 'before_or_equal:to_date'],
            'to_date' => ['nullable', 'date_format:Y-m-d', 'required_if:period,custom', 'after_or_equal:from_date'],
        ]);

        return [
            'period' => $validated['period'] ?? 'today',
            'search' => trim($validated['search'] ?? ''),
            'from_date' => $validated['from_date'] ?? null,
            'to_date' => $validated['to_date'] ?? null,
        ];
    }
}