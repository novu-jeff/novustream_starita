<?php

namespace App\Imports;

use App\Models\ClientInformations;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class ClientInformationImport implements ToCollection, WithStartRow, SkipsEmptyRows, SkipsOnFailure
{
    use SkipsFailures;

    protected $sheetName;
    protected $rowCounter = 0;
    protected $skippedRows = [];
    protected $insertedRows = 0;

    public function __construct($sheetName = 'Unknown Sheet')
    {
        $this->sheetName = $sheetName;
    }

    public function collection(Collection $rows)
    {
        try {
            foreach ($rows as $rowNumber => $row) {
                // First column = key, second column = value
                $key = strtoupper(trim($row[0] ?? ''));
                $value = trim($row[1] ?? '');

                if ($key === '') {
                    $this->skippedRows[] = "Row ".($rowNumber+1)." skipped: Empty key.";
                    continue;
                }

                $data = [];

                switch ($key) {
                    case 'COMPANY NAME / CLIENT NAME':
                    case 'NAME':
                        $data['company_client'] = $value;
                        break;
                    case 'ADDRESS':
                        $data['address'] = $value;
                        break;
                    case 'TELEPHONE NO.':
                        $data['tel_no'] = $value ?: null; // allow empty
                        break;
                    case 'CELLPHONE NO.':
                        $data['phone_no'] = $value ?: null; // allow empty
                        break;
                    case 'EMAIL ADDRESS':
                    case 'EMAIL':
                        $data['email'] = $value ?: null; // allow empty
                        break;
                    case 'TIN NO.':
                        $data['tin_no'] = $value ?: null; // allow empty
                        break;
                    case 'BANK ACCOUNT NO.':
                        $data['bank_account_no'] = $value ?: null; // allow empty
                        break;
                    default:
                        continue 2;
                }

                if (!empty($data)) {
                    ClientInformations::updateOrCreate(
                        ['id' => 1],
                        $data
                    );
                    $this->insertedRows++;
                }

                $this->rowCounter++;
            }

        } catch (\Exception $e) {
            Log::error('Import error in Client Information Sheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function getRowCounter()
    {
        return $this->insertedRows; // count of rows actually stored
    }

    public function getSkippedRows()
    {
        return $this->skippedRows;
    }

    public function startRow(): int
    {
        return 1; // read all rows starting from row 1
    }
}
