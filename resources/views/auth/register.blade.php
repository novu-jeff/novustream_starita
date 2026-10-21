@extends('layouts.auth')

@section('content')

<div class="login register-page">
    <div class="container right-panel-active" id="container">
        <div class="form-container sign-up-container">
            <form id="registerForm" method="POST" action="{{ route('auth.register.store') }}" enctype="multipart/form-data" novalidate>
                @csrf
                <div id="registrationTypeStep" class="registration-type-step">
                    @if(session('error'))
                        <div class="alert alert-danger w-100 py-2 px-3 mb-3">{{ session('error') }}</div>
                    @endif
                    <div class="register-step-label"><span>STEP 1 OF 2</span><span>Account type</span></div>
                    <div class="register-step-heading">
                        <span class="register-heading-icon"><i class="bx bx-water"></i></span>
                        <div>
                            <h1 class="text-uppercase">How do you use Sta. Rita Water District?</h1>
                            <p class="registration-type-subtitle">Choose the option that best describes your application.</p>
                        </div>
                    </div>

                    <div class="account-type-options">
                        <label class="account-type-card selected" for="registration-existing">
                            <input id="registration-existing" type="radio" name="registration_choice" value="existing_account" checked>
                            <span class="account-type-radio"></span>
                            <img src="{{ asset('images/Existing_Conce.png') }}" alt="Existing Sta. Rita account">
                            <span class="account-type-details">
                                <strong class="text-uppercase">Existing account</strong>
                                <span>You already have a Sta. Rita water meter and need online access to your account.</span>
                                <small>Have your account number and latest SOA ready.</small>
                            </span>
                        </label>
                        <label class="account-type-card" for="registration-new">
                            <input id="registration-new" type="radio" name="registration_choice" value="new_connection">
                            <span class="account-type-radio"></span>
                            <img src="{{ asset('images/New_Concessionaire.png') }}" alt="New concessionaire">
                            <span class="account-type-details">
                                <strong class="text-uppercase">New connection</strong>
                                <span>You are applying for a new water service connection.</span>
                                <small>Your application will be reviewed by the district.</small>
                            </span>
                        </label>
                    </div>

                    <div class="registration-type-note"><i class="bx bx-info-circle"></i><span>Not sure which to choose? Contact Sta. Rita Water District before submitting an application.</span></div>
                    <button type="button" id="continueRegistration" class="btn-registration-next">Continue <i class="bx bx-right-arrow-alt"></i></button>
                </div>

                <div id="registrationFormStep" class="registration-form-step d-none">
                <input type="hidden" name="registration_type" id="registration_type" value="existing_account">
                <input type="hidden" name="data_privacy_consent" id="data_privacy_consent" value="">
                <div class="register-form-heading">
                    <div class="register-form-brand">
                        <img class="mobile-logo" src="{{ asset('images/client1nobg.png') }}" alt="Sta. Rita Water District">
                        <div>
                            <div class="register-step-label"><span>STEP 2 OF 2</span><span>Account details</span></div>
                            <h1 class="text-uppercase">Create your online account</h1>
                            <p id="registrationTypeSummary">Existing account registration</p>
                        </div>
                    </div>
                    <button type="button" id="backToRegistrationType" class="register-back-button"><i class="bx bx-left-arrow-alt"></i> Change type</button>
                </div>

                <div id="registerAlert" class="alert d-none w-100 py-2 px-3 mb-2" role="alert"></div>

                <div class="register-fields w-100">
                    <div class="register-name-group register-field-wide">
                        <div class="register-field">
                            <label class="register-field-label text-uppercase" for="first_name">First Name <i class="register-required-mark">*</i></label>
                            <input type="text" class="form-control name-part-input" name="first_name" id="first_name" placeholder="JUAN" maxlength="100" autocomplete="given-name" required />
                        </div>
                        <div class="register-field">
                            <label class="register-field-label text-uppercase" for="middle_initial">Middle Initial</label>
                            <input type="text" class="form-control name-part-input" name="middle_initial" id="middle_initial" placeholder="N" maxlength="50" autocomplete="additional-name" aria-describedby="middle-initial-help" />
                            <small id="middle-initial-help" class="form-text text-muted">Leave it blank if none.</small>
                        </div>
                        <div class="register-field">
                            <label class="register-field-label text-uppercase" for="last_name">Last Name <i class="register-required-mark">*</i></label>
                            <input type="text" class="form-control name-part-input" name="last_name" id="last_name" placeholder="DELA CRUZ" maxlength="100" autocomplete="family-name" required />
                        </div>
                    </div>
                    <div class="register-field">
                        <label class="register-field-label text-uppercase" for="email">Email Address <i class="register-required-mark">*</i></label>
                        <input type="email" class="form-control" name="email" id="email" placeholder="you@example.com" autocomplete="email" required />
                    </div>
                    <div class="register-field">
                        <label class="register-field-label text-uppercase" for="contact_no">Mobile Number <i class="register-required-mark">*</i></label>
                        <input type="tel" class="form-control" name="contact_no" id="contact_no" placeholder="09XXXXXXXXX" maxlength="11" inputmode="numeric" autocomplete="tel" required />
                    </div>
                    <div class="register-field existing-account-field">
                        <label class="register-field-label text-uppercase" for="account_no">Account Number <i class="register-required-mark">*</i></label>
                        <input type="text" class="form-control" name="account_no" id="account_no" placeholder="000-00-000000" maxlength="13" inputmode="numeric" autocomplete="off" aria-describedby="account-no-help" required />
                        <small class="form-text text-muted" id="account-no-help">Enter the account number in the format 000-00-000000.</small>
                    </div>
                    <div class="register-field register-field-wide">
                        <label class="register-field-label text-uppercase" for="address">Service Address <i class="register-required-mark">*</i></label>
                        <input type="text" class="form-control" name="address" id="address" placeholder="House number, street, barangay, municipality" autocomplete="street-address" required />
                    </div>
                    <div class="register-field">
                        <label class="register-field-label text-uppercase" for="password">Password <i class="register-required-mark">*</i></label>
                        <input type="password" class="form-control" name="password" id="password" placeholder="Create a password" minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}" title="Your password must have at least 8 characters, one capital letter, one number, and one special character (like !, @, or #)." autocomplete="new-password" aria-describedby="registration-password-help" required />
                        <small class="form-text text-muted" id="registration-password-help" aria-live="polite">Password must contain at least 8 characters, one capital letter, one number, and one special character (like !, @, or #).</small>
                    </div>
                    <div class="register-field">
                        <label class="register-field-label text-uppercase" for="password_confirmation">Confirm Password <i class="register-required-mark">*</i></label>
                        <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" placeholder="Enter your password again" minlength="8" autocomplete="new-password" required />
                    </div>

                    <div class="register-section-heading register-field-wide existing-account-field">
                        <div><strong>Verify your existing account</strong><small class="form-text text-muted">Upload your latest SOA and a Valid ID. PDF, JPG, or PNG; up to 2 MB each.</small></div>
                    </div>
                    <div class="register-document-grid register-field-wide existing-account-field">
                    <div class="file-upload">
                        <label for="soa_file" class="file-label">
                            <i class="bx bx-receipt"></i>
                            <span class="file-text">Latest statement of account <span class="text-danger">*</span></span>
                            <span class="file-name" id="soa_file_name">Choose file</span>
                        </label>
                        <input type="file" id="soa_file" name="soa_file" accept=".pdf,.jpg,.jpeg,.png" required />
                    </div>

                    <div class="file-upload">
                        <label for="id_file" class="file-label">
                            <i class="bx bx-id-card"></i>
                            <span class="file-text">Valid ID <span class="text-danger">*</span></span>
                            <span class="file-name" id="id_file_name">Choose file</span>
                        </label>
                        <input type="file" id="id_file" name="id_file" accept=".pdf,.jpg,.jpeg,.png" required />
                    </div>
                    </div>

                    <div class="new-connection-fields d-none w-100">
                        <div class="register-section-heading">
                            <div><strong>Application documents</strong><small class="form-text text-muted">Upload clear copies of the required documents. PDF, JPG, or PNG; up to 2 MB each.</small></div>
                        </div>
                        <div class="register-document-grid">
                        <div class="file-upload">
                            <label for="picture_1x1" class="file-label">
                                <i class="bx bx-file"></i>
                                <span class="file-text">1x1 or 2x2 Picture <span class="text-danger">*</span></span>
                                <span class="file-name" id="picture_1x1_name">Choose file</span>
                            </label>
                            <input type="file"
                                id="picture_1x1"
                                name="picture_1x1"
                                accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="file-upload">
                            <label for="cedula_file" class="file-label">
                                <i class="bx bx-file"></i>
                                <span class="file-text">Cedula / Residence Certificate <span class="text-danger">*</span></span>
                                <span class="file-name" id="cedula_file_name">Choose file</span>
                            </label>
                            <input type="file"
                                id="cedula_file"
                                name="cedula_file"
                                accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="file-upload">
                            <label for="billing_file" class="file-label">
                                <i class="bx bx-receipt"></i>
                                <span class="file-text">Proof of Billing <span class="text-danger">*</span></span>
                                <span class="file-name" id="billing_file_name">Choose file</span>
                            </label>
                            <input type="file"
                                id="billing_file"
                                name="billing_file"
                                accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="file-upload">
                            <label for="authorization_file" class="file-label overflow-hidden">
                                <i class="bx bx-file"></i>
                                <span class="file-text">Authorization Letter (if applicable)</span>
                                <span class="file-name" id="authorization_file_name">Choose file</span>
                            </label>
                            <input type="file"
                                id="authorization_file"
                                name="authorization_file"
                                accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        </div>
                    </div>

                </div>

                <div class="register-security-note"><span>Your details are used to verify your account and application.</span></div>
                <div class="w-100 mt-2 recaptcha-wrapper">
                    <div
                        class=" g-recaptcha"
                        data-sitekey="{{ config('services.recaptcha.site_key') }}">
                    </div>
                </div>

                <button type="submit" class="register-submit"><span>Submit application</span><i class="bx bx-right-arrow-alt"></i></button>
                <a href="{{ route('auth.index') }}" class="register-signin-link">Already have an account? Sign in</a>
                </div>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <img src="{{ asset(config('app.product') === 'novustream' ? 'images/client1nobg.png' : 'images/novusurgelogo.png') }}" alt="" class="w-75">
                    <p>Already registered? Sign in to view your bills and make payments.</p>
                    <a href="{{ route('auth.index') }}" class="btn btn-primary border-2 fs-6 px-5 py-3 text-white fw-bold text-uppercase" id="signIn">Sign In</a>
                </div>
                <div class="overlay-panel overlay-right">
                    <h1>Welcome</h1>
                    <p>Create an account to get started.</p>
                    <button class="ghost" id="signUp" type="button">Sign Up</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="consent-modal d-none" id="dataConsentModal" role="dialog" aria-modal="true" aria-labelledby="dataConsentTitle">
    <div class="consent-dialog">
        <h2 id="dataConsentTitle">Data Verification Consent</h2>

        <p>
            Sta. Rita Water District will use the information and documents you submit to verify your concessionaire application, account ownership, billing history, and identity.
        </p>

        <p>
            By continuing, you confirm that the details are accurate and you allow the district to review your submitted data for registration approval.
        </p>

        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="confirmConsent">
            <label class="form-check-label" for="confirmConsent">
                I have read and agree to the data verification consent above.
            </label>
        </div>

        <div class="consent-actions">
            <button type="button" class="btn-cancel" id="declineConsent">Cancel</button>
            <button type="button" class="btn-accept" id="acceptConsent" disabled>
                I Agree
            </button>
        </div>
    </div>
</div>


<style>
    .register-page .registration-type-step,
    .register-page .registration-form-step { width: 100%; box-sizing: border-box; }

    .register-page .registration-type-step { min-height: 100%; display: flex; flex-direction: column; justify-content: center; color: #102b50; }
    .register-page .registration-type-step h1 { color: #102b50; font-size: 1.35rem; text-align: left; }
    .register-page .registration-type-subtitle { color: #64748b; font-size: .8rem; text-align: left; text-transform: none; margin: 2px 0 18px; }
    .register-page .account-type-options { display: grid; gap: 12px; margin: 0 0 14px; }
    .register-page .account-type-card { display: flex; align-items: center; gap: 12px; min-height: 116px; margin: 0; padding: 14px; border: 1px solid #dce5ef; border-radius: 8px; background: #f9fbfd; cursor: pointer; text-align: left; }
    .register-page .account-type-card.selected { border-color: #72aef5; background: #eef6ff; }
    .register-page .account-type-card input { position: absolute; opacity: 0; }
    .register-page .account-type-radio { flex: 0 0 15px; width: 15px; height: 15px; border: 1px solid #73849a; border-radius: 50%; }
    .register-page .account-type-card.selected .account-type-radio { border: 5px solid #1680f8; }
    .register-page .account-type-card img { flex: 0 0 68px; width: 68px; height: 68px; object-fit: cover; border-radius: 50%; }
    .register-page .account-type-details { display: grid; gap: 4px; text-transform: none; }
    .register-page .account-type-details strong { color: #102b50; font-size: .88rem; }
    .register-page .account-type-details span, .register-page .account-type-details small { color: #64748b; font-size: .72rem; line-height: 1.4; }
    .register-page .registration-type-note { color: #64748b; font-size: .7rem; line-height: 1.4; text-transform: none; margin: 0 0 14px; }
    .register-page .registration-type-note i { color: #1680f8; }
    .register-page .btn-registration-next { display: flex; align-items: center; gap: 7px; align-self: flex-end; padding: 10px 20px; border: 0; border-radius: 6px; background: #1678e8; color: #fff; font-size: .8rem; font-weight: 700; }

    .register-page .registration-form-step { display: flex; flex-direction: column; align-items: center; }
    .register-page .registration-form-step > button[type="submit"] { align-self: center; }
    .register-page .registration-form-step > a { align-self: center; text-align: center; }

    .register-page .sign-up-container form {
        justify-content: flex-start;
        padding: 28px 36px;
        overflow-y: auto;
    }

    .register-page .register-fields {
        max-height: none;
    }

    .register-page .file-upload {
        margin: 6px 0;
    }

    .register-page .registration-type {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
        margin: 8px 0;
    }

    .register-page .registration-type-option {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f1f4f8;
        border: 1px solid #d8dee8;
        border-radius: 6px;
        padding: 10px;
        cursor: pointer;
        font-size: 0.78rem;
        font-weight: 700;
        color: #333;
        text-transform: none;
    }

    .register-page .registration-type-option input {
        width: auto;
        margin: 0;
    }

    .register-page .file-upload input[type="file"] {
        position: absolute;
        width: 1px;
        height: 1px;
        opacity: 0;
        overflow: hidden;
        z-index: -1;
    }

    .register-page .file-label {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        background-color: #eee;
        border: 1px dashed #b0b0b0;
        border-radius: 6px;
        padding: 10px 12px;
        margin: 0;
        cursor: pointer;
        font-size: 0.85rem;
        color: #333;
        transition: border-color 0.15s ease, background-color 0.15s ease;
    }

    .register-page .file-label:hover {
        border-color: #3771c1;
        background-color: #e8eef7;
    }

    .register-page .file-label i {
        font-size: 1.15rem;
        color: #3771c1;
        flex-shrink: 0;
    }

    .register-page .file-text {
        font-weight: 600;
        text-transform: none;
        font-size: 0.8rem;
        margin: 0;
        flex-shrink: 0;
    }

    .register-page .file-name {
        margin-left: auto;
        font-size: 0.75rem;
        color: #666;
        text-transform: none;
        font-weight: 500;
        max-width: 45%;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        text-align: right;
    }

    .register-page .file-name.has-file {
        color: #185174;
        font-weight: 600;
    }

    .register-page .file-hint {
        font-size: 10px;
        color: #888;
        text-align: left;
        width: 100%;
        margin-top: 2px;
    }

    .register-page .form-control.is-invalid {
        border: 1px solid #dc3545;
        background-color: #fff5f5;
    }

    .register-page .file-label.is-invalid {
        border-color: #dc3545;
        background-color: #fff5f5;
    }

    .consent-modal {
        position: fixed;
        inset: 0;
        z-index: 9999;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.55);
        padding: 20px;
    }

    .consent-modal.d-none {
        display: none;
    }

    .consent-dialog {
        width: min(460px, 100%);
        background: #fff;
        color: #222;
        border-radius: 8px;
        padding: 24px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.25);
        text-align: left;
    }

    .consent-dialog h2 {
        font-size: 1.25rem;
        font-weight: 700;
        margin: 0 0 12px;
    }

    .consent-dialog p {
        font-size: 0.9rem;
        line-height: 1.5;
        margin: 0 0 12px;
        text-transform: none;
    }

    .consent-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 20px;
    }

    .consent-actions button {
        border: 0;
        border-radius: 6px;
        padding: 10px 18px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .btn-cancel {
        background: #e9ecef;
        color: #333;
    }

    .btn-accept {
        background: #3771c1;
        color: #fff;
    }

    .mobile-logo {
        display: none;
    }

    .recaptcha-wrapper {
        width: 100%;
        overflow: hidden;
    }

    .recaptcha-wrapper .g-recaptcha {
        transform: scale(0.75);
        transform-origin: top left;
    }

    @media (min-width: 0px) and (max-width: 600px) {
        .overlay-container {
            display: none;
        }

        .register-page .sign-up-container form {
            justify-content: flex-start;
            padding: 2rem 0 2rem 0;
            overflow-y: auto;
        }

        .mobile-logo {
            width: 8rem;
            height: 8rem;
            display: block;
            text-align: center;
            margin-bottom: 20px;
        }

        .login {
            width: 90%;
            display: flex;
            margin: auto !important;
            justify-content: center;
            height: auto;
            min-height: 90vh;
            padding: 24px 0;
        }

        .login .sign-up-container {
            transform: none !important;
            width: 100%;
            position: relative;
            opacity: 1;
            z-index: 5;
        }

        .login .container {
            min-height: auto;
            height: auto;
        }

        .login form {
            padding: 24px 20px;
            height: auto;
        }
    }

    .register-page .file-size-error {
        display: none;
        width: 100%;
        color: #dc3545;
        font-size: 11px;
        margin-top: 4px;
        text-align: left;
    }

    .register-page .file-size-error.show {
        display: block;
    }

    /* Registration refresh: use the shared module palette with a focused, responsive layout. */
    .login.register-page {
        --register-blue: var(--module-blue, #32667e);
        --register-blue-dark: var(--module-blue-dark, #285467);
        --register-ink: var(--module-ink, #172b36);
        --register-muted: var(--module-muted, #7184a5);
        --register-border: var(--module-border, #dce7ec);
        width: 100%;
        min-height: 100vh;
        height: auto;
        margin: 0 auto !important;
        padding: clamp(1rem, 4vw, 3rem);
        background: radial-gradient(circle at top left, #e8f2f5 0, transparent 38%), #f5f8fa;
    }

    .login.register-page .container#container {
        width: min(100%, 900px);
        max-width: 900px;
        min-height: 0;
        height: auto;
        overflow: hidden;
        border: 1px solid var(--register-border);
        border-radius: 1.25rem;
        background: #fff;
        box-shadow: 0 20px 60px rgba(22, 47, 62, .12);
    }

    .register-page .overlay-container { display: none; }

    .register-page .sign-up-container {
        position: relative;
        inset: auto;
        width: 100%;
        height: auto;
        min-height: 0;
        transform: none !important;
        opacity: 1;
        z-index: 1;
        animation: none !important;
    }

    .register-page .sign-up-container form {
        display: flex;
        align-items: stretch;
        justify-content: flex-start;
        width: 100%;
        height: auto;
        min-height: 0;
        overflow: visible;
        padding: clamp(1.25rem, 4vw, 2.75rem);
        background: #fff;
    }

    .register-page .registration-type-step {
        min-height: 0;
        justify-content: flex-start;
    }

    .register-page .register-step-label {
        display: flex;
        align-items: center;
        gap: .55rem;
        margin-bottom: .75rem;
        color: var(--register-muted);
        font-size: .69rem;
        font-weight: 750;
        letter-spacing: .07em;
        text-transform: uppercase;
    }

    .register-page .register-step-label span:first-child {
        padding: .28rem .5rem;
        border-radius: 999px;
        background: #edf4f6;
        color: var(--register-blue);
        font-size: .65rem;
        font-weight: 800;
    }

    .register-page .register-step-heading {
        display: flex;
        align-items: flex-start;
        gap: .9rem;
        margin-bottom: 1.25rem;
    }

    .register-page .register-heading-icon,
    .register-page .register-section-icon {
        display: grid;
        place-items: center;
        flex: 0 0 46px;
        width: 46px;
        height: 46px;
        border-radius: 14px;
        background: #edf4f6;
        color: var(--register-blue);
        font-size: 1.5rem;
    }

    .register-page .registration-type-step h1,
    .register-page .register-form-heading h1 {
        margin: 0;
        color: var(--register-ink);
        font-size: clamp(1.35rem, 3vw, 1.8rem);
        font-weight: 750;
        letter-spacing: -.035em;
        line-height: 1.2;
        text-align: left;
        text-transform: none;
    }

    .register-page .registration-type-subtitle {
        margin: .4rem 0 0;
        color: var(--register-muted);
        font-size: .88rem;
        line-height: 1.5;
    }

    .register-page .account-type-options {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        margin: 0 0 1rem;
    }

    .register-page .account-type-card {
        position: relative;
        align-items: flex-start;
        min-height: 205px;
        padding: 1.2rem;
        border: 1px solid var(--register-border);
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 3px 12px rgba(22, 47, 62, .035);
        transition: border-color .16s ease, background-color .16s ease, box-shadow .16s ease, transform .16s ease;
    }

    .register-page .account-type-card:hover {
        transform: translateY(-2px);
        border-color: #a8c5cf;
        box-shadow: 0 10px 24px rgba(22, 47, 62, .08);
    }

    .register-page .account-type-card.selected {
        border: 2px solid var(--register-blue);
        padding: calc(1.2rem - 1px);
        background: #f3f8fa;
        box-shadow: 0 0 0 3px rgba(50, 102, 126, .08);
    }

    .register-page .account-type-card:focus-within {
        outline: 3px solid rgba(50, 102, 126, .2);
        outline-offset: 2px;
    }

    .register-page .account-type-card input {
        position: absolute;
        width: 1px;
        height: 1px;
        margin: 0;
        opacity: 0;
    }

    .register-page .account-type-radio {
        position: absolute;
        top: 1rem;
        right: 1rem;
        width: 20px;
        height: 20px;
        flex-basis: 20px;
        border: 1px solid #b9c9ce;
        background: #fff;
    }

    .register-page .account-type-card.selected .account-type-radio {
        border: 6px solid var(--register-blue);
    }

    .register-page .account-type-card img {
        width: 60px;
        height: 60px;
        flex-basis: 60px;
        border-radius: 16px;
        background: #f4f7f8;
        object-fit: contain;
    }

    .register-page .account-type-details {
        gap: .55rem;
        padding-right: 1rem;
    }

    .register-page .account-type-details strong {
        color: var(--register-ink);
        font-size: 1rem;
        font-weight: 750;
    }

    .register-page .account-type-details span {
        color: #536872;
        font-size: .82rem;
        line-height: 1.5;
    }

    .register-page .account-type-details small {
        color: var(--register-muted);
        font-size: .75rem;
        line-height: 1.45;
    }

    .register-page .registration-type-note {
        display: flex;
        align-items: flex-start;
        gap: .5rem;
        margin: .35rem 0 1.25rem;
        padding: .75rem .85rem;
        border-radius: .7rem;
        background: #f5f8fa;
        color: var(--register-muted);
        font-size: .77rem;
        line-height: 1.45;
    }

    .register-page .registration-type-note span { margin: 0; color: var(--register-muted); font-size: .77rem; font-weight: 500; letter-spacing: 0; text-transform: none; }
    .register-page .registration-type-note i { color: var(--register-blue); font-size: 1.05rem; }

    .register-page .btn-registration-next,
    .register-page .register-submit {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: .55rem;
        min-height: 46px;
        align-self: flex-end;
        padding: .7rem 1.15rem;
        border: 1px solid var(--register-blue);
        border-radius: .7rem;
        background: var(--register-blue);
        box-shadow: 0 5px 14px rgba(50, 102, 126, .16);
        color: #fff;
        font-size: .88rem;
        font-weight: 700;
        letter-spacing: 0;
        text-transform: none;
        transition: background-color .15s ease, box-shadow .15s ease, transform .15s ease;
    }

    .register-page .btn-registration-next:hover,
    .register-page .register-submit:hover:not(:disabled) {
        transform: translateY(-1px);
        border-color: var(--register-blue-dark);
        background: var(--register-blue-dark);
    }

    .register-page .btn-registration-next i,
    .register-page .register-submit i { font-size: 1.2rem; }

    .register-page .registration-form-step { align-items: stretch; }

    .register-page .register-form-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        width: 100%;
        margin-bottom: 1.25rem;
        padding-bottom: 1.1rem;
        border-bottom: 1px solid var(--register-border);
    }

    .register-page .register-form-brand { display: flex; align-items: center; gap: .9rem; min-width: 0; }
    .register-page .register-form-brand .mobile-logo { display: block; width: 66px; height: 66px; flex: 0 0 66px; margin: 0; object-fit: contain; }
    .register-page .register-form-heading .register-step-label { margin-bottom: .25rem; }
    .register-page .register-form-heading h1 { font-size: clamp(1.25rem, 2.6vw, 1.6rem); }
    .register-page .register-form-heading p { margin: .3rem 0 0; color: var(--register-muted); font-size: .82rem; font-weight: 500; letter-spacing: 0; line-height: 1.4; text-transform: none; }

    .register-page .register-back-button {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        flex: 0 0 auto;
        padding: .5rem .65rem;
        border: 1px solid var(--register-border);
        border-radius: .6rem;
        background: #fff;
        color: var(--register-blue);
        font-size: .78rem;
        font-weight: 650;
        letter-spacing: 0;
        text-transform: none;
    }

    .register-page .register-fields {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 1rem;
        align-items: start;
        width: 100%;
    }

    .register-page .register-name-group { display: grid; grid-template-columns: minmax(0, 1.25fr) minmax(90px, .65fr) minmax(0, 1.25fr); gap: .75rem; grid-column: 1 / -1; min-width: 0; }
    .register-page .register-field { min-width: 0; width: 100%; }
    .register-page .register-field-wide { grid-column: 1 / -1; }
    #app main .register-page .register-field-label { display: flex; justify-content: flex-start; align-items: baseline; gap: .25rem; margin: 0 0 .4rem; color: var(--register-ink); font-size: .8rem; font-weight: 700; text-transform: none; }
    .register-page .register-field-label span { margin-left: auto; color: var(--register-muted); font-size: .7rem; font-weight: 500; text-transform: none; }
    .register-page .register-required-mark { color: #c93645; font-size: .85rem; font-style: normal; }

    #app main .register-page .register-field .form-control {
        min-height: 46px;
        margin: 0;
        padding: .7rem .8rem;
        border: 1px solid #d8e3e7;
        border-radius: .65rem;
        background: #fff;
        color: var(--register-ink);
        font-size: .87rem;
        box-shadow: none;
        transition: border-color .15s ease, box-shadow .15s ease;
    }

    #app main .register-page .register-field .form-control::placeholder { color: #9aa9ae; opacity: 1; }
    #app main .register-page .register-field .form-control:focus { border-color: var(--register-blue); box-shadow: 0 0 0 .2rem rgba(50, 102, 126, .12); }
    #app main .register-page .register-field .form-control.is-invalid { border-color: #c93645; background: #fff8f8; }
    .register-page .register-field .form-text { display: block; margin-top: .4rem; color: var(--register-muted); font-size: .72rem; line-height: 1.4; }

    .register-page .register-section-heading {
        display: flex;
        align-items: center;
        gap: .75rem;
        margin: .35rem 0 0;
        padding: .85rem;
        border-top: 2px solid #e8eff1;
    }

    .register-page .register-section-icon { flex-basis: 38px; width: 38px; height: 38px; border-radius: 11px; font-size: 1.2rem; }
    .register-page .register-section-heading strong { display: block; color: var(--register-ink); font-size: .82rem; font-weight: 750; }
    .register-page .register-section-heading small { display: block; margin-top: .2rem; color: var(--register-muted); font-size: .72rem; line-height: 1.4; }
    .register-page .register-document-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); grid-column: 1 / -1; gap: .75rem; }
    .register-page .new-connection-fields { grid-column: 1 / -1; }
    .register-page .new-connection-fields .register-document-grid { margin-top: .75rem; }

    .register-page .file-upload { min-width: 0; margin: 0; }
    .register-page .file-label {
        min-height: 70px;
        gap: .65rem;
        padding: .75rem;
        border: 1px dashed #c5d5da;
        border-radius: .7rem;
        background: #fbfcfc;
        color: var(--register-ink);
    }
    .register-page .file-label:hover { border-color: var(--register-blue); background: #f2f7f8; }
    .register-page .file-label:focus-within { outline: 3px solid rgba(50, 102, 126, .18); }
    .register-page .file-label i { color: var(--register-blue); font-size: 1.3rem; }
    .register-page .file-text { min-width: 0; flex: 1 1 auto; font-size: .77rem; line-height: 1.35; }
    .register-page .file-name { max-width: 42%; color: var(--register-muted); font-size: .68rem; }
    .register-page .file-name.has-file { color: var(--register-blue); }
    .register-page .file-size-error { padding: .4rem .2rem 0; color: #b42332; font-size: .72rem; }

    .register-page .register-security-note {
        display: flex;
        align-items: center;
        gap: .45rem;
        width: 100%;
        margin-top: 1rem;
        color: var(--register-muted);
        font-size: .74rem;
        line-height: 1.4;
    }
    .register-page .register-security-note i { color: var(--register-blue); font-size: 1rem; }
    .register-page .recaptcha-wrapper { display: flex; justify-content: center; width: 100%; min-height: 58px; margin-top: .75rem !important; overflow: visible; }
    .register-page .recaptcha-wrapper .g-recaptcha { transform: scale(.88); transform-origin: top center; }
    .register-page .register-submit { width: min(100%, 320px); align-self: center; margin-top: .35rem !important; }
    .register-page .register-submit:disabled { opacity: .65; cursor: wait; }
    .register-page .register-signin-link { align-self: center; margin: .9rem 0 0; color: var(--register-blue); font-size: .82rem; font-weight: 650; text-transform: none; }
    .register-page #registerAlert { font-size: .82rem; line-height: 1.45; }

    @media (max-width: 650px) {
        .login.register-page { padding: .75rem; }
        .login.register-page .container#container { border-radius: 1rem; }
        .register-page .sign-up-container form { padding: 1.25rem; }
        .register-page .account-type-options { grid-template-columns: 1fr; }
        .register-page .account-type-card { min-height: 0; padding: 1rem; }
        .register-page .account-type-card.selected { padding: calc(1rem - 1px); }
        .register-page .register-form-heading { flex-direction: column; align-items: stretch; gap: .65rem; }
        .register-page .register-form-brand { align-items: flex-start; gap: .65rem; width: 100%; }
        .register-page .register-form-brand > div { flex: 1 1 auto; min-width: 0; }
        .register-page .register-form-brand .mobile-logo { width: 50px; height: 50px; flex-basis: 50px; }
        .register-page .register-form-heading .register-step-label { flex-wrap: wrap; gap: .3rem; line-height: 1.3; }
        .register-page .register-form-heading h1 { font-size: clamp(1.05rem, 5vw, 1.35rem); line-height: 1.25; overflow-wrap: anywhere; }
        .register-page .register-form-heading p { overflow-wrap: anywhere; }
        .register-page .register-back-button { align-self: flex-start; padding: .4rem .65rem; font-size: .78rem; }
        .register-page .register-back-button i { font-size: 1rem; }
        .register-page .register-fields { grid-template-columns: 1fr; gap: .8rem; }
        .register-page .register-name-group { grid-template-columns: minmax(0, 1fr) 90px; gap: .7rem; }
        .register-page .register-name-group .register-field:last-child { grid-column: 1 / -1; }
        .register-page .register-field-wide,
        .register-page .register-document-grid,
        .register-page .new-connection-fields { grid-column: auto; }
        .register-page .register-document-grid { grid-template-columns: 1fr; }
        .register-page .register-section-heading { grid-column: auto; }
        .register-page .recaptcha-wrapper .g-recaptcha { transform: scale(.78); }
    }

    @media (max-width: 380px) {
        .register-page .register-name-group { grid-template-columns: 1fr; }
        .register-page .register-name-group .register-field:last-child { grid-column: auto; }
    }
</style>

<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<script>
    const MAX_FILE_SIZE = 2 * 1024 * 1024;

    const allowedFileTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png'
    ];

    function validateFile(input, required = false) {
        const file = input.files?.[0];

        if (!file) {
            return !required;
        }

        if (file.size > MAX_FILE_SIZE) {
            input.value = '';

            const nameEl = document.getElementById(`${input.id}_name`);

            if (nameEl) {
                nameEl.textContent = 'Choose file';
                nameEl.classList.remove('has-file');
            }

            const fileUpload = input.closest('.file-upload');
            const label = fileUpload?.querySelector('.file-label');
            const errorEl = fileUpload?.querySelector('.file-size-error');

            label?.classList.add('is-invalid');
            errorEl?.classList.add('show');

            return false;
        }

        if (!allowedFileTypes.includes(file.type)) {
            input.value = '';

            const nameEl = document.getElementById(`${input.id}_name`);

            if (nameEl) {
                nameEl.textContent = 'Choose file';
                nameEl.classList.remove('has-file');
            }

            const fileUpload = input.closest('.file-upload');
            const label = fileUpload?.querySelector('.file-label');
            const errorEl = fileUpload?.querySelector('.file-size-error');

            label?.classList.add('is-invalid');
            errorEl?.classList.add('show');

            return false;
        }

        return true;
    }


    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('registerForm');
        const alertBox = document.getElementById('registerAlert');
        const contactInput = document.getElementById('contact_no');
        const consentModal = document.getElementById('dataConsentModal');
        const consentInput = document.getElementById('data_privacy_consent');
        const acceptConsent = document.getElementById('acceptConsent');
        const declineConsent = document.getElementById('declineConsent');
        const confirmConsent = document.getElementById('confirmConsent');
        const namePartInputs = form.querySelectorAll('.name-part-input');
        const firstNameInput = document.getElementById('first_name');
        const lastNameInput = document.getElementById('last_name');
        const registrationTypeInputs = form.querySelectorAll('input[name="registration_choice"]');
        const registrationTypeValue = document.getElementById('registration_type');
        const registrationTypeStep = document.getElementById('registrationTypeStep');
        const registrationFormStep = document.getElementById('registrationFormStep');
        const continueRegistration = document.getElementById('continueRegistration');
        const backToRegistrationType = document.getElementById('backToRegistrationType');
        const registrationTypeSummary = document.getElementById('registrationTypeSummary');
        const existingAccountFields = form.querySelectorAll('.existing-account-field');

        namePartInputs.forEach(function (input) {
            input.addEventListener('input', function () {
                this.value = this.value.toUpperCase();
            });
        });

        function selectedRegistrationType() {
            return registrationTypeValue.value;
        }

        const newConnectionFields = form.querySelectorAll('.new-connection-fields');

        function syncRegistrationTypeFields() {
            const isExisting = selectedRegistrationType() === 'existing_account';

            registrationTypeSummary.textContent = isExisting
                ? 'Existing account registration'
                : 'New water service connection';

            existingAccountFields.forEach(function (field) {
                field.classList.toggle('d-none', !isExisting);
            });

            newConnectionFields.forEach(function (field) {
                field.classList.toggle('d-none', isExisting);
            });

            form.account_no.required = isExisting;
            form.soa_file.required = isExisting;
            form.id_file.required = isExisting;
            form.picture_1x1.required = !isExisting;
            form.cedula_file.required = !isExisting;
            form.billing_file.required = !isExisting;
            form.authorization_file.required = false;
        }

        registrationTypeInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                registrationTypeValue.value = input.value;
                registrationTypeInputs.forEach(function (option) {
                    option.closest('.account-type-card').classList.toggle('selected', option.checked);
                });
                syncRegistrationTypeFields();
            });
        });

        syncRegistrationTypeFields();

        continueRegistration.addEventListener('click', function () {
            registrationTypeValue.value = form.querySelector('input[name="registration_choice"]:checked').value;
            syncRegistrationTypeFields();
            registrationTypeStep.classList.add('d-none');
            registrationFormStep.classList.remove('d-none');
            registrationFormStep.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        backToRegistrationType.addEventListener('click', function () {
            registrationFormStep.classList.add('d-none');
            registrationTypeStep.classList.remove('d-none');
            registrationTypeStep.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        function bindFileLabel(inputId, nameId) {
            const input = document.getElementById(inputId);
            const nameEl = document.getElementById(nameId);
            const label = input.closest('.file-upload').querySelector('.file-label');
            const errorEl = document.createElement('div');
            errorEl.className = 'file-size-error';
            errorEl.textContent = 'Choose a PDF, JPG, or PNG file up to 2 MB.';
            input.closest('.file-upload').appendChild(errorEl);

            input.addEventListener('change', function () {

                const file = input.files?.[0];

                if (!file) {
                    nameEl.textContent = 'Choose file';
                    nameEl.classList.remove('has-file');
                    label.classList.remove('is-invalid');
                    errorEl.classList.remove('show');
                    return;
                }

                if (file.size > MAX_FILE_SIZE) {
                    input.value = '';
                    nameEl.textContent = 'Choose file';
                    nameEl.classList.remove('has-file');
                    label.classList.add('is-invalid');
                    errorEl.classList.add('show');

                    return;
                }

                errorEl.classList.remove('show');
                label.classList.remove('is-invalid');
                nameEl.textContent = file.name;
                nameEl.classList.add('has-file');
            });
        }

        bindFileLabel('soa_file', 'soa_file_name');
        bindFileLabel('id_file', 'id_file_name');
        bindFileLabel('cedula_file', 'cedula_file_name');
        bindFileLabel('billing_file', 'billing_file_name');
        bindFileLabel('authorization_file', 'authorization_file_name');
        bindFileLabel('picture_1x1', 'picture_1x1_name');

        contactInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 11);
        });

        const accountNoInput = document.getElementById('account_no');
        const passwordHelp = document.getElementById('registration-password-help');
        const passwordInput = document.getElementById('password');

        function passwordRequirementsMissing(password) {
            const missing = [];
            if (password.length < 8) missing.push('at least 8 characters');
            if (!/[A-Z]/.test(password)) missing.push('one capital letter');
            if (!/[0-9]/.test(password)) missing.push('one number');
            if (!/[^A-Za-z0-9]/.test(password)) missing.push('one special character');
            return missing;
        }

        function updatePasswordHelp() {
            const missing = passwordRequirementsMissing(passwordInput.value);
            const isPasswordProvided = passwordInput.value.length > 0;
            passwordHelp.classList.toggle('text-danger', isPasswordProvided && missing.length > 0);
            passwordHelp.classList.toggle('text-success', isPasswordProvided && missing.length === 0);
            passwordHelp.textContent = !isPasswordProvided
                ? 'Password must contain at least 8 characters, one capital letter, one number, and one special character (like !, @, or #).'
                : missing.length
                    ? 'Password must contain ' + missing.join(', ').replace(/, ([^,]*)$/, ' and $1') + '.'
                    : 'Password meets all requirements.';
            passwordInput.classList.toggle('is-invalid', isPasswordProvided && missing.length > 0);
        }

        passwordInput.addEventListener('input', updatePasswordHelp);

        accountNoInput.addEventListener('input', function () {
            const cursor = this.selectionStart ?? this.value.length;
            const digitsBeforeCursor = this.value.slice(0, cursor).replace(/\D/g, '').length;
            const digits = this.value.replace(/\D/g, '').slice(0, 11);
            let formatted = digits;

            if (digits.length > 3) {
                formatted = digits.slice(0, 3) + '-' + digits.slice(3);
            }
            if (digits.length > 5) {
                formatted = digits.slice(0, 3) + '-' + digits.slice(3, 5) + '-' + digits.slice(5);
            }

            this.value = formatted;
            let nextCursor = 0;
            let countedDigits = 0;
            while (nextCursor < formatted.length && countedDigits < digitsBeforeCursor) {
                if (/\d/.test(formatted[nextCursor])) countedDigits++;
                nextCursor++;
            }
            if (formatted[nextCursor] === '-') nextCursor++;
            this.setSelectionRange(nextCursor, nextCursor);
        });

        function showAlert(type, message) {
            alertBox.className = 'alert alert-' + type + ' w-100 py-2 px-3 mb-2';
            alertBox.textContent = message;
            alertBox.classList.remove('d-none');
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function clearErrors() {
            form.querySelectorAll('.is-invalid').forEach(function (el) {
                el.classList.remove('is-invalid');
            });
            alertBox.classList.add('d-none');
        }

        function validateRegistrationForm() {
            const firstName = firstNameInput.value.trim();
            const lastName = lastNameInput.value.trim();
            const email = form.email.value.trim();
            const contact = form.contact_no.value.trim();
            const accountNo = form.account_no.value.trim();
            const address = form.address.value.trim();
            const password = form.password.value;
            const passwordConfirm = form.password_confirmation.value;
            const soaFile = form.soa_file.files[0];
            const idFile = form.id_file.files[0];
            const pictureFile = form.picture_1x1.files[0];
            const cedulaFile = form.cedula_file.files[0];
            const billingFile = form.billing_file.files[0];
            const isExisting = selectedRegistrationType() === 'existing_account';

            let firstInvalid = null;

            function markInvalid(el) {
                if (!el) return;
                el.classList.add('is-invalid');
                if (!firstInvalid) {
                    firstInvalid = el;
                }
            }

            if (!firstName) markInvalid(firstNameInput);
            if (!lastName) markInvalid(lastNameInput);
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                markInvalid(form.email);
            }
            if (!contact || contact.length < 10) {
                markInvalid(form.contact_no);
            }
            if (!address) {
                markInvalid(form.address);
            }
            const missingPasswordRequirements = passwordRequirementsMissing(password);
            if (missingPasswordRequirements.length) {
                markInvalid(form.password);
            }
            if (!passwordConfirm || password !== passwordConfirm) {
                markInvalid(form.password_confirmation);
            }

            if (isExisting) {
                if (!accountNo) {
                    markInvalid(form.account_no);
                }
                if (!soaFile) {
                    markInvalid(document.querySelector('label[for="soa_file"]'));
                }
                if (!idFile) {
                    markInvalid(document.querySelector('label[for="id_file"]'));
                }
            }

            if (!isExisting) {
                if (!pictureFile) {
                    markInvalid(document.querySelector('label[for="picture_1x1"]'));
                }
                if (!cedulaFile) {
                    markInvalid(document.querySelector('label[for="cedula_file"]'));
                }
                if (!billingFile) {
                    markInvalid(document.querySelector('label[for="billing_file"]'));
                }
            }

            if (firstInvalid) {
                const message = firstInvalid === form.password && missingPasswordRequirements.length
                    ? 'Password must contain ' + missingPasswordRequirements.join(', ').replace(/, ([^,]*)$/, ' and $1') + '.'
                    : 'Please fill in all required fields correctly.';
                showAlert('danger', message);
                firstInvalid.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
                return false;
            }
            return true;
        }

        function submitRegistration() {
            const submitButton = form.querySelector('button[type="submit"]');
            const submitButtonLabel = submitButton.querySelector('span');

            const recaptchaResponse = grecaptcha.getResponse();

            console.log('reCAPTCHA token exists:', !!recaptchaResponse);
            console.log('reCAPTCHA token length:', recaptchaResponse ? recaptchaResponse.length : 0);

            if (!recaptchaResponse) {
                showAlert(
                    'danger',
                    'Please complete the reCAPTCHA verification.'
                );
                return;
            }

            submitButton.disabled = true;
            submitButtonLabel.textContent = 'Submitting...';

            const fileInputs = [
                form.soa_file,
                form.id_file,
                form.picture_1x1,
                form.cedula_file,
                form.billing_file,
                form.authorization_file
            ];

            for (const input of fileInputs) {
                if (!input) continue;

                if (!validateFile(input, input.required)) {
                    submitButton.disabled = false;
                    submitButtonLabel.textContent = 'Submit application';
                    return;
                }
            }

            const formData = new FormData(form);

            formData.set(
                'g-recaptcha-response',
                recaptchaResponse
            );

            fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: formData
            })
            .then(async function (response) {
                const payload = await response.json().catch(function () {
                    return {};
                });

                if (!response.ok) {
                    const errors = payload.errors || {};

                    if (errors.first_name) firstNameInput.classList.add('is-invalid');
                    if (errors.middle_initial) document.getElementById('middle_initial').classList.add('is-invalid');
                    if (errors.last_name) lastNameInput.classList.add('is-invalid');

                    if (errors.email) {
                        form.email.classList.add('is-invalid');
                    }

                    if (errors.contact_no) {
                        form.contact_no.classList.add('is-invalid');
                    }

                    if (errors.account_no) {
                        form.account_no.classList.add('is-invalid');
                    }

                    if (errors.address) {
                        form.address.classList.add('is-invalid');
                    }

                    if (errors.password) {
                        form.password.classList.add('is-invalid');
                    }

                    if (errors.password_confirmation) {
                        form.password_confirmation.classList.add('is-invalid');
                    }

                    const firstError =
                        Object.values(errors).flat()[0] ||
                        payload.message ||
                        'Registration failed.';

                    throw new Error(firstError);
                }

                return payload;
            })
            .then(function (payload) {
                showAlert(
                    'success',
                    payload.message || 'Registration submitted successfully.'
                );

                setTimeout(function () {
                    window.location.href =
                        payload.redirect ||
                        "{{ route('register.verify') }}";
                }, 800);
            })
            .catch(function (error) {
                console.error('Registration error:', error);

                showAlert(
                    'danger',
                    error.message || 'Registration failed. Please try again.'
                );

                submitButton.disabled = false;
                submitButtonLabel.textContent = 'Submit application';
                
                if (typeof grecaptcha !== 'undefined') {
                    grecaptcha.reset();
                }
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors();
            consentInput.value = '';

            namePartInputs.forEach(function (input) {
                input.value = input.value.trim().toUpperCase();
            });

            if (!validateRegistrationForm()) {
                return;
            }

            consentModal.classList.remove('d-none');
        });

        declineConsent.addEventListener('click', function () {
            consentInput.value = '';
            confirmConsent.checked = false;
            acceptConsent.disabled = true;
            consentModal.classList.add('d-none');
        });


        acceptConsent.addEventListener('click', function () {
            if (!confirmConsent.checked) {
                return;
            }

            consentInput.value = '1';
            consentModal.classList.add('d-none');
            submitRegistration();
        });

        confirmConsent.addEventListener('change', function () {
            acceptConsent.disabled = !this.checked;
        });

    });
</script>
@endsection
