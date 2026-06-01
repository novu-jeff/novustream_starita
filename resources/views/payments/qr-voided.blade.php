@extends('layouts.status')

@section('base')
@php
    $status = strtolower($payload['status'] ?? 'voided');
@endphp
<div class="outer-wrapper">
    <div class="inner-wrapper">
        <div class="wrapper">
            <div class="top">
                <div class="icon {{ $status === 'paid' ? 'success' : ($status === 'active' ? 'warning' : 'error') }}">
                    @if($status === 'paid')
                        <box-icon color='white' size='md' name='check'></box-icon>
                    @elseif($status === 'active')
                        <box-icon color='white' name='time-five'></box-icon>
                    @else
                        <box-icon color='white' name='x'></box-icon>
                    @endif
                </div>
                <div class="header">
                    <h5>{{ $payload['title'] ?? 'QR Code Unavailable' }}</h5>
                    <p>{{ $payload['message'] ?? '' }}</p>
                </div>
            </div>

            <hr>

            <div class="mid">
                <h6>Bill Details</h6>
                <div class="details">
                    <div class="items">
                        <div>Reference No:</div>
                        <div>{{ $payload['reference_no'] ?? '-' }}</div>
                    </div>
                    <div class="items">
                        <div>Account No:</div>
                        <div>{{ $payload['account_no'] ?? '-' }}</div>
                    </div>
                    <div class="items">
                        <div>Account Name:</div>
                        <div>{{ $payload['account_name'] ?? '-' }}</div>
                    </div>
                    @if(!empty($payload['due_date']))
                        <div class="items">
                            <div>Due Date:</div>
                            <div>{{ $payload['due_date'] }}</div>
                        </div>
                    @endif
                    <div class="items">
                        <div>QR Status:</div>
                        <div>
                            @if($status === 'paid')
                                Paid
                            @elseif($status === 'active')
                                Active
                            @else
                                Voided (past due date)
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if($status === 'voided')
            <div class="wrapper">
                <div class="others">
                    <div class="d-block">
                        <div class="mb-0 fw-bold text-muted" style="font-size: 14px;">
                            To pay online, ask for a new SOA with an updated QR code at the Sta. Rita Water District office.
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
