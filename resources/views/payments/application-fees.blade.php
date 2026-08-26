@extends('layouts.app')

@section('content')
    <div id="page-loader" style="
        display:none; position:fixed; inset:0; background:rgba(255,255,255,0.7);
        z-index:9999; align-items:center; justify-content:center;
    ">
        <div class="text-center">
            <div class="spinner-border text-primary" style="width:3rem;height:3rem;" role="status"></div>
            <div class="mt-3 fw-bold text-uppercase">Loading...</div>
        </div>
    </div>
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>{{ ucfirst($status) }} Application Fees</h1>
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('payments.index') }}"
                        class="btn btn-outline-primary px-5 py-3 text-uppercase">
                        Back to Bill Payments
                    </a>
                    <a href="{{ route('payments.application-fees.index', array_merge(request()->query(), ['status' => $status === 'paid' ? 'unpaid' : 'paid'])) }}"
                        class="btn btn-primary px-5 py-3 text-uppercase">
                        View {{ $status === 'paid' ? 'Unpaid' : 'Paid' }}
                    </a>
                </div>
            </div>
            <div class="inner-content mt-5 pb-5 mb-5">
                <div class="row mb-4">
                    <div class="col-12 col-md-2">
                        <label class="mb-1">Show Entries</label>
                        <select name="entries" id="entries" class="form-select text-uppercase dropdown-toggle">
                            @foreach([10, 25, 50, 100, 200] as $entry)
                                <option value="{{ $entry }}" {{ $entries == $entry ? 'selected' : '' }}>
                                    {{ $entry }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="mb-1">Search <span class="text-muted ms-1">[application no | applicant name]</span></label>
                        <div class="position-relative">
                            <input type="text" name="search" id="search" class="form-control pe-5"
                                value="{{ $toSearch }}">
                            @if(!empty($toSearch))
                                <button type="button" id="clear-search"
                                    class="btn position-absolute top-50 end-0 translate-middle-y me-2 p-0 text-muted"
                                    style="border: none; background: none; font-size: 1.2rem;">&times;</button>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped w-100 mt-4">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Application No</th>
                                <th>Applicant Name</th>
                                <th>Type</th>
                                <th>Service Address</th>
                                <th>Fee Amount</th>
                                <th>Status</th>
                                <th>Filed On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($data as $index => $row)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $row->application_no ?? 'N/A' }}</td>
                                    <td>{{ $row->applicant_name }}</td>
                                    <td>
                                        {{ $row->application_type === 'Others' ? $row->application_type_other : $row->application_type }}
                                    </td>
                                    <td>{{ \Illuminate\Support\Str::limit($row->service_address, 40) }}</td>
                                    <td>₱{{ number_format((float) $row->application_fee_amount, 2) }}</td>
                                    <td>
                                        @if ($row->application_fee_status === 'paid')
                                            <div class="alert alert-primary mb-0 py-1 px-2 text-center text-uppercase">Paid</div>
                                        @else
                                            <div class="alert alert-danger mb-0 py-1 px-2 text-center text-uppercase">{{ $row->application_fee_status }}</div>
                                        @endif
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('M d, Y') }}</td>
                                    <td>
                                        @if ($row->application_fee_status !== 'paid')
                                            <a href="{{ route('payments.application-fees.pay', $row->id) }}"
                                                class="btn btn-primary text-white text-uppercase fw-bold">
                                                <i class="bx bx-credit-card-alt"></i>
                                            </a>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9">
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
@endsection

@section('script')
<script>
    $(function () {
        function updateUrl() {
            const params = new URLSearchParams(window.location.search);
            ['search', 'entries'].forEach(id => {
                const val = $('#' + id).val();
                val ? params.set(id, val) : params.delete(id);
            });
            $('#page-loader').css('display', 'flex');
            window.location.href = window.location.pathname + '?' + params.toString();
        }

        let searchTimer = null;
        $('#search').on('keyup', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(updateUrl, 400);
        });

        $('#entries').on('change', updateUrl);

        $('#clear-search').on('click', function () {
            $('#search').val('');
            updateUrl();
        });
    });
</script>
@endsection
