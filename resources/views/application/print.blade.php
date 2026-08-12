<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application for Service Connection</title>
    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            background: #f3f4f6;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }

        .application-paper {
            width: 215.9mm;
            min-height: 279.4mm;
            margin: 14px auto;
            padding: 11mm 12mm;
            background: #fff;
            border: 1px solid #222;
        }

        .header {
            position: relative;
            text-align: center;
            margin-bottom: 8px;
            min-height: 38px;
        }

        .header h1 {
            margin: 0;
            font-size: 25px;
            font-weight: 700;
            letter-spacing: 0;
        }

        .header .address {
            margin-top: 1px;
            font-size: 15px;
            font-weight: 300;
        }

        .header h2 {
            margin: 8px 0 15rem;
            font-size: 18px;
            font-weight: 700;
        }

        .control-nos {
            position: absolute;
            margin-top: 11rem;
            right: 0;
            top: 30px;
            width: 52mm;
            text-align: left;
        }

        .line-row {
            display: flex;
            align-items: flex-end;
            font-size: 13px;
            gap: 4px;
            min-height: 14px;
        }

        .line-row .label {
            flex: 0 0 auto;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }

        .line {
            flex: 1;
            min-height: 12px;
            border-bottom: 1px solid #000;
            padding: 0 3px 1px;
        }

        .top-phone {
            width: 76mm;
            margin-top: 20px;
            margin-bottom: 4px;
        }

        .columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 14mm;
            align-items: start;
        }

        .side-title {
            text-align: center;
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 8px;
        }

        .section {
            margin-bottom: 20px;
            font-size: 15px;
        }

        .section-title {
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 2px;
            font-size: 13px;
        }

        .check-row {
            display: flex;
            align-items: center;
            min-height: 13px;
        }

        .box {
            width: 8px;
            height: 8px;
            border: 1px solid #000;
            margin-right: 4px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 8px;
            line-height: 1;
        }

        .note {
            font-size: 13px;
            line-height: 1.25;
            margin: 25px 0 10px;
            text-align: justify;
        }

        .gm {
            font-size: 13px;
            margin-top: 25px;
            border-bottom: 1px solid #000;
        }

        .gm-position {
            font-size: 13px;
            margin-bottom: 25px;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 28mm;
            gap: 8mm;
            margin-top: 7px;
            font-size: 13px;
        }

        .office-lines {
            margin-top: 3px;
        }

        .office-lines .line-row {
            min-height: 16px;
        }

        .approved-block {
            width: 52mm;
            text-align: center;
            margin: 9px auto 8px;
        }

        .reading-grid {
            display: grid;
            grid-template-columns: 1fr 26mm;
            gap: 6mm;
            margin-top: 5px;
        }

        .charges {
            margin-top: 13px;
        }

        .charge-row {
            display: grid;
            grid-template-columns: 1fr 22mm;
            gap: 5mm;
            min-height: 14px;
            align-items: end;
            font-size: 13px;
        }

        .amount-line {
            border-bottom: 1px solid #000;
            min-height: 12px;
            display: flex;
            align-items: end;
            gap: 3px;
        }

        .amount-line::before {
            content: "P";
            font-weight: 700;
        }

        .or-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 5mm;
            margin-top: 8px;
        }

        .materials {
            margin-top: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
        }

        .review-toolbar {
            width: 215.9mm;
            margin: 14px auto 0;
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .review-toolbar a,
        .review-toolbar button {
            border: 1px solid #0d6efd;
            background: #0d6efd;
            color: #fff;
            padding: 8px 12px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .review-toolbar .secondary {
            background: #fff;
            color: #0d6efd;
        }

        th,
        td {
            border: 1px solid #000;
            height: 22px;
            padding: 2px;
        }

        th {
            font-weight: 700;
            text-align: center;
        }

        @media print {
    @page {
        size: A4;
        margin: 8mm;
    }

    .application-paper {
        width: auto;
        min-height: auto;
        margin: 0;
        padding: 3mm 4mm;  /* small residual padding instead of 0 */
        border: none;
    }
}
    </style>
</head>
<body>
    @if(!($autoPrint ?? true))
        <div class="review-toolbar">
            <a href="{{ route('account-overview.index') }}" class="btn btn-primary fw-bold text-uppercase">Back to Overview</a>
            <a href="{{ route('application.create') }}" class="btn btn-primary fw-bold text-uppercase">Review Form</a>
            <button type="button" onclick="window.print()" class="btn btn-primary fw-bold text-uppercase">Print</button>
        </div>
    @endif

    <main class="application-paper">
        <header class="header">
            <h1>STA. RITA WATER DISTRICT</h1>
            <div class="address">Brgy. Dila-Dila, Sta. Rita, Pampanga</div>
            <h2>APPLICATION FOR SERVICE CONNECTION</h2>

            <div class="control-nos">
                <div class="line-row"><span class="label">S.C. No.</span><span class="line" data-print-value="sc_no"></span></div>
                <div class="line-row"><span class="label">Meter No.</span><span class="line" data-print-value="meter_no"></span></div>
                <div class="line-row"><span class="label">Account No.</span><span class="line" data-print-value="account_no"></span></div>
            </div>
        </header>

        <div class="line-row top-phone">
            <span class="label">CELLPHONE NUMBER</span>
            <span class="line" data-print-value="cellphone"></span>
        </div>

        <section class="columns">
            <div>
                <div class="section">
                    <div class="line-row">
                        <span class="label">APPLICANT'S NAME</span>
                        <span class="line" data-print-value="applicant_name"></span>
                    </div>
                </div>

                <div class="section">
                    <div class="section-title">SERVICE ADDRESS</div>
                    <div class="line" style="min-height: 22px;" data-print-value="service_address"></div>
                </div>

                <div class="section">
                    <div class="section-title">I hereby apply for:</div>
                    <div class="check-row"><span class="box" data-radio-value="Water Service Connection"></span>Water Service Connection</div>
                    <div class="check-row"><span class="box" data-radio-value="Relocation of Service Line"></span>Relocation of Service Line</div>
                    <div class="check-row"><span class="box" data-radio-value="Replacement of Pipe"></span>Replacement of Pipe</div>
                </div>

                <div class="section">
                    <div class="line-row"><span class="label">Connection Size</span><span class="line" data-print-value="connection_size"></span></div>
                    <div class="line-row"><span class="label">Installation Location</span><span class="line" data-print-value="installation_location"></span></div>
                </div>

                <p class="note">I understand the connection will not be made until it is approved and all charges are paid. I assume responsibility for the meter and all water passing through the connection. I will conform to the rules and regulations of Sta. Rita Water District.</p>

                <div class="signature-grid">
                    <div class="line-row"><span class="line" data-print-value="signature_name"></span></div>
                    <div class="line-row"><span class="line" data-print-value="application_date"></span></div>
                </div>
                <div class="signature-grid" style="margin-top: 1px;">
                    <div>Applicant's Signature</div>
                    <div>Date</div>
                </div>

                <p class="note" style="margin-top: 12px;">I hereby bind myself to pay any unpaid water bills of the occupant in case he/she vacates the premises permanently.</p>

                <div class="signature-grid">
                    <div class="line-row"><span class="line" data-print-value="property_owner"></span></div>
                    <div class="line-row"><span class="line"></span></div>
                </div>
                <div class="signature-grid" style="margin-top: 1px;">
                    <div>Building / Property Owner</div>
                    <div>Date</div>
                </div>

                <div class="section" style="margin-top: 14px;">
                    <div class="section-title">Promissory Note (Optional)</div>
                    <div class="line-row"><span class="label">Amount</span><span class="line" data-print-value="promissory_amount"></span></div>
                </div>
            </div>

            <div>
                <div class="side-title">THIS SIDE IS TO BE FILLED UP BY<br>STA. RITA WATER DISTRICT</div>

                <div class="columns" style="column-gap: 6mm;">
                    <div class="section">
                        <div class="section-title">Investigation of Applicant System is</div>
                        <div class="check-row"><span class="box"></span>Adequate</div>
                        <div class="check-row"><span class="box"></span>Not Adequate</div>
                    </div>

                    <div class="section">
                        <div class="section-title">Availability of Applicant's Plumbing Installation is</div>
                        <div class="check-row"><span class="box"></span>Available</div>
                        <div class="check-row"><span class="box"></span>Not Available</div>
                    </div>
                </div>

                <div class="approved-block">
                    <div class="section-title">Approved of Installation:</div>
                    <div class="gm">Rolando V. Miranda</div>
                    <div class="gm-position">General Manager</div>
                </div>

                <div class="reading-grid">
                    <div class="line-row"><span class="label">Installed By:</span><span class="line"></span></div>
                    <div class="line-row"><span class="label">Date</span><span class="line"></span></div>
                </div>

                <div class="line-row" style="margin-top: 9px;"><span class="label">Reading</span><span class="line"></span></div>

                <div class="charges">
                    <div class="section-title">Amount of Charges Due:</div>
                    <div class="charge-row"><span>Service Fees</span><span class="amount-line"></span></div>
                    <div class="charge-row"><span>Installation Fees</span><span class="amount-line"></span></div>
                    <div class="charge-row"><span>Meter Accessories</span><span class="amount-line"></span></div>
                    <div class="charge-row"><strong>Total Amount Due</strong><span class="amount-line"></span></div>
                </div>

                <div class="or-grid">
                    <div class="line-row"><span class="label">O.R. No.</span><span class="line"></span></div>
                    <div class="line-row"><span class="label">Date</span><span class="line"></span></div>
                    <div class="line-row"><span class="label">Amount</span><span class="line"></span></div>
                </div>

                <div class="materials">
                    <div class="section-title">Materials Needed</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="width: 22%;">Qty</th>
                                <th style="width: 28%;">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td></td><td></td><td></td></tr>
                            <tr><td></td><td></td><td></td></tr>
                            <tr><td></td><td></td><td></td></tr>
                            <tr><td></td><td></td><td></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
	        </section>

            @if(!($autoPrint ?? true))
                <section class="document-summary" style="margin-top: 16px;">
                    <div class="section-title">Submitted Registration Documents</div>
                    <table>
                        <tbody>
                            <tr>
                                <th>Valid ID of Owner / Picture</th>
                                <td>{{ $application->documents?->valid_id ? 'Uploaded' : 'Missing' }}</td>
                            </tr>
                            <tr>
                                <th>Latest Cedula / Residence Certificate</th>
                                <td>{{ $application->documents?->cedula ? 'Uploaded' : 'Missing' }}</td>
                            </tr>
                            <tr>
                                <th>Proof of Billing</th>
                                <td>{{ $application->documents?->proof_of_billing ? 'Uploaded' : 'Missing' }}</td>
                            </tr>
                            <tr>
                                <th>Authorization Letter / SPA with Valid ID</th>
                                <td>{{ $application->documents?->authorization_letter ? 'Uploaded' : 'N/A' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            @endif
	    </main>

    <script>
        (function () {
            const serverData = @json($printData ?? null);
            const previewData = JSON.parse(localStorage.getItem('srwd_application_print_data') || '{}');
            const data = serverData || previewData;

            document.querySelectorAll('[data-print-value]').forEach(function (node) {
                const value = data[node.dataset.printValue] || '';
                node.textContent = value;
            });

            document.querySelectorAll('[data-radio-value]').forEach(function (node) {
                if (data.application_type === node.dataset.radioValue) {
                    node.textContent = 'X';
                }
            });

            if (@json($autoPrint ?? true)) {
                window.addEventListener('load', function () {
                    setTimeout(function () {
                        window.print();
                    }, 150);
                });
            }
        })();
    </script>
</body>
</html>
