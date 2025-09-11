<?php

namespace App\Imports;

use App\Models\Bill;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithValidation;

class AdvancesImport implements ToModel, WithHeadingRow, WithChunkReading, SkipsEmptyRows, SkipsOnFailure, WithValidation
{
    use SkipsFailures;

    protected int $rowCounter = 3;
    protected array $skippedRows = [];
    protected int $inserted = 0;

    public function headingRow(): int
    {
        return 2; // Skip the note row
    }

    public function rules(): array
    {
        return [
            'account_no'   => ['required'],
            'amount'       => ['required', 'numeric'],
            'date_applied' => ['required', 'date'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'account_no.required'   => 'Missing account_no',
            'amount.required'       => 'Missing amount',
            'amount.numeric'        => 'Amount must be numeric',
            'date_applied.required' => 'Missing date_applied',
            'date_applied.date'     => 'date_applied must be a valid date',
        ];
    }

    public function model(array $row)
    {
        $row = array_map('trim', $row);
        $row = array_change_key_case($row, CASE_LOWER);

        if (empty($row['account_no']) || empty($row['amount'])) {
            $this->skippedRows[] = "Row {$this->rowCounter} skipped: Missing data";
            return null;
        }

        return new Bill([
            'reference_no'     => $row['account_no'],
            'advances'         => $row['amount'],
            'bill_period_from' => $row['as_of'] ?? now()->format('Y-m-01'),
            'bill_period_to'   => $row['as_of'] ?? now()->format('Y-m-t'),
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
