@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                @if(isset($data))
                    <h1>Update Admin</h1>
                @else
                    <h1>Add New Admin</h1>
                @endif
                <a href="{{ route('admins.index') }}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Go Back
                </a>
            </div>
            <div class="inner-content mt-5">
                <form action="{{ isset($data) ? route('admins.update', $data->id) : route('admins.store') }}" method="POST">
                    @csrf
                    @if(isset($data))
                        @method('PUT')
                    @endif
                    <div class="row d-flex justify-content-center">
                        <div class="col-12 col-md-7 mb-3">
                            <div class="card shadow border-0 p-2">
                                <div class="card-header border-0 bg-transparent">
                                    <div class="text-uppercase fw-bold">Personal & Account Information</div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">

                                        {{-- Name --}}
                                        <div class="col-md-12 mb-3">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $data->name ?? '') }}">
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        {{-- Role --}}
                                        <div class="col-md-12 mb-3">
                                            <label for="role" class="form-label">Role</label>
                                            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role">
                                                <option value=""> - CHOOSE -</option>
                                                @foreach($roles as $role)
                                                    <option value="{{ $role->name }}" {{ old('role', $data->user_type ?? '') == $role->name ? 'selected' : '' }}>
                                                        {{ strtoupper($role->name) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error('role')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        {{-- Zone Assigned (Checkboxes) --}}
                                        <div class="col-md-12 mb-3" id="zone-assigned-wrapper" style="display: none;">
                                            <label class="form-label d-block mb-2">Zone Assigned</label>
                                            <label class="form-label d-block mb-2"><span class="text-danger">* </span>If the technician is assigned to all zones, leave the zone checkboxes unchecked, as all zones are assigned by default.</label>
                                            <div class="row">
                                                @foreach($zones as $zone)
                                                    @php
                                                        $oldZones = old('zone_assigned', isset($data) && $data->zone_assigned ? explode(',', $data->zone_assigned) : []);
                                                    @endphp
                                                    <div class="col-md-4 mb-2">
                                                        <div class="form-check">
                                                            <input
                                                                class="form-check-input @error('zone_assigned') is-invalid @enderror"
                                                                type="checkbox"
                                                                name="zone_assigned[]"
                                                                id="zone_{{ $zone->id }}"
                                                                value="{{ $zone->id }}"
                                                                {{ in_array($zone->id, $oldZones) ? 'checked' : '' }}
                                                            >
                                                            <label class="form-check-label" for="zone_{{ $zone->id }}">
                                                                {{ strtoupper($zone->zone) }} - {{ strtoupper($zone->area) }}
                                                            </label>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                            @error('zone_assigned')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        {{-- Email --}}
                                        <div class="col-md-12 mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $data->email ?? '') }}">
                                            @error('email')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        {{-- Password --}}
                                        <div class="col-md-12 mb-3">
                                            <label for="password" class="form-label">Password</label>
                                            <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                                            @error('password')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        {{-- Confirm Password --}}
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
            // show/hide zone select based on role
            function toggleZoneAssigned() {
                let selectedRole = $('#role').val().toLowerCase();
                if (selectedRole === 'technician') {
                    $('#zone-assigned-wrapper').slideDown();
                } else {
                    $('#zone-assigned-wrapper').slideUp();
                    $('#zone_assigned').val('');
                }
            }

            $('#role').on('change', toggleZoneAssigned);

            // Run on page load (edit mode)
            toggleZoneAssigned();

            @if (session('alert'))
                setTimeout(() => {
                    let alertData = @json(session('alert'));
                    alert(alertData.status, alertData.message);
                }, 100);
            @endif
        });
    </script>
@endsection
