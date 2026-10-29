<?php

namespace App\Imports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\{
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsEmptyRows,
    SkipsOnFailure,
    WithChunkReading
};
use Maatwebsite\Excel\Concerns\SkipsFailures;

class SequenceImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsEmptyRows,
    SkipsOnFailure,
    WithChunkReading
{
    use SkipsFailures;

    protected $skippedRows = [];
    protected $rowCounter = 3;

    public function rules(): array
    {
        return [
            'account_no'  => ['required'],
            'sequence_no' => ['required', 'numeric'],
        ];
    }

    public function model(array $row)
    {
        $rowNum = $this->rowCounter++;
        $row = array_map('trim', $row);

        try {
            $accountNo = preg_replace('/\s+.*/', '', $row['account_no'] ?? '');
            $sequenceNo = $row['sequence_no'] ?? null;

            if (!$accountNo || !$sequenceNo) {
                $this->skippedRows[] = "Row {$rowNum}: Missing account_no or sequence_no";
            }

            $updated = DB::table('concessioner_accounts')
                ->where('account_no', $accountNo)
                ->update([
                    'sequence_no' => $sequenceNo,
                    'updated_at'  => now(),
                ]);

            if ($updated === 0) {
                $this->skippedRows[] = "Row {$rowNum}: Account no {$accountNo} not found";
            }

        } catch (\Exception $e) {
            Log::error('Sequence Import Error', [
                'row' => $row,
                'error' => $e->getMessage(),
            ]);

            $this->skippedRows[] = "Row {$rowNum}: Exception - {$e->getMessage()}";
        }

        return null;
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function getSkippedRows()
    {
        return $this->skippedRows;
    }

    public function getRowCounter()
    {
        return $this->rowCounter;
    }
}
