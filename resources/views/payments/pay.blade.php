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
            <div class="inner-content mt-5 pb-5">
                <form action="{{route('payments.pay', ['reference_no' => $reference_no]) }}" method="POST">
                    @csrf  
                    <div class="row">
                        <div class="col-12 col-md-6">
                            <div id="bill">
                                <div class="bill-container">
                                    <div style="position: relative; width: 100%; max-width: 400px; margin: 0 auto; padding: 50px; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                                        @if($data['current_bill']->isPaid == true)
                                            <div style="padding: 10px 30px 10px 30px; position: absolute; right: 1px; top: 10px; text-transform: uppercase; color: red; letter-spacing: 3px; font-weight: 600">
                                                PAID
                                            </div>
                                        @endif
                                        <div style="text-align: center; margin: auto !important; padding-bottom: 10px;">
                                            <img src="{{asset('/images/novustreamlogo.png')}}" alt="logo" style="width: 100px; margin: 0 auto 10px auto">
                                            <p style="font-size: 12px; text-transform: uppercase; margin: 0;">VAT Reg TIN: 218-595-528-000</p>
                                            <p style="font-size: 12px; text-transform: uppercase; margin: 0;">Permit No. SP012021-0502-0912233-00000</p>
                                        </div>
                                        <div style="text-align:center; text-transform: uppercase; font-size: 14px; margin: 10px 0 10px 0;">
                                            <div style="font-weight: 800;">{{$data['current_bill']->reference_no}}</div>
                                        </div>
                                        <div style="width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                                        <div>
                                            <h6 style="font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 8px; margin-top: 10px;">Service Information</h6>
                                            <div style="font-size: 10px; text-transform: uppercase; display: flex; flex-direction: column; gap: 1px;">
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Contract No</div>
                                                    <div>{{$data['client']->contract_no ?? ''}}</div>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Account Name</div>
                                                    <div>{{$data['client']->firstname . ' ' . $data['client']->lastname}}</div>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Address</div>
                                                    <div>{{$data['client']->address ?? ''}}</div>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Type</div>
                                                    <div>{{$data['client']->property_types->name ?? ''}}</div>
                                                </div>                
                                            </div>
                                        </div>
                                        <div style="width: 100%; height: 1px; margin: 10px 0 10px 0; border-bottom: 1px dashed black;"></div>
                                        <div>
                                            <h6 style="font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 5px; margin-top: 10px;">Billing Summary</h6>
                                            <div style="text-align: center; font-size: 10px; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 60px;">
                                                <div>
                                                    <div style="display: block; margin: 5px 0 5px 0;">
                                                        <div>Bill Date</div>
                                                        <div>{{$data['current_bill']->created_at->format('m/d/Y')}}</div>
                                                    </div>
                                                    <div style="display: block; margin: 5px 0 5px 0;">
                                                        <div>Billing Period</div>
                                                        <div>{{\Carbon\Carbon::parse($data['current_bill']->bill_period_from)->format('m/d/Y') . ' TO ' . \Carbon\Carbon::parse($data['current_bill']->bill_period_to)->format('m/d/Y')}}</div>
                                                    </div>
                                                </div>
                                                <div>
                                                    {!! $qr_code !!}
                                                </div>
                                            </div>
                                        </div>
                                        <div style="width: 100%; height: 1px; margin: 10px 0 10px 0; border-bottom: 1px dashed black;"></div>                    
                                        <div>
                                            <h6 style="font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 10px; margin-top: 10px;">Billing Details</h6>
                                            <div style="font-size: 10px; text-transform: uppercase; display: flex; flex-direction: column; gap: 1px;">
                                                @foreach($data['current_bill']->breakdown as $breakdown)
                                                    <div style="display: flex; justify-content: space-between;">
                                                        <div>{{ $breakdown->name }} {{ !empty($breakdown->description) ? '(' . $breakdown->description . ')' : '' }}</div>
                                                        <div>₱{{number_format($breakdown->amount ?? 0, 2)}}</div>
                                                    </div>
                                                @endforeach
                                                <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Amount Due</div>
                                                    <div>₱{{number_format($data['current_bill']->amount, 2)}}</div>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Due Date</div>
                                                    <div>{{\Carbon\Carbon::parse($data['current_bill']->due_date)->format('m/d/Y')}}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>                    
                                        <div>
                                            <h6 style="font-weight: bold; text-transform: uppercase; text-align: center; margin-top: 10px; margin-bottom: 10px;">Meter Reading Information</h6>
                                            <div style="text-transform: uppercase; width: 100%; font-size: 10px; display: flex; flex-direction: column; gap: 1px;">
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Meter No</div>
                                                    <div>{{$data['current_bill']->reading->meter_no ?? 'N/A'}}</div>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Previous Reading</div>
                                                    <div>{{$data['current_bill']->reading->previous_reading ?? 'N/A'}}</div>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Present Reading</div>
                                                    <div>{{$data['current_bill']->reading->present_reading ?? 'N/A'}}</div>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Consumption</div>
                                                    <div>{{$data['current_bill']->reading->consumption ?? 'N/A'}}</div>
                                                </div>
                                            </div>                            
                                        </div>  
                                        @if($data['previous_payment'])
                                            <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>                    
                                            <h6 style="font-weight: bold; text-transform: uppercase; text-align: center; margin-top: 10px; margin-bottom: 10px;">Last Payment</h6>
                                            <div style="text-transform: uppercase; width: 100%; font-size: 10px; display: flex; flex-direction: column; gap: 1px;">
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Date Posted</div>
                                                    <div>{{\Carbon\Carbon::parse($data['previous_payment']->date_paid)->format('m/d/Y')}}</div>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Ref No.</div>
                                                    <div>{{$data['previous_payment']->reference_no}}</div>
                                                </div>
                                                <div style="display: flex; justify-content: space-between;">
                                                    <div>Amount</div>
                                                    <div>₱{{number_format($data['previous_payment']->amount, 2)}}</div>
                                                </div>
                                            </div>      
                                        @endif
                                        <div style="margin: 15px 0 15px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>                    
                                    </div>   
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6"> 
                            @if(!$data['current_bill']->isPaid)
                                <div class="bg-danger d-flex align-items-center justify-content-between mt-4 p-3 text-uppercase fw-bold text-white">
                                    Total Amount Due: 
                                    <h3 class="ms-2">
                                        ₱{{number_format($data['current_bill']->amount ?? 0, 2)}}
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
                                        <div class="d-flex justify-content-end align-items-center gap-3 mb-2">
                                            <div class="text-end">
                                                <label for="previous" class="form-label">Previous Unpaid</label>
                                                <h2>₱{{number_format($data['current_bill']->previous_unpaid ?? 0, 2)}}</h2>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end align-items-center gap-3 mb-2">
                                            <div class="text-end">
                                                <label for="total_charges" class="form-label">Current Charges</label>
                                                <h2 class="fw-bold">₱{{number_format($data['current_bill']->amount - $data['current_bill']->previous_unpaid ?? 0, 2)}}</h2>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end align-items-center gap-3 mb-2">
                                            <div class="text-end">
                                                <label for="total_charges" class="form-label">Total Charges</label>
                                                <h1 class="fw-bold text-danger">₱{{number_format($data['current_bill']->amount ?? 0, 2)}}</h1>
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
                                                <h2 class="text-primary fw-bold" id="changeAmount">₱0.00</h2>
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end my-5">
                                            <button type="submit" class="btn btn-primary px-5 py-3 text-uppercase fw-bold">Submit</button>
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
                    alert(alertData.status, alertData.message);
                }, 100);
            @endif

            const total = '{{$data['current_bill']->amount}}';
            let changeAmount = '';

            $('#payment_amount').on('keyup click', function() {
                let value = parseFloat($(this).val()) || 0; 
                let change = (value - total).toFixed(2);

                if (value < total) {
                    $('#changeAmount').text('₱0.00');
                } else {
                    $('#changeAmount').text('₱' + change);
                }
            });



        });
    </script>
@endsection

