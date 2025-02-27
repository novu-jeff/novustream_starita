@extends('layouts.auth')

@section('content')

<div class="login">
    <div class="container right-panel-active scroll" id="container">
        <div class="form-container sign-up-container">
            <form method="POST" action="{{ route('register') }}">
                @csrf                
                <h1>Create Account</h1>
                <span>or use your email for registration</span>
                <div class="w-100">
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="Name" />
                    @error('name')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <div class="w-100">
                    <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Email" />
                    @error('email')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <div class="w-100">
                    <input type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="Password" />
                    @error('password')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <div class="w-100">
                    <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" name="password_confirmation" placeholder="Confirm Password" />
                    @error('password_confirmation')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <button class="mt-4">Sign Up</button>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <h1>Welcome Back!</h1>
                    <p>To keep connected with us please login with your personal info</p>
                    <a href="/login" class="btn btn-outline-light border-2 fs-6 px-5 py-3 text-uppercase fw-bold" id="signIn">Sign In</a>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1>Sofia Waters</h1>
                    <p>Are you ready to view your water bills? and proceed to payments? Start now by creating an account!</p>
                    <button class="ghost" id="signUp">Sign Up</button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
