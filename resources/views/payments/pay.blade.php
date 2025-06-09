@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Bill Payment</h1>
                <a href="{{route('payments.show', ['payment' => $reference_no])}}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Go Back
                </a>
            </div>
            <div class="inner-content mt-5 pb-5 mb-5">
                <form action="{{route('payments.pay', ['reference_no' => $reference_no]) }}" method="POST">
                    @csrf  
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div class="bill-container">
                                <div style="position: relative; width: 100%; max-width: 480px; margin: 0 auto; padding: 25px; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
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
                                            <p style="font-size: 15px; text-transform: uppercase; margin: 0; text-transform: uppercase; font-weight: 600">Bacolor Water District</p>
                                            <p style="font-size: 12px; text-transform: uppercase; margin: 3px 0 0 0;">Sta. Ines, Bacolor, Pampanga</p>
                                            <p style="font-size: 12px; text-transform: uppercase; margin: 0;">Tel No. (045) 900- 2911</p>
                                            <p style="font-size: 12px; text-transform: uppercase; margin: 0;">TIN 003 878 306 000 Non VAT</p>
                                        </div>
                                    </div> 
                                    <div style="text-align:center; text-transform: uppercase; font-size: 16px; margin: 10px 0 10px 0;">
                                        <p style="font-size: 22px; text-transform: uppercase; margin: 0; text-transform: uppercase; font-weight: 600">Statement of Account</p>
                                    </div>
                                    <div style="width: 100%; height: 1px; margin: 10px 0 10px 0; border-bottom: 1px dashed black;"></div>                                     
                                    <div>
                                        <div style="font-size: 10px; text-transform: uppercase; display: flex; flex-direction: column; gap: 1px;">
                                            <div style="margin: 4px 0 0 0; display: flex; align-items: center;">
                                                <div style="font-size: 20px; font-weight: 600">Account No.</div>
                                                <div style="font-size: 20px; font-weight: 600">{{$data['client']['account_no'] ?? ''}}</div>
                                            </div>
                                            <div style="margin: 4px 0 0 0; display: flex; align-items: center;">
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
                                                <div>{{\Carbon\Carbon::parse($data['current_bill']['created_at'])->format('m/d/Y')}}</div>
                                            </div>
                                            <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                                <div>Period</div>
                                                <div>{{\Carbon\Carbon::parse($data['current_bill']['bill_period_from'])->format('m/d/Y') . ' TO ' . \Carbon\Carbon::parse($data['current_bill']['bill_period_to'])->format('m/d/Y')}}</div>
                                            </div>
                                            <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                                <div>Due Date</div>
                                                <div>{{\Carbon\Carbon::parse($data['current_bill']['due_date'])->format('m/d/Y')}}</div>
                                            </div>
                                            <div style="margin: 4px 0 0 0; display: flex; justify-content: space-between;">
                                                <div>Disconnection Date</div>
                                                <div>{{\Carbon\Carbon::parse($data['current_bill']['due_date'])->format('m/d/Y')}}</div>
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
                                        <div style="display: flex; justify-content: space-between; margin-top: 5px">
                                            <div style="font-size: 20px; font-weight: 800; text-transform: uppercase;">Cub. M Used</div>
                                            <div style="font-size: 20px; font-weight: 800; text-transform: uppercase;">{{$data['current_bill']['reading']['consumption'] ?? '0'}}</div>
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
                                                <div style="text-transform: uppercase">({{$discount['amount']}})</div>
                                            </div>
                                        @empty

                                        @endforelse
                                        <div style="display: flex; justify-content: space-between;">
                                            <div style="text-transform: uppercase;">2% Franchise Tax:</div>
                                            <div style="text-transform: uppercase;">0</div>
                                        </div>
                                    </div>
                                    <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>                    
                                    <div style="display: flex; justify-content: space-between; margin: 5px 0 5px 0;">
                                        <div style="font-size: 20px; font-weight: 800; text-transform: uppercase">Current Billing:</div>
                                        <div style="font-size: 20px; font-weight: 800; text-transform: uppercase">{{(float) $data['current_bill']['total'] - (float) $arrears - (float) $totalDiscount}}</div>
                                    </div>
                                    @if($arrears != 0)
                                        <div style="display: flex; justify-content: space-between;">
                                            <div style="text-transform: uppercase;">Arrears:</div>
                                            <div style="text-transform: uppercase;">{{$arrears}}</div>
                                        </div>
                                    @endif
                                    <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>                    
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <div style="text-transform: uppercase; font-size: 20px; font-weight: 800;">Amount Due:</div>
                                        <div style="text-transform: uppercase; font-size: 20px; font-weight: 800;">{{number_format($data['current_bill']['amount'], 2)}} </div>
                                    </div>
                                    <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="text-transform: uppercase;">Payment After Due Date</div>
                                        <div style="text-transform: uppercase;"></div>
                                    </div>
                                    <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="text-transform: uppercase;">Penalty Date: </div>
                                        <div style="text-transform: uppercase;">
                                            @if($data['current_bill']['hasPenalty'])
                                                {{\Carbon\Carbon::parse($data['current_bill']['due_date'])->format('m/d/Y')}}
                                            @endif
                                        </div>
                                    </div>
                                    <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="text-transform: uppercase;">Penalty Amt: </div>
                                        <div style="text-transform: uppercase;">
                                            @if($data['current_bill']['hasPenalty'])
                                                {{number_format($data['current_bill']['penalty'], 2)}}
                                            @endif
                                        </div>
                                    </div>
                                    <div style="margin: 5px 0 0 0; display: flex; justify-content: space-between; align-items: center;">
                                        <div style="text-transform: uppercase; font-size: 20px; font-weight: 800;">Amount After Due:</div>
                                        <div style="text-transform: uppercase; font-size: 20px; font-weight: 800;">
                                            @if($data['current_bill']['hasPenalty'])
                                                {{number_format($data['current_bill']['amount_after_due'], 2)}}
                                            @endif
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
                                                    {{$prevConsump['value']}}
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
                                    <div style="margin: 20px 0 16px 0; display: flex; justify-content: center; align-items: center;">
                                        <div style="text-transform: uppercase; text-align: center; font-weight: 500; background-color: #000; color: #fff; padding: 5px;">This is NOT valid as Official Receipt</div>
                                    </div>
                                </div>                    
                            </div>
                        </div>
                        <div class="col-12 col-md-6"> 
                            @if(!$data['current_bill']['isPaid'])
                                <div class="bg-danger d-flex align-items-center justify-content-between mt-4 p-3 text-uppercase fw-bold text-white">
                                    Total Amount Due: 
                                    <h3 class="ms-2">
                                        PHP {{number_format((float) $data['current_bill']['amount'] + (float) $data['current_bill']['penalty'] ?? 0, 2)}}
                                    </h3>
                                </div>
                                <div class="card mt-4">
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="payor" class="form-label">Payor Name (optional)</label>
                                            <input type="text" class="form-control @error('payor') is-invalid @enderror" id="payor" name="payor" value="{{ old('payor') }}">
                                            @error('payor')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="mb-3">
                                            <div class="text-end">
                                                <label for="previous" class="form-label">Arrears</label>
                                                <h2>PHP {{number_format((float)($data['current_bill']['previous_unpaid'] ?? 0), 2)}}</h2>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <div class="text-end">
                                                <label for="total_charges" class="form-label">Current Billing</label>
                                                <h2 class="fw-bold">PHP {{number_format((float)$data['current_bill']['amount'] - (float) $data['current_bill']['previous_unpaid'] ?? 0, 2)}}</h2>
                                            </div>
                                            <div class="text-end">
                                                <h6 class="text-danger" style="font-size: 12px;">+ PHP {{number_format((float)($data['current_bill']['penalty'] ?? 0), 2)}} (PENALTY)</h6>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end align-items-center gap-3 mb-2">
                                            <div class="text-end">
                                                <label for="total_charges" class="form-label">Total Amount</label>
                                                <h1 class="fw-bold text-danger">PHP {{number_format((float) $data['current_bill']['amount'] + (float) $data['current_bill']['penalty'] ?? 0, 2)}}</h1>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end w-100">
                                            <hr class="w-75">
                                        </div>
                                        <div class="d-flex justify-content-end align-items-center gap-3 mb-4">
                                            <div class="text-end">
                                                <label for="payment_amount" class="form-label">Payment Amount</label>
                                                <input type="text" class="form-control form-control-lg text-end" id="payment_amount" name="payment_amount" value="{{old('payment_amount', 0)}}">
                                                @error('payment_amount')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end align-items-center gap-3 mb-1">
                                            <div class="text-end">
                                                <label for="changeAmount" class="form-label">Change</label>
                                                <h2 class="text-primary fw-bold" id="changeAmount">PHP 0.00</h2>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end gap-3 text-end my-5">
                                            <button type="submit" class="mb-3 btn btn-primary px-5 py-3 text-uppercase fw-bold" name="payment_type" value="cash">Pay Cash</button>
                                            <button class="mb-3 btn btn-outline-primary px-5 py-3 text-uppercase fw-bold" name="payment_type" value="online">Pay Online</button>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="bg-primary d-flex align-items-center justify-content-center mt-4 p-3 text-uppercase fw-bold text-white">
                                    <h3 class="ms-2 mb-0 text-center">
                                        Already Paid
                                    </h3>
                                </div>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
@endsection

@section('script')
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


            const isPaid = '{{$data['current_bill']['isPaid'] == true}}';

            if(!isPaid) {

                async function checkPaymentStatus() {

                    const reference_no = '{{$reference_no}}';
                    const url = `{!! route('transaction.status', ['reference_no' => '__reference_no__']) !!}`.replace('__reference_no__', encodeURIComponent(reference_no));

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{csrf_token()}}'
                            },
                            body: JSON.stringify({ isApi: true })
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP error! Status: ${response.status}`);
                        }

                        const data = await response.json();

                        if (data.status == 'paid') {
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

            const total = '{{(float) $data['current_bill']['amount'] + (float) $data['current_bill']['penalty']}}';
            let changeAmount = '';

            $('#payment_amount')
                .val('0') 
                .on('focus', function () {
                    if ($(this).val() === '0') {
                        $(this).val('');
                    }
                })
                .on('blur', function () {
                    if ($(this).val().trim() === '') {
                        $(this).val('0');
                    }
                })
                .on('input', function () {
                    let input = $(this).val();

                    // Allow only digits and decimal point
                    input = input.replace(/[^0-9.]/g, '');

                    // Ensure only one decimal point
                    if ((input.match(/\./g) || []).length > 1) {
                        input = input.substring(0, input.length - 1);
                    }

                    $(this).val(input);

                    let value = parseFloat(input) || 0;
                    let change = (value - total).toFixed(2);

                    if (value < total) {
                        $('#changeAmount').text('PHP 0.00');
                    } else {
                        $('#changeAmount').text('PHP ' + change);
                    }
                });




        });
    </script>
@endsection

