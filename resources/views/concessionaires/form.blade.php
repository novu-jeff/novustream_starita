@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                @if(isset($data))
                    <h1>Update Client</h1>
                @else
                    <h1>Add New Client</h1>
                @endif
                <a href="{{route('concessionaires.index')}}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Go Back
                </a>
            </div>
            <div class="inner-content mt-5">
                <form action="{{ isset($data) ? route('concessionaires.update', $data->id) : route('concessionaires.store') }}" method="POST">
                    @csrf
                    @if(isset($data))
                        @method('PUT')
                    @endif       
                    <div class="row">
                        <div class="col-12 col-md-7 mb-3">
                            <div class="card shadow border-0 p-2">
                                <div class="card-header border-0 bg-transparent">
                                    <div class="text-uppercase fw-bold">Personal Information</div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-12">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $data->name ?? '') }}">
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="address" class="form-label">Address</label>
                                            <input type="text" class="form-control @error('address') is-invalid @enderror" id="address" name="address" value="{{ old('address', $data->address ?? '') }}">
                                            @error('address')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label for="contact_no" class="form-label">Contact No</label>
                                            <input type="text" class="form-control @error('contact_no') is-invalid @enderror" id="contact_no" name="contact_no" value="{{ old('contact_no', $data->contact_no ?? '') }}">
                                            @error('contact_no')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    <hr class="my-5">
                                    <div class="row mb-3">
                                        <div class="col-md-12 mb-3">
                                            <label for="property_type" class="form-label">Property Type</label>
                                            <select class="form-select @error('property_type') is-invalid @enderror" id="property_type" name="property_type">
                                                <option value=""> - CHOOSE - </option>
                                                @foreach($property_types as $type)
                                                    <option value="{{ $type->id }}" {{ old('property_type', $data->property_type ?? '') == $type->id ? 'selected' : '' }}>{{ strtoupper($type->name) }}</option>
                                                @endforeach
                                            </select>
                                            @error('property_type')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="rate_code" class="form-label">Rate Code</label>
                                            <input type="text" class="form-control @error('rate_code') is-invalid @enderror" id="rate_code" name="rate_code" value="{{ old('rate_code', $data->rate_code ?? '') }}">
                                            @error('rate_code')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status">
                                                <option value=""> - CHOOSE - </option>
                                                @foreach(['AB' => 'AB - Active Billed', 'BL' => 'BL - Black Listed', 'ID' => 'ID - Inactive Deliquent', 'IV' => 'IV - Inactive Discon'] as $key => $label)
                                                    <option value="{{ $key }}" {{ old('status', $data->status ?? '') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error('status')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="sc_no" class="form-label">SC No.</label>
                                            <input type="text" class="form-control @error('sc_no') is-invalid @enderror" id="sc_no" name="sc_no" value="{{ old('sc_no', $data->sc_no ?? '') }}">
                                            @error('sc_no')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="meter_brand" class="form-label">Meter Brand</label>
                                            <input type="text" class="form-control @error('meter_brand') is-invalid @enderror" id="meter_brand" name="meter_brand" value="{{ old('meter_brand', $data->meter_brand ?? '') }}">
                                            @error('meter_brand')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="meter_serial_no" class="form-label">Meter Serial No.</label>
                                            <input type="text" class="form-control @error('meter_serial_no') is-invalid @enderror" id="meter_serial_no" name="meter_serial_no" value="{{ old('meter_serial_no', $data->meter_serial_no ?? '') }}">
                                            @error('meter_serial_no')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="date_connected" class="form-label">Date Connected</label>
                                            <input type="date" class="form-control @error('date_connected') is-invalid @enderror" id="date_connected" name="date_connected" value="{{ old('date_connected', isset($data->date_connected) ? \Carbon\Carbon::parse($data->date_connected)->format('Y-m-d') : '') }}">
                                            @error('date_connected')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="sequence_no" class="form-label">Sequence No.</label>
                                            <input type="text" class="form-control @error('sequence_no') is-invalid @enderror" id="sequence_no" name="sequence_no" value="{{ old('sequence_no', $data->sequence_no ?? '') }}">
                                            @error('sequence_no')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-5 mb-3">
                            <div class="card shadow border-0 p-2">
                                <div class="card-header border-0 bg-transparent">
                                    <div class="text-uppercase fw-bold">Account Information</div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-12 mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $data->email ?? '') }}">
                                            @error('email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label for="password" class="form-label">Password</label>
                                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                                            @error('password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-12 mb-3">
                                            <label for="confirm_password" class="form-label">Confirm Password</label>
                                            <input type="password" class="form-control @error('confirm_password') is-invalid @enderror" id="confirm_password" name="confirm_password">
                                            @error('confirm_password')
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

