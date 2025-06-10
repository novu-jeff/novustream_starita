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

    public function model(array $row)
    {
        try {
            $accountNo = trim($row['account_no']);

            $effectiveDate = $this->parseDate($row['effectivity_date']);
            $expiredDate = $this->parseDate($row['expired_date']);

            $existing = SeniorDiscount::where('account_no', $accountNo)->first();

            if ($existing) {
                $existing->update([
                    'id_no'          => trim($row['id_no']),
                    'effective_date' => $effectiveDate,
                    'expired_date'   => $expiredDate,
                ]);
                return null;
            } else {
                return new SeniorDiscount([
                    'account_no'     => $accountNo,
                    'id_no'          => trim($row['id_no']),
                    'effective_date' => $effectiveDate,
                    'expired_date'   => $expiredDate,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Import error in SCDiscountImport', [
                'error' => $e->getMessage(),
                'row'   => $row,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    private function parseDate($value)
    {
        // If the value is numeric, it's likely an Excel serial date
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject($value)->format('Y-m-d');
        }

        // Otherwise, attempt to parse it as a string date
        return date('Y-m-d', strtotime($value));
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

    public function chunkSize(): int
    {
        return 1000;
    }
}
