<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Print Bill {{$reference_no}}</title>
</head>
<body>
    <div class="print-controls" style="display: flex; justify-content: center; margin: 50px 0 50px 0; gap: 12px;">

        @php
            $previousUrl = url()->previous();
            $currentUrl = url()->current();
            $fallbackUrl = Auth::user()->user_type == 'client' ? route('account-overview.show') : route('reading.index');
            $backUrl = ($previousUrl !== $currentUrl) ? $previousUrl : $fallbackUrl;
        @endphp

        <a href="{{ $backUrl }}" 
            style="border: 1px solid #32667e; padding: 12px 40px; text-transform: uppercase; display: flex; align-items: center; gap: 8px; text-decoration: none; color: #32667e; background-color: transparent; border-radius: 5px; font-weight: bold;">
            <i style="font-size: 18px;" class='bx bx-left-arrow-alt'></i> Go Back
        </a>

        <button 
            class="download-js" 
            data-target="#bill" 
            data-filename="{{$data['current_bill']->reference_no}}" 
            style="background-color: #32667e; color: white; padding: 12px 40px; text-transform: uppercase; display: flex; align-items: center; gap: 8px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">
            <i style="font-size: 18px;" class='bx bxs-download'></i> Download
        </button>

        <button 
            class="print-js" 
            style="background-color: #32667e; color: white; padding: 12px 40px; text-transform: uppercase; display: flex; align-items: center; gap: 8px; border: none; border-radius: 5px; font-weight: bold; cursor: pointer;">
            <i style="font-size: 18px;" class='bx bxs-printer'></i> Print
        </button>
    </div>
    <div style="padding-bottom: 50px">
        <div id="bill" style="margin-top: 30px">
            <div class="bill-container">
                <div style="position: relative; width: 100%; max-width: 300px; margin: 0 auto; padding: 20px; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                    @if($data['current_bill']->isPaid == true)
                        <div class="isPaid" style="padding: 10px 30px 10px 30px; position: absolute; right: -10px; top: 4px; text-transform: uppercase; color: red; letter-spacing: 3px; font-size: 12px; font-weight: 600">
                            PAID
                        </div>
                    @endif
                    @php
                        $logoPath = env('APP_PRODUCT') === 'novustream' 
                                    ? public_path('images/novustreamlogodarken.png') 
                                    : public_path('images/novusurgelogodarken.png');

                        $base64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
                    @endphp

                    <div style="text-align: center; margin-top: 0; margin-bottom: 10px; padding-bottom: 10px;">
                        <img src="{{ asset(env('APP_PRODUCT') === 'novustream' ? 'images/novustreamlogo.png' : 'images/novupowerlogo.png') }}" 
                            alt="logo" class="web-logo">
                        <img src="{{ $base64 }}" alt="logo" class="print-logo">
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
                                <div>Account Name</div>
                                <div>{{$data['client']->name}}</div>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <div>Account No.</div>
                                <div>{{$data['client']->account_no ?? ''}}</div>
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
                        <h6 style="font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 0px; margin-top: 10px;">Billing Summary</h6>
                        <div style="text-align: center; font-size: 10px; text-transform: uppercase; display: flex; align-items: center; justify-content: center; gap: 20px;">
                            <div>
                                <div style="display: block; margin: 10px 0 5px 0;">
                                    <div>Bill Date</div>
                                    <div>{{$data['current_bill']->created_at->format('m/d/Y')}}</div>
                                </div>
                                <div style="display: block; margin: 5px 0 10px 0;">
                                    <div>Billing Period</div>
                                    <div>{{\Carbon\Carbon::parse($data['current_bill']->bill_period_from)->format('m/d/Y') . ' TO ' . \Carbon\Carbon::parse($data['current_bill']->bill_period_to)->format('m/d/Y')}}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div style="width: 100%; height: 1px; margin: 0px 0 10px 0; border-bottom: 1px dashed black;"></div>                    
                    <div>
                        <h6 style="font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 10px; margin-top: 10px;">Billing Details</h6>
                        <div style="font-size: 10px; text-transform: uppercase; display: flex; flex-direction: column; gap: 1px;">
                            @foreach($data['current_bill']->breakdown as $breakdown)
                                <div style="display: flex; justify-content: space-between;">
                                    <div>{{ $breakdown->name }} {{ !empty($breakdown->description) ? '(' . $breakdown->description . ')' : '' }}</div>
                                    <div>PHP {{number_format($breakdown->amount ?? 0, 2)}}</div>
                                </div>
                            @endforeach
                            <div style="margin: 5px 0 5px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                            <div style="display: flex; justify-content: space-between;">
                                <div>Amount Due</div>
                                <div>PHP {{number_format($data['current_bill']->amount, 2)}}</div>
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
                    <div>
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
                                        <div>PHP {{number_format($data['previous_payment']->amount, 2)}}</div>
                                    </div>
                                </div>      
                            </div>
                        @endif
                        <div style="width: 100%; height: 1px; margin: 10px 0 10px 0; border-bottom: 1px dashed black;"></div>                    
                        <div style="display: flex; justify-content: center; gap: 20px; align-items: center;">
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
                    </div>
                </div>                    
            </div>
        </div>
    </div>
    <style>

        @import url("https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&display=swap");

        .web-logo {
            width: 100px;
            margin: 0 auto 10px auto !important;
        }

        .print-logo {
            display: none !important;
        }

        @media print {
            
            @page {
                margin: 0mm 5mm 0mm 0mm;
            }
    
            body * {
                padding: 0px !important;
                box-shadow: none !important;
                visibility: visible !important;
                font-size: 10px !important;
                font-weight: 800;
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
</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/notyf@3/notyf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
@vite(['resources/js/app.js'])
@section('script')
    <script>
        $(function () {
            @if (session('alert'))
                setTimeout(() => {
                    let alertData = @json(session('alert'));
                    alert(alertData.status, alertData.message);
                }, 100);
            @endif
        });
    </script>
@endsection
</html>
