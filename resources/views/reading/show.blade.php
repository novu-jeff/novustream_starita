<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Print Bill {{ $reference_no }}</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">

  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>

  @vite(['resources/js/app.js'])

  <style>
    /* Page fixed size for 4" x 8.5" (10.16cm x 21.59cm) */
    .receipt-sheet {
      width: 10.16cm;
      height: 21.59cm;
      box-sizing: border-box;
      background: white;
      font-family: "Times New Roman", serif;
      color: #000;
      position: relative;
      margin: 0 auto;
    }

    /* Outer thin page border when viewing (optional) */
    .page-wrap { padding: 6px; }

    /* print rules: show only the visible printable block */
    @media print {
      html, body { height: 100%; margin: 0; padding: 0; }
      body * { visibility: hidden !important; }
      .printable, .printable * { visibility: visible !important; }
      .printable { position: absolute; top: 0; left: 0; margin: 0; }
    }

    /* On-screen */
    .controls { margin: 20px auto; text-align: center; }
    .small-note { font-size: 8px; color: #333; }

    /* Header container */
    .or-header {
      border: 1px solid #000;      /* single solid border as requested */
      padding: 6px;
      box-sizing: border-box;
      display: flex;
      gap: 8px;
      align-items: flex-start;
      height: 3.5cm;               /* fixed header height */
    }

    .or-logo {
      width: 18%;
      min-width: 1.7cm;
      text-align: center;
    }
    .or-logo .crest-box {
      width: 100px;
      height: 120px;
      border: 1px solid #000;
      background: #f8f8f8;
      margin: 0 auto;
      padding-top: 6px;
      box-sizing: border-box;
    }
    .or-center {
      width: 44%;
      text-align: center;
      font-weight: 700;
      font-size: 12px;
      line-height: 1.1;
      display:flex;
      align-items:center;
      justify-content:center;
      flex-direction:column;
      gap:2px;
    }
    .or-right {
      width: 38%;
      text-align: right;
      box-sizing:border-box;
    }
    .or-right .or-no {
      display:inline-block;
      border:1px solid #000;
      padding:6px 8px;
      font-weight:700;
    }

    /* small helper text */
    .muted-small { font-size: 8px; color: #666; }

    /* Section rows */
    .row-label {
      width: 22%;
      font-weight:700;
      font-size:10px;
    }
    .underline-field {
      border-bottom: 1px solid #000;
      padding: 6px 8px;
      font-size:11px;
    }

    /* table style */
    .or-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 10px;
      margin-top: 6px;
    }
    .or-table thead th {
      border:1px solid #000;
      padding:6px;
      vertical-align: middle;
    }
    .or-table tbody td {
      border-left:1px solid #000;
      border-right:1px solid #000;
      padding:6px;
      vertical-align: middle;
    }
    .or-table tbody tr:last-child td { border-bottom:1px solid #000; }
    /* make right-most column show right-aligned numbers */
    .amount-col { text-align: right; }

    /* Amount in words box */
    .amount-words {
      border:1px solid #000;
      padding:8px;
      font-style: italic;
      text-align:center;
      margin-top:8px;
      font-size:10px;
      min-height: 2.0cm;
      box-sizing: border-box;
    }

    /* Payment & drawee row */
    .payment-row { margin-top:8px; font-size:10px; }
    .drawee-box { border-bottom: 1px solid #000; height:18px; }

    /* signature area */
    .signature-area { margin-top:18px; display:flex; justify-content:flex-end; }
    .sig-box { width:60%; text-align:center; }

    /* overlay preview background (on-screen only) */
    .overlay-preview {
      background-repeat: no-repeat;
      background-position: center top;
      background-size: contain;
      opacity: 0.92;
    }
    @media print {
      .overlay-preview { background: transparent !important; opacity: 1 !important; }
    }
  </style>
</head>

<body>
@extends('layouts.app')
@section('content')

@php
    // --- Normalize and compute values ---
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

    $penalty = (float) ($cb['penalty'] ?? 0);
    $total = round($amount + $arrears + $penalty - $discount, 2);

    $pesos = intval(floor($total));
    $centavos = intval(round(($total - $pesos) * 100));
    $fmt = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
    $pesos_words = ucfirst($fmt->format($pesos));
    $amount_in_words = "{$pesos_words} Pesos & " . str_pad($centavos, 2, '0', STR_PAD_LEFT) . "/100";

    $receipt_no = $receipt_no ?? ('436' . str_pad(rand(0,9999), 4, '0', STR_PAD_LEFT));
    $cashier = auth()->user()->name ?? ($cb['collecting_officer'] ?? 'NA');
    $bill_month = !empty($cb['bill_period_from']) ? \Carbon\Carbon::parse($cb['bill_period_from'])->format('M Y') : '';
    $account_no = $cb['account_no'] ?? $cb['account_number'] ?? '011-12-011110';
    $datePaid = $cb['date_paid'] ?? \Carbon\Carbon::now()->format('Y-m-d');

    // prepare preview background (if user placed scanned image in public/images/receipt-scan.png)
    $previewBg = null;
    $previewPath = public_path('images/receipt-scan.png');
    if (file_exists($previewPath)) {
        $dataUri = base64_encode(file_get_contents($previewPath));
        $mime = mime_content_type($previewPath) ?: 'image/png';
        $previewBg = "data:{$mime};base64,{$dataUri}";
    }
@endphp

<div class="print-controls" style="display: grid; grid-template-columns: repeat(2, auto); gap: 12px; margin: 50px auto; width: fit-content;">
        @php
            $previousUrl = url()->previous();
            $currentUrl = url()->current();
            $fallbackUrl = Auth::user()->user_type == 'client' ? route('account-overview.show') : route('reading.index');
            $backUrl = ($previousUrl !== $currentUrl) ? $previousUrl : $fallbackUrl;
            $logoPath = public_path('images/client.png');
            $base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
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
        @endif
        <div style="padding-bottom: 50px">
            <div id="bill" style="margin-top: 30px">
                <div class="bill-container">
                    <div style="position: relative; width: 100%; max-width: 450px; margin: 0 auto; padding: 25px; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                        @if($data['current_bill']['isPaid'] == true)
                            <div class="isPaid" style="padding: 10px 30px 10px 30px; position: absolute; right: -10px; top: 4px; text-transform: uppercase; color: red; letter-spacing: 3px; font-size: 12px; font-weight: 600">
                                PAID
                            </div>
                        @endif
                        @php
                            $logoPath = public_path('images/client.png');

                            $base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
                        @endphp

                        <div style="text-align: center; margin-top: 18px; margin-bottom: 10px; padding-bottom: 10px; display: flex; justify-content: center; align-items: center; gap: 15px;">
                            <div>
                                <img src="{{ asset('images/client.png')}}"
                                    alt="logo" class="web-logo">
                                <img src="{{ $base64 }}" alt="logo" class="print-logo">
                            </div>
                            <div style="width: fit-content;">
                                <p style="font-size: 11px; text-transform: uppercase; margin: 0; font-weight: 600">Republic of the Philippines</p>
                                <p style="font-size: 15px; text-transform: uppercase; margin: 0; text-transform: uppercase; font-weight: 600">Sta. Rita Water District</p>
                                <p style="font-size: 12px; text-transform: uppercase; margin: 3px 0 0 0;">Zone 6 Dila-Dila, Santa Rita, Pampanga</p>
                                <p style="font-size: 12px; text-transform: uppercase; margin: 0;">Facebook Page: Sta. Rita Water District</p>
                                <p style="font-size: 12px; text-transform: uppercase; margin: 0;">Cell No. 0917-103-2421 | 0917-104-7196</p>
                                <p style="font-size: 12px; text-transform: uppercase; margin: 0;">TIN 261-304-832-000 Non VAT</p>
                            </div>
                        </div>
                        <div style="text-align:center; text-transform: uppercase; font-size: 16px; margin: 10px 0 10px 0;">
                            <p style="font-size: 22px; text-transform: uppercase; margin: 0; text-transform: uppercase; font-weight: 600">Statement of Account</p>
                        </div>
                        <div style="width: 100%; height: 1px; margin: 10px 0 10px 0; border-bottom: 1px dashed black;"></div>
                        <div>
                            <div style="font-size: 10px; text-transform: uppercase; display: flex; flex-direction: column; gap: 1px;">
                                <div class="oversized" style="margin: 4px 0 0 0; display: flex; gap: 5px; align-items: center;">
                                    <div style="font-size: 20px; font-weight: 600">Account No. </div>
                                    <div style="font-size: 20px; font-weight: 600">{{$data['client']['account_no'] ?? ''}}</div>
                                </div>
                                <div class="oversized" style="margin: 4px 0 0 0; display: flex; align-items: center;">
                                    <div style="font-size: 20px; font-weight: 600">{{$data['client']['name']}}</div>
                                </div>
                                <div style="margin: 4px 0 0 0; display: flex;">
                                    <div style="font-size: 15px;">{{$data['client']['address'] ?? ''}}</div>
                                </div>
                                <div style="margin: 4px 0 0 0; display: flex; gap: 10px;">
                                    <div style="font-size: 18px;">Meter No: </div>
                                    <div style="font-size: 18px;">{{$data['client']['meter_serial_no']}}</div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div style="width: 100%; height: 1px; margin: 15px 0 10px 0; border-bottom: 1px dashed black; position: relative; display: flex; justify-content: center; align-items: center;">
                                <h6 style="font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 0px; margin-top: 10px; position: absolute; top: -17px; background-color: #fff; padding: 0 10px 0 10px;">Current Billing Info</h6>
                            </div>
                            <div style="text-align: center; text-transform: uppercase;">
                                <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                    <div>Bill Date</div>
                                    <div>11/04/2025</div>
                                </div>
                                    @php
                                        use Carbon\Carbon;

                                        $zone = $data['current_bill']['reading']['zone'] ?? null;
                                        $zoneCode = substr($zone, 0, 3);

                                        $books = [
                                            '061' => ['from' => '2025-10-03', 'to' => '2025-11-05'],
                                            '071' => ['from' => '2025-10-03', 'to' => '2025-11-05'],
                                            '081' => ['from' => '2025-10-03', 'to' => '2025-11-05'],
                                            '091' => ['from' => '2025-10-06', 'to' => '2025-11-06'],
                                            '101' => ['from' => '2025-10-06', 'to' => '2025-11-06'],
                                            '111' => ['from' => '2025-10-06', 'to' => '2025-11-06'],
                                        ];

                                        $date = '';
                                        $due_date = '';
                                        $disconnection_date = '';

                                        // ✅ Determine date range
                                        if (array_key_exists($zoneCode, $books)) {
                                            $from = Carbon::parse($books[$zoneCode]['from'])->format('m/d/Y');
                                            $to   = Carbon::parse($books[$zoneCode]['to'])->format('m/d/Y');
                                            $date = "$from TO $to";
                                        } else {
                                            $date = '10/04/2025 TO 11/05/2025';
                                        }

                                        // ✅ Assign due and disconnection dates based on zone group
                                        if (in_array($zoneCode, ['061', '071', '081'])) {
                                            $due_date = Carbon::parse('2025-11-19')->format('m/d/Y');
                                            $disconnection_date = Carbon::parse('2025-11-26')->format('m/d/Y');
                                        } elseif (in_array($zoneCode, ['091', '101', '111'])) {
                                            $due_date = Carbon::parse('2025-11-20')->format('m/d/Y');
                                            $disconnection_date = Carbon::parse('2025-11-27')->format('m/d/Y');
                                        } else {
                                            $due_date = Carbon::parse('2025-11-21')->format('m/d/Y');
                                            $disconnection_date = Carbon::parse('2025-11-28')->format('m/d/Y');
                                        }
                                    @endphp

                                <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                    <div>Period</div>
                                    <div>{{$date}}</div>
                                </div>
                                <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                    <div>Due Date</div>
                                    <div>{{$due_date}}</div>
                                </div>
                                <!-- <div class="oversized-2" style="text-align: center; margin: 10px 0 10px 0; font-size: 10px; font-weight: 800; font-style: italic; color:rgb(91, 91, 91)">
                                    <ul style="list-style: none !important">
                                        <li>> Office - Last working day of the month</li>
                                        <li>> Online - Last day of the month</li>
                                    </ul>
                                </div> -->
                                <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                    <div>Disconnection Date</div>
                                    <div>{{$disconnection_date}}</div>
                                </div>
                            </div>
                        </div>
                        <div style="width: 100%; height: 1px; margin: 10px 0 10px 0; border-bottom: 1px dashed black;"></div>
                        <div>
                            <div style="display: flex; justify-content: space-between;">
                                <div style="text-transform: uppercase">Previous Reading</div>
                                <div style="text-transform: uppercase">{{$data['current_bill']['reading']['previous_reading'] ?? 'N/A'}}</div>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <div style="text-transform: uppercase">Present Reading</div>
                                <div style="text-transform: uppercase">{{$data['current_bill']['reading']['present_reading'] ?? '0'}}</div>
                            </div>
                            <div class="oversized" style="display: flex; justify-content: space-between; margin-top: 5px">
                                <div style="font-size: 20px; font-weight: 800; text-transform: uppercase">Cub. M Used</div>
                                <div style="font-size: 20px; font-weight: 800; text-transform: uppercase">{{$data['current_bill']['reading']['consumption'] ?? '0'}}</div>
                            </div>
                        </div>
                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                        <div>
                            @php
                                $breakdown = collect($data['current_bill']['breakdown']);
                                $arrears = $breakdown->firstWhere('name', 'Previous Balance')['amount'] ?? 0;
                                $deductions = $breakdown->reject(fn($item) => $item['name'] === 'Previous Balance')->values();
                            @endphp

                            @forelse($deductions as $deduction)
                                @php
                                    if (strtolower($deduction['name']) === 'system fee') {
                                        continue;
                                    }
                                @endphp
                                <div style="display: flex; justify-content: space-between;">
                                    <div style="text-transform: uppercase">{{$deduction['name']}}</div>
                                    <div style="text-transform: uppercase">{{$deduction['amount']}}</div>
                                </div>
                            @empty

                            @endforelse

                            @php
                                $discounts = $data['current_bill']['discount'];
                                $totalDiscount = collect($discounts)->sum('amount');
                            @endphp

                            @forelse($discounts as $discount)

                                <div style="display: flex; justify-content: space-between;">
                                    <div style="text-transform: uppercase">{{$discount['name']}}</div>
                                    <div style="text-transform: uppercase">- ({{$discount['amount']}})</div>
                                </div>
                            @empty

                            @endforelse
                            @if(!empty($data['current_bill']['advances']))
                                <div style="display: flex; justify-content: space-between; margin: 5px 0 5px 0;">
                                    <div>Advances</div>
                                    <div>- ({{$data['current_bill']['advances']}})</div>
                                </div>
                            @endif
                        </div>
                        @php
                            $prevUnpaid = $data['current_bill']['previous_unpaid'];
                            $discount = 0;
                                if (isset($data['current_bill']['discount'])) {
                                    if (is_array($data['current_bill']['discount'])) {
                                        $discount = collect($data['current_bill']['discount'])->sum('amount');
                                    } else {
                                        $discount = (float) $data['current_bill']['discount'];
                                    }
                                }
                            $penalty = (float)($data['current_bill']['penalty'] ?? 0);
                            $dueDate = isset($data['current_bill']['due_date'])
                                ? \Carbon\Carbon::parse($data['current_bill']['due_date'])
                                : null;

                            $today = \Carbon\Carbon::today();

                            $applicablePenalty = ($dueDate && $today->gt($dueDate)) ? $penalty : 0;
                        @endphp

                        @php
                            $prevUnpaid = $data['current_bill']['previous_unpaid'];
                            $advances = $data['current_bill']['advances'];


                        @endphp
                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                        <div class="oversized" style="display: flex; justify-content: space-between; margin: 5px 0 5px 0;">
                            <div style="font-size: 20px; font-weight: 800; text-transform: uppercase">Current Billing:</div>
                            <div style="font-size: 20px; font-weight: 800; text-transform: uppercase">
                                {{number_format($data['current_bill']['total'] - $data['current_bill']['previous_unpaid'], 2)}}
                            </div>
                        </div>

                        @if($prevUnpaid != 0)
                            <div style="display: flex; justify-content: space-between;">
                                <div style="text-transform: uppercase;">Arrears:</div>
                                <div style="text-transform: uppercase;">{{$prevUnpaid}}</div>
                            </div>
                        @endif
                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                        <div class="oversized" style="display: flex; justify-content: space-between; align-items: center;">
                            <div style="text-transform: uppercase; font-size: 20px; font-weight: 800;">Amount Due:</div>
                            <div style="text-transform: uppercase; font-size: 20px; font-weight: 800;">{{ number_format(abs((float) $data['current_bill']['total'] - (float) $discount - (float) $advances - (float) ($franchise->amount ?? 0)), 2) }}</div>
                        </div>
                        <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                            <div style="text-transform: uppercase;">Payment After Due Date</div>
                            <div style="text-transform: uppercase;"></div>
                        </div>
                        <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                            <div style="text-transform: uppercase;">Penalty Date: </div>
                            <div style="text-transform: uppercase;">
                                11/19/2025
                            </div>
                        </div>
                        <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                            <div style="text-transform: uppercase;">Penalty Amt: </div>
                            <div style="text-transform: uppercase;">
                                {{number_format($data['current_bill']['penalty'], 2)}}
                            </div>
                        </div>
                        <div class="oversized" style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                            <div style="text-transform: uppercase; font-size: 20px;">Amount After Due:</div>
                            <div style="text-transform: uppercase; font-size: 20px;">
                                {{number_format($data['current_bill']['amount'] - $advances - $discount, 2)}}
                            </div>
                        </div>
                        <div style="margin: 8px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                        <h6 style="font-weight: bold; text-transform: uppercase; text-align: center; margin-top: 10px; margin-bottom: 10px;">6 months Consumption History</h6>
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; text-transform: uppercase;">
                            @foreach($data['previousConsumption'] as $prevConsump)
                                <div style="text-align: center;">
                                    <div>
                                        {{$prevConsump['month']}}
                                    </div>
                                    <div>
                                        {{ !empty($prevConsump['value']) && $prevConsump['value'] != 0 ? $prevConsump['value'] : 'NA' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div style="margin: 10px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                        <h6 style="font-weight: bold; text-align: center; margin-top: 10px; margin-bottom: 10px;">Two (2) months of non-payment of bills mean AUTOMATIC DISCONNECTION</h6>
                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                        <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                            <div style="text-transform: uppercase;">Bill No:</div>
                            <div style="text-transform: uppercase;">{{$data['current_bill']['reference_no']}}</div>
                        </div>
                        <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                            <div style="text-transform: uppercase;">Meter Reader</div>
                            <div style="text-transform: uppercase;">{{$data['current_bill']['reading']['reader_name']}}</div>
                        </div>
                        <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                            <div style="text-transform: uppercase;">Time Stamp: </div>
                            <div style="text-transform: uppercase;">{{\Carbon\Carbon::now()->format('D M d H:i:s \G\M\TP Y')}}</div>
                        </div>
                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                        <div style="margin-top: 15px; display: flex; justify-content: center; gap: 35px; align-items: center;">
                            <div>
                                {!! $qr_code !!}
                            </div>
                            <div>
                                <h6 style="font-weight: bold; text-transform: uppercase; text-align: left; margin-top: 0; margin-bottom: 5px;">Pay Now</h6>
                                <ol style="font-size: 10px; text-transform: uppercase; list-style-type: decimal; padding: 0; margin-top: 0px">
                                    <li>Scan the QR code.</li>
                                    <li>Choose a merchant on NovuPay.</li>
                                    <li>Pay the total amount due.</li>
                                    <li>Keep your receipt.</li>
                                </ol>
                            </div>
                        </div>

                        @php
                            $bill = $data['current_bill']['created_at'] ?? null;
                            $start = $data['client']['sc_discount']['effective_date'] ?? null;
                            $end = $data['client']['sc_discount']['expired_date'] ?? null;
                        @endphp

                        @php
                            $remarks = [];

                            if ($bill && $start && $end) {
                                $billDate = \Carbon\Carbon::parse($bill);
                                $startDate = \Carbon\Carbon::parse($start);
                                $endDate = \Carbon\Carbon::parse($end);

                                if ($billDate->between($startDate, $endDate) && $billDate->diffInMonths($endDate, false) <= 1) {
                                    $remarks[] = 'senior citizen discount will expire on ' . $endDate->format('F d, Y') . ', renew now';
                                }
                            }

                            if (!empty($data['current_bill']['isHighConsumption'])) {
                                $remarks[] = 'high consumption';
                            }

                            $note = $data['current_bill']['high_consumption_note'] ?? null;

                        @endphp

                        @if (!empty($remarks))
                            <div style="margin: 20px 0 16px 0; display: flex; justify-content: center; align-items: center;">
                                <div style="color: red; text-transform: uppercase; text-align: center; font-style: italic; font-weight: 500;">
                                    REMARKS: {{ implodeWithAnd($remarks) }}
                                </div>
                            </div>
                            <div style="text-transform: uppercase; text-align: center; font-style: bold; font-weight: bold;">
                                Note: {{ $note }}
                            </div>
                        @endif
                        <div style="margin: 30px 0 0 0; display: flex; justify-content: center; align-items: center;">
                            <div class="emp">This is NOT valid as Official Receipt</div>
                        </div>
                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="reReadModal" tabindex="-1" aria-labelledby="reReadModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title text-uppercase fw-bold" id="reReadModalLabel">Confirm to Re-Read?</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-uppercase fw-medium alert alert-danger text-center" style="font-size: 14px;">Once you proceed, the current bill will be discarded and replaced with an updated version</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" style="font-size: 14px; background-color: #32667e !important" class="btn btn-primary text-uppercase fw-medium px-4 py-2" id="confirmReRead">Yes, Proceed</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
<div class="container controls">
  <div class="d-flex justify-content-center gap-3">
    <button class="btn btn-primary print-btn" data-target="#receipt-full">🧾 Print Full Receipt</button>
    <button class="btn btn-secondary print-btn" data-target="#receipt-overlay">🖋 Print Overlay Only</button>
  </div>
  <div class="mt-2 text-muted small-note">Printer: set to Actual Size / 100% and No margins</div>
</div>

<!-- =========================
     Option 1 - FULL RECEIPT (exact layout)
     ========================= -->
<div id="receipt-full" class="printable page-wrap" style="display:none;">
  <div class="receipt-sheet receipt-border">

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
        <div class="text-center" style="width: 50%; padding: 5px;">
          <div class="border border-dark mx-auto" style="width: 100px; height: 120px; background: #f8f8f8;">
            <img src="{{ asset('images/rnp.png')}}"
            style="width: 80px; margin: 5px auto 10px auto"
            alt="logo" class="web-logo">
            <p class="small mt-1" style="font-size: 8px;">{{ \Carbon\Carbon::now()->format('H:i:s') }}</p>
          </div>
        </div>
        <div class="text-center" style="width: 50%; height: 100%">
            <div class="fw-bold" style="font-size: 10px;">
            Official Receipt <br> of the <br> Republic of the Philippines
          </div>
          <div class="fw-bold border border-dark d-inline-block px-2 py-1">
            No. <span class="fw-bold" style="font-size: 13px; letter-spacing: 1px;">4364322</span> <span class="suffix">T</span>
          </div>
          <div class="border border-dark pt-1 text-start" style="font-size: 9px;" >
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
          <tr style="line-height: 5px;">
            <td>WB {{ $bill_month }}</td>
            <td></td>
            <td class="text-end">₱ {{ number_format($amount, 2) }}</td>
          </tr>

          {{-- Conditionally show Arrears and Penalty --}}
          @if($arrears > 0)
            <tr style="line-height: 5px;">
              <td>Arrears</td>
              <td></td>
              <td class="text-end">₱ {{ number_format($arrears, 2) }}</td>
            </tr>
          @endif

          @if($penalty > 0)
            <tr style="line-height: 5px;">
              <td>Penalty</td>
              <td></td>
              <td class="text-end">₱ {{ number_format($penalty, 2) }}</td>
            </tr>
          @endif

          {{-- Blank rows --}}
          @php
            $filled = 1 + ($arrears > 0 ? 1 : 0) + ($penalty > 0 ? 1 : 0);
            $blank = 6 - $filled;
          @endphp
          @for($i = 0; $i < $blank; $i++)
            <tr style="line-height: 5px;">
              <td>&nbsp;</td>
              <td></td>
              <td></td>
            </tr>
          @endfor

          {{-- Discount and Total --}}
          <tr style="line-height: 5px;">
            <td style="font-size: 8px;">Less: Senior Discount</td>
            <td></td>
            <td class="text-end">₱ {{ number_format($discount, 2) }}</td>
          </tr>
          <tr class="fw-bold" style="line-height: 5px;">
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
  </div> <!-- .receipt-sheet -->
</div> <!-- #receipt-full -->

<!-- =========================
     Option 2 - OVERLAY ONLY
     (absolute positioned fields to print on pre-printed form)
     ========================= -->
<div id="receipt-overlay" class="printable receipt-sheet overlay-preview" style="display:none;
     @if($previewBg) background-image: url('{{ $previewBg }}'); @endif">

  {{-- NOTE: positions are in cm and intended to match the full receipt above.
             If you need to calibrate, tweak the top/left/right values by +/- 0.05cm. --}}

  {{-- OR Number (top-right) --}}
  <div style="position:absolute; top:0.9cm; right:0.7cm; font-weight:700; font-size:13px;">
    {{ $receipt_no }}
  </div>

  {{-- Date (below OR no.) --}}
  <div style="position:absolute; top:1.6cm; right:0.7cm; font-size:9px;">
    {{ \Carbon\Carbon::parse($datePaid)->format('F d, Y') }}
  </div>

  {{-- Agency --}}
  <div style="position:absolute; top:3.9cm; left:1.2cm; font-size:11px;">
    SANTA RITA WATER DISTRICT
  </div>

  {{-- Payor --}}
  <div style="position:absolute; top:4.4cm; left:1.2cm; right:1.2cm; font-size:11px; text-transform:uppercase;">
    {{ $data['client']['name'] ?? 'N/A' }} {{ !empty($data['client']['account_no']) ? ' | '.$data['client']['account_no'] : '' }}
  </div>

  {{-- Account No --}}
  <div style="position:absolute; top:4.9cm; left:1.2cm; font-size:11px;">
    {{ $account_no }}
  </div>

  {{-- Table: WB (Nature) main --}}
  <div style="position:absolute; top:7.0cm; left:0.7cm; font-size:10px;">
    WB {{ $bill_month }}
  </div>
  <div style="position:absolute; top:7.0cm; right:0.8cm; width:3.0cm; font-size:10px; text-align:right;">
    ₱ {{ number_format($amount,2) }}
  </div>

  {{-- Arrears --}}
  @if($arrears > 0)
    <div style="position:absolute; top:7.6cm; left:0.7cm; font-size:10px;">Arrears</div>
    <div style="position:absolute; top:7.6cm; right:0.8cm; width:3.0cm; font-size:10px; text-align:right;">₱ {{ number_format($arrears,2) }}</div>
  @endif

  {{-- Penalty --}}
  @if($penalty > 0)
    <div style="position:absolute; top:8.2cm; left:0.7cm; font-size:10px;">Penalty</div>
    <div style="position:absolute; top:8.2cm; right:0.8cm; width:3.0cm; font-size:10px; text-align:right;">₱ {{ number_format($penalty,2) }}</div>
  @endif

  {{-- Discount --}}
  <div style="position:absolute; top:10.1cm; left:0.7cm; font-size:10px;">Less: Senior Discount</div>
  <div style="position:absolute; top:10.1cm; right:0.8cm; width:3.0cm; font-size:10px; text-align:right;">₱ {{ number_format($discount,2) }}</div>

  {{-- Total --}}
  <div style="position:absolute; top:10.6cm; left:0.7cm; font-size:11px; font-weight:700;">TOTAL</div>
  <div style="position:absolute; top:10.6cm; right:0.8cm; width:3.0cm; font-size:11px; font-weight:700; text-align:right;">₱ {{ number_format($total,2) }}</div>

  {{-- Amount in words --}}
  <div style="position:absolute; top:12.5cm; left:0.9cm; right:0.9cm; font-size:10px; text-align:center; font-style:italic;">
    {{ $amount_in_words }}
  </div>

  {{-- Collecting Officer signature --}}
  <div style="position:absolute; bottom:2.0cm; right:1.6cm; text-align:center; width:5.0cm; font-size:10px;">
    <div style="border-top:1px solid #000; font-weight:700;">{{ strtoupper($cashier) }}</div>
    <div style="font-size:9px;">Collecting Officer</div>
  </div>

</div> <!-- #receipt-overlay -->

<script>
  $(function () {
    // show full receipt in the browser by default for preview
    $('#receipt-full').show();

    // Print button logic toggles which printable to show and triggers print
    $(document).on('click', '.print-btn', function (e) {
      e.preventDefault();
      const target = $(this).data('target'); // "#receipt-full" or "#receipt-overlay"
      $('#receipt-full, #receipt-overlay').hide();
      $(target).show();
      // slight delay for rendering changes to take effect before print dialog
      setTimeout(() => window.print(), 180);
    });
  });
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
</html>
