@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header">
                <h1>Account Overview</h1>
            </div>
            <div class="inner-content mt-5">
                @if($statement) 
                    <div class="row">
                        <div class="col-12 col-md-6 mb-3">
                            <div class="bg-info mt-1 p-3 text-uppercase fw-bold text-white fs-5">
                                Payment Due Date:
                                <span class="ms-2 text-decoration-underline">
                                    @if(!$statement->isPaid)
                                        {{\Carbon\Carbon::parse($statement->due_date)->format('F d, Y')}}
                                    @else
                                        Already Paid
                                    @endif
                                </span>
                            </div>
                            <div class="card shadow border-0 p-4 mt-3">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <h5 class="text-uppercase fw-bold text-muted mb-3">Account Name: <span class="ms-3 fw-normal">{{$data->firstname . ' ' . $data->lastname}}</span><h5>
                                        <h5 class="text-uppercase fw-bold text-muted mb-3">Address: <span class="ms-3 fw-normal">{{$data->address ?? 'N/A'}}</span><h5>
                                        <h5 class="text-uppercase fw-bold text-muted mb-3">Property Type: <span class="ms-3 fw-normal">{{$data->property_type->name ?? 'N/A'}}</span><h5>
                                        <h5 class="text-uppercase fw-bold text-muted mb-3">Meter No: <span class="ms-3 fw-normal">{{$data->meter_no ?? 'N/A'}}</span><h5>
                                        <h5 class="text-uppercase fw-bold text-muted mb-3">Contract No: <span class="ms-3 fw-normal">{{$data->contract_no ?? 'N/A'}}</span><h5>
                                        <h5 class="text-uppercase fw-bold text-muted mb-3">Contract Date: <span class="ms-3 fw-normal">{{\Carbon\Carbon::parse($data->contract_date)->format('F d, Y') ?? 'N/A'}}</span><h5>    
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6 mb-3">
                            <div class="card shadow border-0 p-1">
                                <div class="card-body">
                                    <div class="bg-primary mt-1 p-3 text-uppercase fw-bold text-white fs-5">Statement of Account as of <span class="text-decoration-underline text-offset-2">{{Carbon\Carbon::now()->format('F d, Y')}}</span></div>
                                    <div class="note mt-3 ms-0 fst-italic text-uppercase fw-medium" style="font-size: 12px;"><strong>Disclaimer:</strong> Successful payments will be reflected on the next statement and can be viewd via the <strong>Payment History</strong></div>
                                    <hr class="my-4">
                                    <div class="bg-danger d-flex align-items-center justify-content-between mt-1 p-3 text-uppercase fw-bold text-white">Total Amount Due: 
                                        <h3 class="ms-2">
                                            @if(!$statement->isPaid)
                                                ₱{{number_format($statement->amount, 2)}}
                                            @else
                                                ₱0.00
                                            @endif
                                        </h3>
                                    </div>
                                    <div class="mt-3 text-center">
                                        <a href="#" class="text-uppercase fw-medium" style="font-size: 13px">View Statement</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="alert alert-primary text-uppercase fw-medium text-center">No data found, Please make sure to have a meter no. connected to this account!</div>
                @endif
            </div>
        </div>
    </main>
@endsection
