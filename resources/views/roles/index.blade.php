@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <div class="d-flex align-items-start gap-3">
                    <div class="report-title-icon"><i class="bx bx-shield" aria-hidden="true"></i></div>
                    <div><h1 class="report-page-title mb-1">Role Lists</h1><p class="report-page-subtitle mb-0">Review the roles and access descriptions configured in the system.</p></div>
                </div>
                {{-- <a href="{{route('roles.create')}}" class="btn btn-primary px-5 py-3 text-uppercase">
                    Add New
                </a> --}}
            </div>
            <div class="inner-content mt-5">
                <table class="w-100 table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
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
                { data: 'description', name: 'description' },
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');
            const token = '{{csrf_token()}}';
            const url = '{{route("roles.destroy", ["role" => "__ID__"])}}'.replace('__ID__', id);
        
            remove(table, url, token)

        });
    });
</script>
@endsection
