@extends('layouts.app')

@section('content')

<div class="container mt-4">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">Installment Dashboard</h4>

        <div class="d-flex gap-2">
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

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted">Total Installments</h6>
                    <h3 class="mb-0">{{ $totalInstallments }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted">Active</h6>
                    <h3 class="text-warning mb-0">{{ $activeInstallments }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted">Completed</h6>
                    <h3 class="text-success mb-0">{{ $completedInstallments }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h6 class="text-muted">Monthly Collectible</h6>
                    <h3 class="mb-0">₱ {{ number_format($monthlyCollectible, 2) }}</h3>
                </div>
            </div>
        </div>

    </div>

    <!-- Installment Table -->
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="mb-0">Installment Accounts</h5>
        </div>

        <div class="card-body">

            <div class="table-responsive">

                <table class="table table-bordered table-striped align-middle">

                    <thead class="table-light">
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
                                    <span class="badge bg-warning text-dark">Active</span>
                                @else
                                    <span class="badge bg-success">Completed</span>
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
                            <td colspan="8" class="text-center text-muted">
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
                <button class="btn btn-success">
                    Save Installment
                </button>
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
                <button class="btn btn-success">Save Changes</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
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

                    <p><strong>Account No:</strong> <span id="detail_account"></span></p>
                    <p><strong>Name:</strong> <span id="detail_name"></span></p>
                    <p><strong>Bill Ref:</strong> <span id="detail_reference"></span></p>
                    <p><strong>Total Bill:</strong> ₱<span id="detail_bill_amount"></span></p>
                    <p><strong>Monthly:</strong> ₱<span id="detail_monthly"></span></p>
                    <p><strong>Installment Span:</strong> <span id="detail_span"></span></p>

                    <hr>

                    <h6>Installment Schedule</h6>

                    <table class="table table-bordered">
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
                        <thead class="table-light">
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
                                                'created' => 'bg-success',
                                                'updated' => 'bg-warning text-dark',
                                                'deleted' => 'bg-danger',
                                                'restored' => 'bg-info text-dark',
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
                                    <td colspan="7" class="text-center text-muted">No installment history found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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

    if(monthsInput){
        monthsInput.addEventListener('input', function(){

            let months = this.value;
            let selected = billSelect.options[billSelect.selectedIndex];

            if(!selected || !selected.dataset.amount) return;

            let amount = selected.dataset.amount;

            if(months > 0){
                monthlyInput.value = (amount / months).toFixed(2);
            }

        });
    }

    if(searchBtn){
        searchBtn.addEventListener('click', function () {

        let keyword = document.getElementById('search_keyword').value.trim();

        if (keyword === '') {
            alert('Please enter an account number or customer name.');
            return;
        }

        billSelect.disabled = true;
        billSelect.innerHTML = `
            <option>Loading bills...</option>
        `;

        fetch(`/admin/installment/bills-by-account?search=${encodeURIComponent(keyword)}`)
            .then(response => response.json())
            .then(data => {

                billSelect.disabled = false;
                billSelect.innerHTML = '';

                if (data.length === 0) {
                    billSelect.innerHTML = `
                        <option value="">No unpaid bills found</option>
                    `;
                    return;
                }

                billSelect.innerHTML = `
                    <option value="">Select Bill</option>
                `;

                data.forEach(bill => {

                    const today = new Date();
                    const dueDate = new Date(bill.due_date);

                    let rawAmount = (today <= dueDate)
                        ? bill.total
                        : bill.amount_after_due;

                    let amount = rawAmount - bill.partial_payment;

                    if (amount < 0) amount = 0;

                    const date = new Date(bill.bill_period_to);

                    const monthName = date.toLocaleString('en-US', {
                        month: 'long'
                    });

                    billSelect.innerHTML += `
                        <option value="${bill.id}" data-amount="${amount}">
                            ${bill.account_no} - ${bill.name} | ${monthName} | ₱${amount.toFixed(2)}
                        </option>
                    `;
                });

            })
            .catch(error => {

                console.error(error);

                billSelect.disabled = false;
                billSelect.innerHTML = `
                    <option value="">Error loading bills</option>
                `;
            });

    });
    }

    document.addEventListener('click', function(e){
        const editButton = e.target.closest('.edit-installment');
        const deleteButton = e.target.closest('.delete-installment');

        if (editButton) {
            editBillAmount = parseFloat(editButton.dataset.billAmount || 0);

            document.getElementById('editInstallmentForm').action = editButton.dataset.action;
            document.getElementById('edit_reference').value = editButton.dataset.reference;
            document.getElementById('edit_months').value = editButton.dataset.months;
            document.getElementById('edit_first_due_date').value = editButton.dataset.firstDueDate;
            document.getElementById('edit_monthly_amount').value = (editBillAmount / parseInt(editButton.dataset.months || 1)).toFixed(2);

            new bootstrap.Modal(document.getElementById('editInstallmentModal')).show();
            return;
        }

        if (deleteButton) {
            document.getElementById('deleteInstallmentForm').action = deleteButton.dataset.action;
            document.getElementById('delete_reference').innerText = deleteButton.dataset.reference;

            new bootstrap.Modal(document.getElementById('deleteInstallmentModal')).show();
            return;
        }

        if(e.target.classList.contains('view-installment')){

            let id = e.target.dataset.installment;

            let url = "{{ route('installment.details', ':id') }}";
            url = url.replace(':id', id);

            fetch(url)
            .then(res => res.json())
            .then(data => {

                document.getElementById('detail_account').innerText = data.account_no;
                document.getElementById('detail_name').innerText = data.name ?? '-';
                document.getElementById('detail_reference').innerText = data.reference_no;
                document.getElementById('detail_bill_amount').innerText = parseFloat(data.bill_amount).toFixed(2);
                document.getElementById('detail_monthly').innerText = parseFloat(data.monthly_amount).toFixed(2);
                if (data.schedules.length) {

                    const first = new Date(data.schedules[0].due_date);
                    const last = new Date(data.schedules[data.schedules.length - 1].due_date);

                    const firstMonth = first.toLocaleString('en-US', {
                        month: 'long',
                        year: 'numeric'
                    });

                    const lastMonth = last.toLocaleString('en-US', {
                        month: 'long',
                        year: 'numeric'
                    });

                    document.getElementById('detail_span').innerText =
                        `${data.schedules.length} Months (${firstMonth} - ${lastMonth})`;

                }

                let scheduleTable = document.getElementById('schedule_table');
                scheduleTable.innerHTML = '';


                data.schedules.forEach(row => {
                    const dueDate = new Date(row.due_date);

                    const monthName = dueDate.toLocaleString('en-US', {
                        month: 'long',
                        year: 'numeric'
                    });

                    scheduleTable.innerHTML += `
                        <tr>
                            <td>${row.month_no} - ${monthName}</td>
                            <td>₱${parseFloat(row.amount).toFixed(2)}</td>
                            <td>
                                ${row.is_paid
                                    ? '<span class="badge bg-success">Paid</span>'
                                    : '<span class="badge bg-warning text-dark">Pending</span>'}
                            </td>
                        </tr>
                    `;

                });

                new bootstrap.Modal(
                    document.getElementById('viewInstallmentModal')
                ).show();

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

