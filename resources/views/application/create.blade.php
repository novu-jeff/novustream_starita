@extends('layouts.app')

@section('content')

<style>

.application-page {
    width: 100%;
    min-height: calc(100vh - 90px);
    background: #f3f4f6;
    padding: 32px 24px 56px;
}

.application-toolbar {
    width: 215.9mm;
    max-width: 100%;
    margin: 0 auto 16px;
    display: flex;
    justify-content: flex-end;
}

.application-paper {
    width: 215.9mm;
    min-height: 279.4mm;
    background: #fff;
    margin: 0 auto;
    padding: 11mm 12mm;
    border: 1px solid #333;
    font-size: 12px;
    color:#000;
    box-sizing: border-box;
    box-shadow: 0 16px 45px rgba(0, 0, 0, 0.14);
    overflow: hidden;
}


.application-paper .form-control {
    border-radius:0;
    border:1px solid #000;
    height:32px;
    font-size:12px;
    padding: 4px 7px;
}


.application-paper textarea {
    resize:none;
    min-height: 48px;
}


.form-title {
    text-align:center;
    font-weight:bold;
}


.form-title h3 {
    font-size:20px;
    margin-bottom:3px;
}


.form-title h5 {
    font-size:15px;
    text-decoration:underline;
}


.section-label {

    font-weight:bold;
    text-transform:uppercase;
    background:#eee;
    padding:3px;

}


.print-btn {
    margin-bottom:15px;
}


.no-print {
    display:block;
}


@media print {

.no-print {
    display:none !important;
}

    @page {
        size: letter;
        margin: 0;
    }


    html,
    body {
        width: 215.9mm;
        min-height: 279.4mm;
        background:white !important;
        margin: 0 !important;
        padding: 0 !important;
    }


    header,
    nav,
    footer,
    .navbar,
    .sidebar,
    .menu,
    .print-btn,
    .submit-btn {
        display:none !important;
    }


    /* Hide everything except application */
    body * {
        visibility:hidden;
    }


    .application-page,
    .application-print-area,
    .application-paper,
    .application-paper * {
        visibility:visible;
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

        width:215.9mm;

        min-height:279.4mm;

        margin:0;

        padding:8mm;

        border:none;

        background:white;
        box-shadow: none;

    }



    /* Keep bootstrap */
    .application-paper .row {
        display:flex !important;
    }


    .application-paper .col-6,
    .application-paper .col-4 {

        display:block !important;

    }


}

</style>

<div class="application-page">
    <div class="application-toolbar print-btn no-print">
        <a href="{{ route('application.print.preview') }}"
           target="_blank"
           id="print-application-link"
           class="btn btn-primary">
            <i class="bx bx-printer"></i>
            Print Application
        </a>
    </div>
    <div class="application-print-area">
        <div class="application-paper printable-form">
            <form action="{{ route('application.store') }}"
                  method="POST">
                @csrf
                <!-- HEADER -->
                <div class="form-title mb-3">
                    <h3>
                        STA. RITA WATER DISTRICT
                    </h3>
                    <div>
                        Brgy. Dila-Dila, Sta. Rita, Pampanga
                    </div>
                    <h5>
                        APPLICATION FOR SERVICE CONNECTION
                    </h5>
                </div>
                <!-- CONTROL NUMBERS -->
                <div class="row mb-2">
                    <div class="col-4">
                        <label class="fw-bold">
                            S.C. No.
                        </label>
                        <input name="sc_no" class="form-control" value="{{ old('sc_no', $applicationDefaults['sc_no'] ?? '') }}">
                    </div>
                    <div class="col-4">
                        <label class="fw-bold">
                            Meter No.
                        </label>
                        <input name="meter_no" class="form-control" value="{{ old('meter_no', $applicationDefaults['meter_no'] ?? '') }}">
                    </div>
                    <div class="col-4">
                        <label class="fw-bold">
                            Account No.
                        </label>
                        <input name="account_no" class="form-control" value="{{ old('account_no', $applicationDefaults['account_no'] ?? '') }}">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="section-label">
                        Cellphone Number
                    </label>
                    <input name="cellphone"
                           class="form-control"
                           value="{{ old('cellphone', $applicationDefaults['cellphone'] ?? '') }}">
                </div>
                <div class="row">
                    <!-- LEFT -->
                    <div class="col-6 border-end">
                        <div class="mb-2">
                            <label class="fw-bold">
                                Applicant's Name
                            </label>
                            <input
                                name="applicant_name"
                                class="form-control"
                                value="{{ old('applicant_name', $applicationDefaults['applicant_name'] ?? '') }}">
                        </div>
                        <div class="mb-2">
                            <label class="fw-bold">
                                Service Address
                            </label>
                            <textarea
                                name="service_address"
                                class="form-control"
                                rows="2">{{ old('service_address', $applicationDefaults['service_address'] ?? '') }}</textarea>
                        </div>
                        <div class="mb-2">
                            <label class="fw-bold">
                                I hereby apply for:
                            </label>
                            <div>
                                <input type="radio"
                                       name="application_type"
                                       value="Water Service Connection">
                                Water Service Connection
                            </div>
                            <div>
                                <input type="radio"
                                       name="application_type"
                                       value="Relocation of Service Line">
                                Relocation of Service Line
                            </div>
                            <div>
                                <input type="radio"
                                       name="application_type"
                                       value="Replacement of Pipe">

                                Replacement of Pipe
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-6">
                                <label class="fw-bold">
                                    Connection Size
                                </label>
                                <input name="connection_size"
                                       class="form-control"
                                       value="{{ old('connection_size') }}">
                            </div>
                            <div class="col-6">
                                <label class="fw-bold">
                                    Installation Location
                                </label>
                                <input name="installation_location"
                                       class="form-control"
                                       value="{{ old('installation_location') }}">
                            </div>
                        </div>
                        <hr>
                        <p>
                            I understand the connection will not be made until approved and all charges are paid.
                        </p>
                        <p>
                            I assure responsibility for the meter and all water passing through the connection.
                        </p>
                        <p>
                            I will conform to the rules and regulations of Sta. Rita Water District.
                        </p>
                        <div class="row">
                            <div class="col-6">
                                <label class="fw-bold">
                                    Applicant Signature
                                </label>
                                <input name="signature_name"
                                       class="form-control"
                                       value="{{ old('signature_name', $applicationDefaults['signature_name'] ?? '') }}">
                            </div>
                            <div class="col-6">
                                <label class="fw-bold">
                                    Date
                                </label>
                                <input type="date"
                                       name="application_date"
                                       class="form-control"
                                       value="{{ old('application_date', $applicationDefaults['application_date'] ?? '') }}">
                            </div>
                        </div>
                        <hr>
                        <label class="fw-bold">
                            Building / Property Owner
                        </label>
                        <input name="property_owner"
                               class="form-control"
                               value="{{ old('property_owner', $applicationDefaults['property_owner'] ?? '') }}">
                        <hr>
                        <label class="fw-bold">
                            PROMISSORY NOTE (Optional)
                        </label>
                        <input
                            type="number"
                            name="promissory_amount"
                            class="form-control"
                            value="{{ old('promissory_amount') }}">
                    </div>
                    <!-- RIGHT -->
                    <div class="col-6">
                        <h6 class="text-center fw-bold">
                            THIS SIDE IS TO BE FILLED UP BY<br>
                            STA. RITA WATER DISTRICT
                        </h6>
                        <hr>
                        <label class="fw-bold">
                            Investigation
                        </label>
                        <div>
                            ☐ Adequate
                        </div>
                        <div>
                            ☐ Not Adequate
                        </div>
                        <br>
                        <label class="fw-bold">
                            Plumbing Installation
                        </label>
                        <div>
                            ☐ Available
                        </div>
                        <div>
                            ☐ Not Available
                        </div>
                        <br>
                        <label class="fw-bold">
                            Approved By
                        </label>
                        <input value="Rolando V. Miranda"
                               class="form-control"
                               disabled>
                        <br>
                        <label class="fw-bold">
                            Charges
                        </label>
                        <input class="form-control mb-2"
                               placeholder="Service Fee">
                        <input class="form-control mb-2"
                               placeholder="Installation Fee">
                        <input class="form-control mb-2"
                               placeholder="Initial Payment">
                        <input class="form-control"
                               placeholder="Total">
                        <br>
                        <label class="fw-bold">
                            Materials Needed
                        </label>
                        <table class="table table-bordered">
                            <tr>
                                <th>Description</th>
                                <th width="20%">Qty</th>
                                <th width="25%">Amount</th>
                            </tr>
                            <tr>
                                <td height="30"></td>
                                <td></td>
                                <td></td>
                            </tr>
                            <tr>
                                <td height="30"></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="text-end mt-3 submit-btn">
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
        const printLink = document.getElementById('print-application-link');
        const form = document.querySelector('.application-paper form');

        if (!printLink || !form) {
            return;
        }

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
</script>
@endsection
