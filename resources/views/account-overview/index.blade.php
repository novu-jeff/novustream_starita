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

                                                        {{-- Valid ID --}}
                                                        <div class="document-row">
                                                            <span>
                                                                Valid ID:
                                                                {{ $serviceApplication->documents?->valid_id ? 'Uploaded' : 'Missing' }}
                                                            </span>

                                                            @if($serviceApplication->documents?->valid_id)
                                                                <a  type="button"
                                                                    class="document-btn document-btn-view"
                                                                    title="View Valid ID"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#viewDocumentModal"
                                                                    data-document-url="{{ \Illuminate\Support\Facades\Storage::url($serviceApplication->documents->valid_id) }}"
                                                                    data-document-name="Valid ID">
                                                                    <i class="bx bx-show"></i>
                                                                </a>
                                                            @endif

                                                            <button type="button"
                                                                    class="document-btn document-btn-edit"
                                                                    title="Replace Valid ID"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#replaceDocumentModal"
                                                                    data-document="valid_id"
                                                                    data-label="Valid ID">
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

                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
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
                                    <div class="bg-primary mt-1 p-3 text-uppercase fw-bold text-white fs-5">Statement of Account as of <span class="text-decoration-underline text-offset-2">{{Carbon\Carbon::now()->format('F d, Y')}}</span></div>
                                    <div class="note mt-3 ms-0 fst-italic text-uppercase fw-medium" style="font-size: 12px;"><strong>Disclaimer:</strong> Successful payments will be reflected on the next statement and can be viewed via the <strong>Payment History</strong></div>
                                    <hr class="my-4">
                                    <div class="bg-danger d-flex align-items-center justify-content-between mt-1 p-3 text-uppercase fw-bold text-white">Total Amount Due:
                                        <h3 class="ms-2">
                                            @if($statement['total'] != 0)
                                                PHP {{ number_format($statement['total'] ?? 0, 2) }}
                                            @else
                                                PHP 0.00
                                            @endif
                                        </h3>
                                    </div>
                                    <h3 class="ms-2">
                                            @php
                                            $penalty = $statement['current_bill']['computed_penalty'] ?? 0;
                                            $dueDate = isset($data['current_bill']['due_date'])
                                                            ? \Carbon\Carbon::parse($data['current_bill']['due_date'])
                                                            : null;

                                            $today = \Carbon\Carbon::today();

                                            $applicablePenalty = ($dueDate && $today->gt($dueDate)) ? $penalty : 0;
                                            @endphp
                                        </h3>
                                    </div>

                                    @if(!empty($statement['current_bill_qr']))
                                        <div class="d-flex justify-content-center">
                                            <a href="{{ $statement['current_bill_qr'] }}" class="btn btn-success w-50 mt-3" target="_blank">Pay Online</a>
                                        </div>
                                    @endif
                                    <div class="mt-4 pt-2" style="font-size: 14px;">
                                        <div style="display:none;" id="statement-content">
                                            @forelse($statement['transactions'] as $key => $transactions)
                                                <a target="_blank" href="{{route('account-overview.bills.reference_no', ['reference_no' => $transactions['reference_no']])}}">
                                                    <div class="d-flex justify-content-between pb-3 {{$key == 0 ? 'pt-3' : ''}} mb-3" style="{{$key == 0 ? 'border-top: 3px dotted rgba(0, 0, 0, 0.521);' : ''}} border-bottom: 3px dotted rgba(0, 0, 0, 0.521); cursor: pointer;">
                                                        <div>
                                                            <div>
                                                                {{$transactions['reference_no']}} | {{$transactions['account_no']}}
                                                            </div>
                                                            <div class="text-uppercase">
                                                                {{\Carbon\Carbon::parse($transactions['bill_period_from'])->format('M d, Y')}} - {{\Carbon\Carbon::parse($transactions['bill_period_to'])->format('M d, Y')}}
                                                            </div>
                                                        </div>
                                                        <div class="text-end">
                                                            <div class="fw-bold">
                                                                PHP {{number_format($transactions['amount'], 2)}}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    </a>
                                            @empty
                                                <div class="alert alert-danger text-uppercase text-center text-muted fw-bold" style="font-size: 12px">No Statement Found</div>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <a href="javascript:void(0)" id="show-statement" class="text-uppercase fw-medium" style="font-size: 13px">View Statement</a>
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
            bottom: 24px;
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
            bottom: 64px;
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
        document.getElementById('replaceDocumentModal')
            .addEventListener('show.bs.modal', function (event) {

                const button = event.relatedTarget;

                const documentType = button.getAttribute('data-document');
                const documentLabel = button.getAttribute('data-label');

                document.getElementById('documentType').value = documentType;
                document.getElementById('documentLabel').textContent = documentLabel;
            });

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
