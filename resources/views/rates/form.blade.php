@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                @if(isset($data))
                    <h1>Update Admin</h1>
                @else
                    <h1>Add New Water Rate</h1>
                @endif
                <a href="{{route('rates.index')}}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Go Back
                </a>
            </div>
            <div class="inner-content mt-5">
                <form action="{{ route('rates.store') }}" method="POST">
                    @csrf
                    @if(isset($data))
                        @method('PUT')
                    @endif       
                    <div class="row d-flex justify-content-center">
                        <div class="col-12 col-md-7 mb-3">
                            <div class="card shadow border-0 p-2">
                                <div class="card-header border-0 bg-transparent">
                                    <div class="text-uppercase fw-bold">Rate Information</div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-12 mb-3">
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
                                        <div class="col-md-6 mb-3">
                                            <label for="cubic_meter" class="form-label fw-bold">Cubic Meter</label>
                                            <input type="number" class="form-control @error('cubic_meter') is-invalid @enderror" id="cubic_meter" name="cubic_meter" value="{{ old('cubic_meter', $data->cubic_meter ?? '') }}" placeholder="Enter cubic meter">
                                            @error('cubic_meter')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="charge" class="form-label fw-bold">Charge</label>
                                            <input type="number" class="form-control @error('charge') is-invalid @enderror" id="charge" name="charge" value="{{ old('charge', $data->charge ?? '') }}" placeholder="Enter charge">
                                            @error('charge')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-end my-5">
                        <button type="submit" class="btn btn-primary px-5 py-3 text-uppercase fw-bold">Submit</button>
                    </div>
                </form>
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
        });
    </script>
@endsection

