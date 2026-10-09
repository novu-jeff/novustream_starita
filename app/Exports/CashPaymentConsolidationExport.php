<?php

namespace App\Exports;

use App\Services\CashPaymentConsolidationQuery;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CashPaymentConsolidationExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    private int $rowNumber = 0;

    public function __construct(
        private string $period,
        private string $search,
        private ?string $fromDate = null,
        private ?string $toDate = null
    )
    {
    }

    public function query(): Builder
    {
        return CashPaymentConsolidationQuery::build(
            $this->period,
            $this->search,
            $this->fromDate,
            $this->toDate
        );
    }

    public function headings(): array
    {
        return ['Index No.', 'Name', 'Account No.', 'Reference No.', 'Payment', 'Date Paid'];
    }

    public function map($row): array
    {
        try {
            $datePaid = $row->date_paid
                ? Carbon::parse($row->date_paid)->format('M d, Y H:i:s')
                : '';
        } catch (\Throwable $exception) {
            $datePaid = (string) ($row->date_paid ?? '');
        }

        return [
            ++$this->rowNumber,
            $row->name ?? '',
            $row->account_no ?? '',
            $row->reference_no ?? '',
            '₱ ' . number_format((float) config('payments.system_fee', 10), 2),
            $datePaid,
        ];
    }
}