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
                                    <div>
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
                                            class="btn btn-primary btn-sm text-white fw-bold ms-2">
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
@endsection
