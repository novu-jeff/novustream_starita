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
                        $fail("Missing required field: account no");
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
            'zone.required' => 'Missing required field: zone',
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

                $zone = null;
                if (!empty($row['account_no'])) {
                    $cleanAccountNo = preg_replace('/\s+/', '', $row['account_no']);
                    if (preg_match('/^(\d{3})/', $cleanAccountNo, $matches)) {
                        $zone = $matches[1];
                    }
                }

                $date_connected = null;
                if (isset($row['date_connected']) && $row['date_connected'] !== '') {
                    if (is_numeric($row['date_connected'])) {
                        $date_connected = Carbon::instance(
                            \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($row['date_connected'])
                        )->format('Y-m-d');
                    } else {
                        $timestamp = strtotime($row['date_connected']);
                        $date_connected = $timestamp !== false ? Carbon::createFromTimestamp($timestamp)->format('Y-m-d') : null;
                    }
                }

                UserAccounts::create([
                    'user_id'         => $user->id,
                    'zone'            => $zone,
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
