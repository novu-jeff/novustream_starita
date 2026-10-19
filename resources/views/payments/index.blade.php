@extends('layouts.app')

@section('content')
    <div id="page-loader" style="
        display:none;
        position:fixed;
        inset:0;
        background:rgba(255,255,255,0.7);
        z-index:9999;
        align-items:center;
        justify-content:center;
    ">
        <div class="text-center">
            <div class="spinner-border text-primary" style="width:3rem;height:3rem;" role="status"></div>
            <div class="mt-3 fw-bold text-uppercase">Loading...</div>
        </div>
    </div>
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>{{$filter}} Bills</h1>
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('payments.index', array_merge(request()->query(), ['filter' => $filter === 'paid' ? 'unpaid' : 'paid'])) }}"
                        class="btn btn-primary px-5 py-3 text-uppercase">
                         View {{ $filter === 'paid' ? 'Unpaid' : 'Paid' }}
                     </a>
                     <a href="{{ route('payments.application-fees.index') }}"
                        class="btn btn-outline-primary px-5 py-3 text-uppercase">
                        Application Fees
                    </a>
                </div>
            </div>
            <div class="inner-content mt-5 pb-5 mb-5">
                <div class="row mb-4">
                    <div class="col-12 col-md-1">
                        <label class="mb-1">Entries</label>
                        <select name="entries" id="entries" class="form-select text-uppercase dropdown-toggle">
                            @foreach([10, 25, 50, 100, 200, 250, 350, 400, 450, 500] as $entry)
                                <option value="{{ $entry }}" {{ $entries == $entry ? 'selected' : '' }}>
                                    {{ $entry }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2 mb-3">
                        <label class="mb-1">Filter</label>
                        <select name="filter" id="filter" class="form-select text-uppercase dropdown-toggle">
                            <option value="unpaid" {{$filter == 'unpaid' ? 'selected' : ''}}>UnPaid</option>
                            <option value="paid" {{$filter == 'paid' ? 'selected' : ''}}>Paid</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 mb-3">
                        <label class="mb-1">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-select text-uppercase dropdown-toggle">
                            <option value="all" {{ $paymentMethod == 'all' ? 'selected' : '' }}>All</option>
                            <option value="walk-in" {{ $paymentMethod == 'walk-in' ? 'selected' : '' }}>Walk-in</option>
                            <option value="online" {{ $paymentMethod == 'online' ? 'selected' : '' }}>Online</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 mb-3">
                        <label class="mb-1">Zone</label>
                        <select name="zone_no" id="zone_no" class="form-select text-uppercase dropdown-toggle">
                            <option value="all">All Zones</option>
                            @forelse($zones as $targetedZone)
                                <option value="{{$targetedZone->zone}}" {{$targetedZone->zone == $zone ? 'selected' : ''}}> {{$targetedZone->zone . ' - ' . $targetedZone->area}} </option>
                            @empty
                                <option value="">No Zones Available</option>
                            @endforelse
                        </select>
                    </div>
                    <div class="col-12 col-md-2 mb-3">
                        <label class="mb-1">Reading Month</label>
                        <input type="month" name="month" id="date" class="form-control" value="{{$date}}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="mb-1">Search <span class="text-muted ms-1">[account no | name]</span></label>
                        <div class="position-relative">
                            <input
                                type="text"
                                name="search"
                                id="search"
                                class="form-control pe-5"
                                value="{{ $toSearch }}"
                                placeholder=""
                            >

                            @if(!empty($toSearch))
                                <button
                                    type="button"
                                    id="clear-search"
                                    class="btn position-absolute top-50 end-0 translate-middle-y me-2 p-0 text-muted"
                                    style="border: none; background: none; font-size: 1.2rem;"
                                    aria-label="Clear search"
                                >
                                    &times;
                                </button>
                            @endif
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button type="button" id="apply-filters" class="btn btn-primary">
                            <i class="bx bx-filter-alt" aria-hidden="true"></i> Apply Filters
                        </button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped w-100 mt-4">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Account No</th>
                                <th>Name</th>
                                <th>Zone</th>
                                <th>Billing Period</th>
                                <th>Reading Date</th>
                                <th>Amount</th>
                                <th>Due Date</th>
                                @if($filter === 'paid')
                                    <th>Payment Method</th>
                                @endif
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->reading->account_no ?? 'N/A' }}</td>
                                    <td>{{ $row->reading?->concessionaire?->user?->name ?? 'N/A' }}</td>
                                    <td>{{ $row->reading->zone ?? 'N/A' }}</td>
                                    <td>
                                        @if ($row->bill_period_from && $row->bill_period_to)
                                            {{ \Carbon\Carbon::parse($row->bill_period_from)->format('M d, Y') }}
                                            TO
                                            {{ \Carbon\Carbon::parse($row->bill_period_to)->format('M d, Y') }}
                                        @else
                                            N/A
                                        @endif
                                    </td>
                                    <td>
                                        {{ !empty($row->bill_period_to)
                                            ? \Carbon\Carbon::parse($row->created_at)->format('M d, Y')
                                            : 'N/A' }}
                                    </td>
                                    <td>₱{{ number_format((float)($row->total ?? 0), 2) }}</td>
                                    <td>
                                        {{ !empty($row->due_date)
                                            ? \Carbon\Carbon::parse($row->due_date)->format('M d, Y')
                                            : 'N/A' }}
                                    </td>
                                    @if($filter === 'paid')
                                        <td>{{ $row->payment_method === 'cash' ? 'Walk-in' : (ucfirst($row->payment_method ?? 'N/A')) }}</td>
                                    @endif
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            @if (!$row->isPaid)
                                                <a href="{{ route('payments.pay', ['reference_no' => $row->reference_no]) }}"
                                                class="btn btn-primary text-white text-uppercase fw-bold">
                                                    <i class="bx bx-credit-card-alt"></i>
                                                </a>
                                            @else
                                                <a target="_blank" href="{{ route('reading.orshow', $row->reference_no) }}"
                                                class="btn btn-primary text-white text-uppercase fw-bold"
                                                id="show-btn" data-id="{{ $row->id }}">
                                                    <i class="bx bx-receipt"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                            <tr>
                                <td colspan="{{ $filter === 'paid' ? 11 : 10 }}">
                                    <div class="text-uppercase text-center">No Data Found</div>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="w-100 mt-4">
                    {{ $data->links() }}
                </div>
            </div>
        </div>
    </main>

<style>
    main.main {
        --pay-blue: #1769f5;
        --pay-blue-dark: #1247a8;
        --pay-blue-soft: #edf5ff;
        --pay-blue-border: #d6e6ff;
        --pay-text: #142b52;
        --pay-muted: #7184a5;
        color: var(--pay-text);
    }
    main.main .responsive-wrapper { max-width: 1800px; margin: 0 auto; padding: 0 1rem; }
    main.main .main-header { flex-wrap: wrap; align-items: center; gap: 1rem; }
    main.main .main-header h1 { color: var(--pay-text); font-size: clamp(1.65rem, 2.5vw, 2.2rem); font-weight: 750; letter-spacing: -.035em; margin: 0; }
    main.main .main-header > div { flex-wrap: wrap; gap: .5rem !important; }
    main.main .btn-primary, main.main .btn-outline-primary { border-radius: .65rem; font-weight: 650; transition: all .15s ease; }
    main.main .btn-primary { background: linear-gradient(135deg, #3988ff, var(--pay-blue)); border-color: var(--pay-blue); box-shadow: 0 5px 14px rgba(23,105,245,.14); }
    main.main .btn-primary:hover { background: var(--pay-blue-dark); border-color: var(--pay-blue-dark); }
    main.main .btn-outline-primary { color: var(--pay-blue); border-color: var(--pay-blue-border); background: #fff; }
    main.main .btn-outline-primary:hover { color: var(--pay-blue-dark); background: var(--pay-blue-soft); border-color: #a9cbff; }
    main.main .main-header .btn { padding: .7rem 1rem !important; font-size: .82rem; }
    main.main .inner-content { background: #fff; border: 1px solid var(--pay-blue-border); border-radius: 1rem; box-shadow: 0 8px 26px rgba(30,79,145,.045); padding: 1.25rem; }
    main.main .inner-content label { color: #31496e; font-size: .79rem; font-weight: 700; }
    main.main .form-control, main.main .form-select { min-height: 43px; border: 1px solid #d6e2f3; border-radius: .6rem; color: var(--pay-text); font-size: .88rem; background-color: #fff; }
    main.main .form-control:focus, main.main .form-select:focus { border-color: #7eb0ff; box-shadow: 0 0 0 .2rem rgba(23,105,245,.11); }
    main.main .row.mb-4 { row-gap: .15rem; }
    main.main #apply-filters { border-radius: .65rem; padding: .7rem 1rem; font-weight: 650; }
    main.main .table-responsive { width: 100%; border: 1px solid #e1ebfa; border-radius: .8rem; overflow-x: auto; }
    main.main .table { min-width: 1250px; font-size: .86rem; margin-top: 0 !important; }
    main.main .table thead th { background: #f2f7ff; color: #3c557b; border-bottom: 1px solid var(--pay-blue-border); border-top: 0; padding: .95rem .85rem; font-size: .72rem; font-weight: 800; letter-spacing: .035em; text-transform: uppercase; white-space: nowrap; }
    main.main .table tbody td { padding: .9rem .85rem; border-color: #edf2fa; color: #344969; vertical-align: middle; }
    main.main .table-striped > tbody > tr:nth-of-type(odd) > * { --bs-table-bg-type: #fbfdff; }
    main.main .table tbody tr:hover > * { --bs-table-bg-state: #f3f8ff; }
    main.main .table tbody td:nth-child(2), main.main .table tbody td:nth-child(5), main.main .table tbody td:nth-child(6), main.main .table tbody td:nth-child(7), main.main .table tbody td:nth-child(8), main.main .table tbody td:nth-child(9) { white-space: nowrap; }
    main.main .table tbody td:nth-child(3) { min-width: 180px; font-weight: 600; color: var(--pay-text); }
    main.main .table tbody td .btn-primary { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; padding: 0; border-radius: .6rem; font-size: 1.1rem; }
    main.main .pagination { margin-bottom: 0; }
    main.main .pagination .page-link { color: var(--pay-blue); border-color: #dbe7f8; }
    main.main .pagination .page-item.active .page-link { color: #fff; background: var(--pay-blue); border-color: var(--pay-blue); }
    #page-loader { background: rgba(245,249,255,.82) !important; backdrop-filter: blur(3px); color: var(--pay-text); }
    #page-loader .spinner-border { color: var(--pay-blue) !important; }
    @media (max-width: 767.98px) {
        main.main .responsive-wrapper { padding: 0 .25rem; }
        main.main .main-header > div { width: 100%; }
        main.main .main-header .btn { flex: 1 1 auto; padding: .65rem .7rem !important; font-size: .75rem; }
        main.main .inner-content { padding: .85rem; margin-top: 1.25rem !important; }
        main.main .inner-content .col-12 { margin-bottom: .25rem; }
        main.main .table { min-width: 1200px; }
    }
</style>

@endsection

@section('script')
<script>
    $(function () {
        function updateUrl() {
            const params = new URLSearchParams(window.location.search);

            ['search', 'entries', 'filter', 'payment_method', 'zone_no', 'date'].forEach(id => {
                const val = $('#' + id).val();
                const key = id === 'zone_no' ? 'zone' : id;

                val ? params.set(key, val) : params.delete(key);
            });

            $('#page-loader').css('display', 'flex');

            window.location.href = window.location.pathname + '?' + params.toString();
        }

        $('#apply-filters').on('click', updateUrl);
        $('#search').on('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                updateUrl();
            }
        });

        $('#clear-search').on('click', function () {
            $('#search').val('');
            $('#search').trigger('focus');
        });

        $('#download-summary').on('click', function() {
        const type = $('#print').val();
        const date = $('#date').val();
        const zone = $('#zone_no').val() === 'all' ? '' : $('#zone_no').val();

        if (!type) {
            alert('Please select summary type');
            return;
        }

        const url = `/admin/reports/download?type=${type}&date=${date}&zone=${zone}`;
        window.location.href = url;
    });
    });
</script>
@endsection
