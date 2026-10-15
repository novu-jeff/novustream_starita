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

    <style>
        .payment-consolidation-page {
            --payment-blue: #32667e;
            --payment-blue-dark: #32667e;
            --payment-blue-soft: #edf5ff;
            --payment-blue-border: #d7e7ff;
            --payment-text: #32667e;
            --payment-muted: #6f83a5;
            color: var(--payment-text);
        }

        .payment-consolidation-page .responsive-wrapper {
            width: 100%;
            max-width: 1600px;
            margin-inline: auto;
            padding-inline: clamp(.75rem, 2vw, 1.5rem);
        }

        .payment-page-header {
            padding: .5rem 0 .25rem;
        }

        .payment-page-icon,
        .payment-section-icon,
        .payment-empty-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            color: var(--payment-blue);
            background: var(--payment-blue-soft);
            border: 1px solid var(--payment-blue-border);
        }

        .payment-page-icon {
            width: 58px;
            height: 58px;
            border-radius: 17px;
            font-size: 1.9rem;
        }

        .payment-eyebrow {
            color: var(--payment-blue);
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .1em;
        }

        .payment-page-header h1 {
            color: var(--payment-text);
            font-size: clamp(1.45rem, 2.2vw, 2rem);
            font-weight: 750;
            letter-spacing: -.035em;
        }

        .payment-page-subtitle {
            color: var(--payment-muted);
            font-size: .92rem;
        }

        .payment-filter-card,
        .payment-table-card {
            overflow: hidden;
            background: #fff;
            border: 1px solid var(--payment-blue-border);
            border-radius: 16px;
            box-shadow: 0 8px 28px rgba(31, 91, 164, .055);
        }

        .payment-filter-card {
            padding: clamp(1rem, 2vw, 1.5rem);
        }

        .payment-section-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            font-size: 1.25rem;
        }

        .payment-consolidation-page .form-label {
            margin-bottom: .45rem;
            color: #30496e;
            font-size: .82rem;
            font-weight: 650;
        }

        .payment-consolidation-page .form-control,
        .payment-consolidation-page .form-select {
            min-height: 44px;
            border-color: #d7e2f1;
            border-radius: 9px;
            color: var(--payment-text);
            background-color: #fff;
            box-shadow: none;
            font-size: .9rem;
        }

        .payment-consolidation-page .form-control:focus,
        .payment-consolidation-page .form-select:focus {
            border-color: #78adff;
            box-shadow: 0 0 0 .2rem rgba(23, 105, 224, .12);
        }

        .payment-search-wrap {
            position: relative;
        }

        .payment-search-wrap > i {
            position: absolute;
            z-index: 2;
            top: 50%;
            left: .85rem;
            color: #7690b7;
            font-size: 1.15rem;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .payment-search-wrap .form-control {
            padding-left: 2.55rem;
        }

        .payment-consolidation-page .btn {
            min-height: 40px;
            border-radius: 9px;
            font-size: .86rem;
            font-weight: 650;
            transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease, transform .15s ease;
        }

        .payment-consolidation-page .btn-primary {
            background: var(--payment-blue);
            border-color: var(--payment-blue);
            color: #fff;
        }

        .payment-consolidation-page .btn-primary:hover,
        .payment-consolidation-page .btn-primary:focus {
            background: var(--payment-blue-dark);
            border-color: var(--payment-blue-dark);
            color: #fff;
            box-shadow: 0 4px 12px rgba(23, 105, 224, .18);
        }

        .payment-consolidation-page .btn-outline-primary {
            color: var(--payment-blue);
            border-color: #a9caff;
            background: #fff;
        }

        .payment-consolidation-page .btn-outline-primary:hover,
        .payment-consolidation-page .btn-outline-primary:focus {
            color: #fff;
            background: var(--payment-blue);
            border-color: var(--payment-blue);
        }

        .payment-primary-btn,
        .payment-outline-btn {
            padding-inline: 1rem;
        }

        .payment-table-heading {
            padding: 1.15rem 1.35rem;
            border-bottom: 1px solid #e8eff9;
        }

        .payment-table-heading h2 {
            color: var(--payment-text);
            font-weight: 750;
        }

        .payment-records-label {
            display: inline-flex;
            align-items: center;
            padding: .42rem .7rem;
            border: 1px solid var(--payment-blue-border);
            border-radius: 999px;
            background: var(--payment-blue-soft);
            color: var(--payment-blue-dark);
            font-size: .78rem;
            font-weight: 650;
            white-space: nowrap;
        }

        .payment-table-responsive {
            width: 100%;
        }

        .payment-table {
            min-width: 900px;
            color: #294264;
            font-size: .88rem;
        }

        .payment-table thead th {
            padding: .9rem 1rem;
            background: #f2f7ff;
            color: #3d5a83;
            border-bottom: 1px solid var(--payment-blue-border);
            border-top: 0;
            font-size: .72rem;
            font-weight: 800;
            letter-spacing: .045em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .payment-table tbody td {
            padding: .9rem 1rem;
            border-color: #edf2f9;
            vertical-align: middle;
        }

        .payment-table tbody tr {
            transition: background-color .15s ease;
        }

        .payment-table tbody tr:hover > * {
            background: #f6faff;
        }

        .payment-reference {
            display: inline-block;
            padding: .28rem .48rem;
            border: 1px solid #e1ebfa;
            border-radius: 6px;
            background: #f7faff;
            color: #365987;
            font-size: .82rem;
            white-space: nowrap;
        }

        .payment-table .receipt-open {
            min-height: 33px;
            padding: .35rem .6rem;
        }

        .payment-empty-cell {
            padding: 0 !important;
        }

        .payment-empty-state {
            display: flex;
            min-height: 220px;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            text-align: center;
        }

        .payment-empty-icon {
            width: 54px;
            height: 54px;
            margin-bottom: .9rem;
            border-radius: 16px;
            font-size: 1.7rem;
        }

        .payment-empty-state h3 {
            color: var(--payment-text);
            font-weight: 750;
        }

        .payment-pagination {
            padding: 1rem 1.25rem;
            border-top: 1px solid #e8eff9;
        }

        .payment-pagination nav {
            display: flex;
            justify-content: flex-end;
        }

        .payment-pagination .pagination {
            margin-bottom: 0;
            gap: .25rem;
        }

        .payment-pagination .page-link {
            border-color: #dce8f8;
            border-radius: 7px;
            color: var(--payment-blue);
        }

        .payment-pagination .page-item.active .page-link {
            background: var(--payment-blue);
            border-color: var(--payment-blue);
            color: #fff;
        }

        .payment-pagination .page-item.disabled .page-link {
            color: #9aabc3;
            background: #f7faff;
        }

        @media (max-width: 767.98px) {
            .payment-page-header {
                align-items: flex-start !important;
            }

            .payment-page-icon {
                width: 48px;
                height: 48px;
                border-radius: 14px;
                font-size: 1.6rem;
            }

            .payment-filter-card {
                padding: 1rem;
            }

            .payment-table-heading {
                padding: 1rem;
            }

            .payment-pagination {
                padding: 1rem;
                overflow-x: auto;
            }

            .payment-pagination nav {
                justify-content: flex-start;
            }
        }
    </style>
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
