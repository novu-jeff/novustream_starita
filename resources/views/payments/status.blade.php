
@extends('layouts.status')

@section('base')
@php
    $status = strtolower($payload['status'] ?? 'unknown');
    $isSuccess = in_array($status, ['paid', 'completed', 'succeeded'], true);
    $isPending = in_array($status, ['pending', 'processing'], true);
    $iconClass = $isSuccess ? 'success' : ($isPending ? 'pending' : 'error');
    $pillClass = $isSuccess ? '' : ($isPending ? 'pending' : 'error');
@endphp

<style>
    .icon {
        background-color: #28a745;
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top .icon {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        margin: 0 auto 15px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .top .icon box-icon {
        width: 48px;
        height: 48px;
    }

    /* Help center alignment */
.others > .d-flex {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
}

.others .help-text {
    flex: 1;
    min-width: 0;
    text-align: center;
}

.others .help-text div {
    font-size: 14px;
    line-height: 1.5;
}

/* Bottom buttons alignment */
.bottom {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    width: 100%;
}

.bottom button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-align: center;
    line-height: 1.2;
    flex: 1;
    min-width: 0;
}

.bottom button box-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    vertical-align: middle;
}

/* Help center container */
.help-center {
    width: 100%;
}

.help-center-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
}

/* Left and right icons */
.help-center-icon,
.help-center-arrow {
    width: 44px;
    height: 44px;
    min-width: 44px;
    border-radius: 50%;
    background-color: #28a745;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.help-center-icon box-icon,
.help-center-arrow box-icon {
    width: 24px;
    height: 24px;
}

/* Center the text between both icons */
.help-center-text {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.help-center-text div {
    width: 100%;
    font-size: 14px;
    line-height: 1.5;
}


@media (max-width: 767px) {
    .others .help-text div {
        font-size: 13px;
    }

    .bottom {
        gap: 8px;
    }

    .bottom button {
        gap: 5px;
        font-size: 14px;
    }

    .help-center-content {
        gap: 10px;
    }

    .help-center-icon,
    .help-center-arrow {
        width: 40px;
        height: 40px;
        min-width: 40px;
    }

    .help-center-text div {
        font-size: 13px;
    }
}
</style>

<div class="outer-wrapper">
    <div class="inner-wrapper">
        <div class="brand-mark">
            <img class="logo-starita" src="{{ asset('images/client1nobg.png') }}" alt="Sta. Rita Water District">
        </div>

        <div class="wrapper">
            <div class="top">
                <div class="icon {{ in_array(strtolower($payload['status']), ['paid', 'completed', 'succeeded']) ? 'success' : 'error' }}">
                    @if(in_array(strtolower($payload['status']), ['paid', 'completed', 'succeeded']))
                        <box-icon color="white" size="md" name="check"></box-icon>
                    @elseif(strtolower($payload['status']) === 'pending')
                        <box-icon color="white" name="time-five"></box-icon>
                    @else
                        <box-icon color="white" name="x"></box-icon>
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

                    {{-- Show expiration if not yet paid --}}
                    @if(!in_array(strtolower($payload['status']), ['paid', 'completed', 'succeeded']) && isset($payload['expires_at']))
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

                <div class="text-center mt-3">
                    <div class="fw-bold">Payment ID</div>
                    <div class="fw-bold text-muted mt-1">
                        {{ $payload['payment_id'] ?? '-' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="wrapper">
            <div class="help-center">
                <div class="help-center-content">

                    <div class="help-center-icon">
                        <box-icon color="white"
                                animation="tada"
                                name="question-mark"></box-icon>
                    </div>

                    <div class="help-center-text">
                        <div class="mb-0 fw-bold text-muted">
                            Trouble with your payment?
                        </div>

                        <div class="mb-0 fw-bold text-muted">
                            Let us know on our help center!
                        </div>
                    </div>

                    <div class="help-center-arrow">
                        <a href="{{route('client.support-ticket.create')}}">
                            <box-icon name="chevron-right"
                                    color="white"></box-icon>
                        </a>
                    </div>

                </div>
            </div>
        </div>


        @if(in_array(strtolower($payload['status']), ['paid', 'completed', 'succeeded']))
            <div class="wrapper">
                <div class="bottom">
                    <button onclick="window.print()">
                        <box-icon color="white" name="download"></box-icon>
                        <span>Download Receipt</span>
                    </button>

                    <button onclick="window.location.href='{{ $payload['back_url'] ?? '/' }}'">
                        <box-icon color="dark" name="arrow-back"></box-icon>
                        <span>Go Back</span>
                    </button>
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
