<?php

namespace App\Imports;

use App\Models\Bill;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithValidation;

class OutstandingBalanceImport implements
    ToModel,
    WithHeadingRow,
    WithChunkReading,
    SkipsEmptyRows,
    SkipsOnFailure,
    WithValidation
{
    use SkipsFailures;

    protected array $skippedRows = [];
    protected int $rowCounter = 3;
    protected int $inserted = 0;

    public function headingRow(): int
    {
        return 2;
    }

    public function rules(): array
    {
        return [
            'account_no' => ['required'],
            'amount'     => ['required', 'numeric'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'account_no.required' => 'Missing account_no',
            'amount.required'     => 'Missing amount',
            'amount.numeric'      => 'Amount must be numeric',
        ];
    }

    public function model(array $row)
    {
        $rowNum = $this->rowCounter++;
        $row = array_map('trim', $row);
        $row = array_change_key_case($row, CASE_LOWER);

        $accountNo = $row['account_no'] ?? null;
        $amount = $row['amount'] ?? null;
        $asOf = $row['as_of'] ?? null;

        if (!$accountNo || !$amount) {
            $this->skippedRows[] = "Row $rowNum skipped: Missing required data.";
            return null;
        }

        $reading = DB::table('readings')
            ->where('account_no', $accountNo)
            ->orderByDesc('id')
            ->first();

        if (!$reading) {
            $this->skippedRows[] = "Row $rowNum skipped: No reading found for account $accountNo.";
            return null;
        }

        $billFrom = $asOf ? date('Y-m-01', strtotime($asOf)) : date('Y-m-01');
        $billTo = $asOf ? date('Y-m-t', strtotime($asOf)) : date('Y-m-t');

        $this->inserted++;

        return new Bill([
            'reading_id'       => $reading->id,
            'reference_no'     => $accountNo,
            'previous_unpaid'  => $amount,
            'amount'           => $amount,
            'bill_period_from' => $billFrom,
            'bill_period_to'   => $billTo,
        ]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function getSkippedRows(): array
    {
        return $this->skippedRows;
    }

    public function getInsertedCount(): int
    {
        return $this->inserted;
    }

    public function getRowCounter(): int
    {
        return $this->rowCounter;
    }
}
