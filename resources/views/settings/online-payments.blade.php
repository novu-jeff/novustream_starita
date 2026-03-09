@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper pb-5">
            <div class="main-header d-flex justify-content-between flex-wrap align-items-center gap-2">
                <h1>Online Payments</h1>
            </div>
            <p class="text-muted small mt-1 mb-0">
                <strong>Automatic sync:</strong> When a payment is marked paid in Novupay, sync runs automatically every 3 minutes.
                Use <strong>Sync Now</strong> below if you want to run immediately or if the automatic sync failed.
            </p>
            <div class="inner-content mt-4">
                {{-- Filters --}}
                <div class="card shadow border-0 mb-4">
                    <div class="card-header bg-light">
                        <strong>Filters</strong>
                    </div>
                    <div class="card-body">
                        <div class="row g-2 align-items-end">
                            <div class="col-6 col-md-2">
                                <label class="form-label small mb-0">Month</label>
                                <select class="form-select form-select-sm" id="filterMonth">
                                    {{-- Options filled by JS --}}
                                </select>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small mb-0">Account No / Name</label>
                                <input type="text" class="form-control form-control-sm" id="filterAccountNo" placeholder="Account no or name">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small mb-0">Reference No</label>
                                <input type="text" class="form-control form-control-sm" id="filterReferenceNo" placeholder="Reference no">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-4 align-items-center">
                    <span class="text-muted small me-2">View:</span>
                    <div class="btn-group btn-group-sm" role="group" id="viewGroup">
                        <input type="radio" class="btn-check" name="viewMode" id="viewReady" value="ready" checked autocomplete="off">
                        <label class="btn btn-outline-primary" for="viewReady">Ready to sync</label>
                        <input type="radio" class="btn-check" name="viewMode" id="viewRecent" value="recent" autocomplete="off">
                        <label class="btn btn-outline-secondary" for="viewRecent">Recent synced</label>
                    </div>
                    <span class="text-muted small ms-2 me-1">Show:</span>
                    <div class="btn-group btn-group-sm" role="group" id="limitGroup">
                        <input type="radio" class="btn-check" name="showLimit" id="limit20" value="20" autocomplete="off">
                        <label class="btn btn-outline-secondary" for="limit20">20</label>
                        <input type="radio" class="btn-check" name="showLimit" id="limit50" value="50" autocomplete="off">
                        <label class="btn btn-outline-secondary" for="limit50">50</label>
                        <input type="radio" class="btn-check" name="showLimit" id="limit100" value="100" checked autocomplete="off">
                        <label class="btn btn-outline-secondary" for="limit100">100</label>
                        <input type="radio" class="btn-check" name="showLimit" id="limitAll" value="500" autocomplete="off">
                        <label class="btn btn-outline-secondary" for="limitAll">ALL</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="syncOnlinePaymentsBtn" title="Run sync now (e.g. if automatic sync failed)">
                        Sync Now
                    </button>
                    <a href="#" id="downloadRecentSyncedBtn" class="btn btn-outline-success btn-sm" title="Download recent synced payments as CSV (respects current filters)">
                        Download
                    </a>
                </div>

                <div id="onlinePaymentsTableWrap" class="mb-3">
                    <div class="card shadow border-0">
                        <div class="card-header bg-light">
                            <strong id="onlinePaymentsTableTitle">Payments</strong>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-striped table-hover mb-0" id="onlinePaymentsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Account No</th>
                                        <th>Reference No</th>
                                        <th>Payor</th>
                                        <th>Amount</th>
                                        <th>Paid At</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody><tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                            </table>
                        </div>
                        <div class="card-footer text-muted small" id="onlinePaymentsTableFooter"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('script')
    <script>
        $(function () {
            function getCsrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            }

            // Build month dropdown (last 24 months)
            (function () {
                var sel = document.getElementById('filterMonth');
                var d = new Date();
                var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
                for (var i = 0; i < 24; i++) {
                    var y = d.getFullYear();
                    var m = d.getMonth();
                    var val = y + '-' + String(m + 1).padStart(2, '0');
                    var opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = months[m] + ' ' + y;
                    if (i === 0) opt.selected = true;
                    sel.appendChild(opt);
                    d.setMonth(d.getMonth() - 1);
                }
            })();

            function getMonthRange(ym) {
                if (!ym) return { date_from: '', date_to: '' };
                var parts = ym.split('-');
                var y = parseInt(parts[0], 10);
                var m = parseInt(parts[1], 10);
                var dateFrom = y + '-' + String(m).padStart(2, '0') + '-01';
                var lastDay = new Date(y, m, 0).getDate();
                var dateTo = y + '-' + String(m).padStart(2, '0') + '-' + String(lastDay).padStart(2, '0');
                return { date_from: dateFrom, date_to: dateTo };
            }

            function getFilterParams() {
                var ym = document.getElementById('filterMonth').value;
                var range = getMonthRange(ym);
                var limitEl = document.querySelector('input[name="showLimit"]:checked');
                var limit = limitEl ? limitEl.value : '100';
                var params = {
                    account_no: document.getElementById('filterAccountNo').value.trim() || '',
                    reference_no: document.getElementById('filterReferenceNo').value.trim() || '',
                    date_from: range.date_from,
                    date_to: range.date_to,
                    limit: limit
                };
                if (typeof console !== 'undefined' && console.log) {
                    console.log('[OnlinePayments] getFilterParams', { ym: ym, range: range, params: params });
                }
                return params;
            }

            function renderPaymentsTable(payments, title, footerText) {
                var wrap = document.getElementById('onlinePaymentsTableWrap');
                var titleEl = document.getElementById('onlinePaymentsTableTitle');
                var tbody = document.querySelector('#onlinePaymentsTable tbody');
                var footer = document.getElementById('onlinePaymentsTableFooter');
                if (!wrap || !tbody) return;
                titleEl.textContent = title;
                footer.textContent = footerText || '';
                tbody.innerHTML = '';
                if (!payments || payments.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No payments</td></tr>';
                } else {
                    payments.forEach(function (p) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + (p.account_no || '-') + '</td>' +
                            '<td>' + (p.reference_no || '-') + '</td>' +
                            '<td>' + (p.payor_name || '-') + '</td>' +
                            '<td>' + (p.amount != null ? Number(p.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 }) : '-') + '</td>' +
                            '<td>' + (p.paid_at || '-') + '</td>' +
                            '<td>' + (p.status || '-') + '</td>';
                        tbody.appendChild(tr);
                    });
                }
            }

            var currentView = 'ready';

            function loadData() {
                var tbody = document.querySelector('#onlinePaymentsTable tbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Loading…</td></tr>';
                var filterParams = getFilterParams();
                var params = new URLSearchParams(filterParams);
                var url = currentView === 'ready'
                    ? '{{ route("admin.payments-ready-to-sync") }}?' + params
                    : '{{ route("admin.recent-synced-payments") }}?' + params;
                if (typeof console !== 'undefined' && console.log) {
                    console.log('[OnlinePayments] loadData', { view: currentView, url: url, queryString: params.toString() });
                }
                fetch(url, { method: 'GET', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var payments = data.payments || [];
                        var title = currentView === 'ready'
                            ? 'Payments ready to sync (not yet synced)'
                            : 'Recent synced payments (paid)';
                        var footer = currentView === 'ready'
                            ? 'Total: ' + (data.count || 0) + ' payment(s)'
                            : 'Showing ' + (data.count || 0) + ' payment(s).';
                        if (typeof console !== 'undefined' && console.log) {
                            console.log('[OnlinePayments] loadData response', { count: data.count, view: currentView });
                        }
                        renderPaymentsTable(payments, title, footer);
                    })
                    .catch(function (err) {
                        renderPaymentsTable([], 'Error', 'Failed to load: ' + (err.message || 'Unknown error'));
                    });
            }

            document.querySelectorAll('input[name="viewMode"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    currentView = this.value;
                    loadData();
                });
            });

            document.getElementById('filterMonth').addEventListener('change', loadData);
            document.getElementById('filterAccountNo').addEventListener('input', function () { clearTimeout(window._onlinePayDebounce); window._onlinePayDebounce = setTimeout(loadData, 400); });
            document.getElementById('filterReferenceNo').addEventListener('input', function () { clearTimeout(window._onlinePayDebounce); window._onlinePayDebounce = setTimeout(loadData, 400); });
            document.querySelectorAll('input[name="showLimit"]').forEach(function (radio) {
                radio.addEventListener('change', loadData);
            });

            function updateDownloadLink() {
                var params = new URLSearchParams(getFilterParams());
                var url = '{{ route("admin.download-recent-synced-payments") }}?' + params.toString();
                document.getElementById('downloadRecentSyncedBtn').setAttribute('href', url);
            }
            updateDownloadLink();
            document.getElementById('filterMonth').addEventListener('change', updateDownloadLink);
            document.getElementById('filterAccountNo').addEventListener('input', updateDownloadLink);
            document.getElementById('filterReferenceNo').addEventListener('input', updateDownloadLink);
            document.querySelectorAll('input[name="showLimit"]').forEach(function (radio) {
                radio.addEventListener('change', updateDownloadLink);
            });

            loadData();

            document.getElementById('syncOnlinePaymentsBtn').addEventListener('click', function () {
                if (!confirm('Sync Novupay online payments to Sta-Rita (readings + bills) now?')) return;
                var btn = this;
                btn.disabled = true;
                btn.textContent = 'Syncing…';
                fetch('{{ route("admin.sync-online-payments") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({})
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.status === 'error') {
                            renderPaymentsTable([], 'Sync failed', data.message || 'Unknown error');
                            return;
                        }
                        renderPaymentsTable(
                            data.payments || [],
                            'Payments synced',
                            'Synced ' + (data.count || 0) + ' payment(s). ' + (data.message || '')
                        );
                        currentView = 'ready';
                        document.getElementById('viewReady').checked = true;
                    })
                    .catch(function (err) {
                        renderPaymentsTable([], 'Error', 'Sync failed: ' + (err.message || 'Unknown error'));
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.textContent = 'Sync Now';
                    });
            });
        });
    </script>
@endsection
