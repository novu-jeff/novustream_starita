@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Pay Application Fee</h1>
                <a href="{{ route('payments.application-fees.index') }}"
                    class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Back to List
                </a>
            </div>

            <div class="inner-content mt-5 pb-5 mb-5">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-6">
                        <div class="card shadow-sm">
                            <div class="card-body">
                                <h5 class="fw-bold text-uppercase mb-3">Application Details</h5>

                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Application No.</span>
                                    <span class="fw-bold">{{ $application->application_no ?? 'N/A' }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Applicant</span>
                                    <span class="fw-bold">{{ $application->applicant_name }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Type</span>
                                    <span class="fw-bold">
                                        {{ $application->application_type === 'Others' ? $application->application_type_other : $application->application_type }}
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Service Address</span>
                                    <span class="fw-bold text-end">{{ $application->service_address }}</span>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="fs-5 text-uppercase fw-bold">Application Fee</span>
                                    <span class="fs-3 fw-bold text-danger">
                                        PHP {{ number_format((float) $application->application_fee_amount, 2) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <form action="{{ route('payments.application-fees.pay', $application->id) }}" method="POST" class="mt-4">
                            @csrf
                            <div class="mb-3">
                                <label for="payment_amount" class="form-label">Payment Amount</label>
                                <input
                                    type="text"
                                    class="form-control form-control-lg text-end"
                                    id="payment_amount"
                                    name="payment_amount"
                                    value="{{ old('payment_amount', number_format((float) $application->application_fee_amount, 2, '.', '')) }}"
                                >
                                @error('payment_amount')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary px-5 py-3 text-uppercase fw-bold">
                                    Pay Cash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('script')
<script>
    $(function () {
        $('#payment_amount').on('input', function () {
            let input = $(this).val().replace(/[^0-9.]/g, '');
            if ((input.match(/\./g) || []).length > 1) {
                input = input.substring(0, input.lastIndexOf('.'));
            }
            $(this).val(input);
        });

        @if (session('alert'))
            setTimeout(() => {
                alert(@json(session('alert.message')));
            }, 100);
        @endif
    });
</script>
@endsection
