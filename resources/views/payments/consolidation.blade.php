@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper pb-5">
            <div class="main-header d-flex justify-content-between flex-wrap align-items-center gap-3">
                <h1>Cash Payment Consolidation</h1>
            </div>

            <div class="inner-content mt-4">
                <form method="GET" action="{{ route('payments.consolidation.index') }}" class="row g-3 align-items-end mb-4">
                    <div class="col-12 col-md-3">
                        <label for="period" class="form-label">Date Paid</label>
                        <select name="period" id="period" class="form-select">
                            <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Today</option>
                            <option value="week" {{ $period === 'week' ? 'selected' : '' }}>This Week</option>
                            <option value="month" {{ $period === 'month' ? 'selected' : '' }}>This Month</option>
                            <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Custom Range</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 {{ $period === 'custom' ? '' : 'd-none' }}" id="fromDateWrap">
                        <label for="from_date" class="form-label">From</label>
                        <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $from_date }}">
                    </div>
                    <div class="col-6 col-md-2 {{ $period === 'custom' ? '' : 'd-none' }}" id="toDateWrap">
                        <label for="to_date" class="form-label">To</label>
                        <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $to_date }}">
                    </div>
                    <div class="col-12 col-md-5">
                        <label for="search" class="form-label">Search Name, Account No., or OR No.</label>
                        <input type="search" name="search" id="search" class="form-control" value="{{ $search }}" maxlength="100" placeholder="Search payments">
                    </div>
                    <div class="col-12 col-md-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <button type="submit" class="btn btn-outline-success" formaction="{{ route('payments.consolidation.export') }}">
                            <i class="bx bx-download"></i> Download Excel
                        </button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Account No.</th>
                                <th>Reference No.</th>
                                <th>Payment</th>
                                <th>Date Paid</th>
                                <th class="text-center">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data as $index => $row)
                                <tr>
                                    <td>{{ $data->firstItem() + $index }}</td>
                                    <td>{{ $row->name ?? 'N/A' }}</td>
                                    <td>{{ $row->account_no ?? 'N/A' }}</td>
                                    <td>{{ $row->reference_no ?? 'N/A' }}</td>
                                    <td>₱ {{ number_format((float) config('payments.system_fee', 10), 2) }}</td>
                                    <td>
                                        @if ($row->date_paid)
                                            @php
                                                try {
                                                    $displayDatePaid = \Carbon\Carbon::parse($row->date_paid)->format('M d, Y H:i:s');
                                                } catch (\Throwable $exception) {
                                                    $displayDatePaid = $row->date_paid;
                                                }
                                            @endphp
                                            {{ $displayDatePaid }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <a
                                            href="{{ route('payments.consolidation.receipt', $row->id) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="btn btn-outline-primary btn-sm receipt-open"
                                            title="View credited official receipt"
                                            aria-label="View receipt for {{ $row->reference_no }}"
                                            data-bs-toggle="tooltip"
                                            data-bs-title="Print system fee receipt"
                                        >
                                            <i class="bx bx-printer" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No cash payments found for these filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    {{ $data->links() }}
                </div>
            </div>
        </div>
    </main>

@endsection

@section('script')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const periodSelect = document.getElementById('period');
            const fromWrap = document.getElementById('fromDateWrap');
            const toWrap = document.getElementById('toDateWrap');
            const fromDate = document.getElementById('from_date');
            const toDate = document.getElementById('to_date');
            function updateCustomRangeVisibility() {
                const custom = periodSelect.value === 'custom';
                fromWrap.classList.toggle('d-none', !custom);
                toWrap.classList.toggle('d-none', !custom);
                fromDate.required = custom;
                toDate.required = custom;
            }

            periodSelect.addEventListener('change', updateCustomRangeVisibility);
            updateCustomRangeVisibility();

        });
    </script>
@endsection