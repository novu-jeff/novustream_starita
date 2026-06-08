@extends('layouts.app')

@section('content')
<div class="container">

    <div class="d-flex justify-content-between mb-3 mt-4">
        <h4>Billing Adjustments</h4>
        <div class="d-flex justify-content-between mb-3">
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#billHistoryModal">
                View Adjustment History
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger shadow-sm">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <form method="GET" id="filterForm" class="card p-3 mb-3">
                <div class="row">

                    <div class="col-md-4">
                        <label>Search</label>
                        <input type="text" name="search" value="{{ request('search') }}"
                            class="form-control"
                            placeholder="Account No / Name / Reference No">
                    </div>

                    <div class="col-md-3">
                        <label>Month</label>
                        <input type="month" name="month"
                            id="monthFilter"
                            value="{{ request('month', $month ?? '') }}"
                            class="form-control">
                    </div>

                    <div class="col-md-5 d-flex align-items-end">
                        <button class="btn btn-primary me-2">Search</button>
                        <a href="{{ route('admins.billing-adjustments.index') }}" class="btn btn-secondary">Reset</a>
                    </div>

                </div>
            </form>
            <table class="table table-bordered">

                <thead>
                    <tr>
                        <th>Account</th>
                        <th>Name</th>
                        <th>Reference Number</th>
                        <th>Amount</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($bills as $bill)
                        <tr>
                            <td>{{ $bill->account_no }}</td>
                            <td>{{ $bill->concessioner_name ?? '-' }}</td>
                            <td>{{ $bill->reference_no }}</td>
                            <td>{{ $bill->amount }}</td>
                            <td>{{ $bill->total }}</td>
                            <td>
                                @if($bill->isPaid)
                                    <span class="badge bg-success">Paid</span>
                                @else
                                    <span class="badge bg-warning">Unpaid</span>
                                @endif
                            </td>
                            <td>
                                <button
                                    class="btn btn-sm btn-primary open-bill-modal"
                                    data-action="{{ route('admins.billing-adjustments.update', $bill->id) }}"
                                    data-bill-from="{{ $bill->bill_period_from }}"
                                    data-bill-to="{{ $bill->bill_period_to }}"
                                    data-prev="{{ $bill->previous_unpaid }}"
                                    data-basic-charge="{{ number_format(($bill->total ?? 0) - ($bill->previous_unpaid ?? 0), 2, '.', '') }}"
                                    data-total="{{ $bill->total }}"
                                    data-discount="{{ $bill->discount }}"
                                    data-penalty="{{ $bill->penalty }}"
                                    data-amount="{{ $bill->amount }}"
                                    data-after-due="{{ $bill->amount_after_due }}"
                                    data-amount-paid="{{ $bill->amount_paid }}"
                                    data-change="{{ $bill->change }}"
                                    data-partial="{{ $bill->partial_payment }}"
                                    data-advances="{{ $bill->advances }}"
                                    data-date-paid="{{ $bill->date_paid }}"
                                    data-due="{{ $bill->due_date }}"
                                    data-is-paid="{{ $bill->isPaid ? 1 : 0 }}"
                                    data-is-partial="{{ $bill->isPartial ? 1 : 0 }}"
                                    data-change-adv="{{ $bill->isChangeForAdvancePayment ? 1 : 0 }}"
                                >
                                    Edit
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>

            </table>
        </div>
    </div>

    {{ $bills->links() }}

</div>

<div class="modal fade" id="billModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" id="billForm" class="modal-content">
            @csrf
            @method('PUT')

            <div class="modal-header">
                <h5>Edit Bill</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <div class="row gx-2">
                        <div class="col-md-6 mb-2">
                            <label>Bill From</label>
                            <input type="text" name="bill_period_from" id="f_bill_from" class="form-control" readonly>
                        </div>

                        <div class="col-md-6 mb-2">
                            <label>Bill To</label>
                            <input type="text" name="bill_period_to" id="f_bill_to" class="form-control" readonly>
                        </div>
                    </div>
                </div>

                <div class="border rounded p-3 mb-3 bg-white shadow-sm">
                    <div class="row gx-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Previous Unpaid</label>
                            <input type="number" step="0.01" name="previous_unpaid" id="f_prev" class="form-control">
                        </div>

                        <div class="col-6">
                            <label class="form-label">Basic Charge</label>
                            <input type="number" step="0.01" name="basic_charge" id="f_basic_charge" class="form-control">
                        </div>
                    </div>

                    <div class="row gx-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Total</label>
                            <input type="number" step="0.01" name="total" id="f_total" class="form-control" readonly>
                        </div>

                        <div class="col-6">
                            <label class="form-label">Penalty</label>
                            <input type="number" step="0.01" name="penalty" id="f_penalty" class="form-control" readonly>
                        </div>
                    </div>

                    <div class="row gx-2">
                        <div class="col-6 mb-2">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" name="amount" id="f_amount" class="form-control" readonly>
                        </div>

                        <div class="col-6 mb-2">
                            <label class="form-label">Amount After Due</label>
                            <input type="number" step="0.01" name="amount_after_due" id="f_after_due" class="form-control" readonly>
                        </div>
                    </div>
                </div>

                <div class="row gx-2">
                    <div class="col-md-4 mb-2">
                        <label>Discount</label>
                        <input type="number" step="0.01" name="discount" id="f_discount" class="form-control">
                    </div>

                    <div class="col-md-4 mb-2">
                        <label>Amount Paid</label>
                        <input type="number" step="0.01" name="amount_paid" id="f_paid" class="form-control">
                    </div>

                    <div class="col-md-4 mb-2">
                        <label>Change</label>
                        <input type="number" step="0.01" name="change" id="f_change" class="form-control">
                    </div>

                    <div class="col-md-4 mb-2">
                        <label>Partial Payment</label>
                        <input type="number" step="0.01" name="partial_payment" id="f_partial" class="form-control">
                    </div>

                    <div class="col-md-4 mb-2">
                        <label>Advances</label>
                        <input type="number" step="0.01" name="advances" id="f_advances" class="form-control">
                    </div>

                    <div class="col-md-4 mb-2">
                        <label>Date Paid</label>
                        <input type="date" name="date_paid" id="f_date_paid" class="form-control">
                    </div>

                    <div class="col-md-4 mb-2">
                        <label>Due Date</label>
                        <input type="date" name="due_date" id="f_due" class="form-control">
                    </div>

                    <div class="col-md-4 mb-2">
                        <label>Is Paid</label>
                        <select name="isPaid" id="f_paid_status" class="form-control" required>
                            <option value="0" selected>No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-2">
                        <label>Is Partial</label>
                        <select name="isPartial" id="f_is_partial" class="form-control">
                            <option value="">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>

                    <div class="col-md-4 mb-2">
                        <label>Change for Advance</label>
                        <select name="isChangeForAdvancePayment" id="f_change_adv" class="form-control">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label>Reason</label>
                        <textarea name="reason" class="form-control" required></textarea>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Save</button>
            </div>

        </form>
    </div>
</div>

<div class="modal fade" id="billHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Bill Adjustment History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm">

                        <thead class="table-light">

                        </thead>

                        <tbody>
                        @forelse($billAdjustments as $a)

                            @php
                                $old = json_decode($a->old_data, true) ?? [];
                                $new = json_decode($a->new_data, true) ?? [];

                                // get all keys
                                $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
                            @endphp

                            <tr class="table-secondary adjustment-header" style="cursor: pointer;" data-adjustment-id="{{ $a->id }}">
                                <td colspan="7">
                                    <span class="toggle-icon">▼</span>
                                    <strong>{{ $a->created_at }}</strong> |
                                    {{ $a->account_no }} |
                                    {{ $a->concessioner_name ?? '-' }} |
                                    Ref: {{ $a->reference_no }}
                                    <br>
                                    <small class="text-muted">Reason: {{ $a->reason }}</small>
                                </td>
                            </tr>

                            @foreach($keys as $key)

                                @php
                                    $oldVal = $old[$key] ?? null;
                                    $newVal = $new[$key] ?? null;
                                @endphp

                                @if($oldVal != $newVal)
                                    <tr class="adjustment-detail" data-adjustment-id="{{ $a->id }}" style="display: none;">
                                        <td></td>
                                        <td colspan="2"><strong>{{ $key }}</strong></td>

                                        <td class="text-danger">
                                            {{ is_array($oldVal) ? json_encode($oldVal) : $oldVal }}
                                        </td>

                                        <td class="text-success">
                                            {{ is_array($newVal) ? json_encode($newVal) : $newVal }}
                                        </td>

                                        <td colspan="2"></td>
                                    </tr>
                                @endif

                            @endforeach

                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    No adjustment history found
                                </td>
                            </tr>
                        @endforelse
                        </tbody>

                    </table>
                </div>

            </div>

        </div>
    </div>
</div>

<script>

const round2 = (value) => Number.isFinite(value) ? Math.round(value * 100) / 100 : 0;
const formatMoney = (value) => round2(value).toFixed(2);

const refreshBillingCalculations = () => {
    const prev = parseFloat(document.getElementById('f_prev').value) || 0;
    const basicCharge = parseFloat(document.getElementById('f_basic_charge').value) || 0;
    const total = round2(prev + basicCharge);
    const penalty = round2(Math.max(0, basicCharge) * 0.10);
    const computedAmount = round2(total + penalty);

    document.getElementById('f_total').value = formatMoney(total);
    document.getElementById('f_penalty').value = formatMoney(penalty);
    document.getElementById('f_amount').value = formatMoney(computedAmount);
    document.getElementById('f_after_due').value = formatMoney(computedAmount);
};

document.addEventListener('input', function(e) {
    if (['f_prev', 'f_basic_charge'].includes(e.target.id)) {
        refreshBillingCalculations();
    }
});

// Bill adjustment history collapse/expand
document.addEventListener('click', function(e) {
    const header = e.target.closest('.adjustment-header');
    if (header) {
        const adjustmentId = header.dataset.adjustmentId;
        const details = document.querySelectorAll(`.adjustment-detail[data-adjustment-id="${adjustmentId}"]`);
        const toggleIcon = header.querySelector('.toggle-icon');
        const isCollapsed = details[0]?.style.display === 'none';

        details.forEach(row => {
            row.style.display = isCollapsed ? 'table-row' : 'none';
        });

        toggleIcon.textContent = isCollapsed ? '▼' : '▶';
    }
});

document.addEventListener('click', function(e) {

    if (e.target.classList.contains('open-bill-modal')) {

        let b = e.target.dataset;

        document.getElementById('f_bill_from').value = b.billFrom || '';
        document.getElementById('f_bill_to').value = b.billTo || '';
        document.getElementById('f_prev').value = b.prev || 0;
        document.getElementById('f_basic_charge').value = b.basicCharge || 0;
        document.getElementById('f_total').value = b.total || 0;
        document.getElementById('f_discount').value = b.discount || 0;
        document.getElementById('f_penalty').value = b.penalty || 0;
        document.getElementById('f_amount').value = b.amount || 0;
        document.getElementById('f_after_due').value = b.afterDue || 0;
        document.getElementById('f_paid').value = b.amountPaid || null;
        document.getElementById('f_change').value = b.change || null;
        document.getElementById('f_partial').value = b.partial || null;
        document.getElementById('f_advances').value = b.advances || 0;
        document.getElementById('f_date_paid').value = b.datePaid || '';
        document.getElementById('f_due').value = b.due || '';

        document.getElementById('f_paid_status').value = b.isPaid;
        document.getElementById('f_is_partial').value = b.isPartial;
        document.getElementById('f_change_adv').value = b.changeAdv;

        document.getElementById('billForm').action = b.action;

        refreshBillingCalculations();
        new bootstrap.Modal(document.getElementById('billModal')).show();
    }

});

</script>

@endsection
