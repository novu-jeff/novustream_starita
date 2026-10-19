@extends('layouts.auth')

@section('content')
<div class="login">
    <div class="container otp-shell">
        <form method="POST" action="{{ $verifyRoute ?? route('register.verify.store') }}">
            @csrf
            <img src="{{ asset('images/client1nobg.png') }}" alt="Sta. Rita Water District" style="height:84px;width:auto;">
            <h1 class="fw-bold mb-1">{{ $pageTitle ?? 'Verify your email' }}</h1>
            <span>We sent a 6-digit code to {{ $maskedEmail }}</span>

            @if(session('status'))
                <div class="alert alert-success w-100 py-2 px-3 mt-3 mb-0">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger w-100 py-2 px-3 mt-3 mb-0">{{ session('error') }}</div>
            @endif
            @if(!empty($lockedUntil))
                <div class="alert alert-danger w-100 py-2 px-3 mt-3 mb-0" id="otpLockMessage"
                    data-remaining-seconds="{{ $lockoutRemainingSeconds ?? 0 }}">
                    Verification is locked until {{ $lockedUntil }}.
                </div>
            @endif

            <div class="w-100 mt-3">
                <input
                    type="text"
                    name="otp"
                    value="{{ old('otp') }}"
                    class="form-control otp-code @error('otp') is-invalid @enderror"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    maxlength="6"
                    pattern="[0-9]{6}"
                    placeholder="000000"
                    @if(!empty($lockedUntil)) disabled @endif
                    required
                    autofocus
                />
                @if(empty($lockedUntil))
                    @error('otp')
                        <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                @endif
            </div>

            @if(isset($attemptsRemaining))
                <p class="otp-note mb-0">Attempts remaining: {{ $attemptsRemaining }} of 5.</p>
            @endif
            <p class="otp-note">The code expires in {{ $expiryMinutes ?? 10 }} minutes.</p>
            <button type="submit" id="otpVerifyButton" @if(!empty($lockedUntil)) disabled @endif>{{ $submitLabel ?? 'Verify and continue' }}</button>
        </form>
        <form method="POST" action="{{ $resendRoute ?? route('register.verify.resend') }}" class="otp-resend">
            @csrf
            <button type="submit" id="otpResendButton" class="ghost" @if(!empty($lockedUntil)) disabled @endif>Resend code</button>
        </form>
    </div>
</div>
<style>
    .login .container.otp-shell {
        width: 460px;
        min-height: 540px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .login .container.otp-shell form {
        position: static;
        width: 100%;
        height: auto;
        padding: 36px 40px 8px;
    }
    .login .container.otp-shell .otp-resend {
        padding-top: 0;
        padding-bottom: 28px;
    }
    .login .otp-code {
        letter-spacing: 0.45em;
        text-align: center;
        font-size: 1.5rem;
        font-weight: 700;
    }
    .login .otp-note {
        font-size: 13px;
        font-weight: 600;
        margin: 8px 0 18px;
        text-transform: none;
    }
    .login .otp-resend button.ghost {
        background: transparent;
        color: #3771c1;
        border: 0;
        text-transform: none;
        letter-spacing: 0;
        font-size: 14px;
    }
</style>
@if(!empty($lockedUntil))
<script>
    (() => {
        const lockMessage = document.getElementById('otpLockMessage');
        const codeInput = document.querySelector('input[name="otp"]');
        const verifyButton = document.getElementById('otpVerifyButton');
        const resendButton = document.getElementById('otpResendButton');
        let secondsLeft = Number(lockMessage?.dataset.remainingSeconds || 0);

        const unlock = () => {
            if (codeInput) codeInput.disabled = false;
            if (verifyButton) verifyButton.disabled = false;
            if (resendButton) resendButton.disabled = false;
            if (lockMessage) lockMessage.textContent = 'The lockout has ended. Resend a code to continue.';
        };

        if (secondsLeft <= 0) {
            unlock();
            return;
        }

        const timer = window.setInterval(() => {
            secondsLeft -= 1;
            if (secondsLeft <= 0) {
                window.clearInterval(timer);
                unlock();
            }
        }, 1000);
    })();
</script>
@endif
@endsection
