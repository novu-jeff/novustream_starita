@extends('layouts.status')

@section('base')
@php
    $status = strtolower($payload['status'] ?? 'unknown');
    $isSuccess = in_array($status, ['paid', 'completed', 'succeeded'], true);
    $isPending = in_array($status, ['pending', 'processing'], true);
    $iconClass = $isSuccess ? 'success' : ($isPending ? 'pending' : 'error');
    $pillClass = $isSuccess ? '' : ($isPending ? 'pending' : 'error');
@endphp
<div class="outer-wrapper">
    <div class="inner-wrapper">
        <div class="brand-mark">
            <img class="logo-starita" src="{{ asset('images/client1nobg.png') }}" alt="Sta. Rita Water District">
        </div>

        <div class="wrapper">
            <div class="top">
                <div class="icon status-mark {{ $iconClass }}">
                    @if($isSuccess)
                        <box-icon color="white" size="md" name="check"></box-icon>
                    @elseif($isPending)
                        <box-icon color="white" size="md" name="time-five"></box-icon>
                    @else
                        <box-icon color="white" size="md" name="x"></box-icon>
                    @endif
                </div>
                <div class="header">
                    <h5>{{ $payload['title'] ?? 'Payment Status' }}</h5>
                    <p>{{ $payload['message'] ?? '' }}</p>
                    <h3>PHP{{ $payload['amount'] ?? '0.00' }}</h3>
                </div>
            </div>

            <hr>

            <div class="mid">
                <h6>Payment Details :</h6>
                <div class="details">
                    <div class="items">
                        <div>Reference No:</div>
                        <div>{{ $payload['reference_no'] ?? '-' }}</div>
                    </div>
                    <div class="items">
                        <div>Payment Status:</div>
                        <div><span class="status-pill {{ $pillClass }}">{{ ucwords($status === 'unknown' ? ($payload['status'] ?? 'Unknown') : $status) }}</span></div>
                    </div>
                    <div class="items">
                        <div>Date:</div>
                        <div>{{ $payload['date_paid'] ?? now()->format('M d, Y h:i A') }}</div>
                    </div>

                    @if(!$isSuccess && isset($payload['expires_at']))
                        <div class="items">
                            <div>Expires On:</div>
                            <div class="{{ \Carbon\Carbon::parse($payload['expires_at'])->isPast() ? 'text-danger fw-bold' : 'text-warning fw-bold' }}">
                                {{ \Carbon\Carbon::parse($payload['expires_at'])->format('M d, Y h:i A') }}
                                @if(\Carbon\Carbon::parse($payload['expires_at'])->isPast())
                                    (Expired)
                                @else
                                    ({{ \Carbon\Carbon::parse($payload['expires_at'])->diffForHumans() }})
                                @endif
                            </div>
                        </div>
                    @endif

                    <hr>

                    <div class="items">
                        <div>Total Payment</div>
                        <div>PHP{{ $payload['amount'] ?? '0.00' }}</div>
                    </div>
                </div>

                <hr>

                <div class="payment-id">
                    <div class="label">Payment ID</div>
                    <div class="value">{{ $payload['payment_id'] ?? '-' }}</div>
                </div>
            </div>
        </div>

        <a class="wrapper help-card no-print" href="mailto:support@novupay.ph">
            <span class="icon">
                <box-icon color="white" name="question-mark"></box-icon>
            </span>
            <span class="help-copy">
                <strong>Trouble with your payment?</strong>
                <span>Let us know on our help center!</span>
            </span>
            <span class="icon">
                <box-icon color="white" name="chevron-right"></box-icon>
            </span>
        </a>

        @if($isSuccess)
            <div class="wrapper actions no-print">
                <div class="bottom">
                    <button type="button" class="btn-receipt" onclick="window.print()">
                        <box-icon color="white" name="download"></box-icon>
                        Download Receipt
                    </button>
                    <a class="btn-back" href="{{ url('/') }}">
                        <box-icon color="white" name="arrow-back"></box-icon>
                        Go Back
                    </a>
                </div>
            </div>
        @endif

        <div class="powered">
            <div class="powered-by">
                <span>Powered by</span>
                <img class="logo-novulutions" src="{{ asset('images/novulutions-logo.png') }}" alt="Novulutions">
            </div>
            <img class="logo-novupay" src="{{ asset('images/novupay-logo.png') }}" alt="NovuPay">
            <img class="logo-novustream" src="{{ asset('images/novustreamlogo.png') }}" alt="NovuStream">
        </div>
    </div>
</div>
@endsection
