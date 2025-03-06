@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>Payment Breakdown</h1>
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <a class="btn btn-primary px-5 py-3 dropdown-toggle text-uppercase" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Add New
                        </a>
                        <ul class="dropdown-menu">
                          <li><a class="dropdown-item" href="{{route('payment-breakdown.create', ['action' => 'regular'])}}">Regular Breakdown</a></li>
                          <li><a class="dropdown-item" href="{{route('payment-breakdown.create', ['action' => 'penalty'])}}">Penalty Breakdown</a></li>
                          <li><a class="dropdown-item" href="{{route('payment-breakdown.create', ['action' => 'service-fee'])}}">Service Fee</a></li>
                        </ul>
                    </div>                     
                </div>
            </div>
            <div class="inner-content mt-5 pb-5">
                <div class="row">
                    <div class="col-12 col-md-6 mb-3">
                        <div class="card shadow">
                            <div class="card-header border-0 m-0 pt-3">
                                <h6 class="card-title mb-0 text-uppercase fw-bold">Regular Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <table class="w-100 table table-bordered table-hover" id="regular-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Amount</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 mb-3">
                        <div class="card shadow">
                            <div class="card-header border-0 m-0 pt-3">
                                <h6 class="card-title mb-0 text-uppercase fw-bold">Penalty Breakdown</h6>
                            </div>
                            <div class="card-body">
                                <table class="w-100 table table-bordered table-hover" id="penalty-table">
                                    <thead>
                                        <tr>
                                            <th>Due Days (From)</th>
                                            <th>Due Days (To)</th>
                                            <th>Penalty Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6 mb-3">
                        <div class="card shadow">
                            <div class="card-header border-0 m-0 pt-3">
                                <h6 class="card-title mb-0 text-uppercase fw-bold">Service Fee</h6>
                            </div>
                            <div class="card-body">
                                <table class="w-100 table table-bordered table-hover" id="service-table">
                                    <thead>
                                        <tr>
                                            <th>Property</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection

@section('script')
<script>
    $(function() {

        const regular = '{{ route('payment-breakdown.index', ['action' => 'regular']) }}';
        const penalty = '{{ route('payment-breakdown.index', ['action' => 'penalty']) }}';
        const service = '{{ route('payment-breakdown.index', ['action' => 'service-fee']) }}';

        let regularTable = $('#regular-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: regular,
            columns: [
                { data: 'name', name: 'name' }, 
                { data: 'amount', name: 'amount' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false } 
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

        let penaltyTable = $('#penalty-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: penalty,
            columns: [
                { data: 'due_from', name: 'due_from' }, 
                { data: 'due_to', name: 'due_to' },
                { data: 'amount', name: 'amount' },
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

        let serviceTable = $('#service-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: service,
            columns: [
                { data: 'property', name: 'property' }, 
                { data: 'amount', name: 'amount' },
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

        $(document).on('click', '.btn-delete', function() {
            const id = $(this).data('id');
            const token = '{{csrf_token()}}';
            const url = '{{route("payment-breakdown.destroy", ["payment_breakdown" => "__ID__"])}}'.replace('__ID__', id);
        
            remove(table, url, token)

        });

    });
</script>
@endsection