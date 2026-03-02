@extends('layouts.app')

@section('content')
<div class="container py-4">

    <h4 class="fw-bold mb-4">Reading Date Management</h4>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <strong>Success!</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm mb-4">
        <div class="card-header">Set Reading Date</div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger shadow-sm">
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <form method="POST" action="{{ route('reading-dates.store') }}">
                @csrf

                <div class="row g-3">

                    <div class="col-md-3">
                        <label>From Zone</label>
                        <select name="from_zone_id" class="form-select">
                            <option value="">Select From</option>
                            @foreach($zones as $zone)
                                <option value="{{ $zone->id }}">
                                    {{ $zone->zone }} - {{ $zone->area }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>To Zone</label>
                        <select name="to_zone_id" class="form-select">
                            <option value="">Select To</option>
                            @foreach($zones as $zone)
                                <option value="{{ $zone->id }}">
                                    {{ $zone->zone }} - {{ $zone->area }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label>Bill Period From</label>
                        <input type="date" name="bill_period_from"
                               class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label>Bill Period To</label>
                        <input type="date" name="bill_period_to"
                               class="form-control" required>
                    </div>

                    <div class="col-md-3">
                        <label>Due Date</label>
                        <input type="date" name="due_date"
                               class="form-control" required>
                    </div>

                </div>

                <button class="btn btn-primary mt-3">
                    Save Reading Date
                </button>

            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
    <span>Reading Date Schedule</span>

    <button type="button"
        class="btn btn-sm btn-outline-danger"
        data-bs-toggle="modal"
        data-bs-target="#deleteAllModal"
        data-url="{{ route('reading-dates.destroy-all') }}">
        Delete All
    </button>
</div>
        <div class="card-body table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Zone</th>
                        <th>Period From</th>
                        <th>Period To</th>
                        <th>Due Date</th>
                        <th width="180" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($readingDates as $rd)
                        <tr>
                            <td>{{ $rd->zone->zone}} - {{$rd->zone->area}}</td>
                            <td>{{ \Carbon\Carbon::parse($rd->bill_period_from)->format('F j, Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($rd->bill_period_to)->format('F j, Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($rd->due_date)->format('F j, Y') }}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal{{ $rd->id }}">
                                    Edit
                                </button>
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteRowModal"
                                        data-url="{{ route('reading-dates.destroy', $rd->id) }}"
                                        data-zone="{{ $rd->zone->zone }} - {{ $rd->zone->area }}">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @foreach($readingDates as $rd)
            <div class="modal fade" id="editModal{{ $rd->id }}" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content shadow">

                        <form method="POST"
                            action="{{ route('reading-dates.update', $rd->id) }}">
                            @csrf
                            @method('PUT')

                            <div class="modal-header">
                                <h5 class="modal-title">Edit Reading Date</h5>
                                <button type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body">
                                <div class="row g-3">

                                    <div class="col-md-3">
                                        <label>Zone</label>
                                        <select name="zone_id"
                                                class="form-select"
                                                required>
                                            @foreach($zones as $zone)
                                                <option value="{{ $zone->id }}"
                                                    {{ $zone->id == $rd->zone_id ? 'selected' : '' }}>
                                                    {{ $zone->zone }} - {{ $zone->area }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Bill Period From</label>
                                        <input type="date"
                                            name="bill_period_from"
                                            value="{{ $rd->bill_period_from }}"
                                            class="form-control"
                                            required>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Bill Period To</label>
                                        <input type="date"
                                            name="bill_period_to"
                                            value="{{ $rd->bill_period_to }}"
                                            class="form-control"
                                            required>
                                    </div>

                                    <div class="col-md-3">
                                        <label>Due Date</label>
                                        <input type="date"
                                            name="due_date"
                                            value="{{ $rd->due_date }}"
                                            class="form-control"
                                            required>
                                    </div>

                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="submit"
                                        class="btn btn-primary">
                                    Update
                                </button>
                            </div>

                        </form>

                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

</div>
<div class="modal fade" id="deleteAllModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">

            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center"
                         style="width:40px;height:40px;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <h5 class="modal-title fw-semibold text-danger mb-0">
                        Delete All Reading Dates
                    </h5>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body pt-2">
                <p class="text-muted mb-0">
                    You are about to permanently delete
                    <strong class="text-danger">all reading date schedules</strong>.
                </p>
                <p class="small text-muted mt-2 mb-0">
                    This action cannot be undone.
                </p>
            </div>

            <div class="modal-footer border-0 pt-3">
                <form id="deleteAllForm"
                      action="{{ route('reading-dates.destroy-all') }}"
                      method="POST"
                      class="d-flex gap-2 ms-auto">

                    @csrf
                    @method('DELETE')

                    <button type="button"
                            class="btn btn-light border"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-danger px-4">
                        Delete All
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="deleteRowModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">

            <div class="modal-header border-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center"
                         style="width:40px;height:40px;">
                        <i class="bi bi-trash-fill"></i>
                    </div>
                    <h5 class="modal-title fw-semibold text-danger mb-0">
                        Confirm Deletion
                    </h5>
                </div>

                <button type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body pt-2">
                <p id="deleteRowMessage" class="text-muted mb-0"></p>
            </div>

            <div class="modal-footer border-0 pt-3">
                <form id="deleteRowForm"
                      method="POST"
                      class="d-flex gap-2 ms-auto">

                    @csrf
                    @method('DELETE')

                    <button type="button"
                            class="btn btn-light border"
                            data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit"
                            class="btn btn-danger px-4">
                        Delete
                    </button>
                </form>
            </div>

        </div>
    </div>
</div>

<script>
    function openDeleteModal(actionUrl, zoneName) {
        const form = document.getElementById('deleteForm');
        form.action = actionUrl;

        document.querySelector('#deleteConfirmModal .modal-body p')
            .innerHTML = `Are you sure you want to delete reading date for <strong>${zoneName}</strong>?`;

        const modal = new bootstrap.Modal(
            document.getElementById('deleteConfirmModal')
        );
        modal.show();
    }

    document.addEventListener('DOMContentLoaded', function () {

        const deleteModal = document.getElementById('deleteRowModal');

        deleteModal.addEventListener('show.bs.modal', function (event) {

            const button = event.relatedTarget;

            const url = button.getAttribute('data-url');
            const zone = button.getAttribute('data-zone');

            const form = document.getElementById('deleteRowForm');
            const message = document.getElementById('deleteRowMessage');

            form.action = url;
            message.innerHTML =
                `Are you sure you want to delete reading date for <strong>${zone}</strong>?`;
        });

    });
</script>
@endsection
