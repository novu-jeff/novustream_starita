@extends('layouts.app')

@section('content')
    <main class="main payment-consolidation-page">
        <div class="responsive-wrapper pb-5">
            <div class="payment-page-header d-flex justify-content-between flex-wrap align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="payment-page-icon">
                        <i class="bx bx-receipt" aria-hidden="true"></i>
                    </div>
                    <div>
                        <p class="payment-eyebrow mb-1">PAYMENTS</p>
                        <h1 class="mb-1">Cash Payment Consolidation</h1>
                        <p class="payment-page-subtitle mb-0">Review cash payment service fees and access credited receipts.</p>
                    </div>
                </div>
            </div>

            <div class="inner-content mt-4">
                <section class="payment-filter-card mb-4" aria-labelledby="paymentFiltersTitle">
                    <div class="payment-section-heading d-flex align-items-center gap-2 mb-3">
                        <span class="payment-section-icon"><i class="bx bx-filter-alt" aria-hidden="true"></i></span>
                        <div>
                            <h2 id="paymentFiltersTitle" class="h6 mb-0">Filter payments</h2>
                            <p class="small text-muted mb-0">Choose a payment period or search for a specific transaction.</p>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('payments.consolidation.index') }}" class="row g-3 align-items-end">
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
                            <label for="search" class="form-label">Search payment</label>
                            <div class="payment-search-wrap">
                                <i class="bx bx-search" aria-hidden="true"></i>
                                <input type="search" name="search" id="search" class="form-control" value="{{ $search }}" maxlength="100" placeholder="Name, account number, or OR number">
                            </div>
                        </div>
                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2 pt-1">
                            <button type="submit" class="btn btn-primary payment-primary-btn">
                                <i class="bx bx-filter-alt me-1" aria-hidden="true"></i> Apply Filters
                            </button>
                            <button type="submit" class="btn btn-outline-primary payment-outline-btn" formaction="{{ route('payments.consolidation.export') }}">
                                <i class="bx bx-download me-1" aria-hidden="true"></i> Download Excel
                            </button>
                        </div>
                    </form>
                </section>

                <section class="payment-table-card" aria-labelledby="paymentResultsTitle">
                    <div class="payment-table-heading d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h2 id="paymentResultsTitle" class="h6 mb-1">Payment records</h2>
                            <p class="small text-muted mb-0">Cash payment service fees and their credited official receipts.</p>
                        </div>
                        <span class="payment-records-label">
                            <i class="bx bx-list-ul me-1" aria-hidden="true"></i>
                            {{ method_exists($data, 'total') ? number_format($data->total()) : $data->count() }} records
                        </span>
                    </div>

                    <div class="table-responsive payment-table-responsive">
                        <table class="table payment-table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Account No.</th>
                                    <th scope="col">Reference No.</th>
                                    <th scope="col" class="text-end">Payment</th>
                                    <th scope="col">Date Paid</th>
                                    <th scope="col" class="text-center">Receipt</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($data as $index => $row)
                                    <tr>
                                        <td class="text-muted">{{ $data->firstItem() + $index }}</td>
                                        <td class="fw-semibold">{{ $row->name ?? 'N/A' }}</td>
                                        <td>{{ $row->account_no ?? 'N/A' }}</td>
                                        <td><span class="payment-reference">{{ $row->reference_no ?? 'N/A' }}</span></td>
                                        <td class="text-end fw-semibold text-nowrap">₱ {{ number_format((float) config('payments.system_fee', 10), 2) }}</td>
                                        <td class="text-nowrap">
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
                                                <span class="d-none d-lg-inline ms-1">Receipt</span>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="payment-empty-cell">
                                            <div class="payment-empty-state">
                                                <span class="payment-empty-icon"><i class="bx bx-receipt" aria-hidden="true"></i></span>
                                                <h3 class="h6 mb-1">No cash payments found</h3>
                                                <p class="text-muted small mb-0">Try adjusting the date range or search terms.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if (method_exists($data, 'links'))
                        <div class="payment-pagination">
                            {{ $data->links() }}
                        </div>
                    @endif
                </section>
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
