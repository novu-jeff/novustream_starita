@extends('layouts.app')
@section('content')
    <section class="content container my-4 customer">
        <header class="d-flex justify-content-between mb-3">
            <div>
                <h3>{{ __('Tickets') }}</h3>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">{{ __('NBoss') }}</li>
                        <li class="breadcrumb-item active" aria-current="page">{{ __('Support Tickets') }}</li>
                    </ol>
                </nav>
            </div>
        </header>
        <div class="card p-3">
            <table class="table table-bordered table-hover w-100" id="myTable" style="vertical-align: middle">
                <thead>
                    <tr>
                        <th>Ticket No</th>
                        <th>Status</th>
                        <th>Date Submitted</th>
                        <th style="width: 120px">Action</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </div>
    </section>
@endsection

@section('script')
<script>

    $(function() {

        const url = '{{ route(Route::currentRouteName()) }}';

        const isClient = '{{ Auth::check() }}';
        console.log(isClient);

        let table = $('table').DataTable({
            processing: true,
            serverSide: true,
            ajax: url,
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'description', name: 'description' },
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

        $(document).on('click', '#remove-btn', function() {
            const id = $(this).data('id');
            const url = `{{ route('support-ticket.destroy', ['ticket' => '__id__']) }}`.replace('__id__', id);            const data = {
                '_token': '{{csrf_token()}}'
            }
            remove(url, data, $(this))
        });

        $(document).on('click', '.view-btn', function() {

            alert();
            
            const id = $(this).data('id');
            const url = `{{ route('support-ticket.show', ['ticket' => '__id__']) }}`.replace('__id__', id);            
            
            show(url)
                .then(function(response) {
                    if(response.status == 'success') {
                        view(response.data)
                    }
                }) 
                .catch(function(error) {
                    Swal.fire({
                        title: 'Error occured',
                        text: error,
                        icon: 'error',
                    });
                });
        });

        function view(data) {
            let div = `
                <div class="modal fade" id="viewInfo" tabindex="-1">
                    <div class="modal-dialog modal-md modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header d-block pb-0">
                                <div class="d-flex align-items-center pt-3 pb-3">
                                    <h1 class="modal-title fs-4 text-uppercase">Full Information</h1>
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
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Feedback</label>
                                        <div class="alert alert-info py-2 mb-0 text-uppercase text-center">${data.feedback}</div>
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

        function resetAllFields() {
            $('input, textarea, select').each(function(){
                if($(this).is(':checkbox') || $(this).is(':radio')){
                    $(this).prop('checked', false);
                } else {
                    $(this).val('');
                }
            });
        }
    })
</script>
@endsection