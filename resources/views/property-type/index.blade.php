@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper pb-5">
            <div class="main-header d-flex justify-content-between">
                <h1>Property Types</h1>
                <div class="d-flex align-items-center gap-3">
                    <a href="{{  route('base-rate.index') }}" class="btn btn-outline-primary px-5 py-3 text-uppercase">
                        Go to Base Rates
                    </a>
                    <a href="{{  route('property-types.create') }}" class="btn btn-primary px-5 py-3 text-uppercase">
                        Add New
                    </a>
                </div>
            </div>
            <div class="inner-content mt-5 pb-5">
                <table class="w-100 table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
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
                { data: 'actions', name: 'actions', orderable: false, searchable: false }
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');
            const token = '{{csrf_token()}}';
            const url = '{{route("property-types.destroy", ["property_type" => "__ID__"])}}'.replace('__ID__', id);
            remove(table, url, token)
        });

    
    });
</script>
@endsection
