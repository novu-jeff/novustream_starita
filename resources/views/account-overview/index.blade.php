@extends('layouts.app')

@section('content')

    <main class="main">
        <div class="responsive-wrapper">
            @php
                $visibleApprovalNotice = session('approval_notice') ?? ($approvalNotice ?? null);
                $primaryAccount = $accounts->first();
                $primaryStatement = $accountStatements[0] ?? null;
                $portalAccountNo = $primaryAccount->account_no ?? '';
                $portalName = $my->name ?? 'Account Holder';
                $portalInitials = collect(preg_split('/\s+/', trim($portalName)))
                    ->filter()
                    ->take(2)
                    ->map(fn ($part) => mb_substr($part, 0, 1))
                    ->implode('');
                $portalLatestConsumption = collect($chartMonthlyConsumption ?? [])->last() ?? 0;
                $portalCurrentBill = $statement['current_bill'] ?? null;
                $portalCurrentReference = $statement['current_bill']['reference_no']
                    ?? data_get($primaryStatement, 'transactions.0.reference_no');
                $portalDueDate = $portalCurrentBill['due_date'] ?? null;
                $isDue = $portalDueDate
                    ? \Carbon\Carbon::today()->greaterThanOrEqualTo(
                        \Carbon\Carbon::parse($portalDueDate)->startOfDay()
                    )
                    : false;

                $portalDiscount = (float) ($portalCurrentBill['discount'] ?? 0);
                $portalAdvances = (float) ($portalCurrentBill['advances'] ?? 0);
                $portalPenalty = (float) ($portalCurrentBill['penalty'] ?? 0);

                $portalBasicCharge = (float) ($portalCurrentBill['total'] ?? 0);

                $portalAmountToDisplay = $portalBasicCharge
                    - $portalDiscount
                    - $portalAdvances;

                if ($isDue) {
                    $portalAmountToDisplay += $portalPenalty;
                }
                $portalTotalAmount = max(0, $portalAmountToDisplay);
                $portalApprovedAccountCount = $accounts->filter(fn ($account) => $account->isApproved || ($account->access_link_status ?? null) === 'approved')->count();
            @endphp
            @include('account-overview.partials.portal-navigation', ['portalAccount' => $primaryAccount])

            @if(!empty($visibleApprovalNotice))
                <div class="alert alert-{{ $visibleApprovalNotice['status'] ?? 'warning' }} text-uppercase fw-medium text-center mb-3 mt-3">
                    {{ $visibleApprovalNotice['message'] ?? 'Your application is currently in the approval stage.' }}
                </div>
            @endif
            @if(!empty($visibleApprovalNotice) && $accounts->contains('application_type', 'new_connection'))
                <div class="d-flex justify-content-center w-100">
                    <div class="alert alert-{{ $visibleApprovalNotice['status'] ?? 'warning' }} text-uppercase fw-medium text-center mt-3 mb-4">
                        Please provide the original documents in hard copy and submit them to the Sta. Rita Branch.
                    </div>
                </div>
            @endif
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

            @include('account-overview.partials.portal-welcome', [
                'portalName' => $portalName,
                'portalInitials' => $portalInitials,
                'portalAccountNo' => $portalAccountNo,
                'portalAddress' => $primaryAccount->address ?? null,
                'portalEmail' => $my->email ?? null,
                'portalCurrentBill' => $portalCurrentBill,
                'portalTotalAmount' => $portalTotalAmount,
                'portalPaymentUrl' => $statement['current_bill_payment_url'] ?? null,
                'portalStatementUrl' => $portalCurrentReference
                    ? route('account-overview.bills.reference_no', [
                        'reference_no' => $portalCurrentReference
                    ])
                    : null,
                'portalStatementModal' => true,
            ])

            @include('account-overview.partials.portal-kpis', [
                'portalTotalAmount' => $portalTotalAmount,
                'portalDueDate' => $portalDueDate,
                'portalLatestConsumption' => $portalLatestConsumption,
                'portalAccountCount' => $accounts->count(),
                'portalApprovedAccountCount' => $portalApprovedAccountCount,
                'portalAccountNote' => $portalApprovedAccountCount . ' approved',
            ])

            @if($portalCurrentBill)
                @php
                    $modalBillBreakdown = collect($portalCurrentBill['breakdown'] ?? []);
                    $modalBillDiscounts = is_array($portalCurrentBill['discount'] ?? null)
                        ? collect($portalCurrentBill['discount'])
                        : collect();
                    $modalBillingCharge = $modalBillBreakdown
                        ->reject(fn ($item) => ($item['name'] ?? '') === 'Previous Balance')
                        ->sum('amount');
                @endphp
                <div class="modal fade" id="latestStatementModal" tabindex="-1" aria-labelledby="latestStatementModalTitle" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
                        <div class="modal-content portal-statement-modal">
                            <div class="modal-header">
                                <div>
                                    <span class="portal-modal-eyebrow">NOVUSTREAM E-STATEMENT</span>
                                    <h2 class="modal-title" id="latestStatementModalTitle">Statement of Account</h2>
                                    <span class="portal-modal-reference">{{ $portalCurrentBill['reference_no'] ?? '' }}</span>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="portal-statement-identity">
                                    <div><small>Account Holder</small><strong>{{ $portalName }}</strong></div>
                                    <div><small>Account Number</small><strong>{{ $portalCurrentBill['account_no'] ?? $portalAccountNo }}</strong></div>
                                    <div><small>Service Address</small><strong>{{ $primaryAccount->address ?? 'N/A' }}</strong></div>
                                </div>
                                <div class="portal-statement-period">
                                    <div><small>Billing Period</small><strong>{{ !empty($portalCurrentBill['bill_period_from']) ? \Carbon\Carbon::parse($portalCurrentBill['bill_period_from'])->format('M d, Y') : 'N/A' }} - {{ !empty($portalCurrentBill['bill_period_to']) ? \Carbon\Carbon::parse($portalCurrentBill['bill_period_to'])->format('M d, Y') : 'N/A' }}</strong></div>
                                    <div><small>Due Date</small><strong>{{ !empty($portalCurrentBill['due_date']) ? \Carbon\Carbon::parse($portalCurrentBill['due_date'])->format('M d, Y') : 'N/A' }}</strong></div>
                                    <div><small>Consumption</small><strong>{{ number_format((float) data_get($portalCurrentBill, 'reading.consumption', 0), 2) }} m³</strong></div>
                                </div>
                                <div class="portal-statement-lines">
                                    @if($modalBillingCharge > 0)
                                        <div><span>Current Billing</span></div>
                                    @endif
                                    @if((float) ($portalCurrentBill['previous_unpaid'] ?? 0) > 0)
                                        <div><span>Previous Balance</span><strong>PHP {{ number_format((float) $portalCurrentBill['previous_unpaid'], 2) }}</strong></div>
                                    @endif
                                    @foreach($modalBillBreakdown as $line)
                                        @if(($line['name'] ?? '') !== 'Previous Balance' && strtolower((string) ($line['name'] ?? '')) !== 'system fee')
                                            <div><span>{{ $line['name'] ?? 'Charge' }}</span><strong>PHP {{ number_format((float) ($line['amount'] ?? 0), 2) }}</strong></div>
                                        @endif
                                    @endforeach
                                    @foreach($modalBillDiscounts as $discount)
                                        <div class="is-discount"><span>{{ $discount['name'] ?? 'Discount' }}</span><strong>- PHP {{ number_format((float) ($discount['amount'] ?? 0), 2) }}</strong></div>
                                    @endforeach
                                    @if((float) ($portalCurrentBill['advances'] ?? 0) > 0)
                                        <div class="is-discount"><span>Advances</span><strong>- PHP {{ number_format((float) $portalCurrentBill['advances'], 2) }}</strong></div>
                                    @endif
                                     @if((float) ($portalCurrentBill['discount'] ?? 0) > 0)
                                        <div class="is-discount"><span>Discount</span><strong>- PHP {{ number_format((float) $portalCurrentBill['discount'], 2) }}</strong></div>
                                    @endif
                                    @if($isDue && (float) ($portalCurrentBill['penalty'] ?? 0) > 0)
                                        <div>
                                            <span>Penalty</span>
                                            <strong>PHP {{ number_format((float) $portalCurrentBill['penalty'], 2) }}</strong>
                                        </div>
                                    @endif
                                </div>
                                @php
                                    $basicCharge = (float) ($statement['total'] ?? 0);
                                    $discount = (float) ($portalDiscount ?? 0);
                                    $advances = (float) ($portalAdvances ?? 0);
                                    $penalty = (float) ($portalCurrentBill['penalty'] ?? 0);

                                    $amountToDisplay = $basicCharge - $discount - $advances - $penalty;

                                    if ($isDue) {
                                        $amountToDisplay += $penalty;
                                    }
                                @endphp

                                <div class="portal-statement-total">
                                    <span>{{ $isDue ? 'Amount Due' : 'Total Amount Due' }}</span>
                                    <strong>PHP {{ number_format($amountToDisplay, 2) }}</strong>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <a href="{{ route('account-overview.bills.reference_no', ['reference_no' => $portalCurrentBill['reference_no']]) }}" target="_blank" rel="noopener" class="portal-button portal-button-outline"><i class="bx bx-file" aria-hidden="true"></i>Full Statement</a>
                                @if(!empty($statement['current_bill_payment_url']) && empty($portalCurrentBill['isPaid']))
                                    <a href="{{ $statement['current_bill_payment_url'] }}" target="_blank" rel="noopener" class="portal-button portal-button-pay"><i class="bx bx-credit-card" aria-hidden="true"></i>Pay Online</a>
                                @endif
                                <button type="button" class="portal-button portal-button-muted" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
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
            <div class="inner-content mt-5 pb-5">
                <section class="portal-view is-active" id="portal-view-overview" data-portal-view="overview">
                    @if(!empty($serviceApplication) && empty($hideServiceApplicationContainer))
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
                                        @else
                                            <button type="button"
                                                    class="btn btn-primary fw-bold text-uppercase"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#serviceApplicationModal">

                                                <i class="bx bx-water"></i>
                                                New Water Service Connection

                                            </button>
                                        @endif
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#viewDocumentModal" data-document-url="{{ route('application.show', $serviceApplication) }}" data-document-name="Application Form" class="btn btn-primary fw-bold text-uppercase">
                                            View Application
                                        </button>
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#viewDocumentModal" data-document-url="{{ route('application.contract', $serviceApplication) }}" data-document-name="Service Contract" class="btn btn-outline-primary fw-bold text-uppercase">
                                            View Contract
                                        </button>
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#viewDocumentModal" data-document-url="{{ route('application.create') }}" data-document-name="Review Application Form" class="btn btn-outline-primary fw-bold text-uppercase">
                                            Review Form
                                        </button>
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
                                                                <button type="button"
                                                                    class="document-btn document-btn-view"
                                                                    title="View {{ $identityDocumentLabel }}"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#viewDocumentModal"
                                                                    data-document-url="{{ route('account-overview.application-document', [$serviceApplication, 'valid_id']) }}"
                                                                    data-document-name="{{ $identityDocumentLabel }}">
                                                                    <i class="bx bx-show"></i>
                                                                </button>
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
                                                                <button type="button"
                                                                    class="document-btn document-btn-view"
                                                                    title="View Cedula"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#viewDocumentModal"
                                                                    data-document-url="{{ route('account-overview.application-document', [$serviceApplication, 'cedula']) }}"
                                                                    data-document-name="Cedula">
                                                                    <i class="bx bx-show"></i>
                                                                </button>
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
                                                                <button type="button"
                                                                    class="document-btn document-btn-view"
                                                                    title="View Proof of Billing"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#viewDocumentModal"
                                                                    data-document-url="{{ route('account-overview.application-document', [$serviceApplication, 'proof_of_billing']) }}"
                                                                    data-document-name="Proof of Billing">
                                                                    <i class="bx bx-show"></i>
                                                                </button>
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
                                                                <button type="button"
                                                                    class="document-btn document-btn-view"
                                                                    title="View Authorization"
                                                                    data-bs-toggle="modal"
                                                                    data-bs-target="#viewDocumentModal"
                                                                    data-document-url="{{ route('account-overview.application-document', [$serviceApplication, 'authorization_letter']) }}"
                                                                    data-document-name="Authorization Letter">
                                                                    <i class="bx bx-show"></i>
                                                                </button>
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
                                                                    <button type="button"
                                                                        class="document-btn document-btn-view"
                                                                        title="View Boring/Cutting Permit"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#viewDocumentModal"
                                                                        data-document-url="{{ route('account-overview.application-document', [$serviceApplication, 'boring_permit']) }}"
                                                                        data-document-name="Boring/Cutting Permit">
                                                                        <i class="bx bx-show"></i>
                                                                    </button>
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

                    <div class="dashboard-charts-grid w-100 mb-4">
                        <div class="chart-card chart-card--wide w-100">

                            <div class="chart-card__header">
                                <div>
                                    <h3>Monthly Consumption & Billing</h3>
                                    <span class="chart-card__sub">Last 12 months</span>
                                </div>
                            </div>

                            <div class="chart-card__body w-100">

                                <div class="custom-chart-legend">
                                    <div class="custom-legend-item">
                                        <span class="legend-dot legend-consumption"></span>
                                        <span>Consumption (m³)</span>
                                    </div>

                                    <div class="custom-legend-item">
                                        <span class="legend-dot legend-bill"></span>
                                        <span>Bill (₱)</span>
                                    </div>
                                </div>

                                <div class="chart-scroll-wrapper">
                                    <div id="chartConsumptionBilling"></div>
                                </div>

                            </div>
                        </div>
                    </div>

                    @if($accounts->isNotEmpty())
                    <div class="card shadow border-0 p-4 mb-4" id="accounts">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                            <div>
                                <small class="text-uppercase fw-bold text-muted">My Accounts</small>
                                <h5 class="fw-bold mb-0">Account Status and Bills</h5>
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
                            <div class="mt-4 text-end">
                                <span class="small text-muted">{{ $accounts->count() }} linked account(s)</span>
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
                            <div class="mt-1 p-3 text-uppercase fw-bold text-white fs-5 rounded-4" style="background: #32667e;">
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
                                    <div class="mt-1 p-3 text-uppercase fw-bold text-white rounded-4" style="background: #32667e;">
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
                                                @if($statementStatus === 'pending')
                                                    <div class="alert alert-warning text-uppercase text-center fw-bold mb-0">
                                                        Account pending approval. Statement details will be available after approval.
                                                    </div>
                                                @else
                                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                                    <div>
                                                        <div class="fw-bold text-uppercase">{{ $statementAccount->account_no }}</div>
                                                        <div class="small text-muted text-uppercase">{{ $statementAccount->address ?: 'N/A' }}</div>
                                                    </div>
                                                </div>
                                                <div class="bg-danger d-flex align-items-center justify-content-between mt-1 p-3 text-uppercase fw-bold text-white rounded-3">
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
                                                            No statement found.
                                                        </div>
                                                    @endforelse
                                                </div>
                                                @endif
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
                </section>

                <section class="portal-view" id="portal-view-bills" data-portal-view="bills" hidden>
                    <div class="portal-section-heading">
                        <div>
                            <span class="portal-section-kicker">Billing</span>
                            <h2>Bills & Payment</h2>
                            <p>Current unpaid statements for your linked accounts.</p>
                        </div>
                    </div>

                    @forelse($accountStatements as $accountStatement)
                        @php
                            $billAccount = $accountStatement['account'];
                            $billAccountStatus = $billAccount->access_link_status
                                ?: ($billAccount->application_status
                                    ?: ($billAccount->isApproved ? 'approved' : ($billAccount->denied_at ? 'denied' : null)));
                        @endphp
                        <section class="portal-bill-account">
                            <div class="portal-bill-account-heading">
                                <div>
                                    <span class="portal-account-number">{{ $billAccount->account_no }}</span>
                                    <span class="portal-address">{{ $billAccount->address ?: 'N/A' }}</span>
                                </div>
                                <a class="portal-text-link" href="{{ route('account-overview.bills', ['account_no' => $billAccount->account_no, 'view' => 'unpaid']) }}">Full billing history <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
                            </div>

                            @if($billAccountStatus === 'pending')
                                <div class="portal-empty-state">This account is pending approval. Bills will appear after approval.</div>
                            @else
                                <div class="portal-bill-list">
                                    @forelse($accountStatement['transactions'] as $transaction)
                                        <a class="portal-bill-row" href="{{ route('account-overview.bills.reference_no', ['reference_no' => $transaction['reference_no']]) }}">
                                            <span class="portal-bill-mark"><i class="bx bx-receipt" aria-hidden="true"></i></span>
                                            <span class="portal-bill-copy">
                                                <strong>{{ $transaction['reference_no'] }}</strong>
                                                <small>{{ !empty($transaction['bill_period_from']) ? \Carbon\Carbon::parse($transaction['bill_period_from'])->format('M d, Y') : 'N/A' }} - {{ !empty($transaction['bill_period_to']) ? \Carbon\Carbon::parse($transaction['bill_period_to'])->format('M d, Y') : 'N/A' }}</small>
                                            </span>
                                            <span class="portal-bill-status {{ !empty($transaction['isPaid']) ? 'is-paid' : '' }}">{{ !empty($transaction['isPaid']) ? 'Paid' : 'Due' }}</span>
                                            <strong class="portal-bill-amount">PHP {{ number_format((float) ($transaction['amount'] ?? 0), 2) }}</strong>
                                            <i class="bx bx-chevron-right portal-bill-chevron" aria-hidden="true"></i>
                                        </a>
                                    @empty
                                        <div class="portal-empty-state">No unpaid bills found for this account.</div>
                                    @endforelse
                                </div>
                            @endif
                        </section>
                    @empty
                        <div class="portal-empty-state">No linked accounts are available yet.</div>
                    @endforelse

                    @if($primaryAccount)
                        <div class="portal-history-link">
                            <a class="portal-button portal-button-primary" href="{{ route('account-overview.bills', ['account_no' => $primaryAccount->account_no, 'view' => 'unpaid']) }}"><i class="bx bx-history" aria-hidden="true"></i>Open Payment History</a>
                        </div>
                    @endif
                </section>

                <section class="portal-view" id="portal-view-guide" data-portal-view="guide" hidden>
                    <div class="portal-section-heading">
                        <div>
                            <span class="portal-section-kicker">Help Center</span>
                            <h2>Novustream Portal User Guide</h2>
                            <p>Account information and billing tools for your water service.</p>
                        </div>
                        <a class="portal-text-link" href="{{ route('guide.concessionaire') }}">Open full user guide <i class="bx bx-link-external" aria-hidden="true"></i></a>
                    </div>
                    <div class="portal-guide-grid">
                        <article class="portal-guide-item">
                            <span>01</span>
                            <h3>Check your account</h3>
                            <p>Your account number and service address appear in the welcome area. Use Manage Linked Accounts to submit another account for verification.</p>
                        </article>
                        <article class="portal-guide-item">
                            <span>02</span>
                            <h3>Review monthly usage</h3>
                            <p>The consumption chart compares monthly water usage with billed amounts. Select an account statement to open its bill details.</p>
                        </article>
                        <article class="portal-guide-item">
                            <span>03</span>
                            <h3>View bills and payments</h3>
                            <p>Open Bills & Payment to review due statements or use the full billing history for paid and unpaid transactions.</p>
                        </article>
                    </div>
                </section>

                <section class="portal-view" id="portal-view-ticket" data-portal-view="ticket" hidden>
                    <div class="portal-ticket-panel">
                        <div class="portal-section-heading">
                            <div>
                                <span class="portal-section-kicker">Customer Support</span>
                                <h2>Submit a Support Ticket</h2>
                                <p>Tell the Sta. Rita Water District team about your concern.</p>
                            </div>
                            <a class="portal-text-link" href="{{ route('client.support-ticket.create') }}">View submitted tickets <i class="bx bx-right-arrow-alt" aria-hidden="true"></i></a>
                        </div>
                        <form method="POST" action="{{ route('client.support-ticket.store') }}" class="portal-ticket-form">
                            @csrf
                            <div class="mb-3">
                                <label for="portal-ticket-category" class="form-label">Concern Category</label>
                                <select id="portal-ticket-category" name="category" class="form-select @error('category') is-invalid @enderror" required>
                                    <option value="">Select a category</option>
                                    @foreach($supportTicketCategories->groupBy('category') as $categoryName => $categoryGroup)
                                        <optgroup label="{{ $categoryName }}">
                                            @foreach($categoryGroup as $category)
                                                <option value="{{ $category->id }}" {{ old('category') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                            @endforeach
                                        </optgroup>
                                    @endforeach
                                </select>
                                @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="portal-ticket-message" class="form-label">Description</label>
                                <textarea id="portal-ticket-message" name="message" rows="5" class="form-control @error('message') is-invalid @enderror" placeholder="Describe your concern" required>{{ old('message') }}</textarea>
                                @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="portal-ticket-actions">
                                <button type="submit" class="portal-button portal-button-primary"><i class="bx bx-send" aria-hidden="true"></i>Submit Ticket</button>
                            </div>
                        </form>
                    </div>
                </section>
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

                        <iframe id="documentViewer"
                            title="Document preview"
                            style="display: block; width: 100%; height: 75vh; border: 0; background: #f5f5f5;">
                        </iframe>

                    </div>

                </div>

            </div>
        </div>
    </main>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap');

body { background: #f5f8fa; color: #172b3a; font-family: 'Inter', sans-serif; }
.main { padding-top: 0; }
.main > .responsive-wrapper { width: min(1280px, calc(100% - 32px)); max-width: 1280px; }
.portal-statement-modal .modal-header { align-items: flex-start; }
.portal-modal-eyebrow { display: block; margin-bottom: 4px; color: #0781a8; font-size: 9px; font-weight: 800; }
.portal-statement-modal .modal-title { font-size: 18px; }
.portal-modal-reference { display: block; margin-top: 4px; color: #71818c; font: 11px monospace; }
.portal-statement-identity,.portal-statement-period { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 12px; padding: 14px; border: 1px solid #e6ecef; border-radius: 7px; background: #f8fafb; }
.portal-statement-identity > div,.portal-statement-period > div { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
.portal-statement-identity small,.portal-statement-period small { color: #788791; font-size: 9px; font-weight: 700; text-transform: uppercase; }
.portal-statement-identity strong,.portal-statement-period strong { overflow-wrap: anywhere; color: #263d4b; font-size: 11px; }
.portal-statement-period { margin-top: 12px; background: #fff; }
.portal-statement-lines { margin-top: 15px; }
.portal-statement-lines > div,.portal-statement-after-due { display: flex; align-items: center; justify-content: space-between; gap: 14px; padding: 8px 2px; border-bottom: 1px solid #edf1f3; color: #536672; font-size: 11px; }
.portal-statement-lines strong,.portal-statement-after-due strong { color: #263d4b; font-size: 11px; white-space: nowrap; }
.portal-statement-lines .is-discount,.portal-statement-lines .is-discount strong { color: #25885d; }
.portal-statement-total { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; padding: 13px 15px; border-radius: 7px; background: #c83d4b; color: #fff; font-size: 11px; font-weight: 700; text-transform: uppercase; }
.portal-statement-total strong { font: 800 20px 'Plus Jakarta Sans',sans-serif; white-space: nowrap; }
.portal-statement-after-due { padding: 11px 2px 0; border: 0; }
.portal-button-outline { border-color: #d7e1e6; background: #fff; color: #405563; }
.portal-button-outline:hover,.portal-button-muted:hover { background: #f0f5f7; color: #0a2940; }
.portal-button-pay { background: #16875b; color: #fff; }
.portal-button-pay:hover { background: #126d49; color: #fff; }
.portal-button-muted { background: #eef2f4; color: #536573; }
.inner-content { margin-top: 22px !important; }
.portal-view { display: none; }
.portal-view.is-active { display: block; animation: portal-enter .2s ease-out; }
.portal-view[hidden] { display: none !important; }
@keyframes portal-enter { from { opacity: .5; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
.portal-view .card { overflow: hidden; border: 1px solid #e0e7eb !important; border-radius: 9px; box-shadow: 0 2px 7px rgba(15,61,94,.035) !important; }
.portal-view .card-body { padding: 0; }
.portal-view .table { margin-bottom: 0; }
.portal-view .table > :not(caption) > * > * { padding: 11px 12px; border-color: #e7edf0; color: #415563; font-size: 11px; }
.portal-view .table thead th { background: #f5f8fa; color: #62727d; font-size: 9px; text-transform: uppercase; }
.portal-view .bg-primary,.portal-view .bg-info { background: #0f3d5e !important; }
.portal-view .bg-danger { background: #c83d4b !important; }
.portal-section-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.portal-section-kicker { color: #0781a8; font-size: 9px; font-weight: 800; text-transform: uppercase; }
.portal-section-heading h2 { margin: 4px 0 0; color: #172b3a; font: 700 19px 'Plus Jakarta Sans',sans-serif; }
.portal-section-heading p { margin: 5px 0 0; color: #70808a; font-size: 11px; }
.portal-text-link { color: #087da5; font-size: 11px; font-weight: 700; text-decoration: none; white-space: nowrap; }
.portal-text-link:hover { color: #0a2940; text-decoration: underline; }
.portal-bill-account { margin-bottom: 15px; overflow: hidden; border: 1px solid #e0e7eb; border-radius: 9px; background: #fff; }
.portal-bill-account-heading { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-bottom: 1px solid #e8eef1; background: #fbfcfd; }
.portal-bill-account-heading > div { display: flex; flex-direction: column; gap: 3px; min-width: 0; }
.portal-account-number { color: #0f3d5e; font: 700 12px monospace; }
.portal-address { color: #71818c; font-size: 10px; }
.portal-bill-row { display: flex; align-items: center; gap: 12px; min-height: 67px; padding: 11px 15px; border-bottom: 1px solid #edf1f3; color: inherit; text-decoration: none; }
.portal-bill-row:last-child { border-bottom: 0; }
.portal-bill-row:hover { background: #f8fbfc; }
.portal-bill-mark { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 auto; border-radius: 7px; background: #edf6f9; color: #14799d; font-size: 17px; }
.portal-bill-copy { display: flex; flex: 1; flex-direction: column; gap: 4px; min-width: 0; }
.portal-bill-copy strong { overflow: hidden; color: #263d4b; font: 600 11px monospace; text-overflow: ellipsis; }
.portal-bill-copy small { color: #798892; font-size: 10px; }
.portal-bill-status { padding: 4px 8px; border-radius: 20px; background: #fff0f1; color: #b83c49; font-size: 9px; font-weight: 700; }
.portal-bill-status.is-paid { background: #eaf7f0; color: #267750; }
.portal-bill-amount { color: #263d4b; font: 700 12px 'Plus Jakarta Sans',sans-serif; white-space: nowrap; }
.portal-bill-chevron { color: #8b9aa3; font-size: 18px; }
.portal-empty-state { padding: 20px; color: #77868f; font-size: 11px; text-align: center; }
.portal-history-link { display: flex; justify-content: flex-end; margin-top: 18px; }
.portal-guide-grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 13px; }
.portal-guide-item { padding: 19px; border: 1px solid #e0e7eb; border-radius: 9px; background: #fff; }
.portal-guide-item > span { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 7px; background: #eaf6fb; color: #087da5; font: 800 11px 'Plus Jakarta Sans',sans-serif; }
.portal-guide-item h3 { margin: 13px 0 7px; color: #172b3a; font: 700 13px 'Plus Jakarta Sans',sans-serif; }
.portal-guide-item p { margin: 0; color: #657782; font-size: 11px; line-height: 1.65; }
.portal-ticket-panel { max-width: 760px; padding: 22px; border: 1px solid #e0e7eb; border-radius: 9px; background: #fff; }
.portal-ticket-form .form-label { color: #344c5a; font-size: 11px; font-weight: 700; }
.portal-ticket-form .form-control,.portal-ticket-form .form-select { border-color: #d6e0e5; border-radius: 7px; font-size: 12px; }
.portal-ticket-form .form-control:focus,.portal-ticket-form .form-select:focus { border-color: #1684aa; box-shadow: 0 0 0 3px rgba(22,132,170,.13); }
.portal-ticket-actions { display: flex; justify-content: flex-end; }
.portal-modal .modal-content,.modal-content { border-color: #dfe7ec; border-radius: 10px; box-shadow: 0 18px 50px rgba(10,41,64,.18); }
.modal-header,.modal-footer { border-color: #e8eef1; }
.modal-title { color: #0f3d5e; font-family: 'Plus Jakarta Sans',sans-serif; }

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

.dashboard-charts-grid {
    width: 100% !important;
    display: block;
}

.chart-card--wide {
    width: 100% !important;
    max-width: none !important;
}

.chart-card__body {
    width: 100% !important;
    min-height: 360px;
}

.apexcharts-legend {
    display: flex !important;
    flex-wrap: nowrap !important;
    justify-content: center !important;
    align-items: center !important;
    gap: 20px !important;
    width: 100% !important;
}

.apexcharts-legend-series {
    margin: 0 !important;
    flex: 0 0 auto !important;
    white-space: nowrap !important;
}

.custom-chart-legend {
    width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 28px;
    padding: 10px 0 15px;
    flex-wrap: nowrap;
}

.custom-legend-item {
    display: flex;
    align-items: center;
    gap: 7px;
    white-space: nowrap;
    font-size: 14px;
    font-weight: 500;
}

.legend-dot {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    flex-shrink: 0;
}

.legend-consumption {
    background: #2196f3;
}

.legend-payment {
    background: #d89a21;
}

.legend-bill {
    background: #22a875;
}

.table-responsive-custom {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
}

.table-responsive-custom table {
    width: 100%;
    min-width: 100%;
    white-space: nowrap;
}

.chart-scroll-wrapper {
    width: 100%;
    max-width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 10px;
}

/*
 * Keep the graph wide enough so it does not get compressed
 * on phones.
 */
#chartConsumptionBilling {
    width: 100%;
    min-width: 100%;
}

/* Scrollbar */
.chart-scroll-wrapper::-webkit-scrollbar {
    height: 7px;
}

.chart-scroll-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.chart-scroll-wrapper::-webkit-scrollbar-thumb {
    background: #b8b8b8;
    border-radius: 10px;
}

.chart-scroll-wrapper::-webkit-scrollbar-thumb:hover {
    background: #888;
}

@media (max-width: 768px) {

    .chart-scroll-wrapper {
        overflow-x: auto;
    }

    #chartConsumptionBilling {
        min-width: 100%;
    }

    .custom-chart-legend {
        justify-content: flex-start;
        min-width: 100%;
    }
}

@media (max-width: 768px) {
    .table-responsive-custom table {
        min-width: 900px;
    }
}

@media (max-width: 600px) {
    .custom-chart-legend {
        gap: 12px;
        font-size: 12px;
    }
}

@media (max-width: 820px) {
    .portal-guide-grid { grid-template-columns: 1fr; }
    .portal-section-heading { align-items: flex-start; flex-direction: column; }
}

@media (max-width: 520px) {
    .portal-bill-account-heading { align-items: flex-start; flex-direction: column; }
    .portal-bill-row { flex-wrap: wrap; gap: 8px; }
    .portal-bill-copy { min-width: calc(100% - 54px); }
    .portal-bill-status { margin-left: 42px; }
    .portal-bill-amount { margin-left: auto; }
    .portal-ticket-panel { padding: 15px; }
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

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

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
        window.switchPortalTab = function (tabName) {
            document.querySelectorAll('[data-portal-view]').forEach(function (view) {
                const isActive = view.dataset.portalView === tabName;
                view.hidden = !isActive;
                view.classList.toggle('is-active', isActive);
            });

            document.querySelectorAll('[data-portal-tab]').forEach(function (button) {
                const isActive = button.dataset.portalTab === tabName;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-current', isActive ? 'page' : 'false');
            });

            const mobileNav = document.getElementById('portal-mobile-nav');
            const mobileToggle = document.getElementById('portalMobileMenuToggle');
            if (mobileNav && mobileToggle) {
                mobileNav.hidden = true;
                mobileToggle.setAttribute('aria-expanded', 'false');
            }

            const profileMenu = document.getElementById('portal-profile-menu');
            const profileToggle = document.getElementById('portalProfileToggle');
            if (profileMenu && profileToggle) {
                profileMenu.hidden = true;
                profileToggle.setAttribute('aria-expanded', 'false');
            }

            window.dispatchEvent(new Event('resize'));
        };

        document.addEventListener('DOMContentLoaded', function () {
            @if($errors->has('category') || $errors->has('message'))
                window.switchPortalTab('ticket');
            @endif
        });

        @if(session('alert'))
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Swal === 'undefined') {
                return;
            }

            const profileAlert = @json(session('alert'));
            Swal.fire({
                icon: profileAlert.status === 'success' ? 'success' : 'error',
                title: profileAlert.password_changed ? 'Password updated' : 'Profile updated',
                text: profileAlert.message || '',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 6000,
                timerProgressBar: true
            });
        });
        @endif

        document.addEventListener('DOMContentLoaded', function () {

            const labels = @json($chartMonthlyLabels ?? []);
            const consumptionData = @json($chartMonthlyConsumption ?? []);
            const billData = @json($chartMonthlyBill ?? []);
            const paymentData = @json($chartMonthlyPayment ?? []);

            const chartElement = document.querySelector('#chartConsumptionBilling');

            if (!chartElement) {
                return;
            }

            if (
                labels.length === 0 ||
                (
                    consumptionData.every(v => Number(v) === 0) &&
                    billData.every(v => Number(v) === 0) &&
                    paymentData.every(v => Number(v) === 0)
                )
            ) {
                chartElement.innerHTML = `
                    <div class="chart-empty">
                        <span>No consumption or billing history available</span>
                    </div>
                `;
                return;
            }

            const options = {

                series: [
                    {
                        name: 'Consumption (m³)',
                        type: 'column',
                        data: consumptionData
                    },
                    {
                        name: 'Bill (₱)',
                        type: 'bar',
                        data: billData,
                    },
                ],

                fill: {
                    opacity: [1, 0]
                },

                chart: {
                    height: 360,
                    width: 1100,
                    type: 'line',
                    toolbar: {
                        show: false
                    }
                },

                stroke: {
                    width: [0, 0],
                    curve: 'smooth'
                },

                plotOptions: {
                    bar: {
                        borderRadius: 4,
                        columnWidth: '85%'
                    }
                },

                dataLabels: {
                    enabled: false
                },

                xaxis: {
                    categories: labels,
                    labels: {
                        style: {
                            fontSize: '11px'
                        }
                    }
                },

                yaxis: [
                    {
                        title: {
                            text: 'Consumption (m³)'
                        }
                    },
                    {
                        opposite: true,
                        title: {
                            text: 'Amount (₱)'
                        }
                    }
                ],

                legend: {
                    show: false,
                },

                tooltip: {
                    shared: true,
                    intersect: false
                }
            };

            const chart = new ApexCharts(chartElement, options);

            chart.render();

        });
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

            const linkApprovalNotifications = accountNotifications.filter(function (notification) {
                return notification.type === 'account_link';
            });

            if (linkApprovalNotifications.length && typeof Swal !== 'undefined') {
                const hasDeniedLink = linkApprovalNotifications.some(function (notification) {
                    return notification.status === 'danger';
                });

                Swal.fire({
                    icon: hasDeniedLink ? 'error' : 'success',
                    title: linkApprovalNotifications.length === 1
                        ? linkApprovalNotifications[0].title
                        : 'Account link decisions updated',
                    text: linkApprovalNotifications.map(function (notification) {
                        return notification.message;
                    }).join('\n'),
                    confirmButtonText: 'OK',
                    confirmButtonColor: hasDeniedLink ? '#dc3545' : '#198754',
                });
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
                viewer.src = url;
            });

            viewModal.addEventListener('hidden.bs.modal', function () {
                viewer.src = 'about:blank';
            });

        });
    </script>
@endsection
