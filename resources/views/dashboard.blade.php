@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper pt-0">
            <div class="main-header">
                <h1>Dashboard</h1>
                <span class="dashboard-badge">{{ \Carbon\Carbon::now()->format('M d, Y \a\t h:i A') }}</span>
            </div>

            @php
                $filters = $filters ?? ['date_from' => '', 'date_to' => '', 'zone' => '', 'payment_method' => ''];
                $hasFilters = $filters['date_from'] !== '' || $filters['date_to'] !== '' || $filters['zone'] !== '' || $filters['payment_method'] !== '';
            @endphp
            <form method="GET" action="{{ route('dashboard') }}" class="dashboard-filters" id="dashboardFilters">
                <div class="dashboard-filters__top">
                    <h2 class="dashboard-filters__title">Filters</h2>
                    <div class="dashboard-filters__top-actions">
                        @if($hasFilters)
                            <a href="{{ route('dashboard') }}" class="dashboard-filters__clear">Clear</a>
                        @endif
                        <button type="button" class="dashboard-filters__toggle" data-bs-toggle="collapse" data-bs-target="#dashboardFilterFields" aria-expanded="false" aria-controls="dashboardFilterFields">
                            <span>Show filters</span><i class="bx bx-chevron-down" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="collapse" id="dashboardFilterFields">
                    <div class="dashboard-filters__grid">
                        <div>
                            <label for="date_from">Date from</label>
                            <input type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] }}">
                        </div>
                        <div>
                            <label for="date_to">Date to</label>
                            <input type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] }}">
                        </div>
                        <div>
                            <label for="zone">Zone</label>
                            <select id="zone" name="zone">
                                <option value="">All zones</option>
                                @foreach($zones ?? [] as $zone)
                                    <option value="{{ $zone->zone }}" {{ $filters['zone'] === $zone->zone ? 'selected' : '' }}>
                                        {{ $zone->zone }} — {{ $zone->area }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="payment_method">Payment method</label>
                            <select id="payment_method" name="payment_method">
                                <option value="">All methods</option>
                                <option value="cash" {{ $filters['payment_method'] === 'cash' ? 'selected' : '' }}>Cash</option>
                                <option value="online" {{ $filters['payment_method'] === 'online' ? 'selected' : '' }}>Online</option>
                            </select>
                        </div>
                        <div class="dashboard-filters__actions">
                            <button type="submit" class="dashboard-filters__apply">Apply</button>
                        </div>
                    </div>
                    <div class="dashboard-filters__presets">
                        <button type="button" class="dashboard-filters__preset" data-range="today">Today</button>
                        <button type="button" class="dashboard-filters__preset" data-range="month">This month</button>
                        <button type="button" class="dashboard-filters__preset" data-range="30">Last 30 days</button>
                        <button type="button" class="dashboard-filters__preset" data-range="365">Last 12 months</button>
                    </div>
                    <p class="dashboard-filters__hint">Collections use payment date. Unpaid bills and readings use the reading date. Staff and account totals stay unfiltered.</p>
                </div>
            </form>

            <div class="dashboard-stats-row">
                <div class="stat-pill"><i class="bx bx-user"></i><span>{{ $data['admins'] ?? 0 }} Admins</span></div>
                <div class="stat-pill"><i class="bx bx-group"></i><span>{{ $data['concessionaires'] ?? 0 }} Concessionaires</span></div>
                <div class="stat-pill"><i class="bx bx-wrench"></i><span>{{ $data['technicians'] ?? 0 }} Technicians</span></div>
                <div class="stat-pill"><i class="bx bx-receipt"></i><span>{{ $data['total_transactions_count'] ?? 0 }} Paid Bills</span></div>
                <div class="stat-pill stat-pill--accent"><i class="bx bx-credit-card"></i><span>{{ $data['unique_online_payments'] ?? 0 }} Online Payments</span></div>
                <div class="stat-pill stat-pill--accent"><i class="bx bx-log-in-circle"></i><span>{{ $data['concessionaire_accounts'] ?? 0 }} Concessionaire Accounts</span></div>
            </div>

            <div class="dashboard-stats-grid">
                <div class="stat-card stat-card--primary">
                    <div class="stat-card__icon"><i class="bx bx-wallet"></i></div>
                    <div class="stat-card__content">
                        <span class="stat-card__label">{{ $data['collection_label'] ?? "Today's Collection" }}</span>
                        <span class="stat-card__value">₱{{ number_format($data['today_paid'] ?? 0, 2) }}</span>
                        <span class="stat-card__sub">{{ $data['collection_meta'] ?? (($data['today_count'] ?? 0).' transactions') }}</span>
                    </div>
                </div>
                <div class="stat-card stat-card--success">
                    <div class="stat-card__icon"><i class="bx bx-check-circle"></i></div>
                    <div class="stat-card__content">
                        <span class="stat-card__label">Total Paid</span>
                        <span class="stat-card__value">₱{{ number_format($data['total_paid'] ?? 0, 2) }}</span>
                        @if(!empty($data['paid_sub']))
                            <span class="stat-card__sub">{{ $data['paid_sub'] }}</span>
                        @endif
                    </div>
                </div>
                <div class="stat-card stat-card--warning">
                    <div class="stat-card__icon"><i class="bx bx-time-five"></i></div>
                    <div class="stat-card__content">
                        <span class="stat-card__label">Unpaid Amount</span>
                        <span class="stat-card__value">₱{{ number_format($data['total_unpaid'] ?? 0, 2) }}</span>
                        @if(!empty($data['unpaid_sub']))
                            <span class="stat-card__sub">{{ $data['unpaid_sub'] }}</span>
                        @endif
                    </div>
                </div>
                <div class="stat-card stat-card--info">
                    <div class="stat-card__icon"><i class="bx bx-file"></i></div>
                    <div class="stat-card__content">
                        <span class="stat-card__label">Readings</span>
                        <span class="stat-card__value">{{ number_format($data['total_readings'] ?? 0) }}</span>
                        @if(!empty($data['readings_sub']))
                            <span class="stat-card__sub">{{ $data['readings_sub'] }}</span>
                        @endif
                    </div>
                </div>
            </div>
            
            <div class="dashboard-charts-grid">
                <div class="chart-card chart-card--wide">
                    <div class="chart-card__header"><h3>Revenue Trend</h3><span class="chart-card__sub">{{ $data['revenue_sub'] ?? 'Last 12 months' }}</span></div>
                    <div id="chartRevenue" class="chart-card__body"></div>
                </div>
                <div class="chart-card">
                    <div class="chart-card__header"><h3>Payment Method</h3><span class="chart-card__sub">{{ $data['method_sub'] ?? 'Cash vs Online' }}</span></div>
                    <div id="chartPaymentMethod" class="chart-card__body"></div>
                </div>
            </div>
            <div class="dashboard-charts-grid">
                <div class="chart-card chart-card--full">
                    <div class="chart-card__header"><h3>{{ $data['zone_title'] ?? 'Readings by Zone' }}</h3><span class="chart-card__sub">{{ $data['zone_sub'] ?? 'This month' }}</span></div>
                    <div id="chartZone" class="chart-card__body chart-card__body--tall"></div>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('script')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const revenueOptions = { series: [{ name: 'Collection (₱)', data: @json($data['chart_monthly_data'] ?? []) }], chart: { type: 'area', height: 320, fontFamily: 'Be Vietnam Pro, sans-serif', toolbar: { show: false } }, colors: ['#434ce8'], fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.05 } }, dataLabels: { enabled: false }, stroke: { curve: 'smooth', width: 2 }, xaxis: { categories: @json($data['chart_monthly_labels'] ?? []), labels: { style: { fontSize: '11px' } } }, yaxis: { labels: { formatter: function(v) { return '₱' + (v >= 1000 ? (v/1000).toFixed(1) + 'k' : v); } } }, grid: { borderColor: '#eff1f6', strokeDashArray: 4 }, tooltip: { y: { formatter: function(v) { return '₱' + v.toLocaleString('en-PH', {minimumFractionDigits: 2}); } } } };
    new ApexCharts(document.querySelector('#chartRevenue'), revenueOptions).render();
    const onlineCount = {{ (int) ($data['payment_method_count']['online'] ?? 0) }};
    const cashCount = Math.max(0, {{ (int) ($data['total_transactions_count'] ?? 0) }} - onlineCount);
    const paymentSeries = [];
    const paymentLabels = [];
    const paymentColors = [];
    if (cashCount > 0) { paymentSeries.push(cashCount); paymentLabels.push('Cash'); paymentColors.push('#00d084'); }
    if (onlineCount > 0) { paymentSeries.push(onlineCount); paymentLabels.push('Online'); paymentColors.push('#434ce8'); }
    if (paymentSeries.length > 0) { new ApexCharts(document.querySelector('#chartPaymentMethod'), { series: paymentSeries, chart: { type: 'donut', height: 280 }, labels: paymentLabels, colors: paymentColors, legend: { position: 'bottom' }, plotOptions: { pie: { donut: { size: '65%', labels: { show: true, total: { show: true } } } } } }).render(); } else { document.querySelector('#chartPaymentMethod').innerHTML = '<div class="chart-empty"><span>No paid transactions yet</span></div>'; }
    const zoneData = @json($data['chart_zone_data'] ?? []);
    const zoneHorizontal = @json($data['chart_zone_horizontal'] ?? true);
    if (zoneData.length > 0) { new ApexCharts(document.querySelector('#chartZone'), { series: [{ name: 'Readings', data: zoneData }], chart: { type: 'bar', height: 320 }, colors: ['#434ce8'], plotOptions: { bar: { borderRadius: 4, horizontal: zoneHorizontal, barHeight: '60%', columnWidth: '55%' } }, dataLabels: { enabled: true }, xaxis: { categories: @json($data['chart_zone_labels'] ?? []), labels: { style: { fontSize: '11px' } } }, grid: { borderColor: '#eff1f6', strokeDashArray: 4 } }).render(); } else { document.querySelector('#chartZone').innerHTML = '<div class="chart-empty"><span>No readings for this period</span></div>'; }

    const filterForm = document.getElementById('dashboardFilters');
    const filterFields = document.getElementById('dashboardFilterFields');
    const filterToggle = document.querySelector('.dashboard-filters__toggle');
    const fromInput = document.getElementById('date_from');
    const toInput = document.getElementById('date_to');
    filterFields.addEventListener('show.bs.collapse', function () {
        document.querySelector('.dashboard-filters__top').classList.add('is-open');
        filterToggle.querySelector('span').textContent = 'Hide filters';
        filterToggle.querySelector('i').classList.replace('bx-chevron-down', 'bx-chevron-up');
    });
    filterFields.addEventListener('hide.bs.collapse', function () {
        document.querySelector('.dashboard-filters__top').classList.remove('is-open');
        filterToggle.querySelector('span').textContent = 'Show filters';
        filterToggle.querySelector('i').classList.replace('bx-chevron-up', 'bx-chevron-down');
    });
    const formatDate = function (date) {
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return date.getFullYear() + '-' + month + '-' + day;
    };
    document.querySelectorAll('.dashboard-filters__preset').forEach(function (button) {
        button.addEventListener('click', function () {
            const today = new Date();
            let from = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            if (button.dataset.range === 'month') {
                from = new Date(today.getFullYear(), today.getMonth(), 1);
            } else if (button.dataset.range === '30') {
                from.setDate(from.getDate() - 29);
            } else if (button.dataset.range === '365') {
                from = new Date(today.getFullYear(), today.getMonth() - 11, 1);
            }
            fromInput.value = formatDate(from);
            toInput.value = formatDate(today);
            filterForm.submit();
        });
    });
});
</script>
@endsection
