@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper pb-5">
            <div class="main-header d-flex justify-content-between flex-wrap align-items-center gap-2">
                <h1>Online Payments</h1>
            </div>
            <div class="inner-content mt-4">
                <div class="d-flex flex-wrap gap-2 mb-4 align-items-center">
                    <button type="button" class="btn btn-outline-primary" id="checkPaymentsReadyBtn">
                        Check Payments Ready to Sync
                    </button>
                    <button type="button" class="btn btn-primary" id="syncOnlinePaymentsBtn">
                        Sync Online Payments Now
                    </button>
                    <span class="text-muted small ms-2">|</span>
                    <button type="button" class="btn btn-outline-secondary" id="getRecentSyncBtn">
                        Get Recent Sync
                    </button>
                    <label class="mb-0 small text-muted ms-1">Show:</label>
                    <div class="btn-group btn-group-sm ms-1" role="group" id="recentSyncLimitGroup">
                        <input type="radio" class="btn-check" name="recentSyncLimit" id="limit10" value="10" autocomplete="off">
                        <label class="btn btn-outline-secondary" for="limit10">10</label>
                        <input type="radio" class="btn-check" name="recentSyncLimit" id="limit20" value="20" checked autocomplete="off">
                        <label class="btn btn-outline-secondary" for="limit20">20</label>
                        <input type="radio" class="btn-check" name="recentSyncLimit" id="limit50" value="50" autocomplete="off">
                        <label class="btn btn-outline-secondary" for="limit50">50</label>
                        <input type="radio" class="btn-check" name="recentSyncLimit" id="limit100" value="100" autocomplete="off">
                        <label class="btn btn-outline-secondary" for="limit100">100</label>
                    </div>
                </div>

                <div id="onlinePaymentsTableWrap" class="mb-3" style="display: none;">
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
                                <tbody></tbody>
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

            function renderPaymentsTable(payments, title, footerText) {
                const wrap = document.getElementById('onlinePaymentsTableWrap');
                const titleEl = document.getElementById('onlinePaymentsTableTitle');
                const tbody = document.querySelector('#onlinePaymentsTable tbody');
                const footer = document.getElementById('onlinePaymentsTableFooter');
                if (!wrap || !tbody) return;
                titleEl.textContent = title;
                footer.textContent = footerText || '';
                tbody.innerHTML = '';
                if (!payments || payments.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No payments</td></tr>';
                } else {
                    payments.forEach(function (p) {
                        const tr = document.createElement('tr');
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
                wrap.style.display = 'block';
            }

            const checkBtn = document.getElementById('checkPaymentsReadyBtn');
            if (checkBtn) {
                checkBtn.addEventListener('click', function () {
                    const btn = this;
                    btn.disabled = true;
                    btn.textContent = 'Loading…';
                    fetch('{{ route("admin.payments-ready-to-sync") }}', {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            renderPaymentsTable(
                                data.payments || [],
                                'Payments ready to sync (not yet synced)',
                                'Total: ' + (data.count || 0) + ' payment(s)'
                            );
                        })
                        .catch(function (err) {
                            renderPaymentsTable([], 'Error', 'Failed to load: ' + (err.message || 'Unknown error'));
                        })
                        .finally(function () {
                            btn.disabled = false;
                            btn.textContent = 'Check Payments Ready to Sync';
                        });
                });
            }

            const syncBtn = document.getElementById('syncOnlinePaymentsBtn');
            if (syncBtn) {
                syncBtn.addEventListener('click', function () {
                    if (!confirm('Sync Novupay online payments to Sta-Rita (readings + bills) now?')) return;
                    const btn = this;
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
                        })
                        .catch(function (err) {
                            renderPaymentsTable([], 'Error', 'Sync failed: ' + (err.message || 'Unknown error'));
                        })
                        .finally(function () {
                            btn.disabled = false;
                            btn.textContent = 'Sync Online Payments Now';
                        });
                });
            }

            const getRecentBtn = document.getElementById('getRecentSyncBtn');
            if (getRecentBtn) {
                getRecentBtn.addEventListener('click', function () {
                    const btn = this;
                    const limitEl = document.querySelector('input[name="recentSyncLimit"]:checked');
                    const limit = limitEl ? limitEl.value : '20';
                    btn.disabled = true;
                    btn.textContent = 'Loading…';
                    fetch('{{ route("admin.recent-synced-payments") }}?limit=' + encodeURIComponent(limit), {
                        method: 'GET',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    })
                        .then(function (r) { return r.json(); })
                        .then(function (data) {
                            renderPaymentsTable(
                                data.payments || [],
                                'Recent synced payments (paid)',
                                'Showing last ' + (data.count || 0) + ' payment(s). Limit: ' + limit + '.'
                            );
                        })
                        .catch(function (err) {
                            renderPaymentsTable([], 'Error', 'Failed to load: ' + (err.message || 'Unknown error'));
                        })
                        .finally(function () {
                            btn.disabled = false;
                            btn.textContent = 'Get Recent Sync';
                        });
                });
            }
        });
    </script>
@endsection
