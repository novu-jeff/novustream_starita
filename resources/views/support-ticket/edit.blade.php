@extends('layouts.app')
@section('content')
<section class="content container my-4 customer">
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Resolve Ticket</h1>
                @php
                    $prefix = Auth::guard('admins')->check() ? 'admin' : 'client';
                @endphp
                <a href="{{route($prefix . '.support-ticket.create')}}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                    Go Back
                </a>
            </div>
            <div class="inner-content mt-5 pb-5">
                <div class="card shadow">
                    <form method="POST" action="{{route($prefix.'.support-ticket.update', ['ticket' => $data->id])}}">
                        @csrf
                        @method('PUT')
                        <div class="card-body">
                            <div class="row">
                                @if ($data->status == 'open')
                                    <div class="col-12 col-md-4 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Ticket Status</label>
                                        <div class="alert alert-primary py-2 mb-0 text-uppercase text-center">Open</div>
                                    </div>
                                @else
                                    <div class="col-12 col-md-4 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Ticket Status</label>
                                        <div class="alert alert-danger py-2 mb-0 text-uppercase text-center">Closed</div>
                                    </div>
                                @endif
                                <div class="col-12 col-md-4 mb-3">
                                    <label style="font-size:12px; font-weight: 500" class="mb-1">Ticket No.</label>
                                    <input type="text" class="form-control restricted" value="{{$data->ticket_no}}" readonly>
                                </div>
                                <div class="col-12 col-md-4 mb-3">
                                    <label style="font-size:12px; font-weight: 500" class="mb-1">Submitted By</label>
                                    <input type="text" class="form-control restricted" value="{{$data->user->name . ' - ' . strtoupper($data->user->user_type)}}" readonly>
                                </div>
                                <div class="col-12 col-md-12 mb-3">
                                    <label style="font-size:12px; font-weight: 500" class="mb-1">Category</label>
                                    <input type="text" class="form-control restricted" value="{{$data->ticket_category->category . ' - ' . $data->ticket_category->name}}" readonly>
                                </div>
                                <div class="col-12 col-md-12 mb-4">
                                    <label style="font-size:12px; font-weight: 500" class="mb-1">Issues and Concerns</label>
                                    <textarea name="message" id="message" cols="30" rows="6" class="form-control restricted" readonly>{{$data->message}}</textarea>
                                </div>
                                @if ($data->feedback)
                                    <div class="col-12 col-md-12 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-3 d-flex justify-content-between">Feedback <span class="text-muted fst-italic">{{$data->updated_at ? \Carbon\Carbon::parse($data->updated_at)->format('F d, Y') : ''}}</span></label>
                                        <div class="alert alert-primary py-2 mb-0 text-uppercase">{{$data->feedback}}</div>
                                    </div>
                                @else
                                    <div class="col-12">
                                        <hr>
                                    </div>
                                    <div class="col-12 col-md-12 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Resolved Message or Remarks <span class="text-danger">*</span></label>
                                        <textarea name="feedback" id="feedback" cols="30" rows="6" class="form-control"></textarea>
                                        <div class="error-field"></div>
                                    </div>
                                    <div class="col-12">
                                        <p class="fst-italic text-muted">Note: Once this form is submitted the status of the ticket will be automatically closed.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                        @if ($data->status !== 'close')
                            <div class="card-footer d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary text-uppercase fw-bold px-5 py-3 text-white">Submit</button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </main>
</section>
@endsection

@section('script')
<script>
    
    $(function() {

        @if (session('alert'))
            setTimeout(() => {
                let alertData = @json(session('alert'));
                alert(alertData.status, alertData.message);
            }, 100);
        @endif

        $(document).on('click', '.btn-view', function() {

            const id = $(this).data('id');
            const prefix = @json(Auth::guard('admins')->check() ? 'admin' : 'client'); 
            const url = `{{ url('${prefix}/support-ticket') }}/${id}`;

            show(url)
            .then(function(response) {
                if(response.status == 'success') {
                view(response.data)
                }
            }) 
            .catch(function(error) {
                Swal.fire({
                title: 'Error occurred',
                text: error.responseJSON?.message || 'An unexpected error occurred.',
                icon: 'error',
                });
            });
        });

        function show(url) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: url,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        resolve(response);
                    },
                    error: function(xhr) {
                        reject(xhr);
                    }
                });
            });
        }

        function view(data) {
            
            let div = `
                <div class="modal fade" id="viewInfo" tabindex="-1">
                    <div class="modal-dialog modal-md modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header d-block pb-0">
                                <div class="d-flex align-items-center pt-1 pb-2">
                                    <h5 class="modal-title text-uppercase">Full Information</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                            </div>
                            <div class="modal-body pb-5">
                                <div class="row">
                                    `;
                                if(data.status === 'open') {
                                    div += `
                                    <div class="col-12 col-md-12 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Ticket Status</label>
                                        <div class="alert alert-primary py-2 mb-0 text-uppercase text-center">Open</div>
                                    </div>
                                    `;
                                } else if(data.status === 'close') {
                                    div += `
                                    <div class="col-12 col-md-12 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Ticket Status</label>
                                        <div class="alert alert-danger py-2 mb-0 text-uppercase text-center">Closed</div>
                                    </div>
                                    `;
                                }
                                div += `
                                    <div class="col-12 col-md-12 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Category</label>
                                        <input type="text" class="form-control restricted" value="${data.ticket_category.category + ' - ' + data.ticket_category.name}" readonly>
                                    </div>
                                    <div class="col-12 col-md-12 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Your concerns</label>
                                        <textarea name="message" id="message" cols="30" rows="6" class="form-control restricted" readonly>${data.message}</textarea>
                                    </div>
                                `;
                                if(data.feedback) {
                                    div += `
                                    <div class="col-12 col-md-12 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-2">Feedback</label>
                                        <div class="alert alert-primary py-2 mb-0 text-uppercase text-center">${data.feedback}</div>
                                    </div>
                                    `;
                                }
                            div += `</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            $('body').append(div);
            $('#viewInfo').modal('show');
            $('#viewInfo').on('hidden.bs.modal', function (e) {
                $(this).remove();
            });

            }  

        });
</script>
@endsection