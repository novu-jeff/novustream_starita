<div class="table-responsive">
    <table class="table table-bordered table-striped table-hover align-middle">
        <thead>
            <tr>
                <th>#</th>
                <th>Account</th>
                <th>Name</th>
                <th>Bill Ref</th>
                <th>Total Bill</th>
                <th>Monthly</th>
                <th>Months</th>
                <th>Status</th>
                <th width="190">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($installments as $installment)
                <tr>
                    <td>{{ $installments->firstItem() + $loop->index }}</td>
                    <td>{{ $installment->bill->reading->account_no ?? '-' }}</td>
                    <td>{{ $installment->bill->reading->concessionaire->user->name ?? '-' }}</td>
                    <td>{{ $installment->bill->reference_no }}</td>
                    <td>₱ {{ number_format($installment->bill_amount, 2) }}</td>
                    <td>₱ {{ number_format($installment->monthly_amount, 2) }}</td>
                    <td>{{ $installment->months }}</td>
                    <td>
                        @if($installment->status === 'active')
                            <span class="status-active"><span class="status-dot"></span>Active</span>
                        @else
                            <span class="status-completed">Completed</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $firstDueDate = optional($installment->schedules->sortBy('month_no')->first())->due_date;
                        @endphp
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-primary btn-sm px-2 py-1 view-installment"
                                    data-installment="{{ $installment->id }}">View</button>
                            <button type="button" class="btn btn-warning btn-sm px-2 py-1 edit-installment"
                                    data-action="{{ route('installment.update', $installment->id) }}"
                                    data-reference="{{ $installment->bill->reference_no }}"
                                    data-months="{{ $installment->months }}"
                                    data-first-due-date="{{ $firstDueDate ? \Carbon\Carbon::parse($firstDueDate)->format('Y-m-d') : now()->format('Y-m-d') }}"
                                    data-bill-amount="{{ $installment->bill_amount }}">Edit</button>
                            <button type="button" class="btn btn-danger btn-sm px-2 py-1 delete-installment"
                                    data-action="{{ route('installment.destroy', $installment->id) }}"
                                    data-reference="{{ $installment->bill->reference_no }}">Delete</button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">{{ $emptyMessage }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-3">
    {{ $installments->appends(['tab' => $tabKey])->links() }}
</div>
