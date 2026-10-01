@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2>Generate Reports</h2>

    <form action="{{ route('reports.download.generate') }}" method="POST" class="mt-3">
        @csrf

        <div class="mb-3">
            <label class="form-label">Pick reports</label>

            {{-- Select All Checkbox --}}
            <!-- <div class="form-check">
                <input class="form-check-input" type="checkbox" id="select-all-reports">
                <label class="form-check-label mt-1" for="select-all-reports">
                    Select All
                </label>
            </div> -->

            <div class="row">
                @foreach($availableReports as $report)
                <div class="col-md-4">
                    <div class="form-check ">
                        <input class="form-check-input report-checkbox" type="checkbox" value="{{ $report }}" name="reports[]" id="report-{{ \Illuminate\Support\Str::slug($report) }}">
                        <label class="form-check-label mt-1" for="report-{{ \Illuminate\Support\Str::slug($report) }}">
                            {{ $report }}
                        </label>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <label class="form-label fw-medium">Mode</label>
                <select name="mode" class="form-select">
                    <option value="combined">Combined (all reports in one file)</option>
                    <option value="separate">Separate (one file per report, returned as ZIP)</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-medium">Sheet Grouping</label>
                <select name="sheet_grouping" class="form-select">
                    <option value="all">All Months in One Sheet</option>
                    <option value="monthly">Separate Sheet per Month</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-medium">Format</label>
                <select name="format" class="form-select">
                    <option value="xlsx">Excel (.xlsx)</option>
                    <option value="csv">CSV (.csv)</option>
                    <option value="plain">Plain Sheet (.xlsx)</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-medium">Start Date</label>
                <input type="date" name="start_date" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-medium">End Date</label>
                <input type="date" name="end_date" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label fw-medium">Zone <span class="text-danger">*</span></label>
                <select name="zone" class="form-select" required>
                    <option value="">Select Zone</option>
                    <option value="all">All Zones</option>
                    @foreach ($zones as $z)
                        <option value="{{ $z }}">{{ $z }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-4">
            <button type="submit" class="btn btn-primary px-4">Generate</button>
        </div>
    </form>
</div>
@endsection

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const selectAll = document.getElementById('select-all-reports');
        const checkboxes = document.querySelectorAll('.report-checkbox');

        selectAll.addEventListener('change', function () {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
        });
    });
</script>
