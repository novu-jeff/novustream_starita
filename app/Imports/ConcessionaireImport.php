<?php

namespace App\Imports;

use App\Models\User;
use App\Models\UserAccounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ConcessionaireImport implements
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
            'account_no' => [
                'required',
                function ($attribute, $value, $fail) {
                    // sanitize first to ensure consistency
                    $accountNo = $this->sanitizeAccountNo($value);
                    if (
                        $accountNo &&
                        DB::table('concessioner_accounts')->where('account_no', $accountNo)->exists()
                    ) {
                        $fail("Account no `{$accountNo}` already exists.");
                    }
                }
            ],
            'name' => ['required'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Missing required field: name',
            'zone.required' => 'Missing required field: zone',
        ];
    }

    public function model(array $row)
    {
        $rowNum = $this->rowCounter++;
        $row = array_map('trim', $row);

        try {
            // sanitize account number and extract zone
            $accountNo = $this->sanitizeAccountNo($row['account_no'] ?? null);
            $zone      = $this->extractZone($accountNo);

            $user = User::create([
                'name'       => $row['name'],
                'contact_no' => $row['contact_no'] ?? null,
            ]);

            if ($user) {
                $property_type  = $this->getPropertyType($row['rate_code']);
                $date_connected = $this->parseDate($row['date_connected'] ?? null);

                UserAccounts::create([
                    'user_id'         => $user->id,
                    'zone'            => $zone,
                    'account_no'      => $accountNo,
                    'address'         => $row['address'] ?? null,
                    'property_type'   => $property_type,
                    'rate_code'       => $row['rate_code'] ?? null,
                    'status'          => $row['status'] ?? null,
                    'meter_brand'     => $row['meter_brand'] ?? null,
                    'meter_serial_no' => $row['meter_serial_no'] ?? null,
                    'sc_no'           => $row['sc_no'] ?? null,
                    'date_connected'  => $date_connected,
                    'sequence_no'     => $row['sequence_no'] ?? null,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Import error in Concessionaire Informations Sheet', [
                'error' => $e->getMessage(),
                'row'   => $row,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->skippedRows[] = "Row $rowNum skipped: Exception - " . $e->getMessage();
            return null;
        }
    }

    public function validateRow(array $row, $index)
    {
        if ($this->isRowEmpty($row)) {
            return true;
        }
        return null;
    }

    protected function sanitizeAccountNo(?string $accountNo): ?string
    {
        if (!$accountNo) {
            return null;
        }

        $accountNo = trim($accountNo);

        // If PhpSpreadsheet gave us a formula string, ignore it
        if (str_starts_with($accountNo, '=')) {
            return null;
        }

        return $accountNo;
    }

    protected function extractZone(?string $accountNo): ?string
    {
        if (!$accountNo) {
            return null;
        }

        // Always take first 3 digits if present
        if (preg_match('/^\d{3}/', $accountNo, $matches)) {
            return $matches[0];
        }

        return null;
    }

    protected function parseDate($value): ?string
    {
        if (!$value) {
            return null;
        }

        if (is_numeric($value)) {
            return Carbon::instance(
                \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($value)
            )->format('Y-m-d');
        }

        $timestamp = strtotime($value);
        return $timestamp !== false
            ? Carbon::createFromTimestamp($timestamp)->format('Y-m-d')
            : null;
    }

    public function getPropertyType($rate_code)
    {
        $types = [
            12 => 'Residential 1/2"',
            22 => 'Government 1/2"',
            32 => 'Commercial & Industrial 1/2"',
            42 => 'Commercial C 1/2"',
            52 => 'Commercial B 1/2"',
            62 => 'Commercial A 1/2"',
        ];

        return $types[(int) $rate_code] ?? null;
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
