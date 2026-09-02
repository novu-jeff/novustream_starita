@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Registrants</h1>
                <a href="{{ route('concessionaires.index') }}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Concessionaires
                </a>
            </div>
            <div class="inner-content mt-5 pb-5">
                <div class="ledger-tabs">
                    <ul class="nav nav-tabs mb-4" id="registrantTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link {{ request('tab') === 'linked' ? '' : 'active' }} fw-bold"
                                id="registrants-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#registrants-pane"
                                type="button"
                                role="tab"
                                aria-controls="registrants-pane"
                                aria-selected="{{ request('tab') === 'linked' ? 'false' : 'true' }}">
                                Registrants
                            </button>
                        </li>

                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link {{ request('tab') === 'linked' ? 'active' : '' }} fw-bold"
                                id="linked-accounts-tab"
                                data-bs-toggle="tab"
                                data-bs-target="#linked-accounts-pane"
                                type="button"
                                role="tab"
                                aria-controls="linked-accounts-pane"
                                aria-selected="{{ request('tab') === 'linked' ? 'true' : 'false' }}">
                                Linked Accounts
                                @if($accountLinkRequests->isNotEmpty())
                                    <span class="badge bg-warning text-dark ms-1">
                                        {{ $pendingAccountLinkCount }}
                                    </span>
                                @endif
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="registrantTabsContent">
                        <div
                            class="tab-pane fade {{ request('tab') === 'linked' ? '' : 'show active' }}"
                            id="registrants-pane"
                            role="tabpanel"
                            aria-labelledby="registrants-tab"
                            tabindex="0">
                            <div class="row mb-4">
                                <div class="col-12 col-md-2 mb-3">
                                    <label class="mb-1">Show Entries</label>
                                    <select
                                        name="entries"
                                        id="entries"
                                        class="form-select text-uppercase dropdown-toggle">
                                        @foreach([10, 25, 50, 100, 200, 250, 350, 400, 450, 500] as $entry)
                                            <option
                                                value="{{ $entry }}"
                                                {{ $entries == $entry ? 'selected' : '' }}>
                                                {{ $entry }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-12 col-md-3 mb-3">
                                    <label class="mb-1">Status</label>
                                    <select
                                        name="status"
                                        id="status"
                                        class="form-select text-uppercase dropdown-toggle">
                                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>
                                            All
                                        </option>
                                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>
                                            Pending
                                        </option>
                                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>
                                            Approved
                                        </option>
                                        <option value="denied" {{ $status === 'denied' ? 'selected' : '' }}>
                                            Denied
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-3 mb-3">
                                    <label class="mb-1">Registrant Type</label>
                                    <select
                                        name="type"
                                        id="type"
                                        class="form-select text-uppercase dropdown-toggle">
                                        <option value="all" {{ $type === 'all' ? 'selected' : '' }}>
                                            All
                                        </option>
                                        <option
                                            value="existing_account"
                                            {{ $type === 'existing_account' ? 'selected' : '' }}>
                                            Existing Accounts
                                        </option>
                                        <option
                                            value="new_connection"
                                            {{ $type === 'new_connection' ? 'selected' : '' }}>
                                            New Connections
                                        </option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <label class="mb-1">Search</label>
                                    <div class="position-relative">
                                        <input
                                            type="text"
                                            name="search"
                                            id="search"
                                            class="form-control pe-5"
                                            value="{{ $search }}">
                                        @if(!empty($search))
                                            <button
                                                type="button"
                                                id="clear-search"
                                                class="btn position-absolute top-50 end-0 translate-middle-y me-2 p-0 text-muted"
                                                style="border: none; background: none; font-size: 1.2rem;"
                                                aria-label="Clear search">
                                                &times;
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="card shadow-sm">
                                <div class="table-responsive">
                                <table class="table table-bordered table-striped align-middle w-100 mb-0">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Contact No.</th>
                                            <th>Type</th>
                                            <th>Account No.</th>
                                            <th>Address</th>
                                            <th>Status</th>
                                            <th>Documents</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($data as $account)
                                            @php
                                                $applicationType = $account->application_type ?: 'existing_account';
                                                $applicationStatus = $account->application_status
                                                    ?: ($account->isApproved
                                                        ? 'approved'
                                                        : ($account->denied_at ? 'denied' : 'pending'));
                                                $registrant = $account->user;
                                                $serviceApplication = $registrant?->serviceApplications?->first();
                                                $connectionType = $serviceApplication?->connection_type ?? 'on_line';
                                                $hasBoringPermit = !empty(
                                                    $serviceApplication?->documents?->boring_permit
                                                );
                                                $needsCompletion =
                                                    $applicationType === 'new_connection'
                                                    && str_starts_with(
                                                        (string) $account->account_no,
                                                        'NEW-'
                                                    );
                                            @endphp
                                            <tr>
                                                <td>
                                                    {{ optional($account->created_at)->format('M d, Y') }}
                                                </td>
                                                <td>
                                                    {{ $registrant->registrants ?? $registrant->name ?? 'N/A' }}
                                                </td>
                                                <td>
                                                    {{ $registrant->email ?? 'N/A' }}
                                                </td>
                                                <td>
                                                    {{ $registrant->contact_no ?? 'N/A' }}
                                                </td>
                                                <td>
                                                    @if($applicationType === 'new_connection')
                                                        <span class="d-inline-flex align-items-center">
                                                            <span
                                                                class="rounded-circle bg-success me-2"
                                                                style="width: 8px; height: 8px;">
                                                            </span>
                                                            <span>New</span>
                                                        </span>
                                                        <div class="small text-muted">
                                                            {{ $connectionType === 'traverse' ? 'Traverse' : 'On-line' }}
                                                        </div>
                                                    @else
                                                        <span class="d-inline-flex align-items-center">
                                                            <span
                                                                class="rounded-circle bg-info me-2"
                                                                style="width: 8px; height: 8px;">
                                                            </span>
                                                            <span>Existing</span>
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    {{ $applicationType === 'new_connection'
                                                        ? 'N/A'
                                                        : $account->account_no }}
                                                </td>
                                                <td>
                                                    {{ $account->address }}
                                                </td>
                                                <td>
                                                    @if($applicationStatus === 'approved')
                                                        <span class="d-inline-flex align-items-center">
                                                            <span
                                                                class="rounded-circle bg-success me-2"
                                                                style="width: 8px; height: 8px;">
                                                            </span>
                                                            <span>Approved</span>
                                                        </span>

                                                        @if($account->approved_at)
                                                            <div class="small text-muted">
                                                                {{ $account->approved_at->format('M d, Y') }}
                                                            </div>
                                                        @endif

                                                    @elseif($applicationStatus === 'denied')
                                                        <span class="d-inline-flex align-items-center">
                                                            <span
                                                                class="rounded-circle bg-danger me-2"
                                                                style="width: 8px; height: 8px;">
                                                            </span>
                                                            <span>Denied</span>
                                                        </span>

                                                        @if($account->approval_denial_reason)
                                                            <div class="small text-muted">
                                                                {{ $account->approval_denial_reason }}
                                                            </div>
                                                        @endif
                                                    @else
                                                        <span class="d-inline-flex align-items-center">
                                                            <span
                                                                class="rounded-circle bg-warning me-2"
                                                                style="width: 8px; height: 8px;">
                                                            </span>
                                                            <span>Pending</span>
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        @if(
                                                            $applicationType === 'new_connection'
                                                            && str_starts_with(
                                                                (string) $account->account_no,
                                                                'NEW-'
                                                            )
                                                        )
                                                            <a
                                                                href="{{ route('registrants.form', $account->id) }}"
                                                                target="_blank"
                                                                class="btn btn-outline-primary btn-sm">
                                                                Form
                                                            </a>
                                                        @endif
                                                        @if($serviceApplication)
                                                            <a
                                                                href="{{ route('admin.application.contract', $serviceApplication) }}"
                                                                target="_blank"
                                                                class="btn btn-outline-primary btn-sm">
                                                                Contract
                                                            </a>
                                                        @endif
                                                        @if($serviceApplication?->documents?->boring_permit)
                                                            <a
                                                                href="{{ asset('storage/' . $serviceApplication->documents->boring_permit) }}"
                                                                target="_blank"
                                                                class="btn btn-outline-primary btn-sm">
                                                                Permit
                                                            </a>
                                                        @elseif($connectionType === 'traverse')
                                                            <span class="d-inline-flex align-items-center">
                                                                <span
                                                                    class="rounded-circle bg-warning me-2"
                                                                    style="width: 8px; height: 8px;">
                                                                </span>
                                                                <span>Permit</span>
                                                            </span>
                                                        @endif

                                                        @if($account->application_soa_path)
                                                            <a
                                                                href="{{ asset('storage/' . $account->application_soa_path) }}"
                                                                target="_blank"
                                                                class="btn btn-outline-primary btn-sm">
                                                                SOA
                                                            </a>
                                                        @endif

                                                        @if($account->application_id_path)
                                                            <a
                                                                href="{{ asset('storage/' . $account->application_id_path) }}"
                                                                target="_blank"
                                                                class="btn btn-outline-primary btn-sm">
                                                                Valid ID
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    @if($applicationStatus === 'pending')
                                                        <div class="d-flex align-items-center gap-2">
                                                            @if(
                                                                $applicationType === 'new_connection'
                                                                && $needsCompletion
                                                            )
                                                                <a
                                                                    href="{{ route('registrants.complete', $account->id) }}"
                                                                    class="btn btn-primary text-uppercase fw-bold">
                                                                    Complete
                                                                </a>
                                                            @elseif(
                                                                $applicationType === 'new_connection'
                                                                && $connectionType === 'traverse'
                                                                && !$hasBoringPermit
                                                            )
                                                                <span class="d-inline-flex align-items-center">
                                                                    <span
                                                                        class="rounded-circle bg-warning me-2"
                                                                        style="width: 8px; height: 8px;">
                                                                    </span>
                                                                    <span>Permit</span>
                                                                </span>
                                                            @elseif(
                                                                $applicationType === 'new_connection'
                                                                && $serviceApplication
                                                                && $serviceApplication->application_fee_status !== 'paid'
                                                            )
                                                                <a
                                                                    href="{{ route('payments.application-fees.pay', $serviceApplication->id) }}"
                                                                    class="btn btn-warning text-uppercase fw-bold d-flex flex-row align-items-center text-nowrap">

                                                                    <i class="bx bx-error me-1"></i>
                                                                    Pay Fee
                                                                </a>
                                                            @else
                                                                <form
                                                                    method="POST"
                                                                    action="{{ route('registrants.approve', $account->id) }}"
                                                                    class="registrant-action-form"
                                                                    data-action="approve">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <button
                                                                        type="submit"
                                                                        class="btn btn-success text-uppercase fw-bold">
                                                                        Approve
                                                                    </button>
                                                                </form>
                                                            @endif
                                                            <form
                                                                method="POST"
                                                                action="{{ route('registrants.deny', $account->id) }}"
                                                                class="registrant-action-form"
                                                                data-action="deny">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input
                                                                    type="hidden"
                                                                    name="approval_denial_reason"
                                                                    class="denial-reason-input">
                                                                <button
                                                                    type="submit"
                                                                    class="btn btn-danger text-uppercase fw-bold">
                                                                    Deny
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @else
                                                        <span class="text-muted">
                                                            No action
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="10" class="text-center">
                                                    No registrants found.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                                </div>
                            </div>
                            <div class="w-100 mt-4">
                                {{ $data->appends(request()->except(['page', 'links_page']))->links() }}
                            </div>
                        </div>
                        <div
                            class="tab-pane fade {{ request('tab') === 'linked' ? 'show active' : '' }}"
                            id="linked-accounts-pane"
                            role="tabpanel"
                            aria-labelledby="linked-accounts-tab"
                            tabindex="0">
                            <form method="GET" class="row g-3 mb-4">
                                <input type="hidden" name="tab" value="linked">
                                <input type="hidden" name="entries" value="{{ $entries }}">
                                <input type="hidden" name="status" value="{{ $status }}">
                                <input type="hidden" name="type" value="{{ $type }}">
                                <input type="hidden" name="search" value="{{ $search }}">
                                <div class="col-12 col-md-6">
                                    <label for="link_search" class="form-label">Search Linked Accounts</label>
                                    <input type="search"
                                        class="form-control"
                                        id="link_search"
                                        name="link_search"
                                        value="{{ $linkSearch }}"
                                        placeholder="Account No., Existing Account Name, or Requested By">
                                </div>
                                <div class="col-12 col-md-2 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary text-uppercase fw-bold w-100">
                                        <i class="bx bx-search"></i> Search
                                    </button>
                                </div>
                                @if($linkSearch !== '')
                                    <div class="col-12 col-md-2 d-flex align-items-end">
                                        <a href="{{ route('registrants.index', ['tab' => 'linked', 'entries' => $entries, 'status' => $status, 'type' => $type, 'search' => $search]) }}"
                                        class="btn btn-outline-secondary text-uppercase fw-bold w-100">Clear</a>
                                    </div>
                                @endif
                            </form>
                            <div class="card shadow-sm">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Account No.</th>
                                                <th>Existing Account Name</th>
                                                <th>Requested By</th>
                                                <th>Documents</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($accountLinkRequests as $link)
                                                <tr>
                                                    <td>
                                                        {{ optional($link->created_at)->format('M d, Y') }}
                                                    </td>
                                                    <td class="fw-bold">
                                                        {{ $link->account?->account_no ?? 'N/A' }}
                                                    </td>
                                                    <td>
                                                        {{ $link->account?->user?->name ?? 'N/A' }}
                                                    </td>
                                                    <td>
                                                        {{ $link->requested_name }}
                                                        <div class="small text-muted">
                                                            {{ $link->user?->email ?? 'N/A' }}
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex gap-2">
                                                            <a
                                                                href="{{ asset('storage/' . $link->soa_path) }}"
                                                                target="_blank"
                                                                class="btn btn-outline-primary btn-sm">
                                                                SOA
                                                            </a>
                                                            <a
                                                                href="{{ asset('storage/' . $link->id_path) }}"
                                                                target="_blank"
                                                                class="btn btn-outline-primary btn-sm">
                                                                Valid ID
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <form
                                                                method="POST"
                                                                action="{{ route('account-links.approve', $link->id) }}"
                                                                class="registrant-action-form"
                                                                data-action="approve">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="tab" value="linked">
                                                                <input type="hidden" name="link_search" value="{{ $linkSearch }}">
                                                                <button
                                                                    type="submit"
                                                                    class="btn btn-success text-uppercase fw-bold">
                                                                    Approve
                                                                </button>
                                                            </form>
                                                            <form
                                                                method="POST"
                                                                action="{{ route('account-links.deny', $link->id) }}"
                                                                class="registrant-action-form"
                                                                data-action="deny">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="tab" value="linked">
                                                                <input type="hidden" name="link_search" value="{{ $linkSearch }}">
                                                                <input
                                                                    type="hidden"
                                                                    name="denial_reason"
                                                                    class="denial-reason-input">
                                                                <button
                                                                    type="submit"
                                                                    class="btn btn-danger text-uppercase fw-bold">
                                                                    Deny
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        No linked account requests found.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="w-100 mt-4">
                                {{ $accountLinkRequests->appends(request()->except(['page', 'links_page']))->links() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('script')
<script>
    $(function () {
        function updateUrl() {
            const params = new URLSearchParams(window.location.search);

            ['search', 'entries', 'status', 'type'].forEach(id => {
                const val = $('#' + id).val();
                val ? params.set(id, val) : params.delete(id);
            });

            window.location.href = window.location.pathname + '?' + params.toString();
        }

        let searchTimer = null;

        $('#search').on('keyup', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(updateUrl, 400);
        });

        $('#entries, #status, #type').on('change', updateUrl);

        $('#clear-search').on('click', function () {
            $('#search').val('');
            updateUrl();
        });

        $('.registrant-action-form').on('submit', function (event) {
            event.preventDefault();

            const form = this;
            const action = form.dataset.action;
            const isApprove = action === 'approve';

	            const options = {
	                title: isApprove ? 'Approve application?' : 'Deny application?',
	                text: isApprove
	                    ? 'This registrant will be allowed to use concessionaire online services.'
	                    : 'Please enter the reason why this registration is denied.',
	                icon: isApprove ? 'question' : 'warning',
	                showCancelButton: true,
	                confirmButtonText: isApprove ? 'Approve' : 'Deny',
	                cancelButtonText: 'Cancel',
	                confirmButtonColor: isApprove ? '#198754' : '#c92a07',
	            };

	            if (!isApprove) {
	                options.input = 'textarea';
	                options.inputPlaceholder = 'Reason for denial';
	                options.inputAttributes = {
	                    'aria-label': 'Reason for denial',
	                };
	                options.inputValidator = function (value) {
	                    if (!value || !value.trim()) {
	                        return 'Please provide a denial reason.';
	                    }
	                };
	            }

	            Swal.fire(options).then(function (result) {
	                if (result.isConfirmed) {
	                    if (!isApprove) {
	                        $(form).find('.denial-reason-input').val(result.value.trim());
	                    }

	                    form.submit();
	                }
	            });

                $('.fee-unpaid-btn').on('click', function () {
                    const amount = $(this).data('fee-amount');

                    Swal.fire({
                        icon: 'warning',
                        title: 'Application Fee Unpaid',
                        text: `This registrant's application fee of PHP ${amount} must be paid before the application can be approved. Please process the payment in the Application Fees tab first.`,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#c92a07',
                    });
                });
        });

        const registrantAction = @json(session('registrant_action'));

        if (registrantAction && typeof Swal !== 'undefined') {
            Swal.fire({
                icon: registrantAction.icon || 'success',
                title: registrantAction.title || 'Action successful',
                text: registrantAction.message || 'The action was completed successfully.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#198754',
            });
        }
    });
</script>
@endsection
