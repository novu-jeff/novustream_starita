@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-0">Penalty Exemption Management</h4>
            <small class="text-muted">Manage accounts exempted from penalty computation</small>
        </div>
    </div>
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-light fw-semibold">
            Add New Penalty Exemption
        </div>
        <div class="card-body">
            <form action="{{ route('penalty-exemption.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Account No</label>
                        <input type="text" name="account_no" class="form-control" placeholder="e.g. 091-22-091130" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Exemption Type</label>
                        <select name="penalty_exemption_type_id" class="form-select" required>
                            <option value="">Select Type</option>
                            @foreach($types as $type)
                                <option value="{{ $type->id }}">
                                    {{ $type->penalty_exemption_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" required>Effective Date</label>
                        <input type="date" name="effective_date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label" required>Expired Date</label>
                        <input type="date" name="expired_date" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            Add Exemption
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-light fw-semibold">
            Exempted Accounts
        </div>
        <div class="card-body table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Account No</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Effective</th>
                        <th>Expired</th>
                        <th>Status</th>
                        <th class="text-center" width="160">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($exemptions as $exemption)
                    @php
                        $today = \Carbon\Carbon::today();
                        $isActive = !$exemption->expired_date ||
                                    \Carbon\Carbon::parse($exemption->expired_date)->gte($today);
                    @endphp
                    <tr>
                        <td class="fw-semibold">{{ $exemption->account_no }}</td>
                        <td>{{ optional(optional($exemption->account)->user)->name ?? '—' }}</td>
                        <td>{{ optional($exemption->type)->penalty_exemption_name }}</td>
                        <td>{{\Carbon\Carbon::parse($exemption->effective_date)->format('F j, Y') ?? '-'}}</td>
                        <td>{{\Carbon\Carbon::parse($exemption->expired_date)->format('F j, Y') ?? '-' }}</td>
                        <td>
                            @if($isActive)
                                <span class="badge bg-success">Active</span>
                            @else
                                <span class="badge bg-danger">Expired</span>
                            @endif
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal" data-bs-target="#editModal{{ $exemption->id }}">
                                Edit
                            </button>
                            <form action="{{ route('penalty-exemption.destroy', $exemption->id) }}" method="POST" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this exemption?')">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">
                            No penalty exemptions found.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>


@foreach($exemptions as $exemption)
<div class="modal fade" id="editModal{{ $exemption->id }}" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">

            <form method="POST" action="{{ route('penalty-exemption.update', $exemption->id) }}">
                @csrf
                @method('PUT')

                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-semibold">
                        Edit Penalty Exemption
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-3">

                        {{-- Account No --}}
                        <div class="col-md-6">
                            <label class="form-label">Account No</label>
                            <input type="text"
                                   name="account_no"
                                   class="form-control @error('account_no') is-invalid @enderror"
                                   value="{{ old('account_no', $exemption->account_no) }}"
                                   required>
                            @error('account_no')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        {{-- Exemption Type --}}
                        <div class="col-md-6">
                            <label class="form-label">Exemption Type</label>
                            <select name="penalty_exemption_type_id"
                                    class="form-select @error('penalty_exemption_type_id') is-invalid @enderror"
                                    required>
                                @foreach($types as $type)
                                    <option value="{{ $type->id }}"
                                        {{ old('penalty_exemption_type_id', $exemption->penalty_exemption_type_id) == $type->id ? 'selected' : '' }}>
                                        {{ $type->penalty_exemption_name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('penalty_exemption_type_id')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        {{-- Effective Date --}}
                        <div class="col-md-6">
                            <label class="form-label">Effective Date</label>
                            <input type="date"
                                   name="effective_date"
                                   class="form-control @error('effective_date') is-invalid @enderror"
                                   value="{{ old('effective_date', $exemption->effective_date) }}">
                            @error('effective_date')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        {{-- Expired Date --}}
                        <div class="col-md-6">
                            <label class="form-label">Expired Date</label>
                            <input type="date"
                                   name="expired_date"
                                   class="form-control @error('expired_date') is-invalid @enderror"
                                   value="{{ old('expired_date', $exemption->expired_date) }}"
                                   >
                            @error('expired_date')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary px-4">
                        Update
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>
@endforeach

@if(session('open_modal'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = new bootstrap.Modal(
            document.getElementById('editModal{{ session('open_modal') }}')
        );
        modal.show();
    });
</script>
@endif
@endsection
