@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Client Lists</h1>
                <div class="d-flex align-items-center gap-3">
                    <a href="{{route('concessionaires.import.view')}}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                        Import
                    </a>
                    <a href="{{route('concessionaires.create')}}" class="btn btn-primary px-5 py-3 text-uppercase">
                        Add New
                    </a>
                </div>
            </div>
            <div class="inner-content mt-5 pb-5">
                <table class="w-100 table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Account No.</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
@endsection

@section('script')
<script>
    $(function() {
        const url = '{{ route(Route::currentRouteName()) }}';

        let table = $('table').DataTable({
            processing: true,
            serverSide: true,
            ajax: url,
            columns: [
                { data: 'id', name: 'id' }, 
                { data: 'name', name: 'name' },
                { data: 'account_no', name: 'account_no' },
                { data: 'status', name: 'status' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false } // Fix: Explicitly set actions as non-sortable
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');
            const token = '{{csrf_token()}}';
            const url = '{{route("concessionaires.destroy", ["concessionaire" => "__ID__"])}}'.replace('__ID__', id);
        
            remove(table, url, token)

        });
    });
</script>
@endsection
