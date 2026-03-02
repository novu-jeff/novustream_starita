@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 mt-2">Statement of Account</h4>
            <p class="text-muted"><span>Account Name: <strong>{{ $user->name }}</strong></span><br /> Account Number: <strong>{{ $user->accounts->pluck('account_no')->implode(', ') }}</strong></p>
        </div>
        <div class="text-end">
            <a href="{{route('concessionaires.index')}}" class="btn btn-outline-primary px-5 py-3 text-uppercase">Go Back</a>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-start border-primary border-4 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted uppercase small fw-bold">Total Invoiced</h6>
                    <h3 class="mb-0">PHP {{ number_format($bills->sum('computed_amount'), 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-start border-success border-4 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted uppercase small fw-bold">Total Paid</h6>
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

    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-20">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Date / Reference</th>
                        <th>Reading</th>
                        <th>Due Date</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Payments</th>
                        <th class="text-end">Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @php $runningBalance = 0; @endphp
                    @foreach($bills as $bill)
                    @php $runningBalance += $bill->computed_balance; @endphp
                    <tr>
                        <td class="ps-3">
                            <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($bill->bill_period_to)->format('M d, Y') }}</div>
                            <small class="text-muted">{{ $bill->reference_no }}</small>
                        </td>
                        <td>
                            <div class="fw-bold text-dark">
                                {{ number_format((float) optional($bill->reading)->present_reading, 0) }}
                            </div>
                            <small class="text-muted">
                                Prev: {{ number_format((float) optional($bill->reading)->previous_reading, 0) }}
                                |
                                Cub: {{ number_format((float) optional($bill->reading)->consumption, 0) }}
                            </small>
                        </td>
                        <td>
                            <span class="{{ \Carbon\Carbon::parse($bill->due_date)->isPast() && $bill->computed_status !== 'PAID' ? 'text-danger fw-bold' : '' }}">
                                {{ \Carbon\Carbon::parse($bill->due_date)->format('m/d/Y') }}
                            </span>
                        </td>
                        <td class="text-end">
                            <div>{{ number_format($bill->computed_amount, 2) }}</div>
                            @if($bill->computed_penalty > 0)
                                <small class="text-danger" style="font-size: 0.75rem;">
                                    +{{ number_format($bill->computed_penalty, 2) }} (Penalty)
                                </small>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="text-success">{{ number_format($bill->computed_paid, 2) }}</div>
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
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td colspan="5" class="text-end fw-bold text-uppercase small">Total Outstanding:</td>
                        <td class="text-end fw-bold text-danger">{{ number_format($runningBalance, 2) }}</td>
                        @php
                            $totalAdvances = $bills->sum('advances');
                        @endphp

                        @if ($totalAdvances > 0)
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
            </table>
        </div>
    </div>
</div>
@endsection
