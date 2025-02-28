@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Submit Ticket</h1>
            </div>
            <div class="inner-content mt-5 pb-5">
                @if(Auth::user()->user_type == 'client')
                <form action="{{ route('support-ticket.create') }}" method="POST">
                    @csrf
                    @method('POST')
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12 col-md-12 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Category <span class="text-danger">*</span></label>
                                        <select name="category" id="category" class="form-select @error('category') is-invalid @enderror">
                                            <option value=""> - SELECT - </option>
                                            @foreach ($categories->groupBy('category') as $categoryName => $categoryGroup)
                                                <optgroup label="{{ $categoryName }}">
                                                    @foreach ($categoryGroup as $category)
                                                        <option value="{{ $category->id }}" {{ old('category') == $category->id ? 'selected' : '' }}>
                                                            {{ $category->name }}
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-12 col-md-12 mb-3">
                                        <label style="font-size:12px; font-weight: 500" class="mb-1">Tell us exactly your concerns <span class="text-danger">*</span></label>
                                        <textarea name="message" id="message" cols="30" rows="6" class="form-control">{{old('message')}}</textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0 d-flex justify-content-end pb-4">
                                <button class="btn btn-primary px-5 py-3 text-uppercase">Submit</button>
                            </div>
                        </div>
                    </form>
                @endif
                <div class="card p-5">
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
            </div>
        </div>
    </main>
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

        const url = '{{ route(Route::currentRouteName()) }}';

        let table = $('table').DataTable({
            processing: true,
            serverSide: true,
            ajax: url,
            columns: [
                { data: 'ticket_no', name: 'ticket_no' },
                { data: 'status', name: 'status' },
                { data: 'created_at', name: 'created_at'},
                { data: 'actions', name: 'actions', orderable: false, searchable: false } // Fix: Explicitly set actions as non-sortable
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');
            const token = '{{csrf_token()}}';
            const url = `{{ route('support-ticket.destroy', ['ticket' => '__id__']) }}`.replace('__id__', id);
            
            remove(table, url, token)
        });

        

        $(document).on('click', '.btn-view', function() {

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

    })
</script>
@endsection