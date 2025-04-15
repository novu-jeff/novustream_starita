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
                            <label for="file" class="form-label">Client's CSV File</label>
                            <input type="file" class="form-control @error('file') is-invalid @enderror" id="file" name="file">
                            @error('file')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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

            $("form").on("submit", function(e){

                e.preventDefault(); 

                showLoader();
                
                let formData = new FormData(this); 

                axios.post("{{ route('concessionaires.import.action') }}", formData, {
                    headers: {
                        "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').getAttribute("content"),
                        "Content-Type": "multipart/form-data"
                    }
                })
                .then(response => {
                    alert('success', "File uploaded successfully!");
                    hideLoader();
                })
                .catch(error => {
                    alert('error', "Error uploading file!");
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

