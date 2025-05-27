@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>All Meter Readings</h1>
                {{-- <a href="{{route('roles.create')}}" class="btn btn-primary px-5 py-3 text-uppercase">
                    Add New
                </a> --}}
            </div>
            <div class="inner-content mt-5 pb-5 mb-5">
                <table class="w-100 table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Account No</th>
                            <th>Previous Reading</th>
                            <th>Present Reading</th>
                            <th>Consumption</th>
                            <th>Date</th>
                            <th>Action</th>
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
    $(function () {
        @if (session('alert'))
            setTimeout(() => {
                let { status, message } = @json(session('alert'));
                alert(status, message);
            }, 100);
        @endif

        const url = '{{ route(Route::currentRouteName()) }}';

        let table = $('table').DataTable({
            processing: true,
            serverSide: true,
            ajax: url,
            columns: [
                { data: 'id', name: 'id' },
                { data: 'account_no', name: 'account_no' },
                { data: 'previous_reading', name: 'previous_reading' },
                { data: 'present_reading', name: 'present_reading' },
                { data: 'consumption', name: 'consumption', render: function(data, type, row) {
                    return data + ' m³';
                }},
                { data: 'created_at', name: 'created_at' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false } 
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

    });
</script>
@endsection
