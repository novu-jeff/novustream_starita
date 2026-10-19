@extends('layouts.app')
@php
    $arrearsStack = $arrearsStack ?? collect();
@endphp

@section('content')
    <main class="main payment-pay-page">
        <div class="responsive-wrapper pb-5">
            <div class="payment-pay-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="payment-pay-header-icon"><i class="bx bx-wallet" aria-hidden="true"></i></div>
                    <div>
                        <p class="payment-pay-eyebrow mb-1">PAYMENTS</p>
                        <h1 class="mb-1">Bill Payment</h1>
                        <p class="payment-pay-subtitle mb-0">Review the statement, check the amount due, and record a payment.</p>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('payments.index', ['filter' => 'unpaid']) }}" class="btn btn-outline-primary payment-pay-nav-btn">
                        <i class="bx bx-credit-card me-1" aria-hidden="true"></i> Payments
                    </a>
                    <a href="{{ route('concessionaires.ledger', $user->id) }}" class="btn btn-outline-primary payment-pay-nav-btn">
                        <i class="bx bx-book me-1" aria-hidden="true"></i> Ledger
                    </a>
                </div>
            </div>

            <div class="inner-content mt-4 pb-5 mb-5">
                <div class="row g-4 align-items-start">
                        <div class="col-12 col-xl-6">
                            <div class="payment-statement-card">
                                <div class="payment-statement-toolbar">
                                    <div>
                                        <p class="payment-pay-eyebrow mb-1">CUSTOMER STATEMENT</p>
                                        <h2 class="h5 mb-0">Statement of Account</h2>
                                    </div>
                                    @if($data['current_bill']['isPaid'] == true)
                                        <span class="payment-status-pill"><i class="bx bx-check-circle me-1"></i> Paid</span>
                                    @else
                                        <span class="payment-status-pill payment-status-unpaid"><i class="bx bx-time-five me-1"></i> Unpaid</span>
                                    @endif
                                </div>

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
                                                style="width: 90px; margin: 0 auto 10px auto"
                                                alt="logo" class="web-logo">
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
                                                    <div>{{ \Carbon\Carbon::parse($data['current_bill']['created_at'], 'UTC')->setTimezone('Asia/Manila')->format('m/d/Y') }}</div>
                                                </div>
                                                <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                                    <div>Period</div>
                                                    <div>{{\Carbon\Carbon::parse($data['current_bill']['bill_period_from'])->format('m/d/Y') . ' TO ' . \Carbon\Carbon::parse($data['current_bill']['bill_period_to'])->format('m/d/Y')}}</div>
                                                </div>
                                                <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                                    <div>Due Date</div>
                                                    <div>{{\Carbon\Carbon::parse($data['current_bill']['due_date'])->format('m/d/Y')}}</div>
                                                </div>
                                                <!-- <div class="oversized-2" style="text-align: center; margin: 10px 0 10px 0; font-size: 10px; font-weight: 800; font-style: italic; color:rgb(91, 91, 91)">
                                                    <ul style="list-style: none !important">
                                                        <li>> Office - Last working day of the month</li>
                                                        <li>> Online - Last day of the month</li>
                                                    </ul>
                                                </div> -->
                                                <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                                    <div>Disconnection Date</div>
                                                    <div>{{ \Carbon\Carbon::parse($data['current_bill']['due_date'])->addDays(7)->format('m/d/Y') }}</div>
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
                                                $arrears = (float)($data['current_bill']['previous_unpaid'] ?? 0);
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
                                                    <div>ADVANCES</div>
                                                    <div>- ₱ {{$data['current_bill']['advances']}}</div>
                                                </div>
                                            @endif
                                        </div>
                                        @php
                                        $discount = 0;
                                            if (isset($data['current_bill']['discount'])) {
                                                if (is_array($data['current_bill']['discount'])) {
                                                    $discount = collect($data['current_bill']['discount'])->sum('amount');
                                                } else {
                                                    $discount = (float) $data['current_bill']['discount'];
                                                }
                                            }
                                        $advance_payment = collect($data['current_bill']['advances'] ?? [])->sum();
                                        @endphp
                                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                                        <div class="oversized" style="display: flex; justify-content: space-between; margin: 5px 0 5px 0;">
                                            <div style="font-size: 20px; font-weight: 800; text-transform: uppercase">Current Billing:</div>
                                            <div style="font-size: 20px; font-weight: 800; text-transform: uppercase">₱ {{number_format($data['current_bill']['total'] - $data['current_bill']['previous_unpaid'], 2)}}</div>
                                        </div>

                                        @if($arrearsStack->isNotEmpty())
                                            <div class="d-flex flex-column">
                                                <div class="mb-1">Arrears months:</div>
                                                <div class="d-flex flex-column">
                                                    @foreach($arrearsStack as $month => $amount)
                                                        <div class="d-flex justify-content-between">
                                                            <span>{{ $month }}</span>
                                                            <span>+ ₱ {{ number_format($amount, 2) }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                        @php
                                        $discount = 0;
                                            if (isset($data['current_bill']['discount'])) {
                                                if (is_array($data['current_bill']['discount'])) {
                                                    $discount = collect($data['current_bill']['discount'])->sum('amount');
                                                } else {
                                                    $discount = (float) $data['current_bill']['discount'];
                                                }
                                            }
                                        $advancePayment = (float)($data['current_bill']['advances'] ?? 0);
                                        $isPaid = (float)($data['current_bill']['isPaid'] ?? 0);
                                        if($isPaid == 1) {
                                            $advance = 0;
                                        } else {
                                            $advance = $advancePayment;
                                        }
                                        $hasAdvancePayment = $data['current_bill']['isChangeForAdvancePayment'] ?? false;
                                        $amountDue = (float) $data['current_bill']['total']
                                                    - (float) $discount
                                                    - (float) $advance
                                                    - (float) ($franchise->amount ?? 0);

                                        $amountDue = max(0, $amountDue);


                                        $amountAfter = (float) ($data['current_bill']['amount'] ?? $data['current_bill']['amount_after_due'] ?? 0) - (float) $advance;
                                        $amountAfter = max(0, $amountAfter);
                                        @endphp
                                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                                        <div class="oversized" style="display: flex; justify-content: space-between; align-items: center;">
                                            <div style="text-transform: uppercase; font-size: 20px; font-weight: 800;">Amount Due:</div>
                                            <div style="text-transform: uppercase; font-size: 20px; font-weight: 800;"> ₱ {{number_format ($amountDue, 2)}}</div>
                                        </div>
                                        <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                                            <div style="text-transform: uppercase; font-weight: 800;">Payment After Due Date</div>
                                            <div style="text-transform: uppercase; font-weight: 800;"></div>
                                        </div>
                                        @php
                                        $zone = $data['current_bill']['reading']['zone'];
                                        $zone = str_replace('BOOK ', '', strtoupper(trim($zone)));

                                        if (in_array($zone, ['011', '021', '031', '041', '051'])) {
                                            $penaltyDate = '11/19/2025';
                                        } else if (in_array($zone, ['061', '071', '081'])) {
                                            $penaltyDate = '11/20/2025';
                                        } else if (in_array($zone, ['091', '101', '111'])) {
                                            $penaltyDate = '11/21/2025';
                                        } else {
                                            $penaltyDate = null;
                                        }

                                        @endphp
                                        <!-- <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                                            <div style="text-transform: uppercase;">Penalty Date: </div>
                                            <div style="text-transform: uppercase;">
                                                {{ \Carbon\Carbon::parse($data['current_bill']['due_date'])->addDay()->format('m/d/Y') }}
                                            </div>
                                        </div> -->
                                        <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                                            <div style="text-transform: uppercase;">Penalty Amt: </div>
                                            <div style="text-transform: uppercase;">
                                               ₱ {{number_format($data['current_bill']['penalty'], 2)}}
                                            </div>
                                        </div>
                                        @php
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
                                        $prevUnpaid = ($data['current_bill']['previous_unpaid'] ?? 0);
                                        $advances = ($data['current_bill']['advances'] ?? 0);
                                        $applicablePenalty = ($dueDate && $today->gt($dueDate)) ? $penalty : 0;
                                        @endphp
                                        <div class="oversized" style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                                            <div style="text-transform: uppercase; font-size: 20px;">Amount After Due:</div>
                                            <div style="text-transform: uppercase; font-size: 20px;">
                                               ₱ {{ number_format($amountAfter, 2) }}
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
                                                        {{$prevConsump['value'] == 0 ? 'NA' : $prevConsump['value'] }}
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
                                                    <li>Pay with QR Ph on NovuPay.</li>
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

                                        @endphp

                                        @if (!empty($remarks))
                                            <div style="margin: 20px 0 16px 0; display: flex; justify-content: center; align-items: center;">
                                                <div style="color: red; text-transform: uppercase; text-align: center; font-style: italic; font-weight: 500;">
                                                    REMARKS: {{ implodeWithAnd($remarks) }}
                                                </div>
                                            </div>
                                        @endif
                                        <p class="text-center mt-2 fw-bold">Please disregard any arrears if payment </br>has already been made.</p>
                                        <div style="margin: 30px 0 0 0; display: flex; justify-content: center; align-items: center;">
                                            <div style="background-color: #000; padding: 8px 10px 8px 10px; color: #fff; font-size: 12px; font-weight: 600; text-transform: uppercase; margin-bottom: 20px;">This is NOT valid as Official Receipt</div>
                                        </div>
                                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-12 col-xl-6">
                            <div class="payment-discount-card mb-4">
                                <form method="POST" action="{{ route('payments.applyDiscount', $reference_no) }}" class="payment-discount-form">
                                    @csrf
                                    <div class="input-group">
                                        <div class="payment-discount-heading">
                                            <span class="payment-pay-small-icon"><i class="bx bx-purchase-tag" aria-hidden="true"></i></span>
                                            <div>
                                                <h2 class="h6 mb-0">Apply a discount</h2>
                                                <p class="small text-muted mb-0">Optional — enter a percentage if a discount applies.</p>
                                            </div>
                                        </div>
                                        <input type="number" step="0.01" min="0" max="100" name="appliedDiscount" class="form-control" placeholder="Discount percentage (0–100)" aria-label="Discount percentage">
                                        <button type="submit" class="btn btn-primary"><i class="bx bx-check me-1" aria-hidden="true"></i> Apply</button>
                                    </div>
                                </form>
                            </div>
                            <form id="billPaymentForm" action="{{ route('payments.pay', ['reference_no' => $reference_no]) }}" method="POST">
                                @csrf
                            @if(!$data['current_bill']['isPaid'])
                                @php
                                    $total = (float) ($data['current_bill']['total'] ?? 0);
                                    $dbPenalty = (float) ($data['current_bill']['penalty'] ?? 0);
                                    $computedPenalty = (float) ($data['current_bill']['penalty'] ?? 0);
                                    $totalPenalty = $dbPenalty + $computedPenalty;
                                    $currentBill = (float) ($data['current_bill']['amount'] ?? 0);
                                    $discount = 0;
                                    if (isset($data['current_bill']['discount'])) {
                                        $discount = is_array($data['current_bill']['discount'])
                                            ? collect($data['current_bill']['discount'])->sum('amount')
                                            : (float) $data['current_bill']['discount'];
                                    }
                                    $penalty = (float) ($data['current_bill']['penalty'] ?? 0);
                                    $dueDate = isset($data['current_bill']['due_date']) ? \Carbon\Carbon::parse($data['current_bill']['due_date']) : null;
                                    $today = \Carbon\Carbon::today();
                                    $applicablePenalty = ($dueDate && $today->gt($dueDate)) ? $penalty : 0;
                                    $prevUnpaid = ($data['current_bill']['previous_unpaid'] ?? 0);
                                    $advancePayment = (float) ($data['current_bill']['advances'] ?? 0);
                                    $partialPayment = $data['current_bill']['partial_payment'] ?? 0;
                                    $hasAdvancePayment = $data['current_bill']['isChangeForAdvancePayment'] ?? false;
                                    $netCurrentBill = max(0, $currentBill - $discount - $advancePayment);
                                    $amountAmountDue = (float) $data['current_bill']['total']
                                        - (float) $discount
                                        - (float) $advance
                                        + (float) $applicablePenalty
                                        - (float) $partialPayment
                                        - (float) ($franchise->amount ?? 0);
                                    $amountAmountDue = max(0, $amountAmountDue);
                                @endphp

                                <section class="payment-checkout-card">
                                    <div class="payment-checkout-heading">
                                        <p class="payment-pay-eyebrow mb-1">PAYMENT SUMMARY</p>
                                        <h2 class="h5 mb-0">Review and collect payment</h2>
                                        <p class="small text-muted mb-0 mt-1">Confirm the breakdown and enter the amount received.</p>
                                    </div>
                                    <div class="payment-checkout-body">
                                        <div class="payment-total-banner">
                                            <span><i class="bx bx-wallet me-1" aria-hidden="true"></i> Total Amount Due</span>
                                            <strong>PHP {{ number_format($amountAmountDue, 2) }}</strong>
                                        </div>

                                        <h3 class="payment-breakdown-heading">Bill Breakdown</h3>

                                        @php
                                            $currentBill = (float) ($data['current_bill']['total'] ?? 0);
                                            $arrears = (float) ($data['current_bill']['previous_unpaid'] ?? 0);
                                            $penalty = (float) ($data['current_bill']['penalty'] ?? 0);
                                            $discount = 0;
                                            if (isset($data['current_bill']['discount'])) {
                                                $discount = is_array($data['current_bill']['discount'])
                                                    ? collect($data['current_bill']['discount'])->sum('amount')
                                                    : (float) $data['current_bill']['discount'];
                                            }
                                            $advancePayment = (float) ($data['current_bill']['advances'] ?? 0);
                                            $hasAdvancePayment = $data['current_bill']['isChangeForAdvancePayment'] ?? false;
                                            $dueDate = isset($data['current_bill']['due_date']) ? \Carbon\Carbon::parse($data['current_bill']['due_date']) : null;
                                            $today = \Carbon\Carbon::today();
                                            $applicablePenalty = ($dueDate && $today->gt($dueDate)) ? $penalty : 0;
                                            $partialPayment = $data['current_bill']['partial_payment'] ?? 0;
                                            $netCurrentBill = max(0, $currentBill - $discount - $advancePayment);
                                            $totalDue = $netCurrentBill + $applicablePenalty - $partialPayment;
                                            $forAdvances = $advances - $currentBill;
                                        @endphp

                                        <div class="payment-summary-row">
                                            <span>Arrears</span>
                                            <div class="text-end">
                                                @if($arrearsStack->isNotEmpty())
                                                    @foreach($arrearsStack as $month => $amount)
                                                        <div class="payment-summary-detail">{{ $month }}: ₱ {{ number_format($amount, 2) }}</div>
                                                    @endforeach
                                                @else
                                                    <strong>PHP 0.00</strong>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="payment-summary-row">
                                            <span>Current Billing</span>
                                            <div class="text-end">
                                                @php
                                                    $current_billing = (float) $data['current_bill']['total'];
                                                    $hasAdvancePayment = $data['current_bill']['isChangeForAdvancePayment'];
                                                    $advancePayment = (float) $data['current_bill']['advances'] ?? 0;
                                                    $newCurrentBilling = $current_billing - $prevUnpaid;
                                                    if ($hasAdvancePayment) {
                                                        $current_billing = $current_billing - $advancePayment;
                                                    }
                                                    $penalty = $data['current_bill']['penalty'];
                                                    $dueDate = isset($data['current_bill']['due_date']) ? \Carbon\Carbon::parse($data['current_bill']['due_date']) : null;
                                                    $today = \Carbon\Carbon::today();
                                                    $applicablePenalty = ($dueDate && $today->gt($dueDate)) ? $penalty : 0;
                                                    $prevUnpaid = $data['current_bill']['previous_unpaid'];
                                                @endphp
                                                <strong>PHP {{ number_format($newCurrentBilling, 2) }}</strong>
                                                @if($discount > 0)
                                                    <div class="payment-summary-adjustment text-success">− PHP {{ number_format($discount, 2) }} (Discount)</div>
                                                @endif
                                                @if($advancePayment > 0)
                                                    <div class="payment-summary-adjustment">− PHP {{ number_format($advancePayment, 2) }} (Advance payment)</div>
                                                @endif
                                                @if($partialPayment > 0)
                                                    <div class="payment-summary-adjustment">− PHP {{ number_format($partialPayment, 2) }} (Partial payment)</div>
                                                @endif
                                                @if($applicablePenalty > 0)
                                                    <div class="payment-summary-adjustment payment-summary-penalty">+ PHP {{ number_format($applicablePenalty, 2) }} (Due date penalty)</div>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="payment-total-due-row">
                                            <span>Total Amount</span>
                                            <strong>PHP {{ number_format($totalDue, 2) }}</strong>
                                        </div>

                                        <div class="payment-input-group">
                                            <label for="payment_amount" class="form-label">Payment Amount</label>
                                            <input type="text" class="form-control form-control-lg text-end" id="payment_amount" name="payment_amount" value="{{ old('payment_amount', 0) }}" inputmode="decimal" autocomplete="off">
                                            @error('payment_amount')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        @if($forAdvances > 0)
                                            <div class="payment-summary-adjustment text-end mb-3">+ PHP {{ number_format($forAdvances, 2) }} (For advances)</div>
                                        @endif

                                        <div class="payment-change-panel">
                                            <div>
                                                <span class="payment-change-label">Change</span>
                                                <strong id="changeAmount">PHP 0.00</strong>
                                            </div>
                                            <div id="isForAdvances" class="payment-advance-options"></div>
                                        </div>

                                        <div class="d-flex justify-content-end w-100">
                                            <hr class="w-100">
                                        </div>
                                        <input type="hidden" name="payment_type" id="payment_type" value="">

                                        <div class="payment-action-buttons">
                                            <button type="submit" id="cashPaymentButton" class="btn btn-primary" name="payment_type" value="cash">
                                                <i class="bx bx-money me-1" aria-hidden="true"></i> Pay Cash
                                            </button>
                                            <a href="{{ route('payments.novupay.checkout', ['reference_no' => $reference_no]) }}" class="btn btn-outline-primary">
                                                <i class="bx bx-qr me-1" aria-hidden="true"></i> Pay Online
                                            </a>
                                        </div>
                                    </div>
                                </section>
                            @else
                                <section class="payment-checkout-card">
                                    <div class="payment-already-paid">
                                        <span class="payment-already-paid-icon"><i class="bx bx-check-circle"></i></span>
                                        <h2>Already Paid</h2>
                                        <p class="text-muted mb-3">This bill has already been settled.</p>
                                        <a href="{{ route('reading.orshow', ['reference_no' => $reference_no]) }}" class="btn btn-outline-primary">
                                            <i class="bx bx-printer me-2"></i> Generate Official Receipt
                                        </a>
                                    </div>
                                </section>
                            @endif

                            @if($data['current_bill']['isPartial'] == 1)
                                <a href="{{ route('reading.orshow', ['reference_no' => $arrearsReceiptReference ?: $reference_no]) }}" class="mt-4 btn btn-outline-primary px-4 py-2 fw-semibold text-uppercase shadow-sm">
                                    <i class="bx bx-printer me-2"></i> Generate Official Receipt
                                </a>
                            @endif
                            </form>
                        </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="serviceFeeModal" tabindex="-1" aria-labelledby="serviceFeeModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content payment-fee-modal">
                    <div class="modal-body">
                        <div class="payment-fee-modal-icon"><i class="bx bx-info-circle" aria-hidden="true"></i></div>
                        <h4 class="modal-title fw-bold mb-2" id="serviceFeeModalLabel">Confirm cash payment</h4>
                        <p class="text-secondary mb-4">An additional <strong>₱{{ number_format((float) ($systemFee ?? config('payments.system_fee', 10)), 2) }} system fee</strong> will be charged.</p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <button type="button" class="btn btn-outline-primary px-4" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" id="confirmPaymentBtn" class="btn btn-primary px-4">Confirm Payment</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .payment-pay-page {
            --pay-blue: #32667e;
            --pay-blue-dark: #32667e;
            --pay-blue-soft: #edf5ff;
            --pay-blue-border: #d7e7ff;
            --pay-text: #122b55;
            --pay-muted: #6f83a5;
            color: var(--pay-text);
        }
        .payment-pay-page .responsive-wrapper { max-width: 1700px; width: 100%; margin-inline: auto; padding-inline: clamp(.75rem, 2vw, 1.5rem); }
        .payment-pay-header { padding: .5rem 0 .25rem; }
        .payment-pay-header-icon, .payment-pay-small-icon, .payment-already-paid-icon {
            display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto;
            color: var(--pay-blue); background: var(--pay-blue-soft); border: 1px solid var(--pay-blue-border);
        }
        .payment-pay-header-icon { width: 56px; height: 56px; border-radius: 16px; font-size: 1.8rem; }
        .payment-pay-eyebrow { color: var(--pay-blue); font-size: .7rem; font-weight: 800; letter-spacing: .1em; }
        .payment-pay-header h1 { color: var(--pay-text); font-size: clamp(1.5rem, 2.2vw, 2rem); font-weight: 750; letter-spacing: -.035em; }
        .payment-pay-subtitle { color: var(--pay-muted); font-size: .9rem; }
        .payment-pay-page .btn { min-height: 40px; border-radius: 9px; font-size: .86rem; font-weight: 650; transition: .15s ease; }
        .payment-pay-page .btn-primary { background: var(--pay-blue); border-color: var(--pay-blue); color: #fff; }
        .payment-pay-page .btn-primary:hover, .payment-pay-page .btn-primary:focus { background: var(--pay-blue-dark); border-color: var(--pay-blue-dark); color: #fff; }
        .payment-pay-page .btn-outline-primary { background: #fff; border-color: #a9caff; color: var(--pay-blue); }
        .payment-pay-page .btn-outline-primary:hover, .payment-pay-page .btn-outline-primary:focus { background: var(--pay-blue); border-color: var(--pay-blue); color: #fff; }
        .payment-pay-nav-btn { padding: .65rem 1rem; }
        .payment-discount-card, .payment-statement-card, .payment-checkout-card {
            background: #fff; border: 1px solid var(--pay-blue-border); border-radius: 16px;
            box-shadow: 0 8px 28px rgba(31, 91, 164, .055); overflow: hidden;
        }
        .payment-discount-card { width: 100%; display: block; padding: 1rem 1.25rem; }
        .payment-discount-form .input-group { display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: center; column-gap: 0; row-gap: .75rem; }
        .payment-discount-heading { grid-column: 1 / -1; display: flex; align-items: center; gap: .75rem; min-width: 0; }
        .payment-discount-heading > div { min-width: 0; }
        .payment-discount-form .input-group > .form-control { width: 100%; min-width: 0; flex: none; }
        .payment-pay-small-icon { width: 38px; height: 38px; border-radius: 12px; font-size: 1.2rem; }
        .payment-discount-form .form-control { min-height: 42px; border-color: #d7e2f1; border-radius: 9px 0 0 9px; box-shadow: none; }
        .payment-discount-form .btn { border-radius: 0 9px 9px 0; }
        .payment-statement-toolbar, .payment-checkout-heading { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: .75rem; padding: 1.2rem 1.35rem; border-bottom: 1px solid #e8eff9; }
        .payment-statement-toolbar h2, .payment-checkout-heading h2 { color: var(--pay-text); font-weight: 750; }
        .payment-status-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: .4rem .75rem; background: var(--pay-blue-soft); border: 1px solid var(--pay-blue-border); color: var(--pay-blue-dark); font-size: .76rem; font-weight: 750; text-transform: uppercase; }
        .payment-status-unpaid { background: #f3f7ff; }
        .payment-bill-preview { padding: 1.1rem; background: #f5f8fd; }
        .payment-print-bill { position: relative; width: 100%; max-width: 520px; margin: 0 auto; padding: clamp(1rem, 3vw, 1.5rem); background: #fff; border: 1px solid #e1e9f4; border-radius: 8px; box-shadow: 0 2px 12px rgba(24, 58, 105, .06); color: #172b47; font-size: 12px; }
        .payment-print-bill .isPaid { position: absolute; right: 14px; top: 12px; padding: 4px 9px; border: 1px solid #1769e0; border-radius: 5px; color: #1769e0; font-size: 10px; font-weight: 800; letter-spacing: 2px; }
        .payment-bill-issuer { display: flex; justify-content: center; align-items: center; gap: 12px; margin: 18px 0 12px; padding-bottom: 12px; text-align: center; }
        .payment-bill-issuer img { width: 68px; max-width: 25%; height: auto; object-fit: contain; }
        .payment-bill-issuer p { margin: 0; font-size: clamp(8px, 1vw, 10px); text-transform: uppercase; line-height: 1.45; }
        .payment-bill-issuer .issuer-name { font-size: clamp(10px, 1.2vw, 13px); font-weight: 800; }
        .payment-statement-title { margin: 8px 0 12px; text-align: center; text-transform: uppercase; font-size: 20px; font-weight: 800; }
        .payment-dashed-rule { width: 100%; margin: 10px 0; border-bottom: 1px dashed #9caec4; }
        .payment-account-details { display: flex; flex-direction: column; gap: 5px; text-transform: uppercase; overflow-wrap: anywhere; }
        .payment-account-number, .payment-meter-number { display: flex; flex-wrap: wrap; gap: 6px; align-items: baseline; font-size: 15px; font-weight: 750; }
        .payment-account-name { font-size: 18px; font-weight: 800; }
        .payment-bill-section-title { position: relative; margin: 17px 0 8px; padding-top: 10px; border-top: 1px dashed #9caec4; text-align: center; font-weight: 800; text-transform: uppercase; }
        .payment-bill-pair { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin: 5px 0; text-transform: uppercase; }
        .payment-bill-pair > :last-child { text-align: right; overflow-wrap: anywhere; }
        .payment-bill-emphasis { font-size: 16px; font-weight: 800; }
        .payment-bill-total { font-size: 19px; font-weight: 850; }
        .payment-bill-label { font-weight: 800; }
        .payment-arrears-block { margin: 10px 0; }
        .payment-history-title { margin: 14px 0 10px; text-align: center; text-transform: uppercase; font-size: 13px; font-weight: 800; }
        .payment-consumption-history { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 5px; text-align: center; text-transform: uppercase; font-size: 10px; }
        .payment-consumption-history > div { display: flex; flex-direction: column; gap: 4px; padding: 7px 2px; border: 1px solid #e3edfa; border-radius: 6px; background: #f6faff; }
        .payment-disconnection-note { margin: 12px 0; text-align: center; font-weight: 750; }
        .payment-qr-instructions { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 18px; margin: 15px 0; }
        .payment-qr-instructions img, .payment-qr-instructions svg { max-width: 100%; height: auto; }
        .payment-qr-instructions h4 { font-size: 13px; font-weight: 800; text-transform: uppercase; }
        .payment-qr-instructions ol { margin: 0; padding-left: 17px; font-size: 10px; text-transform: uppercase; }
        .payment-bill-remarks { margin: 18px 0; color: var(--pay-blue-dark); text-align: center; font-style: italic; font-weight: 700; text-transform: uppercase; }
        .payment-disregard-note { margin-top: 10px; text-align: center; font-weight: 750; }
        .payment-invalid-receipt { margin: 20px auto 12px; padding: 8px 10px; width: fit-content; max-width: 100%; background: #edf5ff; border: 1px solid var(--pay-blue-border); border-radius: 5px; color: var(--pay-blue-dark); text-align: center; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .payment-checkout-body { padding: clamp(1rem, 2.2vw, 1.5rem); }
        .payment-total-banner { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 1.1rem 1.2rem; border: 1px solid #f1b9bf; border-radius: 12px; background: linear-gradient(120deg, #fff0f1, #fff8f8); color: #b42332; }
        .payment-total-banner span { font-size: .85rem; font-weight: 750; }
        .payment-total-banner strong { font-size: clamp(1.3rem, 2vw, 1.8rem); font-weight: 850; letter-spacing: -.03em; }
        .payment-breakdown-heading { margin: 1.5rem 0 1rem; color: var(--pay-text); font-size: 1rem; font-weight: 800; }
        .payment-summary-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: .9rem 0; border-bottom: 1px solid #edf2f9; color: #526a8c; font-size: .9rem; }
        .payment-summary-row > :last-child { color: var(--pay-text); }
        .payment-summary-detail { margin-bottom: 4px; font-weight: 650; }
        .payment-summary-adjustment { margin-top: 5px; color: #5378a9; font-size: .78rem; font-weight: 650; }
        .payment-summary-penalty { color: #c93645; }
        .payment-total-due-row { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; padding: 1.2rem 0; color: #c93645; }
        .payment-total-due-row span { font-weight: 750; }
        .payment-total-due-row strong { color: #c93645; font-size: clamp(1.4rem, 2.3vw, 2rem); font-weight: 850; }
        .payment-input-group { margin: .5rem 0 1.2rem; }
        .payment-input-group .form-label { display: block; margin-bottom: .5rem; color: #30496e; font-size: .84rem; font-weight: 750; }
        .payment-pay-page .form-control { border-color: #d7e2f1; border-radius: 9px; color: var(--pay-text); box-shadow: none; }
        .payment-pay-page .form-control:focus { border-color: #78adff; box-shadow: 0 0 0 .2rem rgba(23, 105, 224, .12); }
        .payment-pay-page .payment-input-group .form-control { width: 100%; min-height: 82px; padding: .85rem 1rem; font-size: 40px !important; font-weight: 800; letter-spacing: .02em; }
        .payment-change-panel { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; padding: 1rem; border: 1px solid var(--pay-blue-border); border-radius: 12px; background: #f7faff; }
        .payment-change-panel > div:first-child { display: flex; flex-direction: column; gap: 5px; }
        .payment-change-label { color: var(--pay-muted); font-size: .8rem; font-weight: 700; }
        #changeAmount { color: var(--pay-blue-dark); font-size: 1.5rem; font-weight: 800; }
        .payment-advance-options { flex: 1 1 260px; text-align: left; }
        .payment-advance-options .form-check { display: flex; align-items: center; justify-content: flex-start; gap: 10px; margin: 0; padding: .75rem .9rem; border: 1px solid #cbd9eb; border-radius: 10px; background: #fff; }
        .payment-advance-options input[type="checkbox"] { position: static; flex: 0 0 auto; width: 1.25rem; height: 1.25rem; margin: 0; accent-color: var(--pay-blue); cursor: pointer; }
        .payment-advance-options input { accent-color: var(--pay-blue); }
        .payment-advance-options label { margin: 0; color: var(--pay-text); font-size: .9rem; font-weight: 700; line-height: 1.4; cursor: pointer; }
        .payment-action-buttons { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .75rem; margin-top: 1.25rem; }
        .payment-action-buttons .btn { min-height: 48px; padding: .75rem 1.2rem; }
        .payment-already-paid { display: flex; flex-direction: column; align-items: center; padding: 3rem 1.5rem; text-align: center; }
        .payment-already-paid-icon { width: 64px; height: 64px; margin-bottom: 1rem; border-radius: 20px; font-size: 2rem; }
        .payment-already-paid h2 { color: var(--pay-text); font-weight: 800; }
        .payment-fee-modal { max-width: 560px; margin: auto; padding: 1rem; border: 1px solid var(--pay-blue-border); border-radius: 18px; text-align: center; }
        .payment-fee-modal-icon { display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; width: 64px; height: 64px; border-radius: 20px; background: var(--pay-blue-soft); color: var(--pay-blue); font-size: 2.4rem; }
        .payment-fee-modal .modal-title { color: var(--pay-text); }
        @media (max-width: 767.98px) {
            .payment-pay-header { align-items: flex-start !important; }
            .payment-pay-header-icon { width: 46px; height: 46px; border-radius: 13px; font-size: 1.5rem; }
            .payment-discount-card { padding: 1rem; }
            .payment-discount-form .input-group { grid-template-columns: auto minmax(0, 1fr); }
            .payment-discount-form .input-group .form-control { grid-column: 1 / -1; border-radius: 9px; }
            .payment-discount-form .input-group .btn { grid-column: 1 / -1; border-radius: 9px; }
            .payment-statement-toolbar, .payment-checkout-heading { padding: 1rem; }
            .payment-bill-preview { padding: .5rem; }
            .payment-print-bill { padding: .85rem; }
            .payment-bill-issuer { gap: 7px; }
            .payment-account-number, .payment-meter-number { font-size: 13px; }
            .payment-account-name { font-size: 16px; }
            .payment-consumption-history { grid-template-columns: repeat(3, minmax(0, 1fr)); }
            .payment-action-buttons { display: grid; grid-template-columns: minmax(0, 1fr); }
            .payment-action-buttons .btn { width: 100%; }
        }
        @media print {
            .payment-pay-header, .payment-discount-card, .payment-checkout-card, .payment-statement-toolbar { display: none !important; }
            .payment-pay-page .responsive-wrapper { max-width: none; padding: 0; }
            .payment-statement-card, .payment-print-bill { border: 0; box-shadow: none; }
            .payment-bill-preview { padding: 0; background: #fff; }
        }
    </style>
@endsection

@section('script')
@php
    // Simplified discount calculation (for display only; totalDue is calculated in the existing view/controller flow).
    $discounts = $data['current_bill']['discount'] ?? [];
    $totalDiscount = is_array($discounts) ? array_sum(array_column($discounts, 'amount')) : (float) $discounts;
@endphp

<script>
    $(function () {
        @if (session('alert'))
            setTimeout(() => {
                let alertData = @json(session('alert'));
                if (alertData.status === 'success' && alertData.payment_request) {
                    window.open(alertData.redirect, '_blank', 'width=1200,height=900,scrollbars=yes,resizable=yes');
                } else {
                    alert(alertData.status, alertData.message);
                }
            }, 100);
        @endif

        const isPaid = '{{ $data['current_bill']['isPaid'] == true }}';
        const paymentForm = document.getElementById('billPaymentForm');
        const cashPaymentButton = document.getElementById('cashPaymentButton');
        const feeModalElement = document.getElementById('serviceFeeModal');
        let cashPaymentConfirmed = false;

        if (paymentForm && cashPaymentButton && feeModalElement) {
            const feeModal = bootstrap.Modal.getOrCreateInstance(feeModalElement);

            paymentForm.addEventListener('submit', function (event) {
                if (event.submitter !== cashPaymentButton || cashPaymentConfirmed) return;
                event.preventDefault();
                feeModal.show();
            });

            document.getElementById('confirmPaymentBtn').addEventListener('click', function () {
                cashPaymentConfirmed = true;
                this.disabled = true;
                feeModal.hide();
                paymentForm.requestSubmit(cashPaymentButton);
            });

            feeModalElement.addEventListener('hidden.bs.modal', function () {
                if (!cashPaymentConfirmed) {
                    const confirmButton = document.getElementById('confirmPaymentBtn');
                    confirmButton.disabled = false;
                }
            });
        }

        if (!isPaid) {
            async function checkPaymentStatus() {
                const reference_no = '{{ $reference_no }}';
                const url = `{!! route('transaction.status', ['reference_no' => '__reference_no__']) !!}`.replace('__reference_no__', encodeURIComponent(reference_no));

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ isApi: true })
                    });

                    if (!response.ok) throw new Error(`HTTP error! Status: ${response.status}`);
                    const statusData = await response.json();

                    if (statusData.status == 'paid') {
                        window.location.reload();
                    } else {
                        setTimeout(checkPaymentStatus, 5000);
                    }
                } catch (error) {
                    console.error('Error checking payment status:', error);
                    setTimeout(checkPaymentStatus, 5000);
                }
            }
            checkPaymentStatus();
        }

        const total = parseFloat('{{ number_format($totalDue ?? 0, 2, '.', '') }}') || 0;
        $('#payment_amount')
            .val('0')
            .on('focus', function () {
                if ($(this).val() === '0') $(this).val('');
            })
            .on('blur', function () {
                if ($(this).val().trim() === '') $(this).val('0');
            })
            .on('input', function () {
                let input = $(this).val().replace(/[^0-9.]/g, '');
                if ((input.match(/\./g) || []).length > 1) input = input.substring(0, input.lastIndexOf('.'));
                $(this).val(input);

                const value = parseFloat(input) || 0;
                const change = Math.max(0, value - total);
                const previousUnpaid = parseFloat('{{ number_format($data['current_bill']['previous_unpaid'] ?? 0, 2, '.', '') }}') || 0;

                $('#changeAmount').text('PHP ' + change.toFixed(2));
                $('#isForAdvances').empty();

                if (value === previousUnpaid) {
                    $('#isForAdvances').html(`
                        <div class="form-check text-end">
                            <input type="checkbox" id="pay_arrears_only" name="pay_arrears_only" value="1">
                            <label for="pay_arrears_only">Apply to Previous Billing Only</label>
                        </div>
                    `);
                } else if (value < total && value > 0) {
                    $('#isForAdvances').html(`
                        <div class="form-check text-end">
                            <input type="checkbox" id="partial_payment" name="partial_payment" class="form-check-input" value="1">
                            <label for="partial_payment">Process as Partial Payment</label>
                        </div>
                    `);
                } else if (value === total) {
                    $('#changeAmount').text('PHP 0.00');
                } else if (value > total) {
                    $('#isForAdvances').html(`
                        <div class="form-check text-end">
                            <input type="checkbox" id="for_advances" name="for_advances" class="form-check-input" value="true">
                            <label for="for_advances">Save Change to Advance Payment</label>
                        </div>
                    `);
                }
            });
    });
</script>
@endsection
