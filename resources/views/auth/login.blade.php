@extends('layouts.auth')

@section('content')

<div class="login auth-login-page">
    <div class="container" id="container">
        <div class="form-container sign-in-container" style="padding: 0;">
            <form method="POST" action="{{ route('auth.login') }}">
                @csrf
                <div class="auth-form-content">
                    <div class="auth-mobile-brand">
                        <img src="{{ asset(config('app.product') === 'novustream' ? 'images/client1nobg.png' : 'images/novusurgelogo.png') }}" alt="{{ config('app.name') }}">
                    </div>
                    <span class="auth-eyebrow">Welcome back</span>
                    <h1>Sign in</h1>
                    <p class="auth-form-description">Access your account to manage bills and water services.</p>
                    @if (session('success'))
                        <div class="alert alert-success auth-inline-alert" role="status">{{ session('success') }}</div>
                    @endif
                    @if (session('status'))
                        <div class="alert alert-success auth-inline-alert" role="status">{{ session('status') }}</div>
                    @endif
                    @if ($errors->has('login'))
                        <div class="alert alert-danger auth-inline-alert" role="alert">{{ $errors->first('login') }}</div>
                    @endif
                    <label for="login-email">Email address</label>
                    <div class="auth-input-wrap">
                        <i class="bx bx-envelope" aria-hidden="true"></i>
                        <input id="login-email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Enter your email" autocomplete="username" required />
                    </div>
                    @error('email')
                        <span class="invalid-feedback d-block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                    <label for="login-password">Password</label>
                    <div class="auth-input-wrap">
                        <i class="bx bx-lock-alt" aria-hidden="true"></i>
                        <input id="login-password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" placeholder="Enter your password" autocomplete="current-password" required />
                    </div>
                    @error('password')
                        <span class="invalid-feedback d-block" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                    <div class="auth-form-options">
                        @if (Route::has('password.request'))
                            <a href="#forgotPasswordModal" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">{{ __('Forgot password?') }}</a>
                        @endif
                    </div>
                    <button type="submit" class="auth-submit">
                        <span>{{ __('Sign in to your account') }}</span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                    </button>

                    <p class="auth-register-prompt">New to the portal? <a href="{{ route('register') }}">Create an account</a></p>
                    <p class="auth-register-prompt">Want to Pay directly? <a href="{{ env('DIRECT_PAYMENT') }}">Click here!</a></p>
                    <div class="auth-legal">Powered by Novulutions Inc. <button type="button" data-bs-toggle="modal" data-bs-target="#privacyModal">Data Privacy Policy</button></div>
                </div>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-right">
                    <div class="auth-brand-lockup">
                        <img src="{{ asset(config('app.product') === 'novustream' ? 'images/client1nobg.png' : 'images/novusurgelogo.png') }}" alt="{{ config('app.name') }}" style="scale: 1.2;">
                    </div>
                    <span class="auth-side-eyebrow"><i class="bx bx-water" aria-hidden="true"></i> Your water account, made simple</span>
                    <h2>Everything you need, in one place.</h2>
                    <p>Stay up to date with your water service from the comfort of home.</p>
                    <ul class="auth-benefits">
                        <li><i class="bx bx-receipt" aria-hidden="true"></i><span>View bills and account statements</span></li>
                        <li><i class="bx bx-credit-card" aria-hidden="true"></i><span>Make and review payments</span></li>
                        <li><i class="bx bx-bell" aria-hidden="true"></i><span>Keep track of service updates</span></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@if (Route::has('password.email'))
<div class="modal fade forgot-password-modal" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-describedby="forgotPasswordDescription" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="forgot-password-icon"><i class="bx bx-key" aria-hidden="true"></i></div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <h2 id="forgotPasswordModalLabel">Reset your password</h2>
            <p id="forgotPasswordDescription">Enter the email address linked to your account. If we find a matching account, we’ll email you a secure reset link.</p>
            <div id="forgotPasswordFeedback" class="alert d-none" role="status" aria-live="polite"></div>
            <form id="forgotPasswordForm" method="POST" action="{{ route('password.email') }}">
                @csrf
                <label for="forgot-password-email">Email address</label>
                <div class="forgot-password-input">
                    <i class="bx bx-envelope" aria-hidden="true"></i>
                    <input id="forgot-password-email" type="email" name="email" class="form-control" placeholder="Enter your email" autocomplete="email" required>
                </div>
                <button class="forgot-password-submit" type="submit">
                    <span>Send reset link</span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i>
                </button>
            </form>
            <button type="button" class="forgot-password-back" data-bs-dismiss="modal">Back to sign in</button>
        </div>
    </div>
</div>
@endif

<div class="modal fade reset-password-modal" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-describedby="resetPasswordDescription" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="reset-password-icon"><i class="bx bx-lock-alt" aria-hidden="true"></i></div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            <span class="reset-password-eyebrow">Secure your account</span>
            <h2 id="resetPasswordModalLabel">Reset your password</h2>
            <p id="resetPasswordDescription">Choose a new password for your account.</p>

            @if ($errors->has('email') || $errors->has('password') || $errors->has('token'))
                <div class="alert alert-danger reset-password-server-error" role="alert">
                    {{ $errors->first('email') ?: ($errors->first('password') ?: $errors->first('token')) }}
                </div>
            @endif

            <form id="resetPasswordForm" method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" id="reset-password-token" value="{{ old('token', request('reset_token')) }}">
                <input type="hidden" name="email" id="reset-password-email" value="{{ old('email', request('email')) }}">

                <label for="reset-password-new">New password</label>
                <div class="reset-password-input">
                    <i class="bx bx-key" aria-hidden="true"></i>
                    <input id="reset-password-new" type="password" name="password" class="form-control" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}" title="Password must contain at least 8 characters, one capital letter, one number, and one special character." autocomplete="new-password" aria-describedby="reset-password-help" required>
                </div>
                <small id="reset-password-help" class="reset-password-help" aria-live="polite">Password must contain at least 8 characters, one capital letter, one number, and one special character.</small>

                <label for="reset-password-confirm" class="reset-password-confirm-label">Confirm new password</label>
                <div class="reset-password-input">
                    <i class="bx bx-check-shield" aria-hidden="true"></i>
                    <input id="reset-password-confirm" type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required>
                </div>
                <small id="reset-password-confirm-help" class="reset-password-help" aria-live="polite"></small>

                <button type="submit" class="reset-password-submit"><span>Save new password</span><i class="bx bx-right-arrow-alt" aria-hidden="true"></i></button>
            </form>
            <button type="button" class="reset-password-back" data-bs-dismiss="modal">Back to sign in</button>
        </div>
    </div>
</div>

<div class="modal fade privacy-policy-modal" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-describedby="privacyPolicyIntro" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="privacy-policy-header">
        <div class="privacy-policy-header-icon"><i class="bx bx-shield-quarter" aria-hidden="true"></i></div>
        <div>
          <span class="privacy-policy-kicker">Your information matters</span>
          <h2 class="modal-title" id="privacyModalLabel">Data Privacy Notice</h2>
          <p id="privacyPolicyIntro">How the online water services portal handles personal information.</p>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close privacy notice"></button>
      </div>
      <div class="modal-body privacy-policy-body">
        <div class="privacy-policy-summary">
          <i class="bx bx-info-circle" aria-hidden="true"></i>
          <p><strong>In brief:</strong> Information submitted through this portal is used to manage water service accounts, applications, billing, payments, customer support, and account security. It is handled by authorized personnel and service providers for those purposes.</p>
        </div>
        <div class="privacy-policy-meta"><span><i class="bx bx-calendar" aria-hidden="true"></i> Effective date: October 22, 2026</span><span>Philippines · Republic Act No. 10173</span></div>

        <section class="privacy-policy-section" aria-labelledby="privacyControllerTitle">
          <span class="privacy-section-number">01</span>
          <div><h3 id="privacyControllerTitle">Who is responsible?</h3>
            <p>Sta. Rita Water District (SRWD) provides the water service and manages customer accounts. Novulutions Inc. provides and supports the online portal and may process information for SRWD as a service provider. The organization that determines the purposes and means of processing is the Personal Information Controller (PIC).</p>
          </div>
        </section>

        <section class="privacy-policy-section" aria-labelledby="privacyDataTitle">
          <span class="privacy-section-number">02</span>
          <div><h3 id="privacyDataTitle">What information is handled?</h3>
            <ul><li><strong>Identity and contact details:</strong> name, email address, mobile number, service address, and account number.</li>
              <li><strong>Account and service records:</strong> account status, applications, meter and service details, water readings, bills, payment records, and support requests.</li>
              <li><strong>Verification documents:</strong> documents you submit to establish identity, account ownership, or application eligibility, such as an SOA, identification, billing document, or authorization letter.</li>
              <li><strong>Security and technical records:</strong> login and OTP events, session data, and technical logs such as IP address, browser, and request time when recorded by the portal.</li>
            </ul>
            <p>The portal stores passwords in protected form. Do not send full payment card or banking credentials through customer support messages.</p>
          </div>
        </section>

        <section class="privacy-policy-section" aria-labelledby="privacyPurposeTitle">
          <span class="privacy-section-number">03</span>
          <div><h3 id="privacyPurposeTitle">Why and on what basis?</h3>
            <p>Information is used to create and verify accounts; process water-service applications; provide account, meter, billing, and payment services; communicate service or account notices; respond to requests; meet legal and records obligations; and protect the portal from unauthorized access and misuse.</p>
            <p>Depending on the activity, processing may be necessary to provide a requested service or take steps at your request, comply with a legal obligation, perform a public function where applicable, pursue a legitimate interest such as account security where your rights do not override it, or rely on your consent when consent is the appropriate legal basis. Where consent is used, it may be withdrawn, subject to processing that has another lawful basis or must continue under law.</p>
          </div>
        </section>

        <section class="privacy-policy-section" aria-labelledby="privacySharingTitle">
          <span class="privacy-section-number">04</span>
          <div><h3 id="privacySharingTitle">Who may receive information?</h3>
            <p>Access is limited to authorized SRWD personnel and service providers who need the information for the purposes above. Depending on the service used, recipients may include Novulutions Inc. and its authorized technical providers, email delivery providers for OTPs and account notices, payment providers for transactions, and government or regulatory authorities when disclosure is required or authorized by law. Providers must handle information under appropriate instructions and safeguards.</p>
            <p>We do not use this portal notice to authorize unrelated marketing or sell customer personal information.</p>
          </div>
        </section>

        <section class="privacy-policy-section" aria-labelledby="privacyAutomatedTitle">
          <span class="privacy-section-number">05</span>
          <div><h3 id="privacyAutomatedTitle">How is it processed and protected?</h3>
            <p>The portal processes information electronically to support account registration, OTP login verification, service applications, billing, payments, and customer notifications. Authorized staff may review records and documents to verify accounts and applications. The portal uses access controls and security measures intended to protect information; no internet transmission or storage system can be guaranteed completely secure.</p>
          </div>
        </section>

        <section class="privacy-policy-section" aria-labelledby="privacyRetentionTitle">
          <span class="privacy-section-number">06</span>
          <div><h3 id="privacyRetentionTitle">How long is information kept?</h3>
            <p>SRWD keeps account, service, billing, payment, and application records only for the period needed to provide the service, meet applicable government and financial recordkeeping requirements, resolve disputes, and establish or defend legal claims. Security logs and OTP records are kept for operational and security purposes and disposed of when no longer needed. Records are securely disposed of or de-identified when retention is no longer required.</p>
          </div>
        </section>

        <section class="privacy-policy-section" aria-labelledby="privacyRightsTitle">
          <span class="privacy-section-number">07</span>
          <div><h3 id="privacyRightsTitle">Your privacy rights</h3>
            <p>Subject to the Data Privacy Act and other applicable laws, you may ask to be informed about processing, access or correct your information, object to or request restriction of certain processing, request erasure or blocking where legally available, withdraw consent where consent is the basis, request data portability where applicable, and seek damages where provided by law. You may also lodge a complaint with the National Privacy Commission. Some requests may be limited where SRWD is required to retain or process records by law.</p>
            <p>To make a request or raise a concern, contact the privacy team at <a href="mailto:dpo@novulutions.com">dpo@novulutions.com</a>. We may ask for information needed to verify your identity and locate the relevant account.</p>
          </div>
        </section>

        <section class="privacy-policy-section" aria-labelledby="privacyCookiesTitle">
          <span class="privacy-section-number">08</span>
          <div><h3 id="privacyCookiesTitle">Cookies and changes to this notice</h3>
            <p>Necessary session cookies help keep you signed in and protect the portal. Your browser may let you block cookies, but sign-in and other portal features may not work correctly. We will update this notice when our processing practices or applicable requirements change and show the latest effective date here.</p>
          </div>
        </section>

        <div class="privacy-contact-card"><i class="bx bx-envelope" aria-hidden="true"></i><div><strong>Questions or privacy requests?</strong><span>Email <a href="mailto:dpo@novulutions.com">dpo@novulutions.com</a></span></div></div>
        <p class="privacy-policy-note">This notice summarizes portal processing. It does not remove rights provided by Philippine law.</p>
      </div>
    </div>
  </div>
</div>

<style>
    .auth-login-page {
        --auth-primary: var(--module-blue, #32667e);
        --auth-ink: var(--module-ink, #172b36);
        --auth-muted: var(--module-muted, #7184a5);
        --auth-border: var(--module-border, #e1ebfa);
        min-height: 100vh;
        height: auto;
        margin: 0;
        padding: 2rem 1.25rem;
        background: radial-gradient(ellipse at 8% 12%, rgba(50, 102, 126, .10), transparent 34%),
                    radial-gradient(ellipse at 90% 88%, rgba(50, 102, 126, .08), transparent 32%), #f4f8fa;
        color: var(--auth-ink);
        font-family: inherit;
    }
    .auth-login-page .container#container {
        display: grid;
        grid-template-columns: minmax(0, 1.02fr) minmax(0, .98fr);
        width: min(100%, 1000px);
        max-width: 1000px;
        min-height: 620px;
        height: auto;
        overflow: hidden;
        border: 1px solid rgba(225, 235, 250, .9);
        border-radius: 1.5rem;
        background: var(--module-surface, #fff);
        box-shadow: 0 26px 75px rgba(27, 57, 72, .13);
        padding: 0;
    }
    .auth-login-page .form-container,
    .auth-login-page .sign-in-container {
        position: relative;
        inset: auto;
        z-index: 1;
        width: auto;
        height: 100%;
        opacity: 1;
        transform: none;
        transition: none;
    }
    .auth-login-page .sign-up-container { display: none; }
    .auth-login-page .sign-in-container { grid-column: 1; }
    .auth-login-page form {
        align-items: stretch;
        justify-content: center;
        width: 100%;
        min-height: 620px;
        height: 100%;
        padding: clamp(2rem, 5vw, 4.5rem);
        background: #fff;
        text-align: left;
    }
    .auth-login-page .auth-form-content { width: 100%; max-width: 390px; margin: auto; }
    .auth-login-page .auth-eyebrow,
    .auth-login-page .auth-side-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        color: var(--auth-primary);
        font-size: .72rem;
        font-weight: 800;
        letter-spacing: .12em;
        text-transform: uppercase;
    }
    .auth-login-page .auth-form-content h1 {
        margin: .5rem 0 .35rem;
        color: var(--auth-ink);
        font-size: clamp(2rem, 4vw, 2.55rem);
        font-weight: 780;
        letter-spacing: -.055em;
        text-transform: none;
    }
    .auth-login-page .auth-form-description { margin: 0 0 1.8rem; color: var(--auth-muted); font-size: .92rem; font-weight: 450; line-height: 1.6; letter-spacing: 0; }
    .auth-login-page .auth-inline-alert { padding: .7rem .85rem; border-radius: .75rem; font-size: .84rem; }
    .auth-login-page label { margin: 0 0 .45rem; color: #314b59; font-size: .8rem; font-weight: 700; text-transform: none; }
    .auth-login-page .auth-input-wrap { position: relative; margin-bottom: 1.15rem; }
    .auth-login-page .auth-input-wrap > i { position: absolute; z-index: 2; top: 50%; left: .95rem; color: #78909b; font-size: 1.15rem; transform: translateY(-50%); }
    .auth-login-page input.form-control {
        width: 100%;
        min-height: 50px;
        margin: 0;
        padding: .75rem .9rem .75rem 2.75rem;
        border: 1px solid var(--auth-border);
        border-radius: .75rem;
        background: #fbfdfe;
        color: var(--auth-ink);
        font-size: .9rem;
        box-shadow: none;
    }
    .auth-login-page input.form-control:focus { border-color: var(--auth-primary); background: #fff; box-shadow: 0 0 0 .22rem rgba(50, 102, 126, .12); }
    .auth-login-page .invalid-feedback { margin: -.75rem 0 .9rem; font-size: .78rem; }
    .auth-login-page .auth-form-options { display: flex; justify-content: flex-end; min-height: 1.6rem; margin: -.15rem 0 .75rem; }
    .auth-login-page .auth-form-options a,
    .auth-login-page .auth-register-prompt a { margin: 0; color: var(--auth-primary); font-size: .82rem; font-weight: 700; text-decoration: none; }
    .auth-login-page .auth-form-options a:hover,
    .auth-login-page .auth-register-prompt a:hover,
    .auth-login-page .auth-legal button:hover { text-decoration: underline; }
    .auth-login-page button.auth-submit {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        width: 100%;
        min-height: 50px;
        padding: .75rem 1rem;
        border: 1px solid var(--auth-primary);
        border-radius: .75rem;
        background: var(--auth-primary);
        box-shadow: 0 8px 18px rgba(50, 102, 126, .18);
        color: #fff;
        font-size: .87rem;
        font-weight: 750;
        letter-spacing: 0;
        text-transform: none;
        transition: transform .16s ease, box-shadow .16s ease, background .16s ease;
    }
    .auth-login-page button.auth-submit:hover { transform: translateY(-1px); background: #285467; box-shadow: 0 10px 22px rgba(50, 102, 126, .24); }
    .auth-login-page button.auth-submit i { font-size: 1.2rem; }
    .auth-login-page .auth-register-prompt { margin: 1rem 0 1rem; color: var(--auth-muted); font-size: .82rem; font-weight: 500; line-height: 1.5; letter-spacing: 0; text-align: center; }
    .auth-login-page .auth-legal { padding-top: 1rem; border-top: 1px solid #edf2f4; color: #84959d; font-size: .68rem; text-align: center; }
    .auth-login-page .auth-legal button { padding: 0; border: 0; background: none; color: var(--auth-primary); font: inherit; cursor: pointer; }
    .auth-login-page .overlay-container {
        position: relative;
        inset: auto;
        grid-column: 2;
        width: auto;
        height: 100%;
        overflow: hidden;
        transform: none;
        transition: none;
    }
    .auth-login-page .overlay {
        position: relative;
        left: auto;
        display: flex;
        width: 100%;
        height: 100%;
        min-height: 620px;
        background: linear-gradient(145deg, #234b5b 0%, #32667e 48%, #4b8494 100%);
        transform: none;
        transition: none;
    }
    .auth-login-page .overlay-panel,
    .auth-login-page .overlay-right {
        position: relative;
        inset: auto;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        width: 100%;
        height: 100%;
        min-height: 620px;
        padding: clamp(2rem, 5vw, 4rem);
        text-align: left;
        transform: none;
        transition: none;
    }
    .auth-login-page .overlay-left { display: none; }
    .auth-login-page .auth-brand-lockup { display: grid; place-items: center; width: 100%; min-height: 200px; margin-bottom: 1.5rem; }
    .auth-login-page .auth-brand-lockup img { display: block; width: min(100%, 270px); max-height: 140px; object-fit: contain; filter: drop-shadow(0 8px 16px rgba(0,0,0,.12)); }
    .auth-login-page .auth-side-eyebrow { color: #c7e7ef; font-size: .68rem; letter-spacing: .09em; }
    .auth-login-page .auth-side-eyebrow i { font-size: 1.1rem; }
    .auth-login-page .overlay-right h2 { margin: .8rem 0 .6rem; color: #fff; font-size: clamp(1.7rem, 3vw, 2.25rem); font-weight: 760; letter-spacing: -.045em; line-height: 1.15; text-transform: none; }
    .auth-login-page .overlay-right p { margin: 0; color: rgba(255,255,255,.78); font-size: .9rem; font-weight: 450; line-height: 1.6; letter-spacing: 0; }
    .auth-login-page .auth-benefits { display: grid; gap: .85rem; width: 100%; margin: 1.8rem 0 0; padding: 0; list-style: none; }
    .auth-login-page .auth-benefits li { display: flex; align-items: center; gap: .75rem; color: rgba(255,255,255,.92); font-size: .82rem; font-weight: 600; }
    .auth-login-page .auth-benefits i { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; border: 1px solid rgba(255,255,255,.2); border-radius: 10px; background: rgba(255,255,255,.1); color: #d1edf3; font-size: 1.1rem; }
    @media (max-width: 760px) {
        .auth-login-page { min-height: 100svh; padding: 1rem; }
        .auth-login-page .container#container { display: block; width: min(100%, 480px); min-height: 0; border-radius: 1.2rem; }
        .auth-login-page .sign-in-container { width: 100%; }
        .auth-login-page form { min-height: 0; padding: clamp(1.5rem, 7vw, 2.5rem); }
        .auth-login-page .auth-form-content { max-width: 420px; }
        .auth-login-page .overlay-container { display: none; }
        .auth-login-page .auth-mobile-brand { display: grid; place-items: center; width: 100%; height: 84px; margin-bottom: 1.5rem; }
        .auth-login-page .auth-mobile-brand img { max-width: 190px; max-height: 80px; object-fit: contain; }
    }
    @media (min-width: 761px) { .auth-login-page .auth-mobile-brand { display: none; } }

    .forgot-password-modal .modal-content { position: relative; padding: 2.2rem; border: 1px solid var(--module-border, #e1ebfa); border-radius: 1.25rem; box-shadow: 0 24px 70px rgba(19, 43, 54, .2); }
    .forgot-password-modal .btn-close { position: absolute; top: 1rem; right: 1rem; box-shadow: none; }
    .forgot-password-modal .forgot-password-icon { display: grid; width: 52px; height: 52px; margin-bottom: 1.1rem; place-items: center; border-radius: 16px; background: #edf5f7; color: var(--module-blue, #32667e); font-size: 1.6rem; }
    .forgot-password-modal h2 { margin: 0 0 .4rem; color: var(--module-ink, #172b36); font-size: 1.45rem; font-weight: 760; letter-spacing: -.035em; }
    .forgot-password-modal p { margin: 0 0 1.35rem; color: var(--module-muted, #7184a5); font-size: .86rem; line-height: 1.55; }
    .forgot-password-modal label { display: block; margin-bottom: .45rem; color: #314b59; font-size: .79rem; font-weight: 700; }
    .forgot-password-modal .forgot-password-input { position: relative; margin-bottom: 1rem; }
    .forgot-password-modal .forgot-password-input i { position: absolute; top: 50%; left: .9rem; z-index: 1; color: #78909b; font-size: 1.1rem; transform: translateY(-50%); }
    .forgot-password-modal .forgot-password-input input { min-height: 48px; padding-left: 2.55rem; border: 1px solid var(--module-border, #e1ebfa); border-radius: .7rem; font-size: .88rem; }
    .forgot-password-modal .forgot-password-input input:focus { border-color: var(--module-blue, #32667e); box-shadow: 0 0 0 .2rem rgba(50, 102, 126, .12); }
    .forgot-password-modal .forgot-password-submit { display: flex; align-items: center; justify-content: center; gap: .45rem; width: 100%; min-height: 48px; border: 1px solid var(--module-blue, #32667e); border-radius: .7rem; background: var(--module-blue, #32667e); color: #fff; font-size: .85rem; font-weight: 700; }
    .forgot-password-modal .forgot-password-submit:disabled { cursor: wait; opacity: .7; }
    .forgot-password-modal .forgot-password-back { display: block; margin: 1rem auto 0; padding: .25rem .5rem; border: 0; background: transparent; color: var(--module-blue, #32667e); font-size: .8rem; font-weight: 700; }
    .forgot-password-modal #forgotPasswordFeedback { margin: 0 0 1rem; padding: .65rem .8rem; font-size: .8rem; }
    @media (max-width: 575.98px) { .forgot-password-modal .modal-dialog { margin: .75rem; } .forgot-password-modal .modal-content { padding: 1.5rem; } }

    .reset-password-modal .modal-content { position: relative; padding: 2.2rem; border: 1px solid var(--module-border, #e1ebfa); border-radius: 1.25rem; box-shadow: 0 24px 70px rgba(19, 43, 54, .2); }
    .reset-password-modal .btn-close { position: absolute; top: 1rem; right: 1rem; box-shadow: none; }
    .reset-password-modal .reset-password-icon { display: grid; width: 52px; height: 52px; margin-bottom: 1rem; place-items: center; border-radius: 16px; background: #edf5f7; color: var(--module-blue, #32667e); font-size: 1.55rem; }
    .reset-password-modal .reset-password-eyebrow { color: var(--module-blue, #32667e); font-size: .67rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
    .reset-password-modal h2 { margin: .35rem 0; color: var(--module-ink, #172b36); font-size: 1.45rem; font-weight: 760; letter-spacing: -.035em; }
    .reset-password-modal p { margin: 0 0 1.25rem; color: var(--module-muted, #7184a5); font-size: .85rem; line-height: 1.55; }
    .reset-password-modal label { display: block; margin: .9rem 0 .4rem; color: #314b59; font-size: .79rem; font-weight: 700; }
    .reset-password-modal .reset-password-input { position: relative; }
    .reset-password-modal .reset-password-input i { position: absolute; z-index: 1; top: 50%; left: .9rem; color: #78909b; font-size: 1.1rem; transform: translateY(-50%); }
    .reset-password-modal .reset-password-input .password-input-wrap { position: relative; width: 100%; margin: 0; }
    .reset-password-modal .reset-password-input .password-toggle-btn { position: absolute; top: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; width: 44px; padding: 0; border: 0; background: transparent; color: #718792; cursor: pointer; }
    .reset-password-modal .reset-password-input .password-toggle-btn:hover { color: var(--module-blue, #32667e); }
    .reset-password-modal .reset-password-input .password-toggle-btn i { position: static; color: inherit; font-size: 1.1rem; transform: none; }
    .reset-password-modal .reset-password-input input { min-height: 48px; padding-left: 2.55rem; border: 1px solid var(--module-border, #e1ebfa); border-radius: .7rem; font-size: .88rem; }
    .reset-password-modal .reset-password-input input:focus { border-color: var(--module-blue, #32667e); box-shadow: 0 0 0 .2rem rgba(50, 102, 126, .12); }
    .reset-password-modal .reset-password-help { display: block; min-height: 1.1rem; margin-top: .4rem; color: var(--module-muted, #7184a5); font-size: .72rem; line-height: 1.45; }
    .reset-password-modal .reset-password-help.is-invalid { color: #b42332; }
    .reset-password-modal .reset-password-help.is-valid { color: #168653; }
    .reset-password-modal .reset-password-submit { display: flex; align-items: center; justify-content: center; gap: .45rem; width: 100%; min-height: 48px; margin-top: 1rem; border: 1px solid var(--module-blue, #32667e); border-radius: .7rem; background: var(--module-blue, #32667e); color: #fff; font-size: .85rem; font-weight: 700; }
    .reset-password-modal .reset-password-back { display: block; margin: .9rem auto 0; padding: .25rem .5rem; border: 0; background: transparent; color: var(--module-blue, #32667e); font-size: .8rem; font-weight: 700; }
    .reset-password-modal .reset-password-server-error { margin: 0 0 1rem; padding: .65rem .8rem; font-size: .8rem; }
    @media (max-width: 575.98px) { .reset-password-modal .modal-dialog { margin: .75rem; } .reset-password-modal .modal-content { padding: 1.5rem; } }

    .privacy-policy-modal { --privacy-primary: var(--module-blue, #32667e); --privacy-ink: var(--module-ink, #172b36); --privacy-muted: var(--module-muted, #7184a5); --privacy-border: var(--module-border, #e1ebfa); }
    .privacy-policy-modal .modal-dialog { --bs-modal-width: 900px; margin-top: 1.25rem; margin-bottom: 1.25rem; }
    .privacy-policy-modal .modal-content { overflow: hidden; border: 1px solid var(--privacy-border); border-radius: 1.25rem; box-shadow: 0 24px 70px rgba(19, 43, 54, .24); }
    .privacy-policy-header { display: flex; align-items: center; gap: 1rem; padding: 1.4rem 1.65rem; background: linear-gradient(120deg, #234b5b, var(--privacy-primary)); color: #fff; }
    .privacy-policy-header-icon { display: grid; flex: 0 0 52px; width: 52px; height: 52px; place-items: center; border: 1px solid rgba(255,255,255,.22); border-radius: 15px; background: rgba(255,255,255,.12); font-size: 1.75rem; }
    .privacy-policy-header .privacy-policy-kicker { display: block; margin-bottom: .2rem; color: #cce9ef; font-size: .66rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
    .privacy-policy-header h2 { margin: 0; color: #fff; font-size: 1.25rem; font-weight: 760; letter-spacing: -.02em; }
    .privacy-policy-header p { margin: .25rem 0 0; color: rgba(255,255,255,.76); font-size: .8rem; line-height: 1.4; }
    .privacy-policy-header .btn-close { align-self: flex-start; margin-left: auto; box-shadow: none; }
    .privacy-policy-body { max-height: min(74vh, 760px); padding: 1.5rem clamp(1rem, 3.2vw, 2rem) 1.25rem; color: var(--privacy-ink); font-size: .85rem; line-height: 1.65; }
    .privacy-policy-summary { display: flex; align-items: flex-start; gap: .8rem; padding: 1rem 1.1rem; border: 1px solid #d5e7ed; border-radius: .9rem; background: #f1f8fa; color: #385661; }
    .privacy-policy-summary > i { flex: 0 0 auto; margin-top: .1rem; color: var(--privacy-primary); font-size: 1.25rem; }
    .privacy-policy-summary p { margin: 0; }
    .privacy-policy-meta { display: flex; justify-content: space-between; gap: .5rem 1rem; flex-wrap: wrap; padding: .8rem .1rem 1rem; color: var(--privacy-muted); font-size: .72rem; }
    .privacy-policy-meta i { margin-right: .2rem; color: var(--privacy-primary); vertical-align: -1px; }
    .privacy-policy-section { display: grid; grid-template-columns: 42px minmax(0,1fr); gap: .8rem; padding: 1.05rem 0; border-top: 1px solid #edf2f4; }
    .privacy-section-number { display: grid; width: 36px; height: 36px; place-items: center; border-radius: 11px; background: #edf5f7; color: var(--privacy-primary); font-size: .7rem; font-weight: 800; }
    .privacy-policy-section h3 { margin: .15rem 0 .45rem; color: var(--privacy-ink); font-size: .98rem; font-weight: 760; letter-spacing: -.015em; }
    .privacy-policy-section p { margin: 0 0 .55rem; color: #536b75; }
    .privacy-policy-section p:last-child { margin-bottom: 0; }
    .privacy-policy-section ul { display: grid; gap: .42rem; margin: 0 0 .6rem; padding-left: 1.2rem; color: #536b75; }
    .privacy-policy-section li::marker { color: var(--privacy-primary); }
    .privacy-policy-section strong { color: #34515d; }
    .privacy-policy-modal a { color: var(--privacy-primary); font-weight: 700; text-decoration: underline; text-underline-offset: 2px; }
    .privacy-contact-card { display: flex; align-items: center; gap: .8rem; margin-top: .7rem; padding: .95rem 1rem; border: 1px solid var(--privacy-border); border-radius: .9rem; background: #fbfdfe; }
    .privacy-contact-card > i { display: grid; flex: 0 0 38px; width: 38px; height: 38px; place-items: center; border-radius: 12px; background: #edf5f7; color: var(--privacy-primary); font-size: 1.2rem; }
    .privacy-contact-card div { display: grid; gap: .1rem; }
    .privacy-contact-card strong { color: var(--privacy-ink); font-size: .82rem; }
    .privacy-contact-card span { color: var(--privacy-muted); font-size: .76rem; }
    .privacy-policy-note { margin: .9rem 0 0; color: var(--privacy-muted); font-size: .7rem; text-align: center; }
    @media (max-width: 575.98px) {
        .privacy-policy-modal .modal-dialog { margin: .5rem; }
        .privacy-policy-header { gap: .7rem; padding: 1rem; }
        .privacy-policy-header-icon { flex-basis: 42px; width: 42px; height: 42px; font-size: 1.45rem; }
        .privacy-policy-header h2 { font-size: 1.05rem; }
        .privacy-policy-header p { max-width: 250px; font-size: .72rem; }
        .privacy-policy-body { max-height: 78vh; padding: 1rem; font-size: .8rem; }
        .privacy-policy-section { grid-template-columns: 34px minmax(0,1fr); gap: .55rem; padding: .9rem 0; }
        .privacy-section-number { width: 30px; height: 30px; border-radius: 9px; font-size: .64rem; }
    }
</style>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('forgotPasswordModal');
        const form = document.getElementById('forgotPasswordForm');
        if (!modal || !form) return;

        const emailInput = document.getElementById('forgot-password-email');
        const loginEmailInput = document.getElementById('login-email');
        const feedback = document.getElementById('forgotPasswordFeedback');
        const submitButton = form.querySelector('button[type="submit"]');
        const submitLabel = submitButton.querySelector('span');

        modal.addEventListener('show.bs.modal', function () {
            feedback.className = 'alert d-none';
            feedback.textContent = '';
            if (!emailInput.value && loginEmailInput.value) emailInput.value = loginEmailInput.value;
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (!form.reportValidity()) return;

            submitButton.disabled = true;
            submitLabel.textContent = 'Sending…';
            feedback.className = 'alert d-none';
            feedback.textContent = '';

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: new FormData(form)
                });
                const payload = await response.json().catch(function () { return {}; });

                if (response.status === 429) {
                    feedback.className = 'alert alert-warning';
                    feedback.textContent = payload.message || 'A reset link was recently sent. Please wait before requesting another.';
                } else if (response.ok) {
                    feedback.className = 'alert alert-success';
                    feedback.textContent = payload.message || 'Password reset link sent. Check your email inbox.';
                    form.reset();
                } else if (response.status === 422) {
                    feedback.className = 'alert alert-danger';
                    feedback.textContent = payload.errors?.email?.[0] || payload.message || 'Please enter a valid email address.';
                } else {
                    throw new Error('Unable to send the reset link right now. Please try again.');
                }
            } catch (error) {
                feedback.className = 'alert alert-danger';
                feedback.textContent = error.message || 'Unable to send the reset link right now. Please try again.';
            } finally {
                submitButton.disabled = false;
                submitLabel.textContent = 'Send reset link';
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function () {
        const modalElement = document.getElementById('resetPasswordModal');
        const tokenInput = document.getElementById('reset-password-token');
        const passwordInput = document.getElementById('reset-password-new');
        const confirmInput = document.getElementById('reset-password-confirm');
        const passwordHelp = document.getElementById('reset-password-help');
        const confirmHelp = document.getElementById('reset-password-confirm-help');
        const resetForm = document.getElementById('resetPasswordForm');
        if (!modalElement || !resetForm) return;

        function missingPasswordRequirements(password) {
            const missing = [];
            if (password.length < 8) missing.push('at least 8 characters');
            if (!/[A-Z]/.test(password)) missing.push('one capital letter');
            if (!/[0-9]/.test(password)) missing.push('one number');
            if (!/[^A-Za-z0-9]/.test(password)) missing.push('one special character');
            return missing;
        }

        function requirementText(missing) {
            return missing.join(', ').replace(/, ([^,]*)$/, ' and $1');
        }

        function updateResetPasswordFeedback() {
            const missing = missingPasswordRequirements(passwordInput.value);
            const hasPassword = passwordInput.value.length > 0;
            passwordHelp.classList.toggle('is-invalid', hasPassword && missing.length > 0);
            passwordHelp.classList.toggle('is-valid', hasPassword && missing.length === 0);
            passwordHelp.textContent = !hasPassword
                ? 'Password must contain at least 8 characters, one capital letter, one number, and one special character.'
                : missing.length
                    ? 'Password must contain ' + requirementText(missing) + '.'
                    : 'Password meets all requirements.';
            passwordInput.setCustomValidity(missing.length ? 'Password must contain ' + requirementText(missing) + '.' : '');

            const confirmationMatches = confirmInput.value.length > 0 && confirmInput.value === passwordInput.value;
            confirmHelp.classList.toggle('is-invalid', confirmInput.value.length > 0 && !confirmationMatches);
            confirmHelp.classList.toggle('is-valid', confirmationMatches);
            confirmHelp.textContent = confirmInput.value.length === 0
                ? ''
                : confirmationMatches ? 'Passwords match.' : 'Passwords do not match.';
            confirmInput.setCustomValidity(confirmInput.value === passwordInput.value ? '' : 'Passwords do not match.');
        }

        passwordInput.addEventListener('input', updateResetPasswordFeedback);
        confirmInput.addEventListener('input', updateResetPasswordFeedback);

        if (tokenInput.value) {
            const modalTrigger = document.createElement('button');
            modalTrigger.type = 'button';
            modalTrigger.className = 'd-none';
            modalTrigger.setAttribute('data-bs-toggle', 'modal');
            modalTrigger.setAttribute('data-bs-target', '#resetPasswordModal');
            document.body.appendChild(modalTrigger);
            modalTrigger.click();
            modalTrigger.remove();
        }
    });
</script>
@endsection


