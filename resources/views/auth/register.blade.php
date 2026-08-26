@extends('layouts.auth')

@section('content')

<div class="login register-page">
    <div class="container right-panel-active scroll" id="container">
        <div class="form-container sign-up-container">
            <form id="registerForm" novalidate>
                <input type="hidden" name="data_privacy_consent" id="data_privacy_consent" value="">
                <h1 class="fw-bold mb-1">Create Account</h1>
                <span>Register as a concessionaire</span>

                <div id="registerAlert" class="alert d-none w-100 py-2 px-3 mb-2" role="alert"></div>

                <div class="register-fields w-100">
                    <div class="registration-type w-100">
                        <label class="registration-type-option">
                            <input type="radio" name="registration_type" value="existing_account" checked>
                            <span>Existing Sta. Rita Account</span>
                        </label>
                        <label class="registration-type-option">
                            <input type="radio" name="registration_type" value="new_connection">
                            <span>New Concessionaire</span>
                        </label>
                    </div>
                    <div class="w-100">
                        <input type="text" class="form-control" name="name" id="name" placeholder="LASTNAME, FIRSTNAME M.I. (e.g. DELA CRUZ, JUAN P.) *" style="text-transform: uppercase;" required/>
                    </div>
                    <div class="w-100">
                        <input type="email" class="form-control" name="email" id="email" placeholder="Email *" required />
                    </div>
                    <div class="w-100">
                        <input type="tel" class="form-control" name="contact_no" id="contact_no" placeholder="Contact No. *" maxlength="11" inputmode="numeric" required />
                    </div>
                    <div class="w-100 existing-account-field">
                        <input type="text" class="form-control" name="account_no" id="account_no" placeholder="Account No. *" required />
                    </div>
                    <div class="w-100">
                        <input type="text" class="form-control" name="address" id="address" placeholder="Service Address *" required />
                    </div>
                    <div class="w-100">
                        <input type="password" class="form-control" name="password" id="password" placeholder="Password *" minlength="8" required />
                    </div>
                    <div class="w-100">
                        <input type="password" class="form-control" name="password_confirmation" id="password_confirmation" placeholder="Confirm Password *" minlength="8" required />
                    </div>

                    <div class="file-upload w-100 existing-account-field">
                        <label for="soa_file" class="file-label">
                            <i class="bx bx-file"></i>
                            <span class="file-text">Last SOA <span class="text-danger">*</span></span>
                            <span class="file-name" id="soa_file_name">Choose file</span>
                        </label>
                        <input type="file" id="soa_file" name="soa_file" accept=".pdf,.jpg,.jpeg,.png" required />
                    </div>

                    <div class="file-upload w-100 existing-account-field">
                        <label for="id_file" class="file-label">
                            <i class="bx bx-id-card"></i>
                            <span class="file-text">1x1 or 2x2 Picture <span class="text-danger">*</span></span>
                            <span class="file-name" id="id_file_name">Choose file</span>
                        </label>
                        <input type="file" id="id_file" name="id_file" accept=".pdf,.jpg,.jpeg,.png" required />
                    </div>

                    <div class="new-connection-fields d-none w-100">
                        <div class="file-upload w-100">
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
                        <div class="file-upload w-100">
                            <label for="cedula_file" class="file-label">
                                <i class="bx bx-file"></i>
                                <span class="file-text">Latest Cedula / Residence Certificate <span class="text-danger">*</span></span>
                                <span class="file-name" id="cedula_file_name">Choose file</span>
                            </label>
                            <input type="file"
                                id="cedula_file"
                                name="cedula_file"
                                accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="file-upload w-100">
                            <label for="billing_file" class="file-label">
                                <i class="bx bx-receipt"></i>
                                <span class="file-text">Proof of Billing (Electric Bill) <span class="text-danger">*</span></span>
                                <span class="file-name" id="billing_file_name">Choose file</span>
                            </label>
                            <input type="file"
                                id="billing_file"
                                name="billing_file"
                                accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                        <div class="file-upload w-100">
                            <label for="authorization_file" class="file-label">
                                <i class="bx bx-file"></i>
                                <span class="file-text">Authorization Letter / SPA with Valid ID (Representative)</span>
                                <span class="file-name" id="authorization_file_name">Choose file</span>
                            </label>
                            <input type="file"
                                id="authorization_file"
                                name="authorization_file"
                                accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </div>

                    <p class="file-hint mb-0">Accepted: PDF, JPG, PNG</p>
                </div>

                <div class="w-100 mt-2">
                    <div
                        class="g-recaptcha"
                        data-sitekey="{{ config('services.recaptcha.site_key') }}">
                    </div>
                </div>

                <button type="submit" class="mt-3">Submit Registration</button>
                <a href="{{ route('auth.index') }}" class="mt-2 mb-0">Already have an account? Sign in</a>
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
    .register-page .container.scroll {
        min-height: 720px;
    }

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

    @media (min-width: 0px) and (max-width: 600px) {
        .overlay-container {
            display: none;
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
        const nameInput = document.getElementById('name');
        const registrationTypeInputs = form.querySelectorAll('input[name="registration_type"]');
        const existingAccountFields = form.querySelectorAll('.existing-account-field');

        nameInput.addEventListener('input', function () {
            this.value = this.value.toUpperCase();
        });

        function selectedRegistrationType() {
            return form.querySelector('input[name="registration_type"]:checked').value;
        }

        const newConnectionFields = form.querySelectorAll('.new-connection-fields');

        function syncRegistrationTypeFields() {
            const isExisting = selectedRegistrationType() === 'existing_account';

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
            input.addEventListener('change', syncRegistrationTypeFields);
        });

        syncRegistrationTypeFields();

        function bindFileLabel(inputId, nameId) {
            const input = document.getElementById(inputId);
            const nameEl = document.getElementById(nameId);
            const label = input.closest('.file-upload').querySelector('.file-label');
            const errorEl = document.createElement('div');
            errorEl.className = 'file-size-error';
            errorEl.textContent = 'File size must not exceed 2 MB.';
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
            const name = form.name.value.trim();
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

            if (!name) {
                markInvalid(form.name);
            }
            if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                markInvalid(form.email);
            }
            if (!contact || contact.length < 10) {
                markInvalid(form.contact_no);
            }
            if (!address) {
                markInvalid(form.address);
            }
            if (!password || password.length < 8) {
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
                showAlert(
                    'danger',
                    'Please fill in all required fields correctly.'
                );
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
            const recaptchaResponse = grecaptcha.getResponse();

            if (!recaptchaResponse) {
                showAlert(
                    'danger',
                    'Please complete the reCAPTCHA verification.'
                );

                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';

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
                    submitButton.textContent = 'Submit Registration';
                    return;
                }
            }

            const formData = new FormData(form);

            formData.append(
                'g-recaptcha-response',
                recaptchaResponse
            );

            fetch('{{ route('auth.register.store') }}', {
                method: 'POST',

                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },

                body: formData,
            })

            fetch('{{ route('auth.register.store') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: new FormData(form),
            })
            .then(async function (response) {
                const payload = await response.json().catch(function () {
                    return {};
                });

                if (!response.ok) {
                    const errors = payload.errors || {};
                    if (errors.name) {
                        form.name.classList.add('is-invalid');
                    }
                    if (errors.account_no) {
                        form.account_no.classList.add('is-invalid');
                    }
                    const firstError = Object.values(errors).flat()[0] || null;
                    throw new Error(firstError || payload.message || 'Registration failed.');
                }

                return payload;
            })
            .then(function (payload) {

                showAlert('success', payload.message || 'Registration submitted successfully.');

                setTimeout(function () {
                    window.location.href = payload.redirect || "{{ route('account-overview.index') }}";

                }, 1000);

            })
            .catch(function (error) {
                showAlert('danger', error.message);
                submitButton.disabled = false;
                submitButton.textContent = 'Submit Registration';
            });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors();
            consentInput.value = '';

            form.name.value = form.name.value.toUpperCase();

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
