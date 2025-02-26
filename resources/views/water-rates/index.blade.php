@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Water Rates</h1>
                <a href="{{route('water-rates.create')}}" class="btn btn-primary px-5 py-3 text-uppercase">
                    Add New
                </a>
            </div>
            <div class="inner-content mt-5">
                <table class="w-100 table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Property Type</th>
                            <th>From ( m³ ) </th>
                            <th>To ( m³ )</th>
                            <th>Rate</th>
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
                { data: 'property_type.name', name: 'property_type.name' }, 
                { data: 'cubic_from', name: 'cubic_from' }, 
                { data: 'cubic_to', name: 'cubic_to' }, 
                { data: 'rates', name: 'rates' }, 
                { data: 'actions', name: 'actions', orderable: false, searchable: false } 
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');
            const token = '{{csrf_token()}}';
            const url = '{{route("water-rates.destroy", ["water_rate" => "__ID__"])}}'.replace('__ID__', id);
            
            remove(table, url, token)


        });

    });
</script>
@endsection
