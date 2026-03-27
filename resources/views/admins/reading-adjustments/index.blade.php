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
                    <input type="text" id="modal_previous" class="form-control" disabled>
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
        document.getElementById('modal_consumption').value = '';

        document.getElementById('adjustForm').action = btn.dataset.action;

        let modal = new bootstrap.Modal(document.getElementById('adjustModal'));
        modal.show();

        document.getElementById('modal_present').oninput = function() {
            let newVal = parseFloat(this.value) || 0;
            let consumption = newVal - previous;

            document.getElementById('modal_consumption').value =
                consumption >= 0 ? consumption : 0;
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
