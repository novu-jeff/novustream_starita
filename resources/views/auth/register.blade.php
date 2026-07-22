@extends('layouts.auth')

@section('content')

<div class="login register-page">
    <div class="container right-panel-active scroll" id="container">
        <div class="form-container sign-up-container">
            <form id="registerForm" novalidate>
                <h1 class="fw-bold mb-1">Create Account</h1>
                <span>Register as a concessionaire</span>

                <div id="registerAlert" class="alert d-none w-100 py-2 px-3 mb-2" role="alert"></div>

                <div class="register-fields w-100">
                    <div class="w-100">
                        <input type="text" class="form-control" name="name" id="name" placeholder="Full Name *" required />
                    </div>
                    <div class="w-100">
                        <input type="email" class="form-control" name="email" id="email" placeholder="Email *" required />
                    </div>
                    <div class="w-100">
                        <input type="tel" class="form-control" name="contact_no" id="contact_no" placeholder="Contact No. *" maxlength="11" inputmode="numeric" required />
                    </div>
                    <div class="w-100">
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

                    <div class="file-upload w-100">
                        <label for="soa_file" class="file-label">
                            <i class="bx bx-file"></i>
                            <span class="file-text">Last SOA *</span>
                            <span class="file-name" id="soa_file_name">Choose file</span>
                        </label>
                        <input type="file" id="soa_file" name="soa_file" accept=".pdf,.jpg,.jpeg,.png" required />
                    </div>

                    <div class="file-upload w-100">
                        <label for="id_file" class="file-label">
                            <i class="bx bx-id-card"></i>
                            <span class="file-text">Valid ID *</span>
                            <span class="file-name" id="id_file_name">Choose file</span>
                        </label>
                        <input type="file" id="id_file" name="id_file" accept=".pdf,.jpg,.jpeg,.png" required />
                    </div>

                    <p class="file-hint mb-0">Accepted: PDF, JPG, PNG</p>
                </div>

                <button type="submit" class="mt-3">Submit Registration</button>
                <a href="{{ route('auth.index') }}" class="mt-2 mb-0">Already have an account? Sign in</a>
            </form>
        </div>
        <div class="overlay-container">
            <div class="overlay">
                <div class="overlay-panel overlay-left">
                    <img src="{{ asset(config('app.product') === 'novustream' ? 'images/clientnobg.png' : 'images/novusurgelogo.png') }}" alt="" class="w-75">
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
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('registerForm');
    const alertBox = document.getElementById('registerAlert');
    const contactInput = document.getElementById('contact_no');

    function bindFileLabel(inputId, nameId) {
        const input = document.getElementById(inputId);
        const nameEl = document.getElementById(nameId);
        const label = input.closest('.file-upload').querySelector('.file-label');

        input.addEventListener('change', function () {
            if (input.files && input.files[0]) {
                nameEl.textContent = input.files[0].name;
                nameEl.classList.add('has-file');
                label.classList.remove('is-invalid');
            } else {
                nameEl.textContent = 'Choose file';
                nameEl.classList.remove('has-file');
            }
        });
    }

    bindFileLabel('soa_file', 'soa_file_name');
    bindFileLabel('id_file', 'id_file_name');

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

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearErrors();

        const name = form.name.value.trim();
        const email = form.email.value.trim();
        const contact = form.contact_no.value.trim();
        const accountNo = form.account_no.value.trim();
        const address = form.address.value.trim();
        const password = form.password.value;
        const passwordConfirm = form.password_confirmation.value;
        const soaFile = form.soa_file.files[0];
        const idFile = form.id_file.files[0];

        let firstInvalid = null;

        function markInvalid(el) {
            el.classList.add('is-invalid');
            if (!firstInvalid) firstInvalid = el;
        }

        if (!name) markInvalid(form.name);
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) markInvalid(form.email);
        if (!contact || contact.length < 10) markInvalid(form.contact_no);
        if (!accountNo) markInvalid(form.account_no);
        if (!address) markInvalid(form.address);
        if (!password || password.length < 8) markInvalid(form.password);
        if (!passwordConfirm || password !== passwordConfirm) markInvalid(form.password_confirmation);
        if (!soaFile) markInvalid(document.querySelector('label[for="soa_file"]'));
        if (!idFile) markInvalid(document.querySelector('label[for="id_file"]'));

        if (firstInvalid) {
            showAlert('danger', 'Please fill in all required fields correctly.');
            firstInvalid.focus();
            return;
        }

        // Frontend only — no backend save yet
        showAlert('success', 'Registration submitted for review. You may sign in once your account is approved.');
        form.reset();
        document.getElementById('soa_file_name').textContent = 'Choose file';
        document.getElementById('soa_file_name').classList.remove('has-file');
        document.getElementById('id_file_name').textContent = 'Choose file';
        document.getElementById('id_file_name').classList.remove('has-file');
    });
});
</script>
@endsection
