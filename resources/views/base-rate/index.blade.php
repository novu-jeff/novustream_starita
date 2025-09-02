@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Base Rate</h1>
                @if (config('app.product') == 'novustream')
                    <a class="btn btn-outline-primary px-5 py-3 text-uppercase" href="{{ route('rates.index') }}">
                        GO TO WATER RATES
                    </a>
                @endif
            </div>
            <div class="inner-content mt-5 mb-5">
                <div class="row g-3">
                    <div class="col-md-6 col-sm-12  col-lg-5">
                        <div class="card shadow border-0">
                            <div class="card-header border-0 m-0 pt-3">
                                <h6 class="card-title mb-0 text-uppercase fw-bold">Form</h6>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('base-rate.store') }}" method="POST">
                                    @csrf
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <label for="property_type" class="form-label fw-bold">Property Type</label>
                                            <select class="form-select text-uppercase @error('property_type') is-invalid @enderror" id="property_type" name="property_type">
                                                @foreach($property_types as $property_type)
                                                    <option value="{{ $property_type->id }}">
                                                        {{ $property_type->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('property_type')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12">
                                            <label for="rate" class="form-label fw-bold">Rate</label>
                                            <input type="number" step="0.01" class="form-control @error('rate') is-invalid @enderror" id="rate" name="rate" value="{{ old('rate', $data->rate ?? '') }}" placeholder="Enter rate amount">
                                            @error('rate')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="d-flex justify-content-end mt-4">
                                        <button type="submit" class="btn btn-primary px-5 py-3 text-uppercase fw-bold">Update</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-sm-12  col-lg-7">
                        <div class="card shadow border-0">
                            <div class="card-header border-0 m-0 pt-3">
                                <h6 class="card-title mb-0 text-uppercase fw-bold">Base Rate History</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered text-center">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Base Rate</th>
                                            <th>Status</th>
                                            <th>Month</th>
                                            <th>Year</th>
                                        </tr>
                                    </thead>
                                    <tbody>

                                    </tbody>
                                </table>
                            </div>
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

            @if (session('alert'))
                setTimeout(() => {
                    let alertData = @json(session('alert'));
                    alert(alertData.status, alertData.message);
                }, 100);
            @endif

            const url = '{{ route(Route::currentRouteName()) }}';

            let table = $('table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: url,
                    data: function (d) {
                        d.property_type = $('#property_type').val();
                    }
                },
                columns: [
                    { data: 'rate', name: 'rate' },
                    { data: 'status', name: 'status' },
                    { data: 'month_day', name: 'month_day' },
                    { data: 'year', name: 'year' },
                ],
                responsive: true,
                order: [[2, 'desc'], [1, 'desc']],
                scrollX: true
            });

            $('#property_type').change(function () {
                table.ajax.reload();
            });

        });
    </script>
@endsection
