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
                        <label>Zone</label>
                        <select name="zone_id" class="form-select" required>
                            <option value="">Select Zone</option>
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
        <div class="card-header">Reading Date Schedule</div>
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
                            <td>{{ $rd->zone->zone }}</td>
                            <td>{{ \Carbon\Carbon::parse($rd->bill_period_from)->format('F j, Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($rd->bill_period_to)->format('F j, Y') }}</td>
                            <td>{{ \Carbon\Carbon::parse($rd->due_date)->format('F j, Y') }}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-outline-primary me-1"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editModal{{ $rd->id }}">
                                    Edit
                                </button>
                                <form action="{{ route('reading-dates.destroy', $rd->id) }}"
                                    method="POST"
                                    class="d-inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Delete this reading date?')">
                                        Delete
                                    </button>
                                </form>
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
@endsection
