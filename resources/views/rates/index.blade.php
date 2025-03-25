@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>{{ $app_type }} Rate</h1>
            </div>
            <div class="inner-content mt-5">
                <div class="row g-3">
                    <div class="col-md-6 col-sm-12 col-lg-5">
                        <div class="card shadow">
                            <div class="card-header border-0 m-0 pt-3">
                                <h6 class="card-title mb-0 text-uppercase fw-bold">Form</h6>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('rates.store') }}" method="POST">
                                    @csrf  
                                    <div class="row g-3">
                                        <div class="col-md-12">
                                            <div class="property_type_dropdown">
                                                <label for="property_type" class="form-label fw-bold">Type</label>
                                                <select class="form-select text-uppercase @error('property_type') is-invalid @enderror" id="property_type" name="property_type">
                                                    @foreach($property_types as $property_type)
                                                        <option value="{{ $property_type->id }}" {{ old('property_type', $data->property_types_id ?? '') == $property_type->id ? 'selected' : '' }}>
                                                            {{ $property_type->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('property_type')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="cubic_from" class="form-label fw-bold">From ( m³ )</label>
                                            <input type="number" class="form-control @error('cubic_from') is-invalid @enderror" id="cubic_from" name="cubic_from" value="{{ old('cubic_from', $data->cubic_from ?? '') }}" placeholder="Enter starting cubic meter">
                                            @error('cubic_from')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label for="cubic_to" class="form-label fw-bold">To ( m³ )</label>
                                            <input type="number" class="form-control @error('cubic_to') is-invalid @enderror" id="cubic_to" name="cubic_to" value="{{ old('cubic_to', $data->cubic_to ?? '') }}" placeholder="Enter ending cubic meter">
                                            @error('cubic_to')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12">
                                            <label for="charge" class="form-label fw-bold">Charge</label>
                                            <input type="number" step="0.01" class="form-control @error('charge') is-invalid @enderror" id="charge" name="charge" value="{{ old('charge', $data->charge ?? '') }}" placeholder="Enter charge amount">
                                            @error('charge')
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
                    <div class="col-md-6 col-sm-12 col-lg-7">
                        <div class="card shadow">
                            <div class="card-header border-0 m-0 pt-3">
                                <h6 class="card-title mb-0 text-uppercase fw-bold">Water Rates</h6>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered text-center">
                                    <thead class="table-dark">
                                        <tr>
                                            <th>Cu / M</th>
                                            <th>COMM Charge</th>
                                            <th>AMOUNT</th>
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
                    { data: 'cu_m', name: 'cu_m' }, 
                    { data: 'charge', name: 'charge' }, 
                    { data: 'amount', name: 'amount' }, 
                ],
                responsive: true,
                order: [[0, 'asc']],
                scrollX: true
            });

            $('#property_type').change(function () {
                table.ajax.reload();
            });

        });
    </script>
@endsection