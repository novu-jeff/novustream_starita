<?php

namespace App\Imports;

use App\Models\SeniorDiscount;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class SCDiscountImport implements 
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

    public function model(array $row)
    {
        $rowNum = $this->rowCounter++;
        $row = array_map('trim', $row);

        try {
            $accountNo = $row['account_no'] ?? null;
            $idNo = $row['id_no'] ?? null;
            $effectiveDate = $this->parseDate($row['effectivity_date'] ?? null);
            $expiredDate = $this->parseDate($row['expired_date'] ?? null);

            if (!$accountNo || !$idNo || !$effectiveDate || !$expiredDate) {
                $this->skippedRows[] = "Row $rowNum skipped: Missing required fields.";
                return null;
            }

            $existing = SeniorDiscount::where('account_no', $accountNo)->first();

            if ($existing) {
                $existing->update([
                    'id_no'          => $idNo,
                    'effective_date' => $effectiveDate,
                    'expired_date'   => $expiredDate,
                ]);

                $this->skippedRows[] = "Row $rowNum skipped: Existing record updated.";
                return null;
            }

            return new SeniorDiscount([
                'account_no'     => $accountNo,
                'id_no'          => $idNo,
                'effective_date' => $effectiveDate,
                'expired_date'   => $expiredDate,
            ]);

        } catch (\Exception $e) {
            $this->skippedRows[] = "Row $rowNum skipped: Exception - " . $e->getMessage();

            Log::error('Import error in SCDiscountImport', [
                'error' => $e->getMessage(),
                'row'   => $row,
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    private function parseDate($value)
    {
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        $timestamp = strtotime($value);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    public function rules(): array
    {
        return [
            'account_no' => [
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        $fail("The account no is required.");
                    }
                }
            ],
            'id_no' => 'required',
            'effectivity_date' => 'required',
            'expired_date' => 'required',
        ];
    }

    private function isRowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (!is_null($value) && trim($value) !== '') {
                return false;
            }
        }
        return true;
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
