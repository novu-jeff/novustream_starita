@extends('layouts.auth')

@section('content')
<div class="login otp-login-page">
    <div class="container otp-shell">
        <form method="POST" action="{{ $verifyRoute ?? route('register.verify.store') }}">
            @csrf
            <img class="otp-brand" src="{{ asset('images/client1nobg.png') }}" alt="Sta. Rita Water District">
            <h1 class="otp-title">{{ $pageTitle ?? 'Verify your email' }}</h1>
            <p class="otp-description">Enter the 6-digit code sent to <strong>{{ $maskedEmail }}</strong>.</p>

            @if(session('status'))
                <div class="alert alert-success otp-alert" role="status">{{ session('status') }}</div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger otp-alert" role="alert">{{ session('error') }}</div>
            @endif
            @if(!empty($lockedUntil))
                <div class="alert alert-danger otp-alert" id="otpLockMessage" role="alert"
                    data-remaining-seconds="{{ $lockoutRemainingSeconds ?? 0 }}">
                    Verification is locked until {{ $lockedUntil }}.
                </div>
            @endif

            <div class="otp-entry">
                <input type="hidden" name="otp" id="otpValue" value="{{ old('otp') }}">
                <div class="otp-digit-group" role="group" aria-label="6-digit verification code">
                    @for($digitIndex = 0; $digitIndex < 6; $digitIndex++)
                        <input
                            type="text"
                            class="form-control otp-digit @error('otp') is-invalid @enderror"
                            inputmode="numeric"
                            autocomplete="{{ $digitIndex === 0 ? 'one-time-code' : 'off' }}"
                            maxlength="1"
                            pattern="[0-9]"
                            aria-label="Digit {{ $digitIndex + 1 }} of 6"
                            value="{{ substr((string) old('otp', ''), $digitIndex, 1) }}"
                            @if(!empty($lockedUntil) || !empty($codeExpired)) disabled @endif
                            @if($digitIndex === 0) autofocus @endif
                        />
                    @endfor
                </div>
                @if(empty($lockedUntil))
                    @error('otp')
                        <span class="otp-error" role="alert">{{ $message }}</span>
                    @enderror
                @endif
            </div>

            @if(isset($attemptsRemaining))
                <p class="otp-meta">{{ $attemptsRemaining }} of 5 attempts remaining</p>
            @endif
            <p class="otp-expiry">Code expires in <strong id="otpCountdown" data-expires-at="{{ $expiresAtTimestampMs ?? 0 }}">--:--</strong></p>
            <button class="otp-submit" type="submit" id="otpVerifyButton" @if(!empty($lockedUntil) || !empty($codeExpired)) disabled @endif>{{ $submitLabel ?? 'Verify and continue' }}</button>
        </form>
        <form method="POST" action="{{ $resendRoute ?? route('register.verify.resend') }}" class="otp-resend">
            @csrf
            <button type="submit" id="otpResendButton" @if(!empty($lockedUntil) || (int) ($resendCooldownSeconds ?? 0) > 0) disabled @endif>Resend code</button>
            <p class="otp-resend-hint" id="otpResendHint" aria-live="polite"></p>
        </form>
    </div>
</div>
<script>
    (() => {
        const form = document.querySelector('.otp-shell form:first-of-type');
        const hiddenOtp = document.getElementById('otpValue');
        const digitInputs = Array.from(document.querySelectorAll('.otp-digit'));
        if (!form || !hiddenOtp || digitInputs.length !== 6) return;

        const syncOtp = () => {
            hiddenOtp.value = digitInputs.map(input => input.value).join('');
        };
        const fillDigits = (value, startAt = 0) => {
            const digits = value.replace(/\D/g, '').slice(0, 6 - startAt);
            for (let index = 0; index < digits.length; index++) digitInputs[startAt + index].value = digits[index];
            syncOtp();
            const nextInput = digitInputs[Math.min(startAt + digits.length, 5)];
            nextInput.focus();
            nextInput.select();
        };

        digitInputs.forEach((input, index) => {
            input.addEventListener('input', () => {
                const value = input.value.replace(/\D/g, '');
                if (value.length > 1) {
                    input.value = '';
                    fillDigits(value, index);
                    return;
                }
                input.value = value.slice(-1);
                syncOtp();
                if (input.value && index < digitInputs.length - 1) digitInputs[index + 1].focus();
            });
            input.addEventListener('keydown', event => {
                if (event.key === 'Backspace' && !input.value && index > 0) digitInputs[index - 1].focus();
                else if (event.key === 'ArrowLeft' && index > 0) digitInputs[index - 1].focus();
                else if (event.key === 'ArrowRight' && index < digitInputs.length - 1) digitInputs[index + 1].focus();
            });
            input.addEventListener('paste', event => {
                event.preventDefault();
                fillDigits(event.clipboardData.getData('text'), index);
            });
        });
        form.addEventListener('submit', syncOtp);
        syncOtp();

        const countdown = document.getElementById('otpCountdown');
        const resendButton = document.getElementById('otpResendButton');
        const resendHint = document.getElementById('otpResendHint');
        const lockMessage = document.getElementById('otpLockMessage');
        const verifyButton = document.getElementById('otpVerifyButton');
        const expiresAt = Number(countdown?.dataset.expiresAt || 0)
            || Date.now() + ({{ (int) ($expiryMinutes ?? 10) }} * 60 * 1000);
        const resendAvailableAt = Number(@json($resendAvailableAtTimestampMs ?? 0));
        let lockedSeconds = Number(lockMessage?.dataset.remainingSeconds || 0);

        const formatTime = totalSeconds => {
            const minutes = Math.floor(totalSeconds / 60).toString().padStart(2, '0');
            const seconds = (totalSeconds % 60).toString().padStart(2, '0');
            return `${minutes}:${seconds}`;
        };
        const updateCountdowns = () => {
            const secondsRemaining = Math.max(0, Math.ceil((expiresAt - Date.now()) / 1000));
            const resendSeconds = Math.max(0, Math.ceil((resendAvailableAt - Date.now()) / 1000));
            const isLocked = lockedSeconds > 0;
            const codeExpired = secondsRemaining === 0;

            if (countdown) countdown.textContent = codeExpired ? 'Expired' : formatTime(secondsRemaining);
            if (codeExpired) {
                digitInputs.forEach(input => input.disabled = true);
                if (verifyButton) verifyButton.disabled = true;
            }

            if (!isLocked && !codeExpired && lockMessage) {
                digitInputs.forEach(input => input.disabled = false);
                if (verifyButton) verifyButton.disabled = false;
            }

            if (resendButton) resendButton.disabled = isLocked || resendSeconds > 0;
            if (resendHint) {
                if (isLocked) resendHint.textContent = `Resend available after lockout (${formatTime(lockedSeconds)}).`;
                else if (resendSeconds > 0) resendHint.textContent = `Resend available in ${formatTime(resendSeconds)}.`;
                else if (codeExpired) resendHint.textContent = 'Code expired. Request a new code to continue.';
                else resendHint.textContent = '';
            }

            if (isLocked) {
                lockedSeconds -= 1;
                if (lockedSeconds <= 0 && lockMessage) lockMessage.textContent = 'The lockout has ended. Resend a code to continue.';
            }
        };

        updateCountdowns();
        window.setInterval(updateCountdowns, 1000);
    })();
</script>
@endsection
