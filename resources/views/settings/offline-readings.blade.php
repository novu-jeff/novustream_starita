@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper pb-5">
            <div class="main-header d-flex justify-content-between flex-wrap align-items-center gap-2">
                <h1>Offline Readings</h1>
            </div>
            <p class="text-muted small mt-1">Merge pending readings from <code>Offline Device</code> into readings and bills.</p>

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
                                <label class="form-label small mb-0">Account No</label>
                                <input type="text" class="form-control form-control-sm" id="filterAccountNo" placeholder="Account no">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small mb-0">Reference No</label>
                                <input type="text" class="form-control form-control-sm" id="filterReferenceNo" placeholder="Reference no">
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label small mb-0">Source</label>
                                <select class="form-select form-select-sm" id="filterSource">
                                    <option value="">All</option>
                                    <option value="novupay">Novupay</option>
                                    <option value="mobile_app">Mobile App</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex flex-wrap gap-2 mb-4 align-items-center">
                    <span class="text-muted small me-2">View:</span>
                    <div class="btn-group btn-group-sm" role="group" id="viewGroup">
                        <input type="radio" class="btn-check" name="viewMode" id="viewReady" value="ready" checked autocomplete="off">
                        <label class="btn btn-outline-primary" for="viewReady">Ready to merge</label>
                        <input type="radio" class="btn-check" name="viewMode" id="viewRecent" value="recent" autocomplete="off">
                        <label class="btn btn-outline-secondary" for="viewRecent">Recent merged</label>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" id="runMergeBtn">
                        Run Merge Now
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="dryRunBtn">
                        Dry Run
                    </button>
                    <label class="mb-0 small text-muted ms-2">Merge limit:</label>
                    <select class="form-select form-select-sm ms-1" id="mergeLimit" style="width: auto;">
                        <option value="50">50</option>
                        <option value="100">100</option>
                        <option value="200">200</option>
                        <option value="500" selected>500</option>
                    </select>
                </div>

                <div id="offlineReadingsTableWrap" class="mb-3">
                    <div class="card shadow border-0">
                        <div class="card-header bg-light">
                            <strong id="offlineReadingsTableTitle">Readings</strong>
                        </div>
                        <div class="card-body p-0 table-responsive">
                            <table class="table table-striped table-hover mb-0" id="offlineReadingsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Reference No</th>
                                        <th>Account No</th>
                                        <th>Previous</th>
                                        <th>Present</th>
                                        <th>Consumption</th>
                                        <th>Source</th>
                                        <th>Reader</th>
                                        <th>Created</th>
                                        <th>Updated</th>
                                    </tr>
                                </thead>
                                <tbody><tr><td colspan="9" class="text-center text-muted py-4">Loading…</td></tr></tbody>
                            </table>
                        </div>
                        <div class="card-footer text-muted small" id="offlineReadingsTableFooter"></div>
                    </div>
                </div>

                <div id="mergeOutputWrap" class="mb-3" style="display: none;">
                    <div class="card shadow border-0">
                        <div class="card-header bg-light"><strong>Merge output</strong></div>
                        <div class="card-body">
                            <pre class="mb-0 small bg-dark text-light p-3 rounded" id="mergeOutput"></pre>
                        </div>
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
                return {
                    account_no: document.getElementById('filterAccountNo').value.trim() || '',
                    reference_no: document.getElementById('filterReferenceNo').value.trim() || '',
                    source: document.getElementById('filterSource').value || '',
                    date_from: range.date_from,
                    date_to: range.date_to,
                    limit: '500'
                };
            }

            function renderReadingsTable(readings, title, footerText) {
                var wrap = document.getElementById('offlineReadingsTableWrap');
                var titleEl = document.getElementById('offlineReadingsTableTitle');
                var tbody = document.querySelector('#offlineReadingsTable tbody');
                var footer = document.getElementById('offlineReadingsTableFooter');
                var outputWrap = document.getElementById('mergeOutputWrap');
                if (outputWrap) outputWrap.style.display = 'none';
                if (!wrap || !tbody) return;
                titleEl.textContent = title;
                footer.textContent = footerText || '';
                tbody.innerHTML = '';
                if (!readings || readings.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted">No readings</td></tr>';
                } else {
                    readings.forEach(function (r) {
                        var tr = document.createElement('tr');
                        tr.innerHTML =
                            '<td>' + (r.reference_no || '-') + '</td>' +
                            '<td>' + (r.account_no || '-') + '</td>' +
                            '<td>' + (r.previous_reading != null ? r.previous_reading : '-') + '</td>' +
                            '<td>' + (r.present_reading != null ? r.present_reading : '-') + '</td>' +
                            '<td>' + (r.consumption != null ? r.consumption : '-') + '</td>' +
                            '<td>' + (r.source || '-') + '</td>' +
                            '<td>' + (r.reader_name || '-') + '</td>' +
                            '<td>' + (r.created_at || '-') + '</td>' +
                            '<td>' + (r.updated_at || '-') + '</td>';
                        tbody.appendChild(tr);
                    });
                }
                wrap.style.display = 'block';
            }

            function showMergeOutput(text, title) {
                document.getElementById('offlineReadingsTableWrap').style.display = 'none';
                var wrap = document.getElementById('mergeOutputWrap');
                var pre = document.getElementById('mergeOutput');
                if (pre) pre.textContent = text || '(no output)';
                wrap.style.display = 'block';
            }

            var currentView = 'ready';

            function loadData() {
                var tbody = document.querySelector('#offlineReadingsTable tbody');
                if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center text-muted py-4">Loading…</td></tr>';
                var params = new URLSearchParams(getFilterParams());
                var url = currentView === 'ready'
                    ? '{{ route("admin.readings-ready-to-merge") }}?' + params
                    : '{{ route("admin.recent-merged-readings") }}?' + params;
                fetch(url, { method: 'GET', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var readings = data.readings || [];
                        var title = currentView === 'ready'
                            ? 'Readings ready to merge (pending)'
                            : 'Recent merged readings';
                        var footer = currentView === 'ready'
                            ? 'Total: ' + (data.count || 0) + ' reading(s)'
                            : 'Showing ' + (data.count || 0) + ' reading(s).';
                        renderReadingsTable(readings, title, footer);
                    })
                    .catch(function (err) {
                        renderReadingsTable([], 'Error', 'Failed to load: ' + (err.message || 'Unknown error'));
                    });
            }

            document.querySelectorAll('input[name="viewMode"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    currentView = this.value;
                    loadData();
                });
            });

            document.getElementById('filterMonth').addEventListener('change', loadData);
            document.getElementById('filterAccountNo').addEventListener('input', function () { clearTimeout(window._offlineReadDebounce); window._offlineReadDebounce = setTimeout(loadData, 400); });
            document.getElementById('filterReferenceNo').addEventListener('input', function () { clearTimeout(window._offlineReadDebounce); window._offlineReadDebounce = setTimeout(loadData, 400); });
            document.getElementById('filterSource').addEventListener('change', loadData);

            loadData();

            document.getElementById('runMergeBtn').addEventListener('click', function () {
                if (!confirm('Run readings:merge now? This will merge pending readings_offline into readings and create/update bills.')) return;
                const btn = this;
                btn.disabled = true;
                btn.textContent = 'Merging…';
                const mergeLimit = document.getElementById('mergeLimit').value;
                fetch('{{ route("admin.run-merge") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ limit: parseInt(mergeLimit, 10) })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.status === 'error') {
                            showMergeOutput(data.message || '', 'Error');
                            return;
                        }
                        showMergeOutput(data.output || '', 'Merge output');
                        if (data.count > 0) loadData();
                    })
                    .catch(function (err) {
                        showMergeOutput('Request failed: ' + (err.message || 'Unknown error'), 'Error');
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.textContent = 'Run Merge Now';
                    });
            });

            document.getElementById('dryRunBtn').addEventListener('click', function () {
                const btn = this;
                btn.disabled = true;
                btn.textContent = 'Running…';
                const mergeLimit = document.getElementById('mergeLimit').value;
                fetch('{{ route("admin.run-merge") }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ limit: parseInt(mergeLimit, 10), dry_run: true })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        showMergeOutput(data.output || data.message || '', 'Dry run output');
                    })
                    .catch(function (err) {
                        showMergeOutput('Request failed: ' + (err.message || 'Unknown error'), 'Error');
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.textContent = 'Dry Run';
                    });
            });
        });
    </script>
@endsection
