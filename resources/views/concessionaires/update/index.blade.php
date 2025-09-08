<form action="{{ isset($concessioner) ? route('concessioners.update', $concessioner->id) : route('concessioners.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if(isset($concessioner))
        @method('PUT')
    @endif

    <!-- Personal Information -->
    <div class="row">
        <div class="col-md-6 mb-3">
            <label>Full Name <span class="text-danger">*</span></label>
            <input type="text" name="name" value="{{ old('name', $concessioner->name ?? '') }}" class="form-control @error('name') is-invalid @enderror">
            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        <div class="col-md-6 mb-3">
            <label>Contact No <span class="text-danger">*</span></label>
            <input type="text" name="contact_no" value="{{ old('contact_no', $concessioner->contact_no ?? '') }}" class="form-control @error('contact_no') is-invalid @enderror">
            @error('contact_no') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
    </div>

    <!-- Login Info -->
    <div class="row">
        <div class="col-md-4">
            <label>Email <span class="text-danger">*</span></label>
            <input type="email" name="email" value="{{ old('email', $concessioner->email ?? '') }}" class="form-control @error('email') is-invalid @enderror">
            @error('email') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        <div class="col-md-4">
            <label>Password {{ isset($concessioner) ? '' : '*' }}</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
            @error('password') <small class="text-danger">{{ $message }}</small> @enderror
        </div>
        <div class="col-md-4">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control">
        </div>
    </div>

    <!-- Concessioner Status -->
    <div class="col-md-6 mb-3">
        <label>Status <span class="text-danger">*</span></label>
        <select name="isActive" class="form-select @error('isActive') is-invalid @enderror">
            <option value="1" {{ old('isActive', $concessioner->isActive ?? 1) == 1 ? 'selected' : '' }}>Active</option>
            <option value="0" {{ old('isActive', $concessioner->isActive ?? 1) == 0 ? 'selected' : '' }}>Not Active</option>
        </select>
        @error('isActive') <small class="text-danger">{{ $message }}</small> @enderror
    </div>

    <!-- Accounts -->
    <h5 class="mt-4">Accounts</h5>
    <div id="accounts-wrapper">
        @foreach(old('accounts', $concessioner->accounts ?? [ [] ]) as $index => $account)
        <div class="card p-3 mb-3">
            <div class="row">
                <div class="col-md-4">
                    <label>Account No.</label>
                    <input type="text" name="accounts[{{ $index }}][account_no]" value="{{ $account['account_no'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label>Property Type</label>
                    <select name="accounts[{{ $index }}][property_type]" class="form-select">
                        <option value="">-- SELECT --</option>
                        @foreach($property_types as $type)
                            <option value="{{ $type->id }}" {{ ($account['property_type'] ?? '') == $type->id ? 'selected' : '' }}>
                                {{ strtoupper($type->name) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label>Rate Code</label>
                    <input type="number" name="accounts[{{ $index }}][rate_code]" value="{{ $account['rate_code'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-12 mt-3">
                    <label>Inspection Image</label>
                    <input type="file" name="accounts[{{ $index }}][inspectionImage]" class="form-control">
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <button type="submit" class="btn btn-primary">Save Concessioner</button>
</form>
