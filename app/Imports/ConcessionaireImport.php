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
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        $fail("Missing required field: account_no");
                        return;
                    }

                    if (DB::table('concessioner_accounts')->where('account_no', $value)->exists()) {
                        $fail("account no `{$value}` has already been taken");
                    }
                }
            ],
            'name' => 'required',
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'name.required' => 'Missing required field: name',
        ];
    }

    public function model(array $row)
    {
        $rowNum = $this->rowCounter++;
        $row = array_map('trim', $row);

        try {

            $user = User::create([
                'name'       => $row['name'],
                'contact_no' => $row['contact_no'] ?? null,
            ]);

            if ($user) {
                $property_type = $this->getPropertyType($row['rate_code']);
                $timestamp = strtotime($row['date_connected']);
                $date_connected = $timestamp !== false ? Carbon::createFromTimestamp($timestamp)->format('Y-m-d') : '';

                UserAccounts::create([
                    'user_id'         => $user->id,
                    'zone' => $this->getZone($row['account_no']),
                    'account_no'      => $row['account_no'] ?? null,
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
            Log::error('Import error in ConcessionaireImport', [
                'error' => $e->getMessage(),
                'row'   => $row,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->skippedRows[] = "Row $rowNum skipped: Exception - " . $e->getMessage();
            return null;
        }
    }


    public function getZone($account_no)
    {
        return explode('-', $account_no)[0] ?? null;
    }

    public function validateRow(array $row, $index)
    {
        if ($this->isRowEmpty($row)) {
            return true; 
        }
        return null;
    }

    public function getPropertyType($rate_code)
    {
        return match((int) $rate_code) {
            12 => 1,
            22 => 2,
            32 => 3,
            42 => 4,
            52 => 5,
            default => null,
        };
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
