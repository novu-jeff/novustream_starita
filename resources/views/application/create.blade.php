@extends('layouts.app')

@section('content')

<style>

.application-page {
    width: 100% !important;
    min-height: calc(100vh - 90px)!important;
    background: #f3f4f6;
    padding: 32px 24px 56px;
    display: flex;
    flex-direction: column;
    align-items: center; /* Centers the paper and toolbar horizontally */
}

.application-toolbar {
    width: 215.9mm !important;
    max-width: 100%;
    margin-bottom: 16px;
    display: flex;
    justify-content: flex-end;
}

.application-paper {
    width: 215.9mm !important;
    max-width: 100%;
    padding: 16mm 14mm !important;   /* add !important */
    min-height: 279.4mm;
    background: #fff;
    margin: 0 auto !important;       /* add !important */
    border: 1px solid #333;
    font-size: 12px;
    color: #000;
    box-sizing: border-box !important; /* add too, in case box-sizing is being reset */
    box-shadow: 0 16px 45px rgba(0, 0, 0, 0.14);
    overflow: hidden;
}

.application-paper > form > .row,
.application-paper .paper-body {
    --bs-gutter-x: 0 !important;
    margin-left: 0 !important;
    margin-right: 0 !important;
}

.application-paper [class*="col-"] .row {
    --bs-gutter-x: 1.5rem !important;
}

/* ---- paper-style fields: ruled line instead of a boxed input ---- */
.application-paper .form-control,
.application-paper .form-select {
    border: none;
    border-bottom: 1px solid #000;
    border-radius: 0;
    background: transparent;
    font-size: 13px;
    padding: 2px 3px;
    height: auto;
    box-shadow: none !important;
}

.application-paper .form-control:focus {
    background: #fbfbfb;
    border-color: #000;
}

.application-paper .form-control[disabled] {
    border-bottom-style: dashed;
    color: #000;
    background: transparent;
}

.application-paper textarea.form-control {
    resize: none;
    min-height: 44px;
}

/* office-side fields keep a light box border so staff know where to write */
.application-paper .office-field {
    border: 1px solid #999 !important;
    border-radius: 2px;
    padding: 4px 6px !important;
}

.form-title h1 {
    font-size: 22px;
    font-weight: 700;
    margin-bottom: 2px;
}

.form-title .address {
    font-size: 13px;
    font-weight: 300;
}

.form-title h2 {
    font-size: 16px;
    font-weight: 700;
    text-decoration: underline;
    margin-top: 10px;
}

.control-nos {
    font-size: 12px;
}

.control-nos label {
    font-weight: 700;
    margin-bottom: 0;
}

.section-label {
    font-weight: 700;
    text-transform: uppercase;
    font-size: 11px;
    letter-spacing: .3px;
    color: #000;
}

.paper-body {
    border-top: 1px solid #999;
    padding-top: 18px;
    margin-top: 8px;
}

.paper-body .col-left {
    border-right: 1px solid #999;
}

.form-check-label {
    font-size: 13px;
}

.side-title {
    font-weight: 700;
    font-size: 14px;
}

.approved-block {
    max-width: 55mm;
    margin: 10px auto 12px;
}

.approved-name {
    font-size: 13px;
    font-weight: 700;
    border-bottom: 1px solid #000;
    padding-bottom: 2px;
}

.approved-position {
    font-size: 11px;
    color: #333;
}

.materials-table {
    font-size: 11px;
}

.materials-table th {
    background: #f3f3f3;
    text-align: center;
    font-weight: 700;
}

.materials-table td {
    height: 26px;
}

.charge-row label {
    font-size: 12px;
}

.no-print {
    display: block;
}

@media print {

    .no-print {
        display: none !important;
    }

    @page {
        size: letter;
    }

    html,
    body {
        width: 215.9mm;
        min-height: 279.4mm;
        background: white !important;
    }

    header,
    nav,
    footer,
    .navbar,
    .sidebar,
    .menu {
        display: none !important;
    }

    body * {
        visibility: hidden;
    }

    .application-page,
    .application-print-area,
    .application-paper,
    .application-paper * {
        visibility: visible;
    }

    .application-page {
        background: white !important;
        padding: 0 !important;
        margin: 0 !important;
        min-height: 279.4mm;
    }

    .application-print-area {
        position: absolute;
        inset: 0;
        width: 215.9mm;
        min-height: 279.4mm;
        margin: 0;
        padding: 0;
    }

    .application-paper {
        width: 215.9mm;
        min-height: 279.4mm;
        margin: 0;
        padding: 12mm 14mm;
        border: none;
        background: white;
        box-shadow: none;
    }
}

</style>

<div class="application-page">
    <div class="application-toolbar print-btn no-print" style="gap: 10px;">
        <a href="{{ route('application.print.preview') }}"
           target="_blank"
           id="print-application-link"
           class="btn btn-primary print-preview-link">
            <i class="bx bx-printer"></i>
            Print Application
        </a>
        <a href="{{ route('application.contract.preview') }}"
           target="_blank"
           id="print-contract-link"
           class="btn btn-outline-primary print-preview-link">
            <i class="bx bx-file"></i>
            Print Contract
        </a>
        <a href="{{ route('account-overview.index') }}" class="btn btn-primary fw-bold text-uppercase">Back to Overview</a>
    </div>

    <div class="application-print-area">
        <div class="application-paper printable-form">
            <form action="{{ route('application.store') }}" method="POST">
                @csrf

                <!-- HEADER -->
                <div class="row align-items-start mb-2">
                    <div class="col-8 form-title text-center offset-2">
                        <h1>STA. RITA WATER DISTRICT</h1>
                        <div class="address">Brgy. Dila-Dila, Sta. Rita, Pampanga</div>
                        <h2>APPLICATION FOR SERVICE CONNECTION</h2>
                    </div>
                </div>

                <div class="row justify-content-end mb-3">
                    <div class="col-5 col-md-4 control-nos">
                        <div class="row mb-1 align-items-end">
                            <div class="col-5"><label>S.C. No.</label></div>
                            <div class="col-7">
                                <input name="sc_no" class="form-control form-control-sm"
                                       value="{{ old('sc_no', $applicationDefaults['sc_no'] ?? '') }}">
                            </div>
                        </div>
                        <div class="row mb-1 align-items-end">
                            <div class="col-5"><label>Meter No.</label></div>
                            <div class="col-7">
                                <input name="meter_no" class="form-control form-control-sm"
                                       value="{{ old('meter_no', $applicationDefaults['meter_no'] ?? '') }}">
                            </div>
                        </div>
                        <div class="row align-items-end">
                            <div class="col-5"><label>Account No.</label></div>
                            <div class="col-7">
                                <input name="account_no" class="form-control form-control-sm"
                                       value="{{ old('account_no', $applicationDefaults['account_no'] ?? '') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-6">
                        <label class="section-label">Cellphone Number</label>
                        <input name="cellphone"
                               class="form-control"
                               value="{{ old('cellphone', $applicationDefaults['cellphone'] ?? '') }}">
                    </div>
                </div>

                <!-- BODY -->
                <div class="row paper-body">
                    <!-- LEFT -->
                    <div class="col-6 col-left pe-4">

                        <div class="mb-3">
                            <label class="section-label">Applicant's Name</label>
                            <input name="applicant_name"
                                   class="form-control"
                                   value="{{ old('applicant_name', $applicationDefaults['applicant_name'] ?? '') }}">
                        </div>

                        <div class="mb-3">
                            <label class="section-label">Service Address</label>
                            <textarea name="service_address"
                                      class="form-control"
                                      rows="2">{{ old('service_address', $applicationDefaults['service_address'] ?? '') }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="section-label d-block mb-1">I hereby apply for:</label>

                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="application_type"
                                       id="type_new" value="Water Service Connection"
                                       @checked(old('application_type', $applicationDefaults['application_type'] ?? '') === 'Water Service Connection')>
                                <label class="form-check-label" for="type_new">Water Service Connection</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="application_type"
                                       id="type_relocation" value="Relocation of Service Line"
                                       @checked(old('application_type', $applicationDefaults['application_type'] ?? '') === 'Relocation of Service Line')>
                                <label class="form-check-label" for="type_relocation">Relocation of Service Line</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="application_type"
                                       id="type_replacement" value="Replacement of Pipe"
                                       @checked(old('application_type', $applicationDefaults['application_type'] ?? '') === 'Replacement of Pipe')>
                                <label class="form-check-label" for="type_replacement">Replacement of Pipe</label>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="section-label">Connection Size</label>
                                <input name="connection_size"
                                       class="form-control"
                                       value="{{ old('connection_size', $applicationDefaults['connection_size'] ?? '') }}">
                            </div>
                            <div class="col-6">
                                <label class="section-label">Installation Location</label>
                                <input name="installation_location"
                                       class="form-control"
                                       value="{{ old('installation_location', $applicationDefaults['installation_location'] ?? '') }}">
                            </div>
                        </div>

                        <p class="mb-2" style="text-align: justify;">
                            I understand the connection will not be made until it is approved and all charges are paid.
                            I assume responsibility for the meter and all water passing through the connection.
                            I will conform to the rules and regulations of Sta. Rita Water District.
                        </p>

                        <div class="row mb-1 mt-4">
                            <div class="col-8">
                                <input name="signature_name"
                                       class="form-control"
                                       value="{{ old('signature_name', $applicationDefaults['signature_name'] ?? '') }}">
                            </div>
                            <div class="col-4">
                                <input type="date" name="application_date"
                                       class="form-control"
                                       value="{{ old('application_date', $applicationDefaults['application_date'] ?? '') }}">
                            </div>
                        </div>
                        <div class="row text-muted mb-4" style="font-size: 11px;">
                            <div class="col-8">Applicant's Signature</div>
                            <div class="col-4">Date</div>
                        </div>

                        <hr>

                        <div class="mb-3">
                            <label class="section-label">Building / Property Owner</label>
                            <input name="property_owner"
                                   class="form-control"
                                   value="{{ old('property_owner', $applicationDefaults['property_owner'] ?? '') }}">
                        </div>

                        <hr>

                        <div class="mb-2">
                            <label class="section-label">Promissory Note (Optional)</label>
                            <input type="number"
                                   name="promissory_amount"
                                   class="form-control"
                                   value="{{ old('promissory_amount', $applicationDefaults['promissory_amount'] ?? '') }}">
                        </div>
                    </div>

                    <!-- RIGHT -->
                    <div class="col-6 ps-4">
                        <p class="side-title text-center mb-3">
                            THIS SIDE IS TO BE FILLED UP BY<br>
                            STA. RITA WATER DISTRICT
                        </p>

                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="section-label d-block">Investigation</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="inv_adequate" disabled>
                                    <label class="form-check-label" for="inv_adequate">Adequate</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="inv_not_adequate" disabled>
                                    <label class="form-check-label" for="inv_not_adequate">Not Adequate</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="section-label d-block">Plumbing Installation</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="plumb_available" disabled>
                                    <label class="form-check-label" for="plumb_available">Available</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="plumb_not_available" disabled>
                                    <label class="form-check-label" for="plumb_not_available">Not Available</label>
                                </div>
                            </div>
                        </div>

                        <div class="approved-block text-center">
                            <label class="section-label d-block mb-1">Approved By</label>
                            <div class="approved-name">Rolando V. Miranda</div>
                            <div class="approved-position">General Manager</div>
                        </div>

                        <div class="mb-3">
                            <label class="section-label d-block mb-2">Charges</label>

                            <div class="row mb-2 align-items-center charge-row">
                                <div class="col-6"><label>Service Fee</label></div>
                                <div class="col-6">
                                    <input class="form-control office-field text-end">
                                </div>
                            </div>
                            <div class="row mb-2 align-items-center charge-row">
                                <div class="col-6"><label>Installation Fee</label></div>
                                <div class="col-6">
                                    <input class="form-control office-field text-end">
                                </div>
                            </div>
                            <div class="row mb-2 align-items-center charge-row">
                                <div class="col-6"><label>Initial Payment</label></div>
                                <div class="col-6">
                                    <input class="form-control office-field text-end">
                                </div>
                            </div>
                            <div class="row align-items-center charge-row">
                                <div class="col-6"><label><strong>Total</strong></label></div>
                                <div class="col-6">
                                    <input class="form-control office-field text-end">
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="section-label d-block mb-2">Materials Needed</label>
                            <table class="table table-bordered materials-table mb-0">
                                <tr>
                                    <th>Description</th>
                                    <th width="20%">Qty</th>
                                    <th width="25%">Amount</th>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="text-end mt-4 submit-btn no-print">
                    <button class="btn btn-success">
                        Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@section('script')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const printLinks = document.querySelectorAll('.print-preview-link');
        const form = document.querySelector('.application-paper form');

        if (!printLinks.length || !form) {
            return;
        }

        printLinks.forEach(function (printLink) {
            printLink.addEventListener('click', function () {
            const data = {};
            const fields = form.querySelectorAll('input[name], textarea[name], select[name]');

            fields.forEach(function (field) {
                if ((field.type === 'radio' || field.type === 'checkbox') && !field.checked) {
                    return;
                }

                data[field.name] = field.value;
            });

            localStorage.setItem('srwd_application_print_data', JSON.stringify(data));
            });
        });
    });
</script>
@endsection
