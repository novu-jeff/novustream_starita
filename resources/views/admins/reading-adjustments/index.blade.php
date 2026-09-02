@extends('layouts.app')

@section('content')
<div class="container">

    {{-- Header --}}
    <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
        <h4 class="mb-0">Reading Adjustments</h4>
        <div class="d-flex justify-content-between mb-3">
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#globalHistoryModal">
                View Adjustment History
            </button>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="card shadow-sm p-3 mb-3">
        <div class="row">

            <div class="col-md-4">
                <label class="form-label">Search</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       class="form-control" placeholder="Account No / Name">
            </div>

            <div class="col-md-3">
                <label class="form-label">Month</label>
                <input type="month" name="month"
                       value="{{ request('month', $month ?? '') }}"
                       class="form-control">
            </div>

            <div class="col-md-5 d-flex align-items-end">
                <button class="btn btn-primary me-2">Apply</button>
                <a href="{{ route('admins.reading-adjustments.index') }}"
                   class="btn btn-outline-secondary">Reset</a>

            </div>

        </div>
    </form>

    {{-- Success --}}
    @if(session('success'))
        <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
    @endif

    {{-- Table --}}
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th>Account</th>
                        <th>Name</th>
                        <th>Previous</th>
                        <th>Present</th>
                        <th>Consumption</th>
                        <th width="120">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($readings as $reading)
                        <tr>
                            <td class="fw-semibold">{{ $reading->account_no }}</td>
                            <td>{{ $reading->concessioner_name ?? '-' }}</td>
                            <td>{{ $reading->previous_reading }}</td>
                            <td>{{ $reading->present_reading }}</td>
                            <td>{{ $reading->consumption }}</td>
                            <td>
                                <button
                                    class="btn btn-sm btn-primary open-adjust-modal"
                                    data-id="{{ $reading->id }}"
                                    data-action="{{ route('admins.reading-adjustments.update', $reading->id) }}"
                                    data-account="{{ $reading->account_no }}"
                                    data-previous="{{ $reading->previous_reading }}"
                                    data-present="{{ $reading->present_reading }}"
                                    data-created-at="{{ $reading->created_at ? $reading->created_at->format('Y-m-d') : '' }}"
                                >
                                    Adjust
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No records found
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

    <div class="mt-3">
        {{ $readings->links() }}
    </div>

    @if(request('search') && $missingAccounts->isNotEmpty())
        <div class="card shadow-sm mt-4">
            <div class="card-header fw-semibold">
                Concessionaires Without Readings
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Account</th>
                            <th>Name</th>
                            <th>Sequence No.</th>
                            <th>Address</th>
                            <th width="150">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($missingAccounts as $account)
                            <tr>
                                <td class="fw-semibold">{{ $account->account_no }}</td>
                                <td>{{ $account->user->name ?? '-' }}</td>
                                <td>{{ $account->sequence_no ?? '-' }}</td>
                                <td>{{ $account->address ?? '-' }}</td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-success open-create-reading-modal"
                                        data-account="{{ $account->account_no }}"
                                        data-name="{{ $account->user->name ?? '-' }}"
                                    >
                                        Create Reading
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

</div>

{{-- MODAL --}}
<div class="modal fade" id="adjustModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" id="adjustForm" class="modal-content">
            @csrf
            @method('PUT')

            <div class="modal-header">
                <h5 class="modal-title">Adjust Reading</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="mb-2">
                    <label>Account No</label>
                    <input type="text" id="modal_account" class="form-control" disabled>
                </div>

                <div class="mb-2">
                    <label>Previous Reading</label>
                    <div class="input-group">
                        <input type="text" id="modal_previous" class="form-control" disabled>
                        <div class="input-group-text">
                            <input type="checkbox" id="check_edit_previous" title="Check to edit previous reading">
                        </div>
                    </div>
                </div>

                <div class="mb-2" id="edit_previous_section" style="display: none;">
                    <label>New Previous Reading</label>
                    <input type="number" step="0.01" name="new_previous_reading"
                           id="modal_new_previous" class="form-control">
                </div>

                <div class="mb-2">
                    <label>Current Present</label>
                    <input type="text" id="modal_current" class="form-control" disabled>
                </div>

                <div class="mb-2">
                    <label>New Present Reading</label>
                    <input type="number" step="0.01" name="present_reading"
                           id="modal_present" class="form-control" required>
                </div>

                <div class="mb-2">
                    <label>Consumption</label>
                    <input type="text" id="modal_consumption"
                           class="form-control bg-light" readonly>
                </div>

                <div class="mb-3 d-flex justify-content-center">
                    <button
                        type="button"
                        class="btn border border-danger text-danger bg-danger-subtle w-50 mt-2 fw-bold"
                        id="btn_reset_readings">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Reset Both Readings to 0
                    </button>
                </div>

                <div class="mb-2">
                    <label>Date & Time</label>
                    <input
                        type="date"
                        name="reading_datetime"
                        id="modal_reading_datetime"
                        class="form-control"
                        required
                    >
                </div>

                <div class="mb-2">
                    <label>Reason</label>
                    <textarea name="reason" class="form-control" required></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-success">Save Adjustment</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>

        </form>
    </div>
</div>

<div class="modal fade" id="createReadingModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admins.reading-adjustments.create-initial') }}" class="modal-content">
            @csrf

            <div class="modal-header">
                <h5 class="modal-title">Create Initial Reading</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" name="account_no" id="create_account_no">

                <div class="mb-2">
                    <label>Account No</label>
                    <input type="text" id="create_account_display" class="form-control" disabled>
                </div>

                <div class="mb-2">
                    <label>Name</label>
                    <input type="text" id="create_name_display" class="form-control" disabled>
                </div>

                <div class="mb-2">
                    <label>Reading Date</label>
                    <input type="date" name="reading_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                </div>

                <div class="alert alert-info mb-0">
                    This will create a paid zero bill with previous, present, consumption, total, penalty, discount, amount, and amount after due set to 0.
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-success">Create Reading</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="globalHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">All Reading Adjustments</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm">

                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Account</th>
                                <th>Name</th>
                                <th>Reading Change</th>
                                <th>Consumption Change</th>
                                <th>Reason</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($allAdjustments as $a)
                                <tr>
                                    <td>{{ $a->created_at }}</td>
                                    <td>{{ $a->account_no }}</td>
                                    <td>{{ $a->concessioner_name ?? '-' }}</td>

                                    <td>
                                        <span class="text-danger">
                                            {{ $a->old_present_reading }}
                                        </span>
                                        →
                                        <span class="text-success">
                                            {{ $a->new_present_reading }}
                                        </span>
                                    </td>

                                    <td>
                                        {{ $a->old_consumption }}
                                        →
                                        {{ $a->new_consumption }}
                                    </td>

                                    <td>{{ $a->reason }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">
                                        No adjustment records
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

{{-- SCRIPT --}}
<script>

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('open-create-reading-modal')) {
        let btn = e.target;

        document.getElementById('create_account_no').value = btn.dataset.account;
        document.getElementById('create_account_display').value = btn.dataset.account;
        document.getElementById('create_name_display').value = btn.dataset.name;

        let modal = new bootstrap.Modal(document.getElementById('createReadingModal'));
        modal.show();
    }

    if (e.target.classList.contains('open-adjust-modal')) {

        let btn = e.target;

        let id = btn.dataset.id;
        let account = btn.dataset.account;
        let previous = parseFloat(btn.dataset.previous);
        let present = btn.dataset.present;

        document.getElementById('modal_account').value = account;
        document.getElementById('modal_previous').value = previous;
        document.getElementById('modal_current').value = present;

        document.getElementById('modal_present').value = '';
        document.getElementById('modal_new_previous').value = '';
        document.getElementById('modal_consumption').value = '';
        document.getElementById('check_edit_previous').checked = false;
        document.getElementById('edit_previous_section').style.display = 'none';
        document.getElementById('modal_reading_datetime').value = btn.dataset.createdAt || '';

        document.getElementById('adjustForm').action = btn.dataset.action;

        let modal = new bootstrap.Modal(document.getElementById('adjustModal'));
        modal.show();

        // Calculate consumption based on current settings
        const updateConsumption = () => {
            let newPresent = parseFloat(document.getElementById('modal_present').value) || 0;
            let newPrevious = parseFloat(document.getElementById('modal_new_previous').value);
            let usePrevious = newPrevious !== null && !isNaN(newPrevious) ? newPrevious : previous;

            let consumption = newPresent - usePrevious;

            document.getElementById('modal_consumption').value =
                consumption >= 0 ? consumption : 0;
        };

        document.getElementById('modal_present').oninput = updateConsumption;
        document.getElementById('modal_new_previous').oninput = updateConsumption;

        // Toggle previous reading edit field
        document.getElementById('check_edit_previous').onchange = function() {
            document.getElementById('edit_previous_section').style.display =
                this.checked ? 'block' : 'none';
            if (!this.checked) {
                document.getElementById('modal_new_previous').value = '';
            }
            updateConsumption();
        };

        // Reset both readings to 0
        document.getElementById('btn_reset_readings').onclick = function() {
            document.getElementById('modal_present').value = '0';
            document.getElementById('modal_new_previous').value = '0';
            document.getElementById('check_edit_previous').checked = true;
            document.getElementById('edit_previous_section').style.display = 'block';
            updateConsumption();
        };
    }
});

document.addEventListener('click', function(e) {

    if (e.target.classList.contains('open-history-modal')) {

        let id = e.target.dataset.id;
        let rows = adjustmentsData[id] || [];

        let tbody = document.getElementById('historyBody');
        tbody.innerHTML = '';

        if (!rows.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center text-muted">
                        No adjustment history
                    </td>
                </tr>
            `;
        } else {
            rows.forEach(r => {
                tbody.innerHTML += `
                    <tr>
                        <td>${r.created_at}</td>
                        <td>
                            <span class="text-danger">${r.old_present_reading}</span>
                            →
                            <span class="text-success">${r.new_present_reading}</span>
                        </td>
                        <td>
                            ${r.old_consumption} → ${r.new_consumption}
                        </td>
                        <td>${r.reason}</td>
                    </tr>
                `;
            });
        }

        let modal = new bootstrap.Modal(document.getElementById('historyModal'));
        modal.show();
    }

});
</script>

@endsection
