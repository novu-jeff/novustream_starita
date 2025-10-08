@extends('layouts.app')

@section('content')
    <main class="main">
      <div class="container-fluid my-4">
        <div id="zones-container" class="row row-cols-3 row-cols-md-4 row-cols-lg-auto g-3 justify-content-center">
            @foreach($zones as $zone)
                <div class="col">
                    <div class="card h-100 shadow-sm text-center border border-primary-subtle">
                        <div class="card-body d-flex flex-column justify-content-center py-3 px-2">
                            <div class="fw-bold text-primary fs-6">
                                {{ $zone->read_count ?? 0 }} / {{ $zone->total_accounts }}
                            </div>
                            <div class="text-uppercase text-muted mt-1 small">
                                {{ $zone->zone }} - {{ $zone->area }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>All Meter Readings</h1>
            </div>
            <div class="inner-content mt-5 pb-5 mb-5">
                <div class="row mb-4">
                    <div class="col-12 col-md-1">
                        <label class="mb-1">Show Entries</label>
                        <select name="entries" id="entries" class="form-select text-uppercase dropdown-toggle">
                            @foreach([10, 25, 50, 100, 200, 250, 350, 400, 450, 500] as $entry)
                                <option value="{{ $entry }}" {{ $entries == $entry ? 'selected' : '' }}>
                                    {{ $entry }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label class="mb-1">Zone</label>
                        <select name="zone_no" id="zone_no" class="form-select text-uppercase dropdown-toggle">
                            <option value="all" {{ $zone === 'all' ? 'selected' : '' }}>All Zones</option>

                            @foreach($zones as $targetedZone)
                                <option value="{{ $targetedZone->zone }}" {{ $zone === $targetedZone->zone ? 'selected' : '' }}>
                                    {{ $targetedZone->zone }} - {{ $targetedZone->area ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3 mb-3">
                        <label class="mb-1">Reading Month</label>
                        <input type="month" name="month" id="date" class="form-control" value="{{$date}}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="mb-1">Search <span class="text-muted ms-1">[name | account no]</span></label>
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
                </div>
                <table class="w-100 table table-bordered table-hover mt-4">
                    <thead>
                        <tr>
                            <th>Reference No</th>
                            <th>Account No</th>
                            <th>Name</th>
                            <th>Previous Reading</th>
                            <th>Present Reading</th>
                            <th>Consumption</th>
                            <th>Reading Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data as $index => $row)
                             <tr>
                                <td>{{ $row->bill->reference_no ?? 'N/A' }}</td>
                                <td>{{ $row->account_no }}</td>
                                <td>{{ $row->concessionaire->user->name ?? 'N/A' }}</td>
                                <td>{{ $row->previous_reading }}</td>
                                <td>{{ $row->present_reading }}</td>
                                <td>{{ $row->consumption }} m³</td>
                                <td>{{ \Carbon\Carbon::parse($row->created_at)->format('F d, Y') }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="{{ $row->bill ? route('reading.show', $row->bill->reference_no) : '#' }}"
                                        class="btn btn-primary text-white text-uppercase fw-bold"
                                        id="show-btn" data-id="{{ $row->id }}"
                                        {{ $row->bill ? '' : 'disabled' }}>
                                            <i class="bx bx-receipt"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted">No records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div id="pagination-wrapper" class="d-flex w-100 mt-4 justify-content-between align-items-center">
                    <div id="pagination-info" class="text-muted"></div>
                    <div id="pagination-links"></div>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('script')
<script>
$(function () {

    function updateFilters(page = 1) {
        const data = {
            search: $('#search').val(),
            entries: $('#entries').val(),
            zone: $('#zone_no').val(),
            date: $('#date').val(),
            page
        };

        $.ajax({
            url: "{{ route('reading.report') }}",
            type: "GET",
            data,
            success: function(res) {
                renderTable(res.data, res.pagination);
                window.latestZones = res.zones;
                renderZones(window.latestZones, parseInt($('#entries').val()), 1);
            },
            error: function(err) {
                console.log(err);
                alert('Failed to fetch readings.');
            }
        });
    }

    function renderTable(rows, pagination) {
        const tbody = $('table tbody');
        tbody.empty();

        if (!rows.length) {
            tbody.append('<tr><td colspan="12" class="text-center text-muted">No records found.</td></tr>');
        } else {
            rows.forEach(row => {
                tbody.append(`
                    <tr>
                        <td>${row.bill?.reference_no ?? 'N/A'}</td>
                        <td>${row.account_no}</td>
                        <td>${row.concessionaire?.user?.name ?? 'N/A'}</td>
                        <td>${row.previous_reading}</td>
                        <td>${row.present_reading}</td>
                        <td>${row.consumption} m³</td>
                        <td>${new Date(row.created_at).toLocaleDateString('en-US', { month:'long', day:'numeric', year:'numeric' })}</td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <a href="{{ $row->bill ? route('reading.show', $row->bill->reference_no) : '#' }}"
                                class="btn btn-primary text-white text-uppercase fw-bold"
                                id="show-btn" data-id="{{ $row->id }}"
                                {{ $row->bill ? '' : 'disabled' }}>
                                    <i class="bx bx-receipt"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                `);
            });
        }

        renderPagination(pagination);
    }

    function renderZones(zones) {
    const container = $('#zones-container');
    container.empty();

    zones.forEach(zone => {
        container.append(`
            <div class="col">
                <div class="card h-100 shadow-sm text-center border border-primary-subtle">
                    <div class="card-body d-flex flex-column justify-content-center py-3 px-2">
                        <div class="fw-bold text-primary fs-6">
                            ${zone.read_count ?? 0} / ${zone.total_accounts}
                        </div>
                        <div class="text-uppercase text-muted mt-1 small">
                            ${zone.zone} - ${zone.area ?? ''}
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
}


    function renderPagination(pagination) {
        const wrapper = $('#pagination-wrapper');
        wrapper.empty();

        if (!pagination || pagination.last_page <= 1) return;

        // Showing X to Y of Z results
        const start = (pagination.current_page - 1) * pagination.per_page + 1;
        const end = Math.min(start + pagination.per_page - 1, pagination.total);
        wrapper.append(`<div class="mb-2 text-center text-muted">Showing ${start} to ${end} of ${pagination.total} results</div>`);

        let html = '<nav><ul class="pagination justify-content-center">';

        // Previous button
        html += `<li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                    <a href="#" class="page-link" data-page="${pagination.current_page - 1}">&lsaquo;</a>
                 </li>`;

       const totalPages = pagination.last_page;
        const maxPagesToShow = 9; // change this to however many pages you want to show

        let startPage = 1;
        let endPage = Math.min(totalPages, maxPagesToShow);

        if (totalPages > maxPagesToShow) {
            // If current page is beyond half of maxPagesToShow, slide the window
            if (pagination.current_page > Math.floor(maxPagesToShow / 2)) {
                startPage = Math.max(1, pagination.current_page - Math.floor(maxPagesToShow / 2));
                endPage = Math.min(totalPages, startPage + maxPagesToShow - 1);
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                        <a href="#" class="page-link" data-page="${i}">${i}</a>
                    </li>`;
        }

        if (endPage < totalPages) {
            html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            html += `<li class="page-item"><a href="#" class="page-link" data-page="${totalPages}">${totalPages}</a></li>`;
        }

        // Next button
        html += `<li class="page-item ${pagination.current_page === totalPages ? 'disabled' : ''}">
                    <a href="#" class="page-link" data-page="${pagination.current_page + 1}">&rsaquo;</a>
                 </li>`;

        html += '</ul></nav>';
        wrapper.append(html);
    }

    // Event listeners
    $('#search, #entries, #zone_no, #date').on('change keyup', () => updateFilters(1));
    $('#clear-search').on('click', () => {
        $('#search').val('');
        updateFilters(1);
    });

    $(document).on('click', '#pagination-wrapper .page-link', function(e) {
        e.preventDefault();
        const page = $(this).data('page');
        if (page > 0) updateFilters(page);
    });

    // Initial fetch
    updateFilters();
});
</script>

@endsection
