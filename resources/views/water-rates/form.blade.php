@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                @if(isset($data))
                    <h1>Update Water Rate</h1>
                @else
                    <h1>Add New Water Rate</h1>
                @endif
                <a href="{{route('water-rates.index')}}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Go Back
                </a>
            </div>
            <div class="inner-content mt-5">
                <form action="{{ isset($data) ? route('water-rates.update', $data->id) : route('water-rates.store') }}" method="POST">
                    @csrf
                    @if(isset($data))
                        @method('PUT')
                    @endif                
                    <div class="row">
                        <div class="col-12 col-md-12 mb-3">
                            <div class="card shadow border-0 p-2">
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-12 mb-3">
                                            <label for="property_type" class="form-label">Type</label>
                                            <select class="form-control text-uppercase @error('property_type') is-invalid @enderror" id="property_type" name="property_type">
                                                <option value=""> - CHOOSE - </option>
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
                                        <div class="col-md-6 mb-3">
                                            <label for="cubic_from" class="form-label">From ( m³ )</label>
                                            <input type="text" class="form-control @error('cubic_from') is-invalid @enderror" id="cubic_from" name="cubic_from" value="{{ old('cubic_from', $data->cubic_from ?? '') }}">
                                            @error('cubic_from')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="cubic_to" class="form-label">To ( m³ )</label>
                                            <input type="text" class="form-control @error('cubic_to') is-invalid @enderror" id="cubic_to" name="cubic_to" value="{{ old('cubic_to', $data->cubic_to ?? '') }}">
                                            @error('cubic_to')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label for="rate" class="form-label">Rate</label>
                                            <input type="text" class="form-control @error('rate') is-invalid @enderror" id="rate" name="rate" value="{{ old('rate', $data->rates ?? '') }}">
                                            @error('rate')
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
