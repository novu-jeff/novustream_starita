@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>{{$filter}} Bills</h1>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('payments.show', ['payment' => $filter === 'paid' ? 'unpaid' : 'paid']) }}" 
                        class="btn btn-outline-primary px-5 py-3 text-uppercase">
                         View {{ $filter === 'paid' ? 'Unpaid' : 'Paid' }}
                     </a>                     
                </div>
            </div>
            <div class="inner-content mt-5">
                <table class="w-100 table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Billing Period</th>
                            <th>Bill Date</th>
                            <th>Amount</th>
                            <th>Due Date</th>
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
        const url = '{{ route('payments.show', ['payment' => $filter]) }}';

        let table = $('table').DataTable({
            processing: true,
            serverSide: true,
            ajax: url,
            columns: [
                { data: 'id', name: 'id' }, 
                { data: 'billing_period', name: 'billing_period' }, 
                { data: 'bill_date', name: 'bill_date' },
                { data: 'amount', name: 'amount' },
                { data: 'due_date', name: 'due_date' },
                { data: 'status', name: 'status' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false } // Fix: Explicitly set actions as non-sortable
            ],
            responsive: true,
            order: [[0, 'asc']],
            scrollX: true
        });

    });
</script>
@endsection
