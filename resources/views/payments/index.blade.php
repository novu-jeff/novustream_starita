@extends('layouts.app')

@section('content')
    <main class="main">
        <div class="responsive-wrapper">
            <div class="main-header d-flex justify-content-between">
                <h1>{{$filter}} Bills</h1>
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('payments.upload') }}" 
                        class="btn btn-outline-primary px-5 py-3 text-uppercase">
                         Upload Billing
                     </a>     
                    <a href="{{ route('payments.show', ['payment' => $filter === 'paid' ? 'unpaid' : 'paid']) }}" 
                        class="btn btn-primary px-5 py-3 text-uppercase">
                         View {{ $filter === 'paid' ? 'Unpaid' : 'Paid' }}
                     </a>                     
                </div>
            </div>
            <div class="inner-content mt-5 pb-5 mb-5">
                <table class="w-100 table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Account No</th>
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

        @if (session('alert'))
            setTimeout(() => {
                let alertData = @json(session('alert'));
                if (alertData.status === 'success' && alertData.payment_request) {
                    window.open(alertData.redirect, '_blank', 'width=1200,height=900,scrollbars=yes,resizable=yes');
                } else {
                    alert(alertData.status, alertData.message);
                }
            }, 100);
        @endif

        const url = '{{ route('payments.show', ['payment' => $filter]) }}';

        let table = $('table').DataTable({
            processing: true,
            serverSide: true,
            ajax: url,
            columns: [
                { data: 'id', name: 'id' }, 
                { data: 'account_no', name: 'account_no' }, 
                { data: 'billing_period', name: 'billing_period' }, 
                { data: 'bill_date', name: 'bill_date' },
                { data: 'amount', name: 'amount' },
                { data: 'due_date', name: 'due_date' },
                { data: 'status', name: 'status' },
                { data: 'actions', name: 'actions', orderable: false, searchable: false } 
            ],
            responsive: true,
            order: [[0, 'desc']],
            scrollX: true
        });

    });
</script>
@endsection
