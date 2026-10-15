@extends('layouts.app')

@section('content')

<style>
    /* Installment dashboard — consistent blue-and-white theme */
    .installment-page {
        --installment-blue: #32667e;
        --installment-blue-dark: #32667e;
        --installment-blue-soft: #eff6ff;
        --installment-border: #dbe5f1;
        --installment-text: #32667e;
        --installment-muted: #64748b;
        color: var(--installment-text);
    }

    .installment-page .installment-page-header {
        gap: 1rem;
    }

    .installment-page .installment-page-title {
        color: #32667e;
        font-weight: 700;
        letter-spacing: -0.025em;
    }

    .installment-page .card {
        border: 1px solid var(--installment-border);
        border-radius: 0.85rem;
        box-shadow: 0 4px 14px rgba(30, 64, 175, 0.055);
        overflow: hidden;
    }

    .installment-page .summary-card {
        position: relative;
        background: #fff;
        transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
    }

    .installment-page .summary-card:hover {
        border-color: #93c5fd;
        box-shadow: 0 8px 22px rgba(37, 99, 235, 0.09);
        transform: translateY(-2px);
    }

    .installment-page .summary-card::before {
        position: absolute;
        inset: 0 auto 0 0;
        width: 4px;
        background: var(--installment-blue);
        content: "";
    }

    .installment-page .summary-label {
        color: var(--installment-muted);
        font-size: .82rem;
        font-weight: 600;
        margin-bottom: .55rem;
    }

    .installment-page .summary-value {
        color: #32667e;
        font-size: clamp(1.45rem, 2vw, 1.9rem);
        font-weight: 750;
        letter-spacing: -.035em;
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .installment-page .card-header {
        background: var(--installment-blue-soft);
        border-bottom: 1px solid var(--installment-border);
        color: #32667e;
        padding: 1rem 1.15rem;
    }

    .installment-page .card-header h5 {
        font-weight: 700;
    }

    .installment-page .table {
        margin-bottom: 0;
        --bs-table-striped-bg: #f8fbff;
        --bs-table-hover-bg: #eff6ff;
    }

    .installment-page .table thead th {
        background: #eff6ff;
        border-bottom: 1px solid #cbdcf4;
        color: #32667e;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .035em;
        text-transform: uppercase;
        white-space: nowrap;
        padding: .85rem .8rem;
    }

    .installment-page .table tbody td {
        border-color: #e6edf7;
        padding: .8rem;
        vertical-align: middle;
    }

    .installment-page .btn {
        border-radius: .55rem;
        font-weight: 600;
        transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
    }

    .installment-page .btn-primary,
    .installment-page .btn-success {
        background-color: var(--installment-blue);
        border-color: var(--installment-blue);
        color: #fff;
    }

    .installment-page .btn-primary:hover,
    .installment-page .btn-success:hover {
        background-color: var(--installment-blue-dark);
        border-color: var(--installment-blue-dark);
        color: #fff;
    }

    .installment-page .btn-dark {
        background: #fff;
        border-color: #bfdbfe;
        color: #32667e;
    }

    .installment-page .btn-dark:hover {
        background: var(--installment-blue-soft);
        border-color: #93c5fd;
        color: #32667e;
    }

    .installment-page .btn-warning {
        background: #fff;
        border-color: #93c5fd;
        color: #32667e;
    }

    .installment-page .btn-warning:hover {
        background: #dbeafe;
        border-color: #60a5fa;
        color: #32667e;
    }

    .installment-page .btn-danger {
        background: #fff;
        border-color: #fecaca;
        color: #b91c1c;
    }

    .installment-page .btn-danger:hover {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #991b1b;
    }

    .installment-page .form-control,
    .installment-page .form-select,
    .installment-page select.form-control {
        border-color: #cbd5e1;
        border-radius: .55rem;
        min-height: 42px;
    }

    .installment-page .form-control:focus,
    .installment-page .form-select:focus,
    .installment-page select.form-control:focus {
        border-color: #60a5fa;
        box-shadow: 0 0 0 .2rem rgba(37, 99, 235, .13);
    }

    .installment-page .form-label {
        color: #334155;
        font-size: .88rem;
        font-weight: 650;
    }

    .installment-page .input-group .form-control {
        border-radius: .55rem 0 0 .55rem;
    }

    .installment-page .input-group .btn {
        border-radius: 0 .55rem .55rem 0;
    }

    .installment-page .modal-content {
        border: 1px solid var(--installment-border);
        border-radius: .95rem;
        box-shadow: 0 18px 55px rgba(15, 23, 42, .18);
        overflow: hidden;
    }

    .installment-page .modal-header {
        background: var(--installment-blue-soft);
        border-bottom-color: var(--installment-border);
        color: #32667e;
        padding: 1rem 1.25rem;
    }

    .installment-page .modal-title {
        font-weight: 750;
    }

    .installment-page .modal-footer {
        background: #f8fbff;
        border-top-color: var(--installment-border);
        gap: .5rem;
    }

    .installment-page .alert {
        border-radius: .7rem;
        border-width: 1px;
    }

    .installment-page .alert-warning {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #32667e;
    }

    .installment-page .pagination {
        gap: .25rem;
        flex-wrap: wrap;
    }

    .installment-page .page-link {
        border-color: #dbe5f1;
        border-radius: .45rem;
        color: #32667e;
        margin: 0;
    }

    .installment-page .page-item.active .page-link {
        background: var(--installment-blue);
        border-color: var(--installment-blue);
        color: #fff;
    }

    .installment-page .status-completed {
        background: #eaf7ef;
        border: 1px solid #b9e3c8;
        border-radius: 999px;
        color: #17633f;
        display: inline-flex;
        align-items: center;
        font-size: .75rem;
        font-weight: 700;
        padding: .3rem .6rem;
        white-space: nowrap;
    }

    .installment-page .status-active {
        background: transparent;
        border: 0;
        border-radius: 0;
        color: #17633f;
        display: inline-flex;
        align-items: center;
        font-size: .75rem;
        font-weight: 700;
        padding: 0;
        white-space: nowrap;
    }

    .installment-page .summary-status-value {
        color: #198754;
    }

    .installment-page .status-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #198754;
        display: inline-block;
        margin-right: .4rem;
    }

    .installment-page .table-responsive {
        border-radius: .55rem;
    }

    @media (max-width: 767.98px) {
        .installment-page.container {
            margin-top: 1rem !important;
            padding-left: .85rem;
            padding-right: .85rem;
        }

        .installment-page .installment-page-header {
            align-items: flex-start !important;
            flex-direction: column;
        }

        .installment-page .installment-page-header .header-actions {
            display: flex;
            width: 100%;
        }

        .installment-page .installment-page-header .header-actions .btn {
            flex: 1;
        }

        .installment-page .summary-value {
            font-size: 1.45rem;
        }

        .installment-page .modal-dialog {
            margin: .65rem;
        }

        .installment-page .table tbody td {
            white-space: nowrap;
        }
    }
</style>

<div class="container mt-4 installment-page">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 installment-page-header">
        <div>
            <h4 class="mb-1 installment-page-title">Installment Dashboard</h4>
            <p class="mb-0 text-muted small">Manage installment accounts, schedules, and payment arrangements.</p>
        </div>

        <div class="d-flex gap-2 header-actions">
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#installmentHistoryModal">
                View History
            </button>

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#installmentModal">
                Create Installment
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger shadow-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <!-- Summary Cards -->
    <div class="row g-3">

        <div class="col-md-3 col-6">
            <div class="card summary-card h-100">
                <div class="card-body p-3 p-lg-4">
                    <h6 class="summary-label">Total Installments</h6>
                    <h3 class="summary-value mb-0">{{ $totalInstallments }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="card summary-card h-100">
                <div class="card-body p-3 p-lg-4">
                    <h6 class="summary-label">Active</h6>
                    <h3 class="summary-value summary-status-value mb-0">{{ $activeInstallments }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="card summary-card h-100">
                <div class="card-body p-3 p-lg-4">
                    <h6 class="summary-label">Completed</h6>
                    <h3 class="summary-value summary-status-value mb-0">{{ $completedInstallments }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-6">
            <div class="card summary-card h-100">
                <div class="card-body p-3 p-lg-4">
                    <h6 class="summary-label">Monthly Collectible</h6>
                    <h3 class="summary-value mb-0">₱ {{ number_format($monthlyCollectible, 2) }}</h3>
                </div>
            </div>
        </div>

    </div>

    <!-- Installment Table -->
    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Installment Accounts</h5>
            <span class="small text-muted">Account overview and available actions</span>
        </div>

        <div class="card-body p-3 p-lg-4">

            <div class="table-responsive">

                <table class="table table-bordered table-striped table-hover align-middle">

                    <thead>
                        <tr>
                            <th>Account</th>
                            <th>Name</th>
                            <th>Bill Ref</th>
                            <th>Total Bill</th>
                            <th>Monthly</th>
                            <th>Months</th>
                            <th>Status</th>
                            <th width="190">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($installments as $installment)

                        <tr>
                            <td>{{ $installment->bill->reading->account_no ?? '-' }}</td>

                            <td>{{ $installment->bill->reading->concessionaire->user->name ?? '-' }}</td>

                            <td>{{ $installment->bill->reference_no }}</td>

                            <td>₱ {{ number_format($installment->bill_amount, 2) }}</td>

                            <td>₱ {{ number_format($installment->monthly_amount, 2) }}</td>

                            <td>{{ $installment->months }}</td>

                            <td>
                                @if($installment->status === 'active')
                                    <span class="status-active"><span class="status-dot"></span>Active</span>
                                @else
                                    <span class="status-completed">Completed</span>
                                @endif
                            </td>

                            <td>
                                @php
                                    $firstDueDate = optional($installment->schedules->sortBy('month_no')->first())->due_date;
                                @endphp

                                <div class="d-flex gap-1">
                                    <button type="button"
                                            class="btn btn-primary btn-sm px-2 py-1 view-installment"
                                            data-installment="{{ $installment->id }}">
                                        View
                                    </button>

                                    <button type="button"
                                            class="btn btn-warning btn-sm px-2 py-1 edit-installment"
                                            data-action="{{ route('installment.update', $installment->id) }}"
                                            data-reference="{{ $installment->bill->reference_no }}"
                                            data-months="{{ $installment->months }}"
                                            data-first-due-date="{{ $firstDueDate ? \Carbon\Carbon::parse($firstDueDate)->format('Y-m-d') : now()->format('Y-m-d') }}"
                                            data-bill-amount="{{ $installment->bill_amount }}">
                                        Edit
                                    </button>

                                    <button type="button"
                                            class="btn btn-danger btn-sm px-2 py-1 delete-installment"
                                            data-action="{{ route('installment.destroy', $installment->id) }}"
                                            data-reference="{{ $installment->bill->reference_no }}">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>

                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                No installment records found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>

                </table>

            </div>

            <!-- Pagination -->
            <div class="mt-3">
                {{ $installments->links() }}
            </div>

        </div>
    </div>

</div>

<div class="installment-page">
<div class="modal fade" id="installmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">

            <form method="POST" action="{{ route('installment.store') }}">
            @csrf

            <div class="modal-header">
                <h5 class="modal-title">Create Installment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <label class="form-label">Account Number</label>

                    <div class="input-group">

                        <input type="text"
                            id="search_keyword"
                            class="form-control"
                            placeholder="Enter Name or Account Number">

                        <button type="button"
                                class="btn btn-primary"
                                id="search_account">
                            Search
                        </button>

                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Select Bill</label>

                    <select name="bill_id"
                            id="bill_select"
                            class="form-control"
                            required>
                        <option value="">Search account first</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Months</label>

                    <input type="number"
                        name="months"
                        class="form-control"
                        id="months"
                        min="1"
                        required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Monthly Payment</label>

                    <input type="text"
                        class="form-control"
                        id="monthly_amount"
                        readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Reason / Remarks</label>
                    <textarea name="reason" class="form-control" rows="2" placeholder="Optional remarks"></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-primary">Save Installment</button>
            </div>

            </form>

        </div>
    </div>
</div>

<div class="modal fade" id="editInstallmentModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="editInstallmentForm" class="modal-content">
            @csrf
            @method('PUT')

            <div class="modal-header">
                <h5 class="modal-title">Edit Installment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Bill Reference</label>
                    <input type="text" id="edit_reference" class="form-control" disabled>
                </div>

                <div class="mb-3">
                    <label class="form-label">Months</label>
                    <input type="number" name="months" id="edit_months" class="form-control" min="1" max="60" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">First Due Date</label>
                    <input type="date" name="first_due_date" id="edit_first_due_date" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Monthly Payment</label>
                    <input type="text" id="edit_monthly_amount" class="form-control" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>

                <div class="alert alert-warning mb-0">
                    Editing is allowed only while no installment schedule has been paid.
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-primary">Save Changes</button>
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="deleteInstallmentModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="deleteInstallmentForm" class="modal-content">
            @csrf
            @method('DELETE')

            <div class="modal-header">
                <h5 class="modal-title">Delete Installment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p class="mb-2">
                    Delete installment for <strong id="delete_reference"></strong>?
                </p>

                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>

                <div class="alert alert-danger mb-0">
                    This will delete unpaid schedules and restore the bill. Deleting is allowed only before any installment payment is made.
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-danger">Delete Installment</button>
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="viewInstallmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Installment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div id="installmentDetails">

                    <div class="row g-3 mb-3">
                        <div class="col-md-6"><div class="p-3 rounded-3" style="background:#eff6ff;border:1px solid #dbeafe;"><small class="text-muted d-block">Account No.</small><strong id="detail_account"></strong></div></div>
                        <div class="col-md-6"><div class="p-3 rounded-3" style="background:#eff6ff;border:1px solid #dbeafe;"><small class="text-muted d-block">Name</small><strong id="detail_name"></strong></div></div>
                        <div class="col-md-6"><div class="p-3 rounded-3" style="background:#eff6ff;border:1px solid #dbeafe;"><small class="text-muted d-block">Bill Reference</small><strong id="detail_reference"></strong></div></div>
                        <div class="col-md-6"><div class="p-3 rounded-3" style="background:#eff6ff;border:1px solid #dbeafe;"><small class="text-muted d-block">Total Bill</small><strong>₱<span id="detail_bill_amount"></span></strong></div></div>
                        <div class="col-md-6"><div class="p-3 rounded-3" style="background:#eff6ff;border:1px solid #dbeafe;"><small class="text-muted d-block">Monthly</small><strong>₱<span id="detail_monthly"></span></strong></div></div>
                        <div class="col-md-6"><div class="p-3 rounded-3" style="background:#eff6ff;border:1px solid #dbeafe;"><small class="text-muted d-block">Installment Span</small><strong id="detail_span"></strong></div></div>
                    </div>

                    <hr>

                    <h6 class="mb-3" style="color:#1e3a8a;font-weight:700;">Installment Schedule</h6>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="schedule_table"></tbody>
                        </table>
                    </div>

                </div>

            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="installmentHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Installment History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Action</th>
                                <th>Account</th>
                                <th>Bill Ref</th>
                                <th>Old</th>
                                <th>New</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($installmentAdjustments as $history)
                                @php
                                    $oldMonths = data_get($history->old_data, 'installment.months');
                                    $oldMonthly = data_get($history->old_data, 'installment.monthly_amount');
                                    $newMonths = data_get($history->new_data, 'installment.months');
                                    $newMonthly = data_get($history->new_data, 'installment.monthly_amount');
                                @endphp
                                <tr>
                                    <td>{{ optional($history->created_at)->format('M d, Y h:i A') }}</td>
                                    <td>
                                        @php
                                            $badgeClass = match(strtolower($history->action)) {
                                                'created' => 'bg-primary',
                                                'updated' => 'bg-primary',
                                                'deleted' => 'bg-danger',
                                                'restored' => 'bg-primary',
                                                'approved' => 'bg-primary',
                                                'rejected' => 'bg-dark',
                                                default => 'bg-secondary',
                                            };
                                        @endphp

                                        <span class="badge {{ $badgeClass }} text-uppercase">
                                            {{ $history->action }}
                                        </span>
                                    </td>
                                    <td>{{ $history->bill->reading->account_no ?? '-' }}</td>
                                    <td>{{ $history->bill->reference_no ?? '-' }}</td>
                                    <td>
                                        @if($oldMonths)
                                            {{ $oldMonths }} mos / ₱{{ number_format((float) $oldMonthly, 2) }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @if($newMonths)
                                            {{ $newMonths }} mos / ₱{{ number_format((float) $newMonthly, 2) }}
                                        @elseif($history->action === 'deleted')
                                            Deleted
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $history->reason ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No installment history found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

@endsection

<script>
document.addEventListener("DOMContentLoaded", function () {

    const monthsInput = document.getElementById('months');
    const billSelect = document.getElementById('bill_select');
    const monthlyInput = document.getElementById('monthly_amount');
    const searchBtn = document.getElementById('search_account');
    const editMonthsInput = document.getElementById('edit_months');
    const editMonthlyInput = document.getElementById('edit_monthly_amount');
    let editBillAmount = 0;

    if (monthsInput) {
        monthsInput.addEventListener('input', function () {
            const months = parseInt(this.value || 0);
            const selected = billSelect.options[billSelect.selectedIndex];

            if (!selected || !selected.dataset.amount) {
                monthlyInput.value = '';
                return;
            }

            const amount = parseFloat(selected.dataset.amount);

            monthlyInput.value = months > 0 ? (amount / months).toFixed(2) : '';
        });
    }

    if (billSelect) {
        billSelect.addEventListener('change', function () {
            monthsInput.dispatchEvent(new Event('input'));
        });
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', function () {
            const keyword = document.getElementById('search_keyword').value.trim();

            if (keyword === '') {
                alert('Please enter an account number or customer name.');
                return;
            }

            billSelect.disabled = true;
            billSelect.innerHTML = '<option value="">Loading bills...</option>';

            fetch(`/admin/installment/bills-by-account?search=${encodeURIComponent(keyword)}`)
                .then(response => {
                    if (!response.ok) throw new Error('Failed to load bills.');
                    return response.json();
                })
                .then(data => {
                    billSelect.disabled = false;
                    billSelect.innerHTML = '';

                    if (!Array.isArray(data) || data.length === 0) {
                        billSelect.innerHTML = '<option value="">No unpaid bills found</option>';
                        monthlyInput.value = '';
                        return;
                    }

                    billSelect.innerHTML = '<option value="">Select Bill</option>';

                    data.forEach(bill => {
                        const today = new Date();
                        const dueDate = new Date(bill.due_date);

                        const rawAmount = (today <= dueDate) ? bill.total : bill.amount_after_due;
                        let amount = parseFloat(rawAmount || 0) - parseFloat(bill.partial_payment || 0);

                        if (!Number.isFinite(amount) || amount < 0) amount = 0;

                        const date = new Date(bill.bill_period_to);
                        const monthName = Number.isNaN(date.getTime())
                            ? ''
                            : date.toLocaleString('en-US', { month: 'long', year: 'numeric' });

                        const option = document.createElement('option');
                        option.value = bill.id;
                        option.dataset.amount = amount.toFixed(2);
                        option.textContent = `${bill.account_no} - ${bill.name} | ${monthName} | ₱${amount.toFixed(2)}`;
                        billSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error(error);
                    billSelect.disabled = false;
                    billSelect.innerHTML = '<option value="">Error loading bills</option>';
                    monthlyInput.value = '';
                });
        });
    }

    document.addEventListener('click', function (e) {
        const editButton = e.target.closest('.edit-installment');
        const deleteButton = e.target.closest('.delete-installment');
        const viewButton = e.target.closest('.view-installment');

        if (editButton) {
            editBillAmount = parseFloat(editButton.dataset.billAmount || 0);

            document.getElementById('editInstallmentForm').action = editButton.dataset.action;
            document.getElementById('edit_reference').value = editButton.dataset.reference;
            document.getElementById('edit_months').value = editButton.dataset.months;
            document.getElementById('edit_first_due_date').value = editButton.dataset.firstDueDate;
            document.getElementById('edit_monthly_amount').value =
                (editBillAmount / parseInt(editButton.dataset.months || 1)).toFixed(2);

            bootstrap.Modal.getOrCreateInstance(document.getElementById('editInstallmentModal')).show();
            return;
        }

        if (deleteButton) {
            document.getElementById('deleteInstallmentForm').action = deleteButton.dataset.action;
            document.getElementById('delete_reference').textContent = deleteButton.dataset.reference;

            bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteInstallmentModal')).show();
            return;
        }

        if (viewButton) {
            const id = viewButton.dataset.installment;
            const detailsUrlTemplate = @json(route('installment.details', ':id'));
            const url = detailsUrlTemplate.replace(':id', id);

            fetch(url)
                .then(response => {
                    if (!response.ok) throw new Error('Failed to load installment details.');
                    return response.json();
                })
                .then(data => {
                    document.getElementById('detail_account').textContent = data.account_no ?? '-';
                    document.getElementById('detail_name').textContent = data.name ?? '-';
                    document.getElementById('detail_reference').textContent = data.reference_no ?? '-';
                    document.getElementById('detail_bill_amount').textContent = parseFloat(data.bill_amount || 0).toFixed(2);
                    document.getElementById('detail_monthly').textContent = parseFloat(data.monthly_amount || 0).toFixed(2);

                    const schedules = Array.isArray(data.schedules) ? data.schedules : [];
                    const spanElement = document.getElementById('detail_span');

                    if (schedules.length) {
                        const first = new Date(schedules[0].due_date);
                        const last = new Date(schedules[schedules.length - 1].due_date);
                        const firstMonth = first.toLocaleString('en-US', { month: 'long', year: 'numeric' });
                        const lastMonth = last.toLocaleString('en-US', { month: 'long', year: 'numeric' });

                        spanElement.textContent = `${schedules.length} Months (${firstMonth} - ${lastMonth})`;
                    } else {
                        spanElement.textContent = 'No schedule available';
                    }

                    const scheduleTable = document.getElementById('schedule_table');
                    scheduleTable.replaceChildren();

                    schedules.forEach(row => {
                        const tr = document.createElement('tr');
                        const monthCell = document.createElement('td');
                        const amountCell = document.createElement('td');
                        const statusCell = document.createElement('td');

                        const dueDate = new Date(row.due_date);
                        const monthName = Number.isNaN(dueDate.getTime())
                            ? ''
                            : dueDate.toLocaleString('en-US', { month: 'long', year: 'numeric' });

                        monthCell.textContent = `${row.month_no} - ${monthName}`;
                        amountCell.textContent = `₱${parseFloat(row.amount || 0).toFixed(2)}`;

                        const statusBadge = document.createElement('span');
                        statusBadge.className = row.is_paid ? 'badge bg-primary' : 'badge bg-light text-primary border';
                        statusBadge.textContent = row.is_paid ? 'Paid' : 'Pending';
                        statusCell.appendChild(statusBadge);

                        tr.append(monthCell, amountCell, statusCell);
                        scheduleTable.appendChild(tr);
                    });

                    bootstrap.Modal.getOrCreateInstance(document.getElementById('viewInstallmentModal')).show();
                })
                .catch(error => {
                    console.error(error);
                    alert('Unable to load installment details. Please try again.');
                });
        }
    });

    if (editMonthsInput) {
        editMonthsInput.addEventListener('input', function () {
            const months = parseInt(this.value || 0);
            editMonthlyInput.value = months > 0 ? (editBillAmount / months).toFixed(2) : '';
        });
    }

});
</script>
