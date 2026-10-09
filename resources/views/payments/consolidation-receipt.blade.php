<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>OR {{ $receipt->reference_no }}</title>
<style>
    @page { size: 4in 8.5in; margin: 0; }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        color: #111;
        font-family: Arial, sans-serif;
        font-size: 8pt;
    }

    .print-controls {
        display: flex;
        justify-content: center;
        gap: 8px;
        margin: 12px auto;
    }

    .print-controls button, .print-controls a {
        padding: 7px 12px;
        border: 0;
        background: #32667e;
        color: white;
        font: bold 12px Arial, sans-serif;
        text-decoration: none;
        cursor: pointer;
    }

    .receipt-sheet {
        width: 4in;
        min-height: 0;
        margin: 0 auto;
        padding: .16in;
        background: white;
    }

    .receipt-header {
        text-align: center;
        padding: 6px 3px;
        line-height: 1.3;
    }

    p { margin: 3px 0; }

    .district-name {
        font-size: 10pt;
        font-weight: bold;
        text-transform: uppercase;
    }

    .receipt-title {
        margin-top: 4px;
        font-size: 10pt;
        font-weight: bold;
        text-transform: uppercase;
    }

    .details {
        margin-top: 7px;
    }

    .field-row {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 6px;
        margin: 4px 0;
    }

    .field-row strong { white-space: nowrap; }
    .field-row span { text-align: right; overflow-wrap: anywhere; }

    .payor-name {
        font-weight: bold;
        text-transform: uppercase;
        overflow-wrap: anywhere;
    }

    .rule {
        border-top: 1px solid #111;
        margin: 6px 0;
    }

    .collection-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 7px;
    }

    .collection-table th,
    .collection-table td {
        border: 1px solid #111;
        padding: 5px 4px;
    }

    .collection-table th { text-align: left; }
    .collection-table th:last-child,
    .collection-table td:last-child {
        text-align: right;
        white-space: nowrap;
    }

    .total-row { font-weight: bold; }

    .amount-words {
        margin-top: 7px;
        padding: 6px;
        border: 1px solid #111;
        text-align: center;
        font-style: italic;
        overflow-wrap: anywhere;
    }

    .payment-note {
        margin-top: 7px;
        line-height: 1.4;
    }

    .signature-row {
        display: flex;
        gap: 12px;
        margin-top: 30px;
    }

    .signature {
        width: 50%;
        min-width: 0;
        text-align: center;
        font-size: 7pt;
    }

    .signature-name {
        min-height: 12px;
        font-weight: bold;
        text-transform: uppercase;
        overflow-wrap: anywhere;
    }

    .signature-line {
        border-bottom: 1px solid #111;
        margin: 3px 0;
    }

    @media screen {
        body {
            min-height: 100vh;
            padding: 1px 0 20px;
            background: #e9ecef;
        }

        .receipt-sheet {
            box-shadow: 0 2px 12px #0002;
        }
    }

    @media print {
        html, body {
            width: 4in;
            margin: 0;
            padding: 0;
            background: white;
        }

        .print-controls { display: none !important; }

        .receipt-sheet {
            margin: 0;
            box-shadow: none;
        }
    }
</style>
</head>
<body>

<nav class="print-controls">
    <button type="button" onclick="window.print()">Print OR</button>
    <a href="{{ route('payments.consolidation.index') }}">Back</a>
</nav>

@php
    $paidDate = 'N/A';

    if (!empty($receipt->date_paid)) {
        try {
            $paidDate = \Carbon\Carbon::parse($receipt->date_paid)
                ->format('M d, Y');
        } catch (\Throwable $exception) {
            $paidDate = $receipt->date_paid;
        }
    }

    $amountInWords = \App\Helper\NumberHelper::convertToWords($systemFee);
@endphp

<main class="receipt-sheet">

    <header class="receipt-header">
        <p>Republic of the Philippines</p>
        <p class="district-name">Sta. Rita Water District</p>
        <p>Zone 6 Dila-Dila, Santa Rita, Pampanga</p>
        <p class="receipt-title">Acknowledgment Receipt</p>
    </header>

    <section class="details">
        <div class="field-row">
            <strong>Reference No.:</strong>
            <span>{{ $receipt->reference_no }}</span>
        </div>

        <div class="field-row">
            <strong>Date:</strong>
            <span>{{ $paidDate }}</span>
        </div>

        <div class="rule"></div>

        <div class="field-row">
            <strong>Payor:</strong>
            <span>{{ $receipt->name ?? 'N/A' }}</span>
        </div>

        <div class="field-row">
            <strong>Account No.:</strong>
            <span>{{ $receipt->account_no ?? 'N/A' }}</span>
        </div>
    </section>

    <table class="collection-table">
        <thead>
            <tr>
                <th>Nature of Collection</th>
                <th>Amount (PHP)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>System Transaction Fee</td>
                <td>{{ number_format($systemFee, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL</td>
                <td>₱ {{ number_format($systemFee, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="payment-note">
        <p><strong>Payment Method:</strong> Cash</p>
        <p>Received the amount stated above.</p>
    </div>

    <div class="signature-row">
        <div class="signature">
            <div class="signature-name">{{ $cashier }}</div>
            <div class="signature-line"></div>
            Cashier
        </div>

        <div class="signature">
            <div class="signature-name">{{ $receipt->name ?? 'N/A' }}</div>
            <div class="signature-line"></div>
            Payor
        </div>
    </div>

</main>
</body>
</html>