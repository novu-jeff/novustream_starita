@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <div>
                    <h1>Import Files</h1>
                </div>
            </div>
            <div class="inner-content mt-5 pb-5">
                <form method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="file" class="form-label">CSV or Excel File</label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file">
                        </div> 
                        <div class="col-12 mt-3 text-muted">
                            <small class="text-uppercase fw-bold">What can you import?</small>
                            <ul class="mt-2 text-uppercase fw-bold" style="font-size: 12px;">
                                <li>Concessionaires Informations</li>
                                <li>Senior Citizen Discounts</li>
                                <li>Utility Rates</li>
                                <li>Status Codes</li>
                            </ul>
                        </div>
                        <div class="warning">

                        </div>
                    </div>
                    <div class="d-flex justify-content-end my-5">
                        <button type="submit" class="showBtn btn btn-primary px-5 py-3 text-uppercase fw-bold">Submit</button>
                    </div>
                </form>                
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

                
        $("form").on("submit", function (e) {

            e.preventDefault();

            showLoader();

            let formData = new FormData(this);

            $(".warning").html("");

            axios.post("{{ route(Route::currentRouteName()) }}", formData, {
                headers: {
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                    "Content-Type": "multipart/form-data"
                }
            })
            .then(response => {
                const res = response.data;
                const messages = res.messages || [];

                let displayHTML = "";
                let delay = 0;

                messages.forEach(({ sheet, status, message, errors = [] }) => {
                   
                     setTimeout(() => {
                        alert(status, `${message}`);
                    }, delay);

                    delay += 800;

                    let borderColor = status === 'success' ? 'success' : (status === 'warning' ? 'warning' : 'danger');
                    let textColor = status === 'success' ? 'text-success' : (status === 'warning' ? 'text-warning' : 'text-danger');

                    displayHTML += `
                        <hr class="mb-4">
                        <div class="card shadow p-3 rounded mb-3 border-start border-2 border-${borderColor}">
                            <div class="card-body">
                                <h6 class="fw-bold text-uppercase mb-2 ${textColor}">${sheet}</h6>
                                <p class="text-muted text-uppercase fw-bold">${message}</p>
                                ${errors.length ? `
                                    <div style="max-height: 200px; overflow-y: auto">
                                        <ul class="list-unstyled small ${textColor}">
                                            ${errors.map(err => `<li>${err}</li>`).join('')}
                                        </ul>
                                    </div>` : ''
                                }
                            </div>
                        </div>
                    `;
                });

                if (displayHTML) {
                    $(".warning").html(displayHTML);
                }
            })
            .catch(() => {
                alert("error", "Something went wrong while uploading.");
            })
            .finally(() => {
                hideLoader();
            });
        });


            function showLoader() {
                $('.showBtn').html("<i class='bx bx-loader-alt bx-spin' ></i>");
            }

            function hideLoader() {
                $('.showBtn').html("Submit");
            }

        });
    </script>
@endsection

