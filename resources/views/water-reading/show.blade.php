@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="inner-content mt-5 pb-5">
                <div class="d-flex justify-content-center mb-5 gap-3">
                    <div class="btn btn-outline-primary px-5 py-3 text-uppercase d-flex align-items-center gap-2"><i style="font-size: 18px" class='bx bxs-download' ></i> Download</div>
                    <div class="btn btn-primary px-5 py-3 text-uppercase d-flex align-items-center gap-2"><i style="font-size: 18px" class='bx bxs-printer' ></i> Print</div>
                </div>
                <div class="bill-container">
                    <div style="width: 100%; max-width: 600px; margin: 0 auto; padding: 50px; background: white; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);">
                        <div style="text-align: center; margin-bottom: 16px;">
                            <h5>[LOGO]</h5>
                            <h5>[APP NAME]</h5>
                            <p style="text-transform: uppercase; margin: 0;">[CLIENT ADDRESS]</p>
                            <p style="text-transform: uppercase; margin: 0;">VAT Reg TIN: 218-595-528-000</p>
                            <p style="text-transform: uppercase; margin: 0;">Permit No. SP012021-0502-0912233-00000</p>
                        </div>
                    
                        <div style="margin-bottom: 8px;">
                            <h6 style="font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 8px;">Service Information</h6>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Contract No:</span></p>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Account No:</span></p>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Address:</span></p>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Type:</span></p>
                        </div>
                    
                        <div style="margin-bottom: 16px;">
                            <h6 style="font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 16px;">Billing Summary</h6>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Bill Reference No.:</span> 12345</p>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Bill Date:</span> March 06, 2023</p>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Billing Period:</span> 07 February 2023 - 06 March 2023</p>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Consumption:</span> 21 Cubic Meter</p>
                        </div>
                    
                        <div style="margin-bottom: 16px;">
                            <h6 style="font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 16px;">Billing Details</h6>
                            <div style="font-size: 13px; text-transform: uppercase; display: flex; flex-direction: column; gap: 10px;">
                                <div style="display: flex; justify-content: space-between; font-weight: bold;">
                                    <div>Current Charges</div>
                                    <div>Amount (PHP)</div>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <div>Total Vatable Current Charge</div>
                                    <div>0.00</div>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <div>VAT 12%</div>
                                    <div>0.00</div>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-weight: bold;">
                                    <div>Previous Unpaid Amount</div>
                                    <div>0.00</div>
                                </div>
                                <div style="margin: 10px 0 10px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>
                                <div style="display: flex; justify-content: space-between; font-weight: bold;">
                                    <div>Total Amount Due</div>
                                    <div>0.00</div>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-weight: bold;">
                                    <div>Due Date</div>
                                    <div>20 March 2023</div>
                                </div>
                            </div>
                        </div>
                        <div style="margin: 10px 0 10px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>                    
                        <div style="margin-bottom: 10px;">
                            <h6 style="font-weight: bold; text-transform: uppercase; text-align: center; margin: 18px; 0 15px 0;">Meter Reading Information</h6>
                            <div style="width: 100%; font-size: 13px;">
                                <div style="display: flex; justify-content: space-between; font-weight: bold; padding: 4px;">
                                    <div>Meter No</div>
                                    <div>Previous Reading</div>
                                    <div>Present Reading</div>
                                    <div>Consumption</div>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 4px;">
                                    <div>123</div>
                                    <div>35</div>
                                    <div>36</div>
                                    <div>1</div>
                                </div>
                            </div>                            
                        </div>
                        <div style="margin: 10px 0 10px 0; width: 100%; height: 1px; border-bottom: 1px dashed black;"></div>                    
                        <div style="margin-bottom: 12px;">
                            <h6 style="font-weight: bold; text-transform: uppercase; text-align: center; margin: 15px 0 10px 0;">Last Payment</h6>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Posting Date:</span> 13 February 2023</p>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Payment Ref No.:</span> 9082029</p>
                            <p style="margin-bottom: 4px; text-transform: uppercase; font-size: 13px;"><span style="font-weight: bold;">Total Amount Paid:</span> 1,890</p>
                        </div>
                    </div>                    
                </div>
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
        });
    </script>
@endsection
