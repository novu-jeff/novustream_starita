<?php

namespace App\Imports;

use App\Models\Bill;
use App\Models\Reading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PreviousBillingImport implements 
    ToModel, 
    WithHeadingRow,
    WithChunkReading,
    SkipsEmptyRows,
    SkipsOnFailure

{
    
    use SkipsFailures;

    public function model(array $row)
    {
        $reading = Reading::create([
            'account_no' => trim($row['account_no']),
            'previous_reading' => trim($row['previous_reading']),
            'present_reading' => trim($row['present_reading']),
            'consumption' => trim($row['consumption']),
        ]);

        Bill::create([
            'reading_id' => $reading->id,
            'reference_no' => trim($row['reference_no']),
            'bill_period_from' => trim($row['billing_from']),
            'bill_period_to' => trim($row['billing_to']),
            'previous_unpaid' => trim($row['unpaid']),
            'amount' => trim($row['amount']),
            'amount_paid' => trim($row['amount_paid']),
            'change' => trim($row['change']),
            'isPaid' => !empty($row['isPaid']) ? 1 : 0,
            'date_paid' => trim($row['date_paid']),
            'due_date' => trim($row['due_date']),
            'payor_name' => trim($row['payor_name']),
        ]);

    }


    public function chunkSize(): int
    {
        return 1000; 
    }
}
