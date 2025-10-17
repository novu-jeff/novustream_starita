<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Print Bill {{$reference_no}}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js" integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq" crossorigin="anonymous"></script>
    @vite(['resources/js/app.js'])
</head>
<body>

{{-- resources/views/payments/receipt_print.blade.php --}}
@extends('layouts.app')

@section('content')
@php
    // Normalize values (safe against arrays)
    $cb = $data['current_bill'] ?? [];

    $amount = (float) ($cb['amount'] ?? 0);

    $arrears = $cb['arrears'] ?? 0;
    if (is_array($arrears)) {
        $arrears = collect($arrears)->sum();
    } elseif (is_string($arrears)) {
        $arrears = (float) $arrears;
    }

    $discount = $cb['discount'] ?? 0;
    if (is_array($discount)) {
        if (isset($discount[0]) && is_array($discount[0]) && isset($discount[0]['amount'])) {
            $discount = collect($discount)->sum('amount');
        } else {
            $discount = collect($discount)->sum();
        }
    } elseif (is_string($discount)) {
        $discount = (float) $discount;
    }

    $assumed_penalty = (float) ($cb['assumed_penalty'] ?? 0);

    $total = round($amount + $arrears + $assumed_penalty - $discount, 2);

    // amount in words: "Two Hundred Fifty One Pesos & 75/100"
    $pesos = intval(floor($total));
    $centavos = intval(round(($total - $pesos) * 100));
    $fmt = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
    $pesos_words = ucfirst($fmt->format($pesos));
    $amount_in_words = "{$pesos_words} Pesos & " . str_pad($centavos, 2, '0', STR_PAD_LEFT) . "/100";

    // other metadata
    $receipt_no = $receipt_no ?? ('436' . str_pad(rand(0,9999), 4, '0', STR_PAD_LEFT)); // sample-style OR no.
    $cashier = auth()->user()->name ?? ($cb['collecting_officer'] ?? 'KRISHA FERNANDEZ');
    $bill_month = !empty($cb['bill_period_from']) ? \Carbon\Carbon::parse($cb['bill_period_from'])->format('M Y') : '';
    $payor = $cb['payor_name'] ?? $cb['payor'] ?? 'N/A';
    $account_no = $cb['account_no'] ?? $cb['account_number'] ?? '011-12-011110';
    $datePaid = $cb['date_paid'] ?? \Carbon\Carbon::now()->format('Y-m-d');
@endphp

<div class="container">
    <div class="print-controls" style="display: grid; grid-template-columns: repeat(2, auto); gap: 12px; margin: 50px auto; width: fit-content;">
        @php
            $previousUrl = url()->previous();
            $currentUrl = url()->current();
            $fallbackUrl = Auth::user()->user_type == 'client' ? route('account-overview.show') : route('reading.index');
            $backUrl = ($previousUrl !== $currentUrl) ? $previousUrl : $fallbackUrl;
        @endphp

        <a href="{{$backUrl}}"
            id="goBackButton"
            style="border: 1px solid #32667e; padding: 12px 40px; text-align:center; text-transform: uppercase; display: flex; align-items: center; gap: 8px; text-decoration: none; color: #32667e; background-color: transparent; border-radius: 5px;">
            <i style="font-size: 15px;" class='bx bx-left-arrow-alt'></i> Go Back
        </a>

        @if(!$isReRead['status'])
            <button
                class="download-js"
                data-target="#bill"
                data-filename="{{$data['current_bill']['reference_no']}}"
                style="background-color: #32667e; color: white; padding: 12px 40px; text-align:center; text-transform: uppercase; display: flex; align-items: center; gap: 8px; border: none; border-radius: 5px; cursor: pointer;">
                <i style="font-size: 15px;" class='bx bxs-download'></i> Download
            </button>
            @if(!$data['current_bill']['isPaid'] == true)
                <button
                    class="reRead"
                    style="text-align: center; background-color: #32667e; color: white; padding: 12px 40px; text-align:center; text-transform: uppercase; display: flex; align-items: center; gap: 8px; border: none; border-radius: 5px; cursor: pointer;">
                    <i style="font-size: 15px;" class='bx bxs-printer'></i> Re Read
                </button>
            @endif
            <button
                class="print-js"
                type="button"
                style="text-align: center; background-color: #32667e; color: white; padding: 12px 40px; display: flex; align-items: center; gap: 8px; border: none; border-radius: 5px; cursor: pointer;">
                <i style="font-size: 15px;" class='bx bxs-printer'></i> Print Bill
            </button>

        @endif
    </div>
    @if($isReRead['status'])
        <div class="d-flex justify-content-center text-center">
            <div style="font-size: 14px;" class="alert alert-danger px-4 text-uppercase fw-bold">
                This bill has been discarded as it has already been re-read. <br>
                Please refer to the re-read bill with reference number:
                <a style="color: inherit;" href="{{ route('reading.show', ['reference_no' => $isReRead['reference_no']]) }}">
                    {{ $isReRead['reference_no'] }}
                </a>.
            </div>
        </div>
    @endif
</div>
<main id="bill" class="d-flex justify-content-center p-2">

  <div class="border border-dark bg-white text-dark p-2" style="width: 10.16cm; height: 21.59cm; font-family: 'Times New Roman', serif; font-size: 11px; position: relative; box-sizing: border-box;">
    {{-- Form Header --}}
    <div class="d-flex justify-content-between mb-1">
      <div>
        <div class="small text-muted" style="font-size: 8px;">ACCOUNTABLE FORM No. 51-C</div>
        <div class="small text-muted" style="font-size: 8px;">Revised January, 1992</div>
      </div>
      <div class="fw-bold" style="font-size: 9px;">(ORIGINAL)</div>
    </div>

    {{-- Main Frame --}}
    <div class="border border-dark p-1" style="height: calc(100% - 28px); box-sizing: border-box;">

      {{-- Top Row --}}
      <div class="d-flex gap-2 align-items-start mb-2">
        <div class="text-center" style="width: 18%;">
          <div class="border border-dark mx-auto" style="width: 46px; height: 46px; background: #f8f8f8;"></div>
          <div class="small mt-1" style="font-size: 8px;">{{ \Carbon\Carbon::now()->format('H:i:s') }}</div>
        </div>

        <div class="text-center" style="width: 44%;">
          <div class="fw-bold" style="font-size: 10px; line-height: 12px;">
            REPUBLIC<br>of the<br>PHILIPPINES
          </div>
        </div>

        <div class="text-end" style="width: 38%;">
          <div class="fw-bold border border-dark d-inline-block px-2 py-1">
            No. <span class="fw-bold" style="font-size: 13px; letter-spacing: 1px;">4364322</span> <span class="suffix">T</span>
          </div>
          <div class="small text-muted mt-1" style="font-size: 8px;">4364322</div>
          <div class="border-top border-dark pt-1 text-end" style="font-size: 9px;">
            Date: <span>{{ \Carbon\Carbon::parse($datePaid)->format('F d, Y') }}</span>
          </div>
        </div>
      </div>

      {{-- Agency and Payor --}}
      <div class="d-flex gap-2 align-items-center mt-1">
        <div class="fw-bold" style="width: 22%; font-size: 10px;">Agency</div>
        <div class="flex-grow-1 border-bottom border-dark px-2" style="font-size: 11px;">SANTA RITA WATER DISTRICT</div>
      </div>
      <div class="d-flex gap-2 align-items-center mt-1">
        <div class="fw-bold" style="width: 22%; font-size: 10px;">Payor</div>
        <div class="flex-grow-1 border-bottom border-dark px-2 text-uppercase" style="font-size: 11px;">{{$data['client']['name']}} | {{$data['client']['account_no'] ?? ''}}</div>
      </div>

      {{-- Table --}}
      <table class="table table-bordered border-dark mt-2 mb-0" style="font-size: 5px;">
        <thead>
          <tr>
            <th style="width: 55%;">Nature of<br>Collection</th>
            <th style="width: 20%;" class="text-center">Account<br>Code</th>
            <th style="width: 25%;" class="text-end">Amount</th>
          </tr>
        </thead>
        <tbody>
          {{-- Always show current bill --}}
          <tr>
            <td>WB {{ $bill_month }}</td>
            <td></td>
            <td class="text-end">₱ {{ number_format($amount, 2) }}</td>
          </tr>

          {{-- Conditionally show Arrears and Penalty --}}
          @if($arrears > 0)
            <tr>
              <td>Arrears</td>
              <td></td>
              <td class="text-end">₱ {{ number_format($arrears, 2) }}</td>
            </tr>
          @endif

          @if($assumed_penalty > 0)
            <tr>
              <td>Penalty</td>
              <td></td>
              <td class="text-end">₱ {{ number_format($assumed_penalty, 2) }}</td>
            </tr>
          @endif

          {{-- Blank rows --}}
          @php
            $filled = 1 + ($arrears > 0 ? 1 : 0) + ($assumed_penalty > 0 ? 1 : 0);
            $blank = 6 - $filled;
          @endphp
          @for($i = 0; $i < $blank; $i++)
            <tr>
              <td>&nbsp;</td>
              <td></td>
              <td></td>
            </tr>
          @endfor

          {{-- Discount and Total --}}
          <tr>
            <td>Less: Senior Discount</td>
            <td></td>
            <td class="text-end">₱ {{ number_format($discount, 2) }}</td>
          </tr>
          <tr class="fw-bold">
            <td>TOTAL</td>
            <td></td>
            <td class="text-end">₱ {{ number_format($total, 2) }}</td>
          </tr>
        </tbody>
      </table>

      {{-- Amount in words --}}
      <div class="mt-2 border-top border-dark pt-1">
        <div class="fw-bold" style="font-size: 10px;">Amount in Words</div>
        <div class="border border-dark p-1 fst-italic text-center" style="font-size: 10px; min-height: 20px;">
          {{ $amount_in_words }}
        </div>
      </div>

      {{-- Payment methods --}}
      <div class="mt-2">
        <div class="d-flex gap-3">
          <label class="form-check-label d-flex align-items-center gap-1" style="font-size: 10px;">
            <input type="checkbox" disabled> Cash
          </label>
          <label class="form-check-label d-flex align-items-center gap-1" style="font-size: 10px;">
            <input type="checkbox" disabled> Check
          </label>
          <label class="form-check-label d-flex align-items-center gap-1" style="font-size: 10px;">
            <input type="checkbox" disabled> Money Order
          </label>
        </div>

        <div class="d-flex gap-2 mt-2">
          <div class="flex-fill">
            <div style="font-size: 9px;">Drawee Bank</div>
            <div class="border-bottom border-dark" style="height: 18px;"></div>
          </div>
          <div class="flex-fill">
            <div style="font-size: 9px;">Number</div>
            <div class="border-bottom border-dark" style="height: 18px;"></div>
          </div>
          <div class="flex-fill">
            <div style="font-size: 9px;">Date</div>
            <div class="border-bottom border-dark" style="height: 18px;"></div>
          </div>
        </div>
      </div>

      <div class="mt-2" style="font-size: 10px;">Received the amount stated above.</div>

      {{-- Signature --}}
      <div class="d-flex justify-content-end align-items-center mt-2">
        <div class="text-center" style="width: 60%;">
          <div class="border-bottom border-dark pt-1 fw-bold">{{ strtoupper($cashier) }}</div>
          <div style="font-size: 9px;">Collecting Officer</div>
        </div>
      </div>

      <div class="border-top border-dark pt-1 mt-2" style="font-size: 9px;">
        NOTE: Write the number and date of this receipt on the back of check or money order received.
      </div>
    </div>
  </div>
</main>


<style>

        body * {
            font-family: Verdana, Geneva, Tahoma, sans-serif;
        }

        .print-controls {
            a, button {
                font-size: 14px !important;
                font-weight: 600;
            }
        }

        #bill {
            font-size: 13px;
        }

        @import url("https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap");

        .web-logo {
            width: 90px;
            margin: 0 auto 10px auto !important;
        }

        .print-logo {
            display: none !important;
        }

        .emp {
            background-color: #000;
            padding: 8px 10px 8px 10px;
            color: #fff;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 20px !important;
        }

        @media print {

            @page {
                margin: 0mm 5mm 10mm 0mm;
            }

            .oversized div {
                font-size: 15px !important;
                font-weight: 800 !important;
            }

            .oversized-2 ul li {
                font-size: 10px !important;
                font-weight: 800 !important;
            }

            .emp {
                background-color: #000;
                padding: 8px 10px 8px 10px;
                margin-bottom: 100px;
                color: #000;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }

            body * {
                padding: 0px !important;
                box-shadow: none !important;
                visibility: visible !important;
                font-size: 10px !important;
                font-weight: 600;
                font-family: monospace;
            }

            header, .print-controls {
                display: none !important;
            }

            .isPaid {
                display: none;
                visibility: hidden;
            }

            svg {
                width: 80px !important;
            }

            .web-logo {
                display: none !important;
            }

            .print-logo {
                width: 100px;
                margin: 0 auto 10px auto !important;
                display: block !important;
            }

        }
    </style>
    <script>
        $(function () {
            @if (session('alert'))
                setTimeout(() => {
                    let alertData = @json(session('alert'));
                    alert(alertData.status, alertData.message);
                }, 100);
            @endif

            const reference_no = '{{$reference_no}}';

            if (window.opener && window.opener !== window) {
                $('#goBackButton').on('click', function (e) {
                    e.preventDefault();
                    window.close();
                });
            }

            let selectedBillId = null;

            $(document).on('click', '.reRead', function() {
                selectedBillId = $(this).data('bill-id');
                $('#reReadModal').modal('show');
            });

            $('#confirmReRead').on('click', function() {
                $('#reReadModal').modal('hide');
                const redirect = `{{ route('reading.index') }}?re-read=true&reference_no=${encodeURIComponent(reference_no)}`;
                console.log(redirect);
                setTimeout(() => {
                    window.location.href = redirect;
                }, 1000);
            });
        });
    </script>
@endsection
</body>
