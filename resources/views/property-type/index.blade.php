@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Property Types</h1>
                <a href="{{  route('base-rate.index') }}" class="btn btn-primary px-5 py-3 text-uppercase">
                    Go to Base Rates
                </a>
            </div>
            <div class="inner-content mt-5">
                <table class="w-100 table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
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
                { data: 'id', name: 'id' }, // Fix: Converted to object
                { data: 'name', name: 'name' }, // Fix: Converted to object
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });
    });
</script>
@endsection
