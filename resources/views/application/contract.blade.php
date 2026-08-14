<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contract for Water Service Connection</title>
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
            width: 8.5in;
            height: 13in;
            margin: 14px auto;
            padding: 7mm 12mm;
            background: #fff;
            border: 1px solid #222;
            overflow: hidden;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .header h1 {
            font-size: 20px;
            font-weight: 700;
            letter-spacing: 0.3px;
            margin-bottom: 0;
        }

        .header .address {
            margin-bottom: 3rem;
            font-size: 11px;
            font-weight: 400;
        }

        .header h2 {
            margin: 6px 0 3rem;
            font-size: 13px;
            font-weight: 700;
            text-decoration: underline;
        }

        .line {
            display: inline-block;
            min-width: 40mm;
            border-bottom: 1px solid #000;
            padding: 0 3px 1px;
        }

        .line.short {
            min-width: 20mm;
        }

        .line.tiny {
            min-width: 10mm;
        }

        .line.full {
            display: block;
            width: 100%;
        }

        p.body-text {
            font-size: 10px;
            line-height: 1.3;
            text-align: justify;
            margin: 0 0 5px;
        }

        .intro-caps {
            font-weight: 700;
            font-size: 12px;
            margin: 5px 0 10px;
            letter-spacing: 0.4px;
            text-transform: uppercase;
        }

        .witnesseth {
            font-weight: 700;
            text-align: center;
            font-size: 10px;
            margin: 2px 0 4px;
        }

        ol.terms {
            font-size: 10px;
            line-height: 1.3;
            text-align: justify;
            margin: 2px 0 6px;
            padding-left: 16px;
        }

        ol.terms li {
            margin-bottom: 3px;
        }

        ol.terms ul {
            list-style: lower-alpha;
            margin: 4px 0;
            padding-left: 18px;
        }

        .fee-table {
            width: 58mm;
            margin: 2px 0 2px 10rem;
            font-size: 10px;
            align-items: end;
        }

        .fee-table .fee-row {
            display: block;
            justify-content: space-between;
            align-items: end;
            margin-bottom: 2px;
        }

        .fee-table .fee-row > span {
            display: block;
        }

        .fee-table .fee-row > span:last-child {
            margin-top: 2px;
        }

        .fee-table .fee-row .line {
            min-width: 35mm;
        }

        .fee-table .grand-total {
            font-weight: 700;
            padding-top: 5px;
        }

        .witness-clause {
            font-size: 10px;
            text-align: center;
            margin: 6px 0 6px;
        }

        .signature-block {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 14mm;
            font-size: 10px;
            margin-top: 7rem;
        }

        .party-title {
            font-weight: 700;
            text-align: center;
            margin-bottom: 12px;
        }

        .sig-name {
            text-align: center;
            border-bottom: 1px solid #000;
            min-height: 10px;
            padding-bottom: 2px;
            font-weight: 700;
        }

        .sig-caption {
            text-align: center;
            font-size: 8.5px;
            margin-top: 2px;
            margin-bottom: 4px;
        }

        .cert-grid {
            display: grid;
            grid-template-columns: auto 1fr;
            column-gap: 6px;
            row-gap: 1px;
            font-size: 9px;
        }

        .signed-presence {
            text-align: center;
            font-size: 10px;
            margin-top: 5rem;
            margin-bottom: 30px;
        }

        .signed-presence .line {
            display: block;
            margin: 20px auto 0;
            width: 70mm;
        }

        .ack-title {
            text-align: center;
            font-weight: 700;
            font-size: 11px;
            text-decoration: underline;
            margin: 6px 0 3rem;
            text-transform: uppercase;
        }

        .ack-venue {
            font-size: 10px;
            display: flex;
            justify-content: space-between;
            max-width: 90mm;
            margin-bottom: 4px;
        }

        .ack-venue .brace {
            padding-left: 20mm;
        }

        .doc-details {
            font-size: 10px;
            margin-top: 15px;
        }

        .doc-details .cert-grid {
            max-width: 70mm;
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

        @media print {
            @page {
                size: 8.5in 13in; /* short bond paper */
                margin: 0;
            }

            html,
            body {
                background: #fff;
            }

            .application-paper {
                width: 8.5in;
                height: 13in;
                margin: 0;
                padding: 7mm 12mm;
                border: none;
                overflow: hidden;
            }

            .review-toolbar {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="review-toolbar">
        <a href="{{ route('account-overview.index') }}" class="btn btn-primary fw-bold text-uppercase">Back to Overview</a>
        <a href="{{ route('application.create') }}" class="btn btn-primary fw-bold text-uppercase">Review Form</a>
        <button type="button" onclick="printBlankForm()" class="btn btn-outline-primary fw-bold text-uppercase">Print Blank Form</button>
        <button type="button" onclick="window.print()" class="btn btn-primary fw-bold text-uppercase">Print</button>
    </div>

    <main class="application-paper">
        <header class="header">
            <h1>STA. RITA WATER DISTRICT</h1>
            <div class="address">Zone 6 Brgy. Dila-Dila, Sta. Rita, Pampanga</div>
            <h2>CONTRACT FOR WATER SERVICE CONNECTION</h2>
        </header>

        <div class="intro-caps">Know All Men By These Presents:</div>

        <p class="body-text">
            This Contract, made and executed by and between <strong>STA. RITA WATER DISTRICT</strong>,
            with postal address at Zone 6 Brgy. Dila-Dila, Sta. Rita, Pampanga, hereinafter referred to
            as the FIRST PARTY:
        </p>

        <p class="body-text" style="text-align:center; margin: 6px 0;">-and-</p>

        <p class="body-text">
            Mr./Mrs./Ms. <span class="line" style="min-width: 50mm;" data-print-value="applicant_name"></span>
            Filipino, of legal age, single/married and residing at
            <span class="line" style="min-width: 60mm;" data-print-value="service_address"></span>,
            hereinafter referred to as the SECOND PARTY:
        </p>

        <div class="witnesseth">Witnesseth:</div>

        <p class="body-text">
            WHEREAS, the SECOND PARTY is desirous of providing his/her premises with water services and
            the FIRST PARTY is willing to facilitate the same by extending him/her such water meter, its
            accessories and attendant services, and in the manner of providing his/her premises, its
            accessories and attendant services, the FIRST PARTY is subject to the following terms and
            conditions:
        </p>

        <ol class="terms">
            <li>
                That the SECOND PARTY shall pay the following:
                <div class="fee-table">
                    <div class="fee-row">
                        <span>a. Service Connection Fee</span>
                        <span style="padding-left: 4rem;"> P <span class="line short"></span></span>
                    </div>
                    <div class="fee-row">
                        <span>b. Others <span class="line short"></span></span>
                    </div>
                    <div class="fee-row grand-total">
                        <span>Grand Total <span class="line short"></span></span>
                    </div>
                    <div class="fee-row">
                        <span>OR No./Date <span class="line short"></span></span>
                    </div>
                </div>
            </li>
            <li>
                That the FIRST PARTY shall have the exclusive authority to select the meter
                location/relocation, which should be outside the SECOND PARTY perimeter fence
                approximately fifteen (15) meters away from the water mains, for easy access when
                conducting meter reading and routinary maintenance.
            </li>
            <li>
                That the SECOND PARTY shall allow any representative of the FIRST PARTY to enter the
                former's premises without being liable of trespassing, for the purpose of meter reading,
                inspection of water pipes and fixtures to determine the existence of leakages and
                defects, calibration of the water meter, and disconnecting service if no payment was
                made, and all other related undertaking pursuant to the performance of the FIRST
                PARTY representative's duty in connection with the water service connection.
            </li>
            <li>
                That the SECOND PARTY shall pay the billed water bills on or before its due date.
                Delinquent bills unpaid for 5 days after due date will be subject to disconnection. Also,
                the FIRST PARTY by virtue of this contract is hereby given the right to disconnect the
                water service connection even without prior notice.
            </li>
            <li>
                That the FIRST PARTY cannot be held liable if the water meter will be found tampered,
                seal destroyed, obstructed and enclosed such that it cannot be readily read or inspected,
                lost, or intentionally damaged or rendered non-functional due to the act of the SECOND
                PARTY or by any known or unknown third person. In events of stoppage, or failure of the
                water meter to register the full amount of water consumed, the SECOND PARTY's latest
                three (3) months average consumption shall be applied.
            </li>
            <li>
                That the selling of water under this contract is strictly prohibited and the SECOND
                PARTY is not allowed to extend or transfer water services to another party or property
                without prior approval by the FIRST PARTY.
            </li>
            <li>
                That the SECOND PARTY shall strictly observe and abide by the rules, regulations, and
                approved policies imposed and to be implemented by the FIRST PARTY.
            </li>
            <li>
                Non-compliance with or violation of any terms and conditions of this contract by the
                SECOND PARTY shall entitle the FIRST PARTY to terminate this contract and/or
                disconnect the water service connection without prior notice.
            </li>
        </ol>

        <p class="witness-clause">
            IN WITNESS WHEREOF, the parties have affixed their respective signatures this
            <span class="line tiny"></span> day of <span class="line short"></span>, <span class="line tiny"></span>.
        </p>

        <div class="signature-block">
            <div>
                <div class="party-title">FIRST PARTY (STA. RITA WD)</div>
                <div class="sig-name">Rolando V. Miranda</div>
                <div class="sig-caption">General Manager</div>

                <div class="cert-grid">
                    <span>Res. Cert. No.</span><span>: 29071800</span>
                    <span>Issued at</span><span>: Sta. Rita, Pampanga</span>
                    <span>Issued on</span><span>: Jan 07, 2014</span>
                </div>
            </div>

            <div>
                <div class="party-title">SECOND PARTY (APPLICANT)</div>
                <div class="sig-name" data-print-value="applicant_name"></div>
                <div class="sig-caption">Signature of Applicant over printed name</div>

                <div class="cert-grid">
                    <span>Res. Cert. No.</span><span>: <span class="line short"></span></span>
                    <span>Issued at</span><span>: <span class="line short"></span></span>
                    <span>Issued on</span><span>: <span class="line short"></span></span>
                </div>
            </div>
        </div>

        <div class="signed-presence">
            Signed in the presence of:
            <span class="line"></span>
        </div>

        <div class="ack-title">Acknowledgment</div>

        <div class="ack-venue">
            <span>REPUBLIC OF THE PHILIPPINES</span>
            <span class="brace">)</span>
        </div>
        <div class="ack-venue" style="margin-top: -4px;">
            <span>QUEZON CITY</span>
            <span class="brace">) S.S.</span>
        </div>

        <p class="body-text" style="margin-top: 20px;">
            Before me personally appeared <span class="line" style="min-width: 45mm;"></span> and
            <span class="line" style="min-width: 45mm;"></span> with Residence Certificate Number under
            their respective names indicated above, both known to me and to me known to be the same
            persons who executed the foregoing instrument and they acknowledge to me the same as their
            own free act and deed.
        </p>

        <p class="body-text" style="margin-top: 20px;">
            WITNESS MY HAND AND SEAL on this <span class="line tiny"></span> day of
            <span class="line short"></span>, <span class="line tiny"></span> at the place first above
            written.
        </p>

        <div class="doc-details">
            <div class="cert-grid">
                <span>Doc. No.</span><span>: <span class="line short"></span></span>
                <span>Book No.</span><span>: <span class="line short"></span></span>
                <span>Page No.</span><span>: <span class="line short"></span></span>
                <span>Series of</span><span>: <span class="line short"></span></span>
            </div>
        </div>
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

            if (@json($autoPrint ?? true)) {
                window.addEventListener('load', function () {
                    setTimeout(function () {
                        window.print();
                    }, 150);
                });
            }
        })();

        (function () {
            const serverData = @json($printData ?? null);
            const previewData = JSON.parse(localStorage.getItem('srwd_application_print_data') || '{}');
            const data = serverData || previewData;

            function fillValues() {
                document.querySelectorAll('[data-print-value]').forEach(function (node) {
                    const value = data[node.dataset.printValue] || '';
                    node.textContent = value;
                });
            }

            function clearValues() {
                document.querySelectorAll('[data-print-value]').forEach(function (node) {
                    node.textContent = '';
                });
            }

            // Fill in on load, as before
            fillValues();

            // Expose a global function for the "Print Blank Form" button
            window.printBlankForm = function () {
                clearValues();
                window.print();
            };

            // Restore the filled-in values after any print dialog closes
            window.addEventListener('afterprint', fillValues);

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
