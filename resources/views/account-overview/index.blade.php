@extends('layouts.app')

@section('content')

@if(session('using_default_password'))
<div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-md">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white justify-content-center">
                <h5 class="modal-title fw-bold text-uppercase text-center">
                    Security Notice
                </h5>
            </div>
            <div class="modal-body text-center py-5">
                <p class="fs-5">
                    You are using a default password. Please change it now to secure your account.
                </p>

                <div class="d-flex justify-content-center gap-3 mt-4">
                    <a href="{{ route('profile.index', ['user_type' => 'concessionaire']) }}"
                       class="btn btn-primary fw-bold text-uppercase px-4 py-2">
                        Change Password
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('changePasswordModal');
    if (modalEl) {
        const modal = new bootstrap.Modal(modalEl, {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();
    }
});
</script>
@endif

    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between align-items-center">

                <h1>Account Overview</h1>

            </div>
            <div class="mt-3">
                <button type="button"
                        class="btn btn-outline-primary fw-bold text-uppercase"
                        data-bs-toggle="modal"
                        data-bs-target="#addAccountModal">
                    <i class="bx bx-link-alt"></i>
                    Add Account
                </button>
            </div>
            @if(session('status'))
                <div class="alert alert-success text-uppercase fw-medium text-center mt-4 mb-0">
                    {{ session('status') }}
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger text-uppercase fw-medium mt-4 mb-0">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="modal fade" id="addAccountModal" tabindex="-1" aria-labelledby="addAccountModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold text-uppercase" id="addAccountModalLabel">Add Account</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form id="addAccountForm" method="POST" action="{{ route('account-overview.accounts.store') }}" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="data_privacy_consent" id="add_account_consent" value="">
                            <div class="modal-body">
                                @if($errors->has('account_no') || $errors->has('name') || $errors->has('soa_file') || $errors->has('id_file') || $errors->has('data_privacy_consent'))
                                    <div class="alert alert-danger" role="alert">
                                        {{ $errors->first() }}
                                    </div>
                                @endif
                                <p class="text-muted">Submit another account under your existing login. It will remain pending until the district verifies and approves it.</p>
                                <div class="mb-3">
                                    <label for="add_account_no" class="form-label">Account No.</label>
                                    <input type="text" class="form-control" id="add_account_no" name="account_no" value="{{ old('account_no') }}" required>
                                </div>
                                <div class="mb-3">
                                    <label for="add_account_name" class="form-label">Account Holder Name</label>
                                    <input type="text" class="form-control" id="add_account_name" name="name" value="{{ old('name', $my->name) }}" required>
                                </div>
                                <div class="mb-3">
                                    <label for="add_soa_file" class="form-label">Latest SOA</label>
                                    <input type="file" class="form-control" id="add_soa_file" name="soa_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                </div>
                                <div class="mb-3">
                                    <label for="add_id_file" class="form-label">Valid ID</label>
                                    <input type="file" class="form-control" id="add_id_file" name="id_file" accept=".pdf,.jpg,.jpeg,.png" required>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary text-uppercase fw-bold">Submit Account</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="addAccountConsentModal" tabindex="-1" aria-labelledby="addAccountConsentTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered add-account-consent-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title fw-bold text-uppercase" id="addAccountConsentTitle">Account Verification Consent</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Sta. Rita Water District will use the information and documents you submit to verify account ownership and your request to link this account to your login.</p>
                            <p>By continuing, you confirm that the details and documents are accurate and allow the district to review them.</p>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="confirmAddAccountConsent">
                                <label class="form-check-label" for="confirmAddAccountConsent">I have read and agree to the account verification consent above.</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary text-uppercase fw-bold" id="acceptAddAccountConsent" disabled>I Agree</button>
                        </div>
                    </div>
                </div>
            </div>
            @php
                $primaryNotification = $accountNotifications[0] ?? $applicationNotification ?? null;
            @endphp

            @if(!empty($accountNotifications))
                <div class="application-notification">
                    <button type="button" class="application-notification-button" id="applicationNotificationToggle" aria-label="View account notifications">
                        <i class="bx bx-bell"></i>
                        <span class="application-notification-dot bg-{{ $primaryNotification['status'] ?? 'primary' }}"></span>
                    </button>
                    <div class="application-notification-panel d-none" id="applicationNotificationPanel">
                        <div class="fw-bold text-uppercase mb-3">Notifications</div>

                        @foreach($accountNotifications as $notification)
                            <div class="application-notification-item {{ $loop->last ? 'mb-0 pb-0 border-0' : '' }}">
                                <div class="d-flex align-items-start justify-content-between gap-3">
                                    <div>
                                        <div class="fw-bold text-uppercase mb-1">{{ $notification['title'] }}</div>
                                        <div class="small text-muted">{{ $notification['message'] }}</div>
                                        @if(!empty($notification['date']))
                                            <div class="small text-muted mt-2">{{ $notification['date'] }}</div>
                                        @endif
                                    </div>
                                    <span class="badge bg-{{ $notification['status'] ?? 'primary' }}">
                                        {{ ucfirst($notification['status'] ?? 'info') }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <div class="inner-content mt-5 pb-5">
                @php
                    $visibleApprovalNotice = session('approval_notice') ?? ($approvalNotice ?? null);
                @endphp

	                @if(!empty($visibleApprovalNotice))
	                    <div class="alert alert-{{ $visibleApprovalNotice['status'] ?? 'warning' }} text-uppercase fw-medium text-center mb-4">
	                        {{ $visibleApprovalNotice['message'] ?? 'Your application is currently in the approval stage.' }}
	                    </div>
                        @if($accounts->contains('application_type', 'new_connection'))
                            <div class="alert alert-{{ $visibleApprovalNotice['status'] ?? 'warning' }} text-uppercase fw-medium text-center mb-4">
                                Please provide the original documents in hard copy and submit them to the Sta. Rita Branch.
                            </div>
                        @endif
	                @endif

                    @if(!empty($serviceApplication))
                        <div class="card shadow border-0 p-4 mb-4">
                            <div class="card-body">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                                    <div>
                                        <small class="text-uppercase fw-bold text-muted">Water Service Application</small>
                                        <h5 class="fw-bold text-uppercase mb-0">{{ $serviceApplication->application_no }}</h5>
                                    </div>
                                    <div class="d-flex flex-wrap gap-2">
                                        @if(($applicationStatus ?? null) === 'pending')
                                            <button type="button"
                                                    class="btn btn-primary fw-bold text-uppercase"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#serviceApplicationModal">

                                                <i class="bx bx-water"></i>
                                                New Water Service Connection

                                            </button>
                                        @elseif(!empty($serviceApplication))
                                            <a href="{{ route('application.show', $serviceApplication) }}"
                                            class="btn btn-primary fw-bold text-uppercase">

                                                <i class="bx bx-file"></i>
                                                View Application

                                            </a>
                                        @else
                                            <button type="button"
                                                    class="btn btn-primary fw-bold text-uppercase"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#serviceApplicationModal">

                                                <i class="bx bx-water"></i>
                                                New Water Service Connection

                                            </button>
                                        @endif
                                        <a href="{{ route('application.show', $serviceApplication) }}" class="btn btn-primary fw-bold text-uppercase">
                                            View Application
                                        </a>
                                        <a href="{{ route('application.contract', $serviceApplication) }}" class="btn btn-outline-primary fw-bold text-uppercase">
                                            View Contract
                                        </a>
                                        <a href="{{ route('application.contract.print', $serviceApplication) }}" target="_blank" class="btn btn-outline-primary fw-bold text-uppercase">
                                            Print Contract
                                        </a>
                                        <a href="{{ route('application.create') }}" class="btn btn-outline-primary fw-bold text-uppercase">
                                            Review Form
                                        </a>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 col-md-6">
                                        <table class="table table-bordered table-hover mb-0">
                                            <tbody>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Applicant</th>
                                                    <td class="text-uppercase fw-bold text-muted">{{ $serviceApplication->applicant_name }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Contact No</th>
                                                    <td class="text-uppercase fw-bold text-muted">{{ $serviceApplication->cellphone }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Email</th>
                                                    <td class="text-uppercase fw-bold text-muted">{{ $my->email }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Service Address</th>
                                                    <td class="text-uppercase fw-bold text-muted">{{ $serviceApplication->service_address }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="col-12 col-md-6 mt-3 mt-md-0">
                                        <table class="table table-bordered table-hover mb-0">
                                            <tbody>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Application Type</th>
                                                    <td class="text-uppercase fw-bold text-muted">{{ $serviceApplication->application_type }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Connection Type</th>
                                                    <td class="text-uppercase fw-bold text-muted">
                                                        {{ ($serviceApplication->connection_type ?? 'on_line') === 'traverse' ? 'Traverse' : 'On-line' }}
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Application Fee</th>
                                                    <td class="text-uppercase fw-bold text-muted">
                                                        PHP {{ number_format((float) ($serviceApplication->application_fee_amount ?? 4000), 2) }}
                                                        ({{ ucfirst($serviceApplication->application_fee_status ?? 'unpaid') }})
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Installation Location</th>
                                                    <td class="text-uppercase fw-bold text-muted">{{ $serviceApplication->installation_location }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Property Owner</th>
                                                    <td class="text-uppercase fw-bold text-muted">{{ $serviceApplication->property_owner }}</td>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Documents</th>

                                                    <td class="text-uppercase fw-bold text-muted">

                                                        @php
                                                            $isNewConcessionaire = $accounts->contains('application_type', 'new_connection');
                                                            $identityDocumentLabel = $isNewConcessionaire ? '1x1 Image' : 'Valid ID';
                                                        @endphp

                                                        {{-- Valid ID / 1x1 Image --}}
                                                        <div class="document-row">
                                                            <span>
                                                                {{ $identityDocumentLabel }}:
                                                                {{ $serviceApplication->documents?->valid_id ? 'Uploaded' : 'Missing' }}
                                                            </span>

                                                            @if($serviceApplication->documents?->valid_id)
                                                                <a  type="button"
                                                                    class="document-btn document-btn-view"
                                                                    title="View {{ $identityDocumentLabel }}"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#viewDocumentModal"
                                                                    data-document-url="{{ \Illuminate\Support\Facades\Storage::url($serviceApplication->documents->valid_id) }}"
                                                                    data-document-name="{{ $identityDocumentLabel }}">
                                                                    <i class="bx bx-show"></i>
                                                                </a>
                                                            @endif

                                                            <button type="button"
                                                                    class="document-btn document-btn-edit"
                                                                    title="Replace {{ $identityDocumentLabel }}"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#replaceDocumentModal"
                                                                    data-document="valid_id"
                                                                    data-label="{{ $identityDocumentLabel }}">
                                                                <i class="bx bx-edit"></i>
                                                            </button>
                                                        </div>


                                                        {{-- Cedula --}}
                                                        <div class="document-row">
                                                            <span>
                                                                Cedula:
                                                                {{ $serviceApplication->documents?->cedula ? 'Uploaded' : 'Missing' }}
                                                            </span>

                                                            @if($serviceApplication->documents?->cedula)
                                                                <a  type="button"
                                                                    class="document-btn document-btn-view"
                                                                    title="View Cedula"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#viewDocumentModal"
                                                                    data-document-url="{{ \Illuminate\Support\Facades\Storage::url($serviceApplication->documents->cedula) }}"
                                                                    data-document-name="Cedula">
                                                                    <i class="bx bx-show"></i>
                                                                </a>
                                                            @endif

                                                            <button type="button"
                                                                    class="document-btn document-btn-edit"
                                                                    title="Replace Cedula"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#replaceDocumentModal"
                                                                    data-document="cedula"
                                                                    data-label="Cedula">
                                                                <i class="bx bx-edit"></i>
                                                            </button>
                                                        </div>


                                                        {{-- Proof of Billing --}}
                                                        <div class="document-row">
                                                            <span>
                                                                Proof of Billing:
                                                                {{ $serviceApplication->documents?->proof_of_billing ? 'Uploaded' : 'Missing' }}
                                                            </span>

                                                            @if($serviceApplication->documents?->proof_of_billing)
                                                                <a  type="button"
                                                                    class="document-btn document-btn-view"
                                                                    title="View Proof of Billing"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#viewDocumentModal"
                                                                    data-document-url="{{ \Illuminate\Support\Facades\Storage::url($serviceApplication->documents->proof_of_billing) }}"
                                                                    data-document-name="Proof of Billing">
                                                                    <i class="bx bx-show"></i>
                                                                </a>
                                                            @endif

                                                            <button type="button"
                                                                    class="document-btn document-btn-edit"
                                                                    title="Replace Proof of Billing"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#replaceDocumentModal"
                                                                    data-document="proof_of_billing"
                                                                    data-label="Proof of Billing">
                                                                <i class="bx bx-edit"></i>
                                                            </button>
                                                        </div>


                                                        {{-- Authorization --}}
                                                        <div class="document-row">
                                                            <span>
                                                                Authorization:
                                                                {{ $serviceApplication->documents?->authorization_letter ? 'Uploaded' : 'N/A' }}
                                                            </span>

                                                            @if($serviceApplication->documents?->authorization_letter)
                                                                <a  type="button"
                                                                    class="document-btn document-btn-view"
                                                                    title="View Authorization"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#viewDocumentModal"
                                                                    data-document-url="{{ \Illuminate\Support\Facades\Storage::url($serviceApplication->documents->authorization_letter) }}"
                                                                    data-document-name="Authorization Letter">
                                                                    <i class="bx bx-show"></i>
                                                                </a>
                                                            @endif

                                                            <button type="button"
                                                                    class="document-btn document-btn-edit"
                                                                    title="Replace Authorization"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#replaceDocumentModal"
                                                                    data-document="authorization_letter"
                                                                    data-label="Authorization">
                                                                <i class="bx bx-edit"></i>
                                                            </button>
                                                        </div>

                                                        {{-- Boring / Cutting Permit --}}
                                                        @if(($serviceApplication->connection_type ?? 'on_line') === 'traverse')
                                                            <div class="document-row">
                                                                <span>
                                                                    Boring/Cutting Permit:
                                                                    {{ $serviceApplication->documents?->boring_permit ? 'Uploaded' : 'Required' }}
                                                                </span>

                                                                @if($serviceApplication->documents?->boring_permit)
                                                                    <a  type="button"
                                                                        class="document-btn document-btn-view"
                                                                        title="View Boring/Cutting Permit"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#viewDocumentModal"
                                                                        data-document-url="{{ \Illuminate\Support\Facades\Storage::url($serviceApplication->documents->boring_permit) }}"
                                                                        data-document-name="Boring/Cutting Permit">
                                                                        <i class="bx bx-show"></i>
                                                                    </a>
                                                                @endif

                                                                <button type="button"
                                                                        class="document-btn document-btn-edit"
                                                                        title="{{ $serviceApplication->documents?->boring_permit ? 'Replace Boring/Cutting Permit' : 'Upload Boring/Cutting Permit' }}"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#replaceDocumentModal"
                                                                        data-document="boring_permit"
                                                                        data-label="Boring/Cutting Permit">
                                                                    <i class="bx bx-edit"></i>
                                                                </button>
                                                            </div>
                                                        @endif

                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                @if(($serviceApplication->connection_type ?? 'on_line') === 'traverse' && empty($serviceApplication->documents?->boring_permit))
                                    <div class="alert alert-warning mt-4 mb-0">
                                        <div class="fw-bold text-uppercase mb-1">Additional Document Required</div>
                                        Please upload your Boring/Cutting Permit. Your Traverse application will remain pending until admin review.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    @if($accounts->isNotEmpty())
                    <div class="card shadow border-0 p-4 mb-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <small class="text-uppercase fw-bold text-muted">My Accounts</small>
                                <h5 class="fw-bold mb-0">Account Status and Bills</h5>
                            </div>
                            <span class="small text-muted">{{ $accounts->count() }} linked account(s)</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Account No.</th>
                                        <th>Account Name</th>
                                        <th>Address</th>
                                        <th>Status</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($accounts as $account)
                                        @php
                                            $accountStatus = $account->access_link_status
                                                ?: ($account->application_status
                                                ?: ($account->isApproved ? 'approved' : ($account->denied_at ? 'denied' : null)));
                                            $isDenied = $accountStatus === 'denied' || $account->denied_at;
                                            $isPending = $accountStatus === 'pending';
                                            $hasRegistrationDocuments = !empty($account->application_soa_path)
                                                || !empty($account->application_id_path);
                                            $canViewAccountBills = !$isDenied
                                                && ($accountStatus === null || $accountStatus === 'approved'
                                                    || (!$isPending && !$hasRegistrationDocuments));
                                        @endphp
                                        <tr>
                                            <td class="fw-bold">{{ $account->account_no }}</td>
                                            <td>{{ $account->user?->name ?? 'N/A' }}</td>
                                            <td>{{ $account->address ?: 'N/A' }}</td>
                                            <td>
                                                @if($isDenied)
                                                    <span class="badge bg-danger">Denied</span>
                                                @elseif($isPending)
                                                    <span class="badge bg-warning text-dark">Pending Approval</span>
                                                @else
                                                    <span class="badge bg-success">Approved</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if($canViewAccountBills)
                                                    <a href="{{ route('account-overview.bills', ['account_no' => $account->account_no, 'view' => 'unpaid']) }}"
                                                       class="btn btn-sm btn-primary text-uppercase fw-bold">
                                                        <i class="bx bx-receipt"></i> View Bills
                                                    </a>
                                                @elseif($isPending)
                                                    <span class="small text-muted">Waiting for approval</span>
                                                @else
                                                    <span class="small text-danger">Unavailable</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No accounts linked.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

	                @php

                    $currentDate = \Carbon\Carbon::now();

                    foreach ($sc_discounts as $discount) {

                        $startDate = $discount['effective_date'] ?? null;
                        $endDate = $discount['expired_date'] ?? null;

                        if ($startDate && $endDate) {
                            $account_no = $discount['account_no'];
                            $startDate = \Carbon\Carbon::parse($startDate);
                            $endDate = \Carbon\Carbon::parse($endDate);
                            if($currentDate->between($startDate, $endDate) && $currentDate->diffInMonths($endDate, false) <= 1) {
                                echo '<div class="blinking alert alert-danger text-uppercase text-center mb-3 fw-medium" style="word-spacing: 4px; letter-spacing: 0.1em;">Senior citizen discount for account <span class="fst-italic fw-bold text-decoration-underline" style="text-underline-offset: 5px;">' . $account_no . '</span> will be expiring on ' . $endDate->format('F d, Y') . '</div>';
                            }
                        }
                    }
                @endphp

                @if($data->accounts)
                    <div class="row pb-5">
                        <div class="col-12 col-md-6 mb-3">
                            <div class="bg-info mt-1 p-3 text-uppercase fw-bold text-white fs-5">
                                Payment Due Date:
                                <span class="ms-2 text-decoration-underline">
                                    @if($statement['total'] == 0)
                                        N/A
                                    @elseif($statement['total'] != 0)
                                        {{\Carbon\Carbon::parse($statement['due_date'])->format('F d, Y')}}
                                    @else
                                        Already Paid
                                    @endif
                                </span>
                            </div>
                            <div class="card shadow border-0 p-4 mt-3">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="mb-3">
                                            <small class="text-uppercase fw-bold text-muted">[+] Property Owner</small>
                                        </div>
                                        <table class="table table-bordered table-hover">
                                            <tbody>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Account Name</th>
                                                    <th class="text-uppercase fw-bold text-muted">{{$my->name}}</th>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Contact No</th>
                                                    <th class="text-uppercase fw-bold text-muted">{{$my->contact_no}}</th>
                                                </tr>
                                                <tr>
                                                    <th class="text-uppercase fw-bold text-muted">Email</th>
                                                    <th class="text-uppercase fw-bold text-muted">{{$my->email}}</th>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <hr class="my-4">
                                    <div class="mb-3">
                                        <small class="text-uppercase fw-bold text-muted">[+] Properties</small>
                                    </div>
                                    <div>
                                        @php
                                            $product = env('APP_PRODUCT');
                                        @endphp
                                        <div class="accordion accordion-flush" id="accordionAccountConnection">
                                            @forelse($accounts as $key => $account)
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header" id="heading{{$key}}">
                                                        <button class="accordion-button collapsed text-uppercase fw-bold text-muted" type="button" data-bs-toggle="collapse" data-bs-target="#collapse{{$key}}" aria-expanded="false" aria-controls="collapse{{$key}}">
                                                            {{$account->address}}
                                                        </button>
                                                    </h2>
                                                    <div id="collapse{{$key}}" class="accordion-collapse collapse" aria-labelledby="heading{{$key}}" data-bs-parent="#accordionAccountConnection">
                                                        <div class="accordion-body">
                                                            <table class="table table-bordered table-hover">
                                                                <tbody>
                                                                    <tr>
                                                                        <th class="text-uppercase fw-bold text-muted">Account No:</th>
                                                                        <th class="text-uppercase fw-bold text-muted">{{$account->account_no}}</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th class="text-uppercase fw-bold text-muted">Meter No:</th>
                                                                        <th class="text-uppercase fw-bold text-muted">{{$account->meter_serial_no}}</th>
                                                                    </tr>
                                                                   @if($product == 'novusurge')
                                                                        <tr>
                                                                            <th class="text-uppercase fw-bold text-muted">Meter Brand: </th>
                                                                            <th class="text-uppercase fw-bold text-muted">{{$account->meter_brand}}</th>
                                                                        </tr>
                                                                        <tr>
                                                                            <th class="text-uppercase fw-bold text-muted">Meter Type: </th>
                                                                            <th class="text-uppercase fw-bold text-muted">{{$account->meter_type}}</th>
                                                                        </tr>
                                                                        <tr>
                                                                            <th class="text-uppercase fw-bold text-muted">Meter Wire: </th>
                                                                            <th class="text-uppercase fw-bold text-muted">{{$account->meter_wire}}</th>
                                                                        </tr>
                                                                        <tr>
                                                                            <th class="text-uppercase fw-bold text-muted">Meter Form: </th>
                                                                            <th class="text-uppercase fw-bold text-muted">{{$account->meter_form}}</th>
                                                                        </tr>
                                                                        <tr>
                                                                            <th class="text-uppercase fw-bold text-muted">Meter Class: </th>
                                                                            <th class="text-uppercase fw-bold text-muted">{{$account->meter_class}}</th>
                                                                        </tr>
                                                                   @endif
                                                                    <tr>
                                                                        <th class="text-uppercase fw-bold text-muted">Property Type: </th>
                                                                        <th class="text-uppercase fw-bold text-muted">{{$account->property_type->name ?? 'N/A'}}</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th class="text-uppercase fw-bold text-muted">SC No: </th>
                                                                        <th class="text-uppercase fw-bold text-muted">{{$account->rate_code}}</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th class="text-uppercase fw-bold text-muted">Rate Code: </th>
                                                                        <th class="text-uppercase fw-bold text-muted">{{$account->rate_code}}</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th class="text-uppercase fw-bold text-muted">Sequence No: </th>
                                                                        <th class="text-uppercase fw-bold text-muted">{{$account->sequence_mp}}</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th class="text-uppercase fw-bold text-muted">Status: </th>
                                                                        <th class="text-uppercase fw-bold text-muted">{{$account->status}}</th>
                                                                    </tr>
                                                                    <tr>
                                                                        <th class="text-uppercase fw-bold text-muted">SC No: </th>
                                                                        <th class="text-uppercase fw-bold text-muted">{{$account->sc_no}}</th>
                                                                    </tr>
                                                                    @if($product == 'novsurge')
                                                                        <tr>
                                                                            <th class="text-uppercase fw-bold text-muted">Location: </th>
                                                                            <th class="text-uppercase fw-bold text-muted">{{$account->lat_long}}</th>
                                                                        </tr>
                                                                        <tr>
                                                                            <th class="text-uppercase fw-bold text-muted">ERC Seal: </th>
                                                                            <th class="text-uppercase fw-bold text-muted">{{$account->isErcSealed ? 'Yes' : 'No'}}</th>
                                                                        </tr>
                                                                    @endif
                                                                    <tr>
                                                                        <th class="text-uppercase fw-bold text-muted">Date Connected: </th>
                                                                        <th class="text-uppercase fw-bold text-muted">{{\Carbon\Carbon::parse($account->date_connected)->format('M d, Y')}}</th>
                                                                    </tr>
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <div class="alert alert-info text-muted text-center text-uppercase">No Account Linked</div>
                                            @endforelse
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <div class="card shadow border-0 p-3">
                                <div class="card-body">
                                    <div class="bg-primary mt-1 p-3 text-uppercase fw-bold text-white">
                                        Statement of Account as of
                                        <span class="text-decoration-underline">{{ \Carbon\Carbon::now()->format('F d, Y') }}</span>
                                    </div>
                                    <div class="note mt-3 ms-0 fst-italic text-uppercase fw-medium" style="font-size: 12px;">
                                        <strong>Disclaimer:</strong> Successful payments will be reflected on the next statement and can be viewed via the <strong>Payment History</strong>.
                                    </div>
                                    <ul class="nav nav-tabs mt-4" id="accountStatementTabs" role="tablist">
                                        @foreach($accountStatements as $index => $accountStatement)
                                            @php $statementAccount = $accountStatement['account']; @endphp
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link {{ $index === 0 ? 'active' : '' }} text-uppercase fw-bold"
                                                        id="statement-tab-{{ $index }}"
                                                        data-bs-toggle="tab"
                                                        data-bs-target="#statement-pane-{{ $index }}"
                                                        type="button"
                                                        role="tab"
                                                        aria-controls="statement-pane-{{ $index }}"
                                                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}">
                                                    {{ $statementAccount->account_no }}
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="tab-content" id="accountStatementTabContent">
                                        @foreach($accountStatements as $index => $accountStatement)
                                            @php
                                                $statementAccount = $accountStatement['account'];
                                                $statementStatus = $statementAccount->access_link_status
                                                    ?: ($statementAccount->application_status
                                                        ?: ($statementAccount->isApproved ? 'approved' : ($statementAccount->denied_at ? 'denied' : null)));
                                            @endphp
                                            <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }} pt-4"
                                                 id="statement-pane-{{ $index }}"
                                                 role="tabpanel"
                                                 aria-labelledby="statement-tab-{{ $index }}">
                                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                                    <div>
                                                        <div class="fw-bold text-uppercase">{{ $statementAccount->account_no }}</div>
                                                        <div class="small text-muted text-uppercase">{{ $statementAccount->address ?: 'N/A' }}</div>
                                                    </div>
                                                    @if($statementStatus === 'pending')
                                                        <span class="badge bg-warning text-dark">Pending Approval</span>
                                                    @elseif($statementStatus === 'denied')
                                                        <span class="badge bg-danger">Denied</span>
                                                    @else
                                                        <span class="badge bg-success">Available</span>
                                                    @endif
                                                </div>
                                                <div class="bg-danger d-flex align-items-center justify-content-between mt-1 p-3 text-uppercase fw-bold text-white">
                                                    <span>Total Amount Due</span>
                                                    <span>PHP {{ number_format($accountStatement['total'], 2) }}</span>
                                                </div>
                                                <div class="mt-4" style="font-size: 14px;">
                                                    @forelse($accountStatement['transactions'] as $transaction)
                                                        <a target="_blank" href="{{ route('account-overview.bills.reference_no', ['reference_no' => $transaction['reference_no']]) }}" class="text-decoration-none text-reset">
                                                            <div class="d-flex justify-content-between pb-3 pt-3 mb-3" style="border-top: 3px dotted rgba(0, 0, 0, 0.521); border-bottom: 3px dotted rgba(0, 0, 0, 0.521);">
                                                                <div>
                                                                    <div>{{ $transaction['reference_no'] }}</div>
                                                                    <div class="text-uppercase">
                                                                        {{ \Carbon\Carbon::parse($transaction['bill_period_from'])->format('M d, Y') }} - {{ \Carbon\Carbon::parse($transaction['bill_period_to'])->format('M d, Y') }}
                                                                    </div>
                                                                </div>
                                                                <div class="text-end fw-bold">
                                                                    PHP {{ number_format($transaction['amount'] ?? 0, 2) }}
                                                                </div>
                                                            </div>
                                                        </a>
                                                    @empty
                                                        <div class="alert alert-secondary text-uppercase text-center fw-bold">
                                                            {{ $statementStatus === 'pending' ? 'Statement unavailable until approval.' : 'No statement found.' }}
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-primary text-uppercase fw-medium text-center">No data found, Please make sure to have a meter no. connected to this account!</div>
                @endif
            </div>
        </div>

        @if(!empty($serviceApplication))
        <div class="modal fade"
            id="replaceDocumentModal"
            tabindex="-1"
            aria-hidden="true">

            <div class="modal-dialog">
                <div class="modal-content">

                    <form method="POST"
                        action="{{ route('service-application.documents.replace', $serviceApplication->id) }}"
                        enctype="multipart/form-data">

                        @csrf
                        @method('PUT')

                        <div class="modal-header">
                            <h5 class="modal-title">
                                Replace Document
                            </h5>

                            <button type="button"
                                    class="btn-close"
                                    data-bs-dismiss="modal">
                            </button>
                        </div>

                        <div class="modal-body">

                            <p class="mb-3">
                                Replace:
                                <strong id="documentLabel"></strong>
                            </p>

                            <input type="hidden"
                                name="document_type"
                                id="documentType">

                            <div class="mb-3">
                                <label class="form-label">
                                    Select New File
                                </label>

                                <input type="file"
                                    name="document"
                                    class="form-control"
                                    required
                                    accept=".jpg,.jpeg,.png,.pdf">

                                <small class="text-muted">
                                    Accepted: JPG, JPEG, PNG, PDF
                                </small>
                            </div>

                        </div>

                        <div class="modal-footer">

                            <button type="button"
                                    class="btn btn-secondary"
                                    data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button type="submit"
                                    class="btn btn-warning">
                                <i class="bx bx-upload"></i>
                                Replace Document
                            </button>

                        </div>

                    </form>

                </div>
            </div>
        </div>
        @endif

        <div class="modal fade"
            id="viewDocumentModal"
            tabindex="-1"
            aria-labelledby="viewDocumentModalLabel"
            aria-hidden="true">

            <div class="modal-dialog modal-xl modal-dialog-centered">

                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"
                            id="viewDocumentModalLabel">
                            View Document
                        </h5>

                        <button type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close">
                        </button>
                    </div>

                    <div class="modal-body p-0">

                        <div id="documentViewer"
                            style="height: 75vh; background: #f5f5f5;">

                            <div class="d-flex justify-content-center align-items-center h-100">
                                <div class="spinner-border text-primary"
                                    role="status">
                                </div>
                            </div>

                        </div>

                    </div>

                </div>

            </div>
        </div>
    </main>
    <style>

        .add-account-consent-dialog {
            width: min(560px, calc(100% - 2rem));
            max-width: none;
        }

        .add-account-consent-dialog .modal-content {
            border: 2px solid #0d6efd;
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.3);
        }


        .document-actions {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-left: 8px;
}

.document-btn {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: 1px solid transparent;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 17px;
    padding: 0;
}

/* View */
.document-btn-view {
    color: #0d6efd;
    background-color: #eef5ff;
    border-color: #cfe2ff;
}

.document-btn-view:hover {
    color: #fff;
    background-color: #0d6efd;
    border-color: #0d6efd;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(13, 110, 253, 0.25);
}

/* Edit / Replace */
.document-btn-edit {
    color: #f59e0b;
    background-color: #fff8e6;
    border-color: #ffe8a1;
}

.document-btn-edit:hover {
    color: #fff;
    background-color: #f59e0b;
    border-color: #f59e0b;
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(245, 158, 11, 0.25);
}

        .application-notification {
            position: fixed;
            right: 24px;
            top: 8rem;
            z-index: 1050;
        }

        .application-notification-button {
            position: relative;
            width: 52px;
            height: 52px;
            border: 0;
            border-radius: 50%;
            background: #0d6efd;
            color: #fff;
            box-shadow: 0 12px 30px rgba(13, 110, 253, 0.35);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .application-notification-button i {
            font-size: 1.55rem;
        }

        .application-notification-dot {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #fff;
        }

        .application-notification-panel {
            position: absolute;
            right: 0;
            top: 64px;
            width: min(340px, calc(100vw - 32px));
            max-height: min(520px, calc(100vh - 120px));
            overflow-y: auto;
            background: #fff;
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 8px;
            padding: 16px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
        }

        .application-notification-item {
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            padding-bottom: 12px;
            margin-bottom: 12px;
        }

        @media (max-width: 600px) {
            .application-notification {
                right: 16px;
                bottom: 16px;
            }
        }
    </style>

    <div class="modal fade" id="serviceApplicationModal" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header bg-primary text-white">

                <h5 class="modal-title fw-bold text-uppercase">
                    Water Service Application
                </h5>

                <button type="button"
                        class="btn-close btn-close-white"
                        data-bs-dismiss="modal">
                </button>

            </div>


            <div class="modal-body text-center">

                <i class="bx bx-water text-primary"
                   style="font-size:60px">
                </i>


                <h5 class="fw-bold mt-3">
                    Apply for New Service Connection?
                </h5>


                <p class="text-muted">
                    You will be redirected to the Water Service Connection Application Form.
                </p>


            </div>


            <div class="modal-footer justify-content-center">

                <button type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                    Cancel

                </button>


                <a href="/application/create"
                class="btn btn-primary fw-bold">
                    Continue Application
                </a>

            </div>


        </div>

    </div>

</div>
@endsection

@if(session('success'))
<script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: 'success',
            title: 'Document Updated',
            text: @json(session('success')),
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 10000,
            timerProgressBar: true
        });
    });
</script>
@endif

@section('script')
    <script>
        const addAccountForm = document.getElementById('addAccountForm');
        const addAccountConsentModal = document.getElementById('addAccountConsentModal');
        const confirmAddAccountConsent = document.getElementById('confirmAddAccountConsent');
        const acceptAddAccountConsent = document.getElementById('acceptAddAccountConsent');
        const addAccountConsentInput = document.getElementById('add_account_consent');

        if (addAccountForm && addAccountConsentModal) {
            addAccountForm.addEventListener('submit', function (event) {
                if (!addAccountForm.checkValidity()) {
                    return;
                }

                event.preventDefault();
                bootstrap.Modal.getInstance(document.getElementById('addAccountModal')).hide();
                bootstrap.Modal.getOrCreateInstance(addAccountConsentModal).show();
            });

            confirmAddAccountConsent.addEventListener('change', function () {
                acceptAddAccountConsent.disabled = !this.checked;
            });

            acceptAddAccountConsent.addEventListener('click', function () {
                if (!confirmAddAccountConsent.checked) {
                    return;
                }

                addAccountConsentInput.value = '1';
                bootstrap.Modal.getInstance(addAccountConsentModal).hide();
                addAccountForm.submit();
            });
        }

        @if($errors->has('account_no') || $errors->has('name') || $errors->has('soa_file') || $errors->has('id_file') || $errors->has('data_privacy_consent'))
            const addAccountModal = document.getElementById('addAccountModal');
            if (addAccountModal) {
                bootstrap.Modal.getOrCreateInstance(addAccountModal).show();
            }
        @endif

        const replaceDocumentModal = document.getElementById('replaceDocumentModal');

        if (replaceDocumentModal) {
            replaceDocumentModal.addEventListener('show.bs.modal', function (event) {

                const button = event.relatedTarget;

                const documentType = button.getAttribute('data-document');
                const documentLabel = button.getAttribute('data-label');

                document.getElementById('documentType').value = documentType;
                document.getElementById('documentLabel').textContent = documentLabel;
            });
        }

        $(function() {
            $('#show-statement').on('click', function() {
            const statementContent = $('#statement-content');
            if (statementContent.is(':visible')) {
                statementContent.slideUp('slow');
                $(this).text('View Statement');
            } else {
                statementContent.slideDown('slow');
                $(this).text('Hide Statement');
            }
            });

            $('#applicationNotificationToggle').on('click', function () {
                $('#applicationNotificationPanel').toggleClass('d-none');
            });

            $(document).on('click', function (event) {
                if (!$(event.target).closest('.application-notification').length) {
                    $('#applicationNotificationPanel').addClass('d-none');
                }
            });

            const accountNotifications = @json($accountNotifications ?? []);
            const applicationNotification = accountNotifications.find(function (notification) {
                return notification.type === 'application';
            }) || @json($applicationNotification ?? null);

            if (
                applicationNotification
                && ['success', 'danger'].includes(applicationNotification.status)
                && typeof Swal !== 'undefined'
            ) {
                const notificationKey = [
                    'srwd-application-decision',
                    '{{ $my->id ?? 'user' }}',
                    applicationNotification.status,
                    applicationNotification.date || ''
                ].join(':');

                if (localStorage.getItem(notificationKey) !== 'shown') {
                    Swal.fire({
                        icon: applicationNotification.status === 'success' ? 'success' : 'error',
                        title: applicationNotification.title || 'Application update',
                        text: applicationNotification.message || '',
                        confirmButtonText: 'OK',
                        confirmButtonColor: applicationNotification.status === 'success' ? '#198754' : '#dc3545',
                    });

                    localStorage.setItem(notificationKey, 'shown');
                }
            }
        });

        document.addEventListener('DOMContentLoaded', function () {

            const viewModal = document.getElementById('viewDocumentModal');
            const viewer = document.getElementById('documentViewer');
            const modalTitle = document.getElementById('viewDocumentModalLabel');

            viewModal.addEventListener('show.bs.modal', function (event) {

                const button = event.relatedTarget;

                const url = button.getAttribute('data-document-url');
                const name = button.getAttribute('data-document-name');

                modalTitle.textContent = 'View ' + name;

                viewer.innerHTML = `
                    <div class="d-flex justify-content-center align-items-center h-100">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>
                `;

                const extension = url
                    .split('?')[0]
                    .split('.')
                    .pop()
                    .toLowerCase();

                if (extension === 'pdf') {

                    viewer.innerHTML = `
                        <iframe
                            src="${url}"
                            width="100%"
                            height="100%"
                            style="border: none;"
                            title="${name}">
                        </iframe>
                    `;

                } else if (
                    extension === 'jpg' ||
                    extension === 'jpeg' ||
                    extension === 'png' ||
                    extension === 'gif' ||
                    extension === 'webp'
                ) {

                    viewer.innerHTML = `
                        <div class="d-flex justify-content-center align-items-center h-100 p-3">
                            <img
                                src="${url}"
                                alt="${name}"
                                style="
                                    max-width: 100%;
                                    max-height: 100%;
                                    object-fit: contain;
                                    border-radius: 6px;
                                    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
                                ">
                        </div>
                    `;

                } else {

                    viewer.innerHTML = `
                        <div class="d-flex flex-column justify-content-center align-items-center h-100">
                            <i class="bx bx-file-blank text-muted"
                            style="font-size: 60px;">
                            </i>

                            <p class="mt-2 text-muted">
                                This file type cannot be previewed.
                            </p>

                            <a href="${url}"
                            target="_blank"
                            class="btn btn-primary">
                                <i class="bx bx-download"></i>
                                Open File
                            </a>
                        </div>
                    `;
                }
            });

            viewModal.addEventListener('hidden.bs.modal', function () {
                viewer.innerHTML = '';
            });

        });
    </script>
@endsection
