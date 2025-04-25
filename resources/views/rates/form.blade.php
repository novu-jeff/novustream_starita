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
                                            <label for="cubic_meter" class="form-label fw-bold">Max Cubic Meter</label>
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
                        <div class="col-12 col-md-5 mb-3">
                            <div class="card p-4 border rounded shadow-sm">
                                <h3 class="mb-3">💧 Add Water Rate – Instructions</h3>
                                <ol class="mb-3">
                                    <li>
                                        <strong>Select the Property Type</strong> – Choose which type of property the new rates will apply to 
                                        (e.g., Residential, Commercial).
                                    </li>
                                    <li>
                                        <strong>Enter the Maximum Cubic Meter</strong> – Type the maximum <code>cu.m</code> (cubic meter) value 
                                        you want to add rates up to.
                                        <ul>
                                            <li>The system will check the <em>last existing rate</em> for that property type.</li>
                                            <li>It will then automatically <strong>add new rows</strong> for the missing cubic meter values 
                                                starting from the next <code>cu.m</code> after the highest one, up to the number you input.</li>
                                        </ul>
                                    </li>
                                    <li>
                                        <strong>Set the Charge</strong> –
                                        <ul>
                                            <li>For <strong>0 to 10 cu.m</strong>, the system will apply <code>₱0.00</code> charge.</li>
                                            <li>For <strong>above 10 cu.m</strong>, the system will apply the charge you provide.</li>
                                        </ul>
                                    </li>
                                    <li>
                                        <strong>Save Automatically</strong> – The system will then:
                                        <ul>
                                            <li>Store the new cubic meter ranges</li>
                                            <li>Assign the appropriate charge (₱0.00 or your input)</li>
                                            <li>Recalculate the water rates accordingly</li>
                                        </ul>
                                    </li>
                                    <li>
                                        ✅ <strong>Success Message</strong> – You’ll see a confirmation that new water rates have been added successfully.
                                    </li>
                                </ol>
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

