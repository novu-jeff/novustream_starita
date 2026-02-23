@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div id="profilePageData" data-alert="{{ e(json_encode(session('alert'))) }}"></div>
            @if(session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            <div class="main-header d-flex justify-content-between">
                <h1>Update My Profile</h1>
            </div>
            
            <div class="inner-content mt-5 pb-5">
                @php
                    $prefix = Auth::guard('admins')->check() ? 'admin' : 'concessionaire';
                @endphp
                <form action="{{route('profile.update', ['user_type' => $prefix, 'profile' => $data->id],)}}" method="POST">
                    @csrf
                    @method('PUT')       
                    <div class="row">
                        <div class="col-12 col-md-12 mb-3">
                            <div class="card shadow border-0 p-2">
                                <div class="card-header border-0 bg-transparent">
                                    <div class="text-uppercase fw-bold">Account Information</div>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6 mb-3">
                                            <label for="name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $data->name ?? '') }}">
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6 mb-3">
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
            const pageDataEl = document.getElementById('profilePageData');
            const rawAlert = pageDataEl ? pageDataEl.dataset.alert : null;

            if (rawAlert && rawAlert !== 'null') {
                try {
                    const alertData = JSON.parse(rawAlert);
                    setTimeout(() => {
                        alert(alertData.status, alertData.message);
                    }, 100);
                } catch (error) {
                    console.error('Invalid alert payload', error);
                }
            }

            const syncNowBtn = document.getElementById('syncNowBtn');
            if (syncNowBtn && typeof window.syncOfflineReadings === 'function') {
                syncNowBtn.addEventListener('click', window.syncOfflineReadings);
            }
        });
    </script>
@endsection

