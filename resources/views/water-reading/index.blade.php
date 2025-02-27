@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="inner-content mt-5">
                <form action="{{route('water-reading.store')}}" method="POST">
                    @csrf
                    @method('POST')             
                    <div class="row d-flex justify-content-center pb-5">
                        <div class="col-12 col-md-7">
                            <div class="col-12 col-md-12 mb-3">
                                <div class="card shadow border-0 p-2 pb-0 pt-4">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12 mb-5">
                                                <label for="meter_no" class="form-label">Contract No / Meter No</label>
                                                <input type="text" class="form-control h-extend @error('meter_no') is-invalid @enderror" id="meter_no" name="meter_no" value="{{ old('meter_no') }}" placeholder="########">
                                                @error('meter_no')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-12 mb-3">
                                <div class="card shadow border-0 p-2 pt-3">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-12 mb-5">
                                                <label for="present_reading" class="form-label">Present Reading</label>
                                                <input type="number" class="form-control h-extend @error('present_reading') is-invalid @enderror" id="present_reading" name="present_reading" value="{{ old('present_reading', 0) }}" placeholder="########">
                                                @error('present_reading')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6 mb-5">
                                                <label for="previous_reading" class="form-label">Previous Reading</label>
                                                <input type="text" class="form-control restricted h-extend @error('previous_reading') is-invalid @enderror" id="previous_reading" name="previous_reading" value="{{ old('previous_reading', 0) }}" readonly>
                                                @error('previous_reading')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-6 mb-5">
                                                <label for="consumption" class="form-label">Consumption</label>
                                                <input type="text" class="form-control restricted h-extend @error('consumption') is-invalid @enderror" id="consumption" name="consumption" value="{{ old('consumption', 0) }}" readonly>
                                                @error('consumption')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-end mt-4" id="action">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-5 mb-3">
                            <div class="card shadow border-0 p-2 pb-4">
                                <div class="card-body" id="client-info">
                                    <div class="loader pt-3">
                                        <div class="text-uppercase fw-bold text-muted">Waiting for reading...  <i class='bx bx-loader-alt bx-spin' ></i></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
    <style>
        .h-extend {
            height: 50px;
        }
    </style>
@endsection

@section('script')
<script>
    $(function () {
        @if (session('alert'))
            setTimeout(() => {
                let { status, message } = @json(session('alert'));
                alert(status, message);
            }, 100);
        @endif

        let globalSearch = null;
        let globalPreviousReading = null;

        const $meterNo = $('#meter_no');
        const $presentReading = $('#present_reading');
        const $previousReading = $('#previous_reading');
        const $consumption = $('#consumption');
        const $clientInfo = $('#client-info');
        const $action = $('#action');

        // Parse old values safely
        const oldMeterNo = '{{ old('meter_no') }}'.trim();
        const oldPresentReading = parseFloat('{{ old('present_reading', 0) }}') || 0;
        const oldPreviousReading = parseFloat('{{ old('previous_reading', 0) }}') || 0;
        const oldConsumption = parseFloat('{{ old('consumption', 0) }}') || 0;

        // Initialize values from old inputs
        if (oldMeterNo) {
            getWaterData(oldMeterNo, true);
        } else {
            calculateConsumption(oldPresentReading, oldPreviousReading, oldConsumption);
        }

        $meterNo.on('input', function () {
            const meterNo = $(this).val().trim();
            if (meterNo) {
                getWaterData(meterNo);
            } else {
                resetForm();
            }
        });

        $presentReading.on('input', function () {
            if (!globalSearch) {
                Swal.fire({ icon: "error", title: "Oops...", text: "Please provide meter no. first" });
                return;
            }
            calculateConsumption(parseFloat($(this).val()), globalPreviousReading);
        });

        function calculateConsumption(presentReading, previousReading, storedConsumption = null) {
            let computedConsumption = presentReading - previousReading;
            if (isNaN(computedConsumption)) {
                computedConsumption = 0;
            }
            console.log(presentReading + '-' + previousReading);
            $consumption.val(computedConsumption);
        }

        function getWaterData(meterNo, isOldLoad = false) {
            globalSearch = meterNo;

            $.ajax({
                url: '{{ route(Route::currentRouteName()) }}',
                type: 'GET',
                data: { meter_no: meterNo },
                success: function (response) {
                    if (response.status !== 'success' || !response.client) {
                        showWaitingForReading();
                        return;
                    }

                    const { firstname, lastname, address, contact_no, contract_no, contract_date } = response.client;
                    const reading = response.reading ?? {};

                    $clientInfo.html(`
                        <h5 class="text-uppercase mb-4 mt-3">Client Information</h5>
                        <table class="table table-bordered">
                            <tr><th>Full Name</th><td>${firstname ?? ''} ${lastname ?? ''}</td></tr>
                            <tr><th>Address</th><td>${address ?? ''}</td></tr>
                            <tr><th>Contact No</th><td>${contact_no ?? ''}</td></tr>
                            <tr><th>Contract No</th><td>${contract_no ?? ''}</td></tr>
                            <tr><th>Contract Date</th><td>${contract_date ?? ''}</td></tr>
                            <tr><th>Meter No</th><td>${meterNo ?? ''}</td></tr>
                        </table>
                    `);

                    const prevReading = parseFloat(reading.previous_reading ?? 0) || 0;
                    const presReading = parseFloat(reading.present_reading ?? 0) || 0;

                    $previousReading.val(presReading);
                    globalPreviousReading = presReading;

                    // If loading from old values, prioritize them
                    if (isOldLoad) {
                        calculateConsumption(oldPresentReading, oldPreviousReading, oldConsumption);
                    }

                    $action.html(`<button type="submit" class="btn btn-primary px-5 py-3 text-uppercase fw-bold">Print</button>`);
                },
                error: function () {
                    resetForm('<div class="text-danger">Error fetching data</div>');
                }
            });
        }

        function showWaitingForReading() {
            $clientInfo.html(`<div class="loader pt-3"><div class="text-uppercase fw-bold text-muted">Waiting for reading... <i class='bx bx-loader-alt bx-spin'></i></div></div>`);
            $action.html('');
            $consumption.val(0);
        }

        function resetForm(errorMessage = '') {
            $previousReading.val(0);
            $presentReading.val(0);
            $consumption.val(0);
            $clientInfo.html(errorMessage);
            $action.html('');
        }

    });
</script>
@endsection
