<?php

namespace App\Imports;

use App\Models\Bill;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AdvancesImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Bill([
            'reference_no' => $row['reference_no'],
            'advances'     => $row['advances'],
            'amount'       => $row['amount'] ?? 0,
            'bill_period_from' => $row['bill_period_from'] ?? now()->format('Y-m-01'),
            'bill_period_to'   => $row['bill_period_to'] ?? now()->format('Y-m-t'),
        ]);
    }
}
