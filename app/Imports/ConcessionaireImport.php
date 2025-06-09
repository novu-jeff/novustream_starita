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

    public function model(array $row)
    {
        try {
            $user = User::create([
                'name'              => trim($row['name']),
                'contact_no'        => trim($row['contact_no']),
                'senior_citizen_no' => trim($row['senior_citizen_no']),
            ]);

            if ($user) {
                $property_type = $this->getPropertyType(trim($row['rate_code']));
                $dateStr = trim($row['date_connected']);
                $timestamp = strtotime($dateStr);

                if ($timestamp !== false) {
                    $date_connected = Carbon::createFromTimestamp($timestamp)->format('Y-m-d');
                } else {
                    $date_connected = '';
                }

                UserAccounts::create([
                    'user_id'         => $user->id,
                    'account_no'      => trim($row['account_no'] ?? null),
                    'address'         => trim($row['address'] ?? null),
                    'property_type'   => $property_type,
                    'rate_code'       => trim($row['rate_code'] ?? null),
                    'status'          => trim($row['status'] ?? null),
                    'meter_brand'     => trim($row['meter_brand'] ?? null),
                    'meter_serial_no' => trim($row['meter_serial_no'] ?? null),
                    'sc_no'           => trim($row['sc_no'] ?? null),
                    'date_connected'  => $date_connected,
                    'sequence_no'     => trim($row['sequence_no'] ?? null),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Import error in ConcessionaireImport', [
                'error' => $e->getMessage(),
                'row'   => $row,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Override validateRow to skip validation on empty rows.
     */
    public function validateRow(array $row, $index)
    {
        if ($this->isRowEmpty($row)) {
            return true;
        }

        return null;
    }

    public function rules(): array
    {
        return [
            'account_no' => [
                function ($attribute, $value, $fail) {
                    if (empty($value)) {
                        $fail("The account no is required.");
                        return;
                    }

                    if (DB::table('concessioner_accounts')->where('account_no', $value)->exists()) {
                        $fail("account no `{$value}` has already been taken");
                    }
                }
            ],
            'name' => 'required|string',
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

    public function chunkSize(): int
    {
        return 1000;
    }
}
