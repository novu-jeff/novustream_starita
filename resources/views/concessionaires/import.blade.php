@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Import Concessionaires</h1>
                <a href="{{route('concessionaires.index')}}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Go Back
                </a>
            </div>
            <div class="inner-content mt-5">
                <form method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="file" class="form-label">{{$label}}</label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file">
                            <div class="warning">

                            </div>
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

                const toProcess = '{{$toProcess}}';
                let formData = new FormData(this);

                formData.append('toProcess', toProcess);

                $(".warning").html(""); 

                axios.post("{{ route('concessionaires.import.action') }}", formData, {
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                        "Content-Type": "multipart/form-data"
                    }
                })
                .then(response => {

                    const res = response.data;

                    $(".warning").html(""); 

                    if (res.status === 'success') {
                        alert('success', "File uploaded successfully!");
                    } else if (res.status === 'error') {
                        alert('error', res.message || "An error occurred during import.");
                    } else if (res.status === 'warning') {
                        alert('success', "File uploaded successfully but with warnings!");

                        const warnings = Array.isArray(res.errors) ? res.errors : [];

                        const warningHTML = `
                            <div class="card shadow mt-4 p-3 rounded mb-3">
                                <div class="card-body">
                                    <p class="text-uppercase fw-bold mb-4">Warning: Some rows are skipped because of encountered error.</p>
                                    <div style="height: 400px; overflow-y: auto">
                                        <ul class="list-unstyled">
                                            ${warnings.map(error => `<li>${error}</li>`).join('')}
                                        </ul>
                                    </div>
                                </div>
                            </div>`;
                        $(".warning").html(warningHTML);
                    } 
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

