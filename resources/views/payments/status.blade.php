@extends('layouts.status')

@section('base')
@php
    // dd($payload);
@endphp
<div class="outer-wrapper">
    <div class="inner-wrapper">
        <div class="wrapper">
            <div class="top">
                <div class="icon {{ in_array(strtolower($payload['status']), ['paid', 'completed', 'succeeded']) ? 'success' : 'error' }}">
                    @if(in_array(strtolower($payload['status']), ['paid', 'completed', 'succeeded']))
                        <box-icon color='white' size='md' name='check'></box-icon>
                    @elseif(strtolower($payload['status']) === 'pending')
                        <box-icon color='white' name='time-five'></box-icon>
                    @else
                        <box-icon color='white' name='x'></box-icon>
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
                        <div>{{ ucwords($payload['status'] ?? 'Unknown') }}</div>
                    </div>
                    <div class="items">
                        <div>Date:</div>
                        <div>{{ $payload['date_paid'] ?? now()->format('M d, Y h:i A') }}</div>
                    </div>

                    {{-- ✅ Show expiration if not yet paid --}}
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
                    <div class="fw-bold text-muted mt-1">{{ $payload['payment_id'] ?? '-' }}</div>
                </div>
            </div>
        </div>

        <div class="wrapper">
            <div class="others">
                <div class="d-flex justify-content-between m-auto align-items-center gap-3">
                    <div class="d-md-flex align-items-center gap-3">
                        <div class="icon d-none d-md-flex">
                            <box-icon color='white' animation='tada' name='question-mark'></box-icon>
                        </div>
                        <div class="d-block">
                            <div class="mb-0 fw-bold text-muted" style="font-size: 14px;">
                                Trouble with your payment?
                            </div>
                            <div class="mb-0 fw-bold text-muted" style="font-size: 14px;">
                                Let us know on our help center!
                            </div>
                        </div>
                    </div>
                    <div class="icon">
                        <box-icon name='chevron-right'></box-icon>
                    </div>
                </div>
            </div>
        </div>

        @if(in_array(strtolower($payload['status']), ['paid', 'completed', 'succeeded']))
            <div class="wrapper">
                <div class="bottom">
                    <button onclick="window.print()">
                        <box-icon color='white' name='download'></box-icon>
                        Download Receipt
                    </button>
                    <button onclick="window.location.href='/'">
                        <box-icon color='dark' name='arrow-back'></box-icon>
                        Go Back
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

@endsection
