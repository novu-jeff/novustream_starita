@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="inner-content mt-5 pb-5 mb-5">
                <form action="{{route('reading.store')}}" method="POST">
                    @csrf
                    @method('POST')     
                    <div class="row d-flex justify-content-center pb-5">
                        <div class="col-12 col-md-7">
                            <div class="col-12 col-md-12 mb-3">
                                <div class="card shadow border-0 p-2 pb-0 pt-4">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="zone_no" class="form-label">Zone</label>
                                                <select name="zone_no" id="zone_no" class="form-select dropdown-toggle">
                                                    <option value="all"> All </option>
                                                    @foreach($zones as $zone)
                                                        <option value="{{$zone}}"> {{$zone}} </option>
                                                    @endforeach
                                                </select>
                                                @error('zone_no')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="filter" class="form-label">Filter</label>
                                                <select name="filter" id="filter" class="form-select dropdown-toggle">
                                                    <option value="50"> 50 </option>
                                                    <option value="100"> 100 </option>
                                                    <option value="300"> 300 </option>
                                                    <option value="500"> 500 </option>
                                                    <option value="700"> 700 </option>
                                                    <option value="1000"> 1000 </option>
                                                </select>
                                                @error('filter')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="search_by" class="form-label">Search By</label>
                                                <select name="search_by" id="search_by" class="form-select dropdown-toggle">
                                                    <option value="name"> Name </option>
                                                    <option value="account_no">Account No</option>
                                                    <option value="meter_serial_no">Meter No</option>
                                                </select>
                                                @error('search_by')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <label for="search" class="form-label">Search</label>
                                                <input type="text" name="search" id="search" class="form-control" placeholder="Tap to search...">
                                                @error('search')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-5 mb-3">
                            <div class="concessionaire-result">
                                
                            </div>
                            <div class="concessionaire-list">

                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- Modal -->
        <div class="modal fade" id="accountModal" tabindex="-1" aria-labelledby="accountModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header px-4">
                        <h5 class="modal-title text-uppercase" id="accountModalLabel">Proceed Reading</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4" id="accountModalBody">
                    </div>
                </div>
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
    let selectedAccountNo = null;
    let offset = 0;
    const limit = 50;
    let isLoading = false;
    let hasMoreData = true;

    $(function () {
        @if (session('alert'))
            setTimeout(() => {
                const { status, message } = @json(session('alert'));
                alert(status, message);
            }, 100);
        @endif

        function fetchAccountData(append = false) {
            if (isLoading || !hasMoreData) return;
            isLoading = true;

            const zone = $('#zone_no').val();
            const filter = $('#filter').val();
            const searchBy = $('#search_by').val();
            const search = $('#search').val();

            $.get('{{ route(Route::currentRouteName()) }}', {
                zone,
                filter,
                search_by: searchBy,
                search,
                offset,
                limit
            }, function (data) {
                isLoading = false;

                if (!append) {
                    $('.concessionaire-list').empty();
                    offset = 0;
                    hasMoreData = true;
                }

                if (!data.length) {
                    if (!append) {
                        $('.concessionaire-list').html(`
                            <div class="alert alert-danger text-uppercase text-center shadow" role="alert">
                                No records found.
                            </div>
                        `);
                        $('.concessionaire-result').html(`
                            <div class="text-uppercase fw-bold text-muted fst-italic mb-2">
                                Accounts Found: 0
                            </div>
                        `);
                    }
                    hasMoreData = false;
                    return;
                }

                if (!append) {
                    $('.concessionaire-result').html(`
                        <div class="text-uppercase fw-bold text-muted fst-italic mb-2" data-count="${data.length}">
                            Accounts Found: ${data.length}
                        </div>
                    `);
                } else {
                    const $resultDiv = $('.concessionaire-result > div');
                    const currentCount = parseInt($resultDiv.data('count') || 0);
                    const newCount = currentCount + data.length;
                    $resultDiv.data('count', newCount);
                    $resultDiv.html(`Accounts Found: ${newCount}`);
                }

                data.forEach((account, index) => {
                    const html = `
                        <div class="card shadow mb-3 account-card" data-index="${offset + index}" style="cursor: pointer; border: 2px solid #32667e" data-account='${JSON.stringify(account)}'>
                            <div class="card-body">
                                <h5 class="card-title mb-0 fw-normal">Account No: ${account.account_no}</h5>
                                <hr class="my-2 mb-2">
                                <h5 class="fw-normal">Meter Serial No: ${account.meter_serial_no}</h5>
                                <h4>${account.user ? account.user.name : 'N/A'}</h4>
                                <h5 class="fw-normal text-capitalize">${account.address ?? 'N/A'}</h5>
                            </div>
                        </div>
                    `;
                    $('.concessionaire-list').append(html);
                });

                offset += data.length;

                if (data.length < limit) {
                    hasMoreData = false;
                }

            }).fail((jqXHR, textStatus, errorThrown) => {
                isLoading = false;
                console.error('Error fetching data:', textStatus, errorThrown);
            });
        }

        $(document).on('click', '.account-card', function () {
            const account = $(this).data('account');
            selectedAccountNo = account.account_no;

            $.get('{{ route(Route::currentRouteName()) }}', {
                account_no: account.account_no,
                isGetPrevious: true,
            }, function (response) {
                const previousReading = parseFloat(response.previous_reading ?? 0);
                const suggestedNextMonth = response.suggestedNextMonth;

                const modalContent = `
                    <p class="mb-1"><strong class="text-uppercase">Account No:</strong> ${account.account_no}</p>
                    <p class="mb-1"><strong class="text-uppercase">Name:</strong> ${account.user?.name ?? 'N/A'}</p>
                    <p class="mb-1"><strong class="text-uppercase">Address:</strong> ${account.address ?? 'N/A'}</p>
                    <hr>
                    <div class="row mt-3">
                        @if(env('IS_TEST_READING')) 
                            <div class="col-md-12 mb-3">
                                <label for="reading_month" class="form-label">Testing Month</label>
                                <input type="date" class="form-control h-extend" id="reading_month" name="reading_month" value="${suggestedNextMonth}" placeholder="########">
                            </div>
                        @endif
                        <div class="col-md-12 mb-3">
                            <label for="present_reading" class="form-label">Present Reading</label>
                            <input type="number" class="form-control h-extend" id="present_reading" value="0" placeholder="########">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="previous_reading" class="form-label">Previous Reading</label>
                            <input type="text" class="form-control restricted h-extend" id="previous_reading" value="${previousReading}" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="consumption" class="form-label">Consumption</label>
                            <input type="text" class="form-control restricted h-extend" id="consumption" value="0" readonly>
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-primary px-5 py-3 text-uppercase fw-bold d-none" id="proceedButton">Proceed</button>
                    </div>
                `;
                $('#accountModalBody').html(modalContent);
                $('#accountModal').modal('show');
            });
        });

        $(document).on('input', '#present_reading', function () {
            const present = parseFloat($(this).val()) || 0;
            const previous = parseFloat($('#previous_reading').val()) || 0;
            const consumption = Math.max(present - previous, 0);
            $('#consumption').val(consumption);

            if (present > 0 && present > previous) {
                $('#proceedButton').removeClass('d-none');
            } else {
                $('#proceedButton').addClass('d-none');
            }
        });

        $(document).on('click', '#proceedButton', function () {
            const presentReading = $('#present_reading').val();
            const previousReading = $('#previous_reading').val();
            const readingMonth = $('#reading_month').val() ?? null;

            if (!selectedAccountNo) {
                alert('error', 'Account number is missing.');
                return;
            }

            if (!presentReading || isNaN(presentReading) || Number(presentReading) <= Number(previousReading)) {
                alert('error', 'Present reading must be greater than previous reading.');
                return;
            }

            const postData = {
                reading_month: readingMonth,
                account_no: selectedAccountNo,
                previous_reading: previousReading,
                present_reading: presentReading,
            };

            $.ajax({
                url: '{{ route("reading.store") }}',
                type: 'POST',
                data: JSON.stringify(postData),
                contentType: 'application/json',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                success: function (response) {
                    alert(response.status, response.message);
                    $('#accountModal').modal('hide');
                    if (response.redirect_url) {
                        setTimeout(() => {
                            window.location.href = response.redirect_url;
                        }, 2000);
                    } else {
                        // Reset and reload fresh data after update
                        offset = 0;
                        hasMoreData = true;
                        fetchAccountData();
                    }
                },
                error: function (xhr) {
                    let errorMsg = 'An error occurred.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    alert('error', errorMsg);
                }
            });
        });

        // On filters change reset and reload data
        $('#zone_no, #filter, #search_by').on('change', function () {
            offset = 0;
            hasMoreData = true;
            fetchAccountData();
        });

        // Search with debounce
        $('#search').on('keyup', function () {
            clearTimeout($.data(this, 'timer'));
            const wait = setTimeout(() => {
                offset = 0;
                hasMoreData = true;
                fetchAccountData();
            }, 400);
            $(this).data('timer', wait);
        });

        // Infinite scroll for accounts list container
        $('.concessionaire-list').on('scroll', function () {
            const $list = $(this);
            if ($list.scrollTop() + $list.innerHeight() >= $list[0].scrollHeight - 20) {
                fetchAccountData(true);
            }
        });

        // Initial data load
        fetchAccountData();
    });
</script>
@endsection
