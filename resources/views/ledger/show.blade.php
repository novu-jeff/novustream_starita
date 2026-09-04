@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 mt-2">Statement of Account</h4>
            <p class="text-muted"><span>Account Name: <strong>{{ $user->name }}</strong></span><br /> Account Number: <strong>{{ $user->accounts->pluck('account_no')->implode(', ') }}</strong></p>
        </div>
        <div class="d-flex flex-row justify-content-between gap-2">
            <div class="text-end">
                <a href="{{route('concessionaires.index')}}" class="btn btn-outline-primary px-3 py-2 text-uppercase">Go Back</a>
            </div>
            <div class="text-end">
                <a href="{{route('admins.reading-adjustments.index')}}" class="btn btn-outline-primary px-3 py-2 text-uppercase">Change Readings</a>
            </div>
            <div class="text-end">
                <a href="{{route('admins.billing-adjustments.index')}}" class="btn btn-outline-primary px-3 py-2 text-uppercase">Change Billing</a>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-start border-primary border-4 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted uppercase small fw-bold">Total Debit</h6>
                    <h3 class="mb-0">PHP {{ number_format($bills->sum('computed_amount'), 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-success border-4 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted uppercase small fw-bold">Total Credit</h6>
                    <h3 class="mb-0 text-success">PHP {{ number_format($bills->sum('computed_paid'), 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-danger border-4 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted uppercase small fw-bold">Outstanding Balance</h6>
                    <h3 class="mb-0 text-danger">PHP {{ number_format($bills->sum('computed_balance'), 2) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-start mb-3">
        <form method="GET">
            <div class="input-group">
                <label class="input-group-text">Year</label>

                <select name="year" class="form-select" onchange="this.form.submit()">
                    @foreach($years as $year)
                        <option value="{{ $year }}"
                            {{ $selectedYear == $year ? 'selected' : '' }}>
                            {{ $year }}
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
        <div class="ms-3">
            <button type="button" class="btn btn-outline-primary" id="openLedgerMissingReading">
                + Add Missing Reading
            </button>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="ledgerTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="bills-tab" data-bs-toggle="tab"
                data-bs-target="#bills-pane" type="button" role="tab"
                aria-controls="bills-pane" aria-selected="true">
                Bills
                @if($bills->isNotEmpty())
                    <span class="badge bg-secondary ms-1">{{ $bills->count() }}</span>
                @endif
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="application-fees-tab" data-bs-toggle="tab"
                data-bs-target="#application-fees-pane" type="button" role="tab"
                aria-controls="application-fees-pane" aria-selected="false">
                Application Fees
                @if($applications->isNotEmpty())
                    <span class="badge bg-secondary ms-1">{{ $applications->count() }}</span>
                @endif
            </button>
        </li>
    </ul>

    <div class="tab-content" id="ledgerTabsContent">
        <div class="tab-pane fade show active" id="bills-pane" role="tabpanel" aria-labelledby="bills-tab" tabindex="0">
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-20">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Date / Reference</th>
                                <th>Due Date</th>
                                <th>Reading</th>
                                <th>Usage</th>
                                <th class="text-end">Debit</th>
                                <th class="text-end">Credit</th>
                                <th class="text-end">Balance</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $runningBalance = 0; @endphp
                            @forelse($bills as $bill)
                            @php $runningBalance += $bill->computed_balance; @endphp
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($bill->bill_period_to)->format('M d, Y') }}</div>
                                    <small class="text-muted">{{ $bill->reference_no }}</small>
                                </td>
                                <td>
                                    <span class="{{ \Carbon\Carbon::parse($bill->due_date)->isPast() && $bill->computed_status !== 'PAID' ? 'text-danger fw-bold' : '' }}">
                                        {{ \Carbon\Carbon::parse($bill->due_date)->format('m/d/Y') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        {{ number_format((float) optional($bill->reading)->present_reading, 0) }}
                                    </div>
                                    <small class="text-muted">
                                        Prev: {{ number_format((float) optional($bill->reading)->previous_reading, 0) }}
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">
                                        {{ number_format((float) optional($bill->reading)->consumption, 0) }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    <div>{{ number_format($bill->computed_amount, 2) }}</div>
                                    @if($bill->computed_arrears > 0)
                                        <small class="text-muted d-block" style="font-size: 0.75rem;">
                                            arrears: {{ number_format($bill->computed_arrears, 2) }}
                                        </small>
                                    @endif
                                    @if($bill->computed_due_date < \Carbon\Carbon::today())
                                        <small class="text-danger" style="font-size: 0.75rem;">
                                        Penalty: {{ number_format($bill->computed_penalty, 2) }}
                                        </small>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="{{ $bill->computed_paid > 0 ? 'text-success' : 'text-danger' }}">
                                        {{ number_format($bill->computed_paid ?? 0, 2) }}
                                    </div>
                                    @if($bill->partial_payment > 0)
                                        <small class="text-primary" style="font-size: 0.75rem;">
                                            Partial: {{ number_format($bill->partial_payment, 2) }}
                                        </small>
                                    @endif
                                </td>
                                <td class="text-end fw-bold">
                                    {{ number_format($bill->computed_balance, 2) }}
                                </td>
                                <td class="text-center">
                                    @if($bill->computed_status === 'PAID')
                                        <span class="badge rounded-pill bg-success-subtle text-success px-3">PAID</span>
                                    @elseif($bill->computed_status === 'PARTIAL')
                                        <span class="badge rounded-pill bg-warning-subtle text-warning px-3">PARTIAL</span>
                                    @elseif($bill->computed_status === 'OVERDUE')
                                        <span class="badge rounded-pill bg-danger-subtle text-danger px-3">OVERDUE</span>
                                    @else
                                        <span class="badge rounded-pill bg-warning-subtle text-warning px-3">UNPAID</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <form method="POST" class="delete-ledger-bill-form" action="{{ route('admins.billing-adjustments.destroy', $bill->id) }}">
                                            @csrf
                                            @method('DELETE')
                                            <input type="hidden" name="reason">
                                            <button type="submit" class="btn btn-danger btn-sm text-white fw-bold">
                                                <i class="bx bx-trash"></i>
                                            </button>
                                        </form>
                                        @if($bill->computed_status !== 'PAID')
                                            <a href="{{ route('payments.pay', ['reference_no' => $bill->reference_no]) }}"
                                            class="btn btn-primary btn-sm text-white fw-bold">
                                                <i class="bx bx-credit-card-alt"></i>
                                            </a>
                                        @else
                                            <a target="_blank"
                                            href="{{ route('reading.show', $bill->reference_no) }}"
                                            class="btn btn-success btn-sm text-white fw-bold">
                                                <i class="bx bx-receipt"></i>
                                            </a>
                                            <a target="_blank"
                                            href="{{ route('reading.orshow', $bill->reference_no) }}"
                                            class="btn btn-primary btn-sm text-white fw-bold">
                                                <i class="bx bx-receipt"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="9">
                                    <div class="text-uppercase text-center">No Bills Found</div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                        @if($bills->isNotEmpty())
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end fw-bold text-uppercase small">Total Outstanding:</td>
                                <td class="text-end fw-bold text-danger">{{ number_format($runningBalance, 2) }}</td>
                                @php
                                    $unpaidBills = $bills->where('isPaid', 0);
                                    $totalAdvances = $unpaidBills->sum('advances');
                                    $hasUnpaid = $unpaidBills->isNotEmpty();
                                @endphp
                                @if ($totalAdvances > 0 && $hasUnpaid)
                                    <td class="text-center fw-bold text-uppercase small">Advances:</td>
                                    <td class="text-center fw-bold text-primary">
                                        {{ number_format($totalAdvances, 2) }}
                                    </td>
                                @else
                                    <td></td>
                                    <td></td>
                                @endif
                            </tr>
                        </tfoot>
                        @endif
                    </table>
                </div>
            </div>
        </div>
        <div class="tab-pane fade" id="application-fees-pane" role="tabpanel" aria-labelledby="application-fees-tab" tabindex="0">
            <div class="card shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Application No</th>
                                <th>Type</th>
                                <th>Filed On</th>
                                <th class="text-end">Amount</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($applications as $app)
                                <tr>
                                    <td class="ps-3">{{ $app->application_no ?? 'N/A' }}</td>
                                    <td>{{ $app->application_type === 'Others' ? $app->application_type_other : $app->application_type }}</td>
                                    <td>{{ \Carbon\Carbon::parse($app->created_at)->format('M d, Y') }}</td>
                                    <td class="text-end">₱{{ number_format((float) $app->application_fee_amount, 2) }}</td>
                                    <td class="text-center">
                                        @if($app->application_fee_status === 'paid')
                                            <span class="badge rounded-pill bg-success-subtle text-success px-3">PAID</span>
                                        @else
                                            <span class="badge rounded-pill bg-warning-subtle text-warning px-3">{{ strtoupper($app->application_fee_status) }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($app->application_fee_status !== 'paid')
                                            <a href="{{ route('payments.application-fees.pay', $app->id) }}"
                                                class="btn btn-primary btn-sm text-white fw-bold">
                                                <i class="bx bx-credit-card-alt"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">
                                        <div class="text-uppercase text-center">No Application Fees Found</div>
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

<div class="modal fade" id="ledgerMissingReadingModal" tabindex="-1" aria-labelledby="ledgerMissingReadingLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form id="ledgerMissingReadingForm" class="modal-content">
            @csrf
            <input type="hidden" name="account_no" id="ledger_account_no">
            <input type="hidden" name="isReRead" value="false">
            <input type="hidden" name="is_missing_reading" value="1">

            <div class="modal-header">
                <h5 class="modal-title" id="ledgerMissingReadingLabel">Add Missing Reading</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Account No.</label>
                        <input type="text" id="ledger_account_display" class="form-control" readonly>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_reading_month" class="form-label">Reading Month <span class="text-danger">*</span></label>
                        <input type="date" name="reading_month" id="ledger_reading_month" class="form-control" required>
                    </div>

                    <div class="col-md-4">
                        <label for="ledger_previous_reading" class="form-label">Previous Reading <span class="text-danger">*</span></label>
                        <input type="number" name="previous_reading" id="ledger_previous_reading" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_present_reading" class="form-label">Present Reading <span class="text-danger">*</span></label>
                        <input type="number" name="present_reading" id="ledger_present_reading" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Consumption</label>
                        <input type="number" id="ledger_consumption" class="form-control" readonly>
                    </div>

                    <div class="col-12"><hr><h6>Bill Information</h6></div>
                    <div class="col-md-6">
                        <label for="ledger_bill_from" class="form-label">Bill From <span class="text-danger">*</span></label>
                        <input type="date" name="missing_bill_period_from" id="ledger_bill_from" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="ledger_bill_to" class="form-label">Bill To <span class="text-danger">*</span></label>
                        <input type="date" name="missing_bill_period_to" id="ledger_bill_to" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_previous_unpaid" class="form-label">Previous Unpaid <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_previous_unpaid" id="ledger_previous_unpaid" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_basic_charge" class="form-label">Basic Charge <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_basic_charge" id="ledger_basic_charge" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_discount" class="form-label">Discount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_discount" id="ledger_discount" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_penalty" class="form-label">Penalty <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_penalty" id="ledger_penalty" class="form-control" value="0" readonly required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_total" class="form-label">Total <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_total" id="ledger_total" class="form-control" value="0" readonly required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_amount" class="form-label">Amount <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_amount" id="ledger_amount" class="form-control" value="0" readonly required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_amount_after_due" class="form-label">Amount After Due <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_amount_after_due" id="ledger_amount_after_due" class="form-control" value="0" readonly required>
                    </div>

                    <div class="col-12"><hr><h6>Payment Information</h6></div>
                    <div class="col-md-4">
                        <label for="ledger_amount_paid" class="form-label">Amount Paid <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_amount_paid" id="ledger_amount_paid" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_change" class="form-label">Change <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_change" id="ledger_change" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_partial" class="form-label">Partial Payment <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_partial_payment" id="ledger_partial" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_advances" class="form-label">Advances <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="missing_advances" id="ledger_advances" class="form-control" value="0" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_date_paid" class="form-label">Date Paid</label>
                        <input type="date" name="missing_date_paid" id="ledger_date_paid" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_due_date" class="form-label">Due Date <span class="text-danger">*</span></label>
                        <input type="date" name="missing_due_date" id="ledger_due_date" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_is_paid" class="form-label">Is Paid <span class="text-danger">*</span></label>
                        <select name="missing_is_paid" id="ledger_is_paid" class="form-select" required><option value="0">No</option><option value="1">Yes</option></select>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_is_partial" class="form-label">Is Partial <span class="text-danger">*</span></label>
                        <select name="missing_is_partial" id="ledger_is_partial" class="form-select" required><option value="0">No</option><option value="1">Yes</option></select>
                    </div>
                    <div class="col-md-4">
                        <label for="ledger_is_advance" class="form-label">Change for Advance <span class="text-danger">*</span></label>
                        <select name="missing_is_change_for_advance" id="ledger_is_advance" class="form-select" required><option value="0">No</option><option value="1">Yes</option></select>
                    </div>
                    <div class="col-md-12">
                        <label for="ledger_high_consumption" class="form-label">High Consumption <span class="text-danger">*</span></label>
                        <select name="is_high_consumption" id="ledger_high_consumption" class="form-select" required><option value="no">No</option><option value="yes">Yes</option></select>
                    </div>
                    <div class="col-md-12">
                        <label for="ledger_reading_reason" class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="missing_reading_reason" id="ledger_reading_reason" class="form-control" rows="3" required></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-success">Create Missing Bill</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('script')
<script>
const ledgerReadingUrl = @json(route('reading.index'));

function updateLedgerMissingTotals() {
    const previousUnpaid = parseFloat($('#ledger_previous_unpaid').val()) || 0;
    const basicCharge = parseFloat($('#ledger_basic_charge').val()) || 0;
    const discount = parseFloat($('#ledger_discount').val()) || 0;
    const advances = parseFloat($('#ledger_advances').val()) || 0;
    const penalty = basicCharge * 0.10;
    const total = Math.max(basicCharge + previousUnpaid - discount - advances, 0);
    const amount = Math.max(basicCharge + previousUnpaid - discount + penalty - advances, 0);

    $('#ledger_penalty').val(penalty.toFixed(2));
    $('#ledger_total').val(total.toFixed(2));
    $('#ledger_amount').val(amount.toFixed(2));
    $('#ledger_amount_after_due').val(amount.toFixed(2));
}

$('#openLedgerMissingReading').on('click', function () {
    const accountNo = @json($user->accounts->first()->account_no ?? '');
    const today = new Date().toISOString().slice(0, 10);

    $('#ledger_account_no, #ledger_account_display').val(accountNo);
    $('#ledger_reading_month, #ledger_bill_from, #ledger_bill_to').val(today);
    $('#ledger_due_date').val('');
    $('#ledger_date_paid').val('');
    $('#ledger_reading_reason').val('');
    $('#ledgerMissingReadingForm input[name="is_missing_reading"]').val('1');

    $.get(ledgerReadingUrl, {
        account_no: accountNo,
        isGetPrevious: true,
        is_missing_reading: true,
        reading_month: today,
    }, function (response) {
        $('#ledger_previous_reading').val(response.previous_reading ?? 0);
        $('#ledger_previous_unpaid').val(Number(response.previous_unpaid ?? 0).toFixed(2));
        $('#ledger_present_reading').val('');
        $('#ledger_consumption').val('0');
        updateLedgerMissingTotals();
        new bootstrap.Modal(document.getElementById('ledgerMissingReadingModal')).show();
    });
});

$('#ledger_reading_month').on('change', function () {
    const accountNo = $('#ledger_account_no').val();
    if (!accountNo || !this.value) return;

    $.get(ledgerReadingUrl, {
        account_no: accountNo,
        isGetPrevious: true,
        is_missing_reading: true,
        reading_month: this.value,
    }, function (response) {
        $('#ledger_previous_reading').val(response.previous_reading ?? 0);
        $('#ledger_previous_unpaid').val(Number(response.previous_unpaid ?? 0).toFixed(2));
        updateLedgerMissingTotals();
    });
});

$('#ledger_present_reading, #ledger_previous_reading').on('input', function () {
    const present = parseFloat($('#ledger_present_reading').val()) || 0;
    const previous = parseFloat($('#ledger_previous_reading').val()) || 0;
    $('#ledger_consumption').val(Math.max(present - previous, 0));
});

$('#ledger_previous_unpaid, #ledger_basic_charge, #ledger_discount, #ledger_advances').on('input', updateLedgerMissingTotals);

$('#ledgerMissingReadingForm').on('submit', function (event) {
    event.preventDefault();
    const form = this;
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    Swal.fire({
        title: 'Create missing bill?',
        text: 'The reading and bill will be added to this account.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Create Bill',
        confirmButtonColor: '#198754',
    }).then(function (result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: ledgerReadingUrl,
            method: 'POST',
            data: $(form).serialize(),
            success: function () {
                Swal.fire({
                    icon: 'success',
                    title: 'Missing bill created',
                    text: 'The reading and bill were saved successfully.',
                    confirmButtonColor: '#198754',
                }).then(() => window.location.reload());
            },
            error: function (xhr) {
                Swal.fire({
                    icon: 'error',
                    title: 'Unable to create bill',
                    text: xhr.responseJSON?.message || 'Please check the form values and try again.',
                });
            },
        });
    });
});

document.querySelectorAll('.delete-ledger-bill-form').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        event.preventDefault();

        Swal.fire({
            title: 'Delete bill?',
            text: 'The bill, breakdowns, discounts, and linked reading will be deleted.',
            icon: 'warning',
            input: 'textarea',
            inputPlaceholder: 'Enter deletion reason',
            inputValidator: value => !value || !value.trim() ? 'A reason is required.' : undefined,
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#dc3545',
        }).then(function (result) {
            if (result.isConfirmed) {
                form.querySelector('input[name="reason"]').value = result.value.trim();
                form.submit();
            }
        });
    });
});

@if(session('success'))
Swal.fire({
    icon: 'success',
    title: 'Success',
    text: @json(session('success')),
    confirmButtonColor: '#198754',
});
@endif
</script>
@endsection
