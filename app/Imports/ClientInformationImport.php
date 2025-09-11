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

    public function __construct($sheetName = 'Unknown Sheet')
    {
        $this->sheetName = $sheetName;
    }

    public function collection(Collection $rows)
    {
        try {
            $data = [];

            foreach ($rows as $index => $row) {
                $key = strtoupper(trim($row[0] ?? ''));
                $value = trim($row[1] ?? '');

                if ($key === '') {
                    $this->skippedRows[] = "Row " . ($index + 1) . " skipped: Empty key.";
                    continue;
                }

                if ($value === '') {
                    $value = null;
                }


                switch ($key) {
                    case 'COMPANY NAME / CLIENT NAME':
                        $data['company_client'] = $value;
                        break;
                    case 'ADDRESS':
                        $data['address'] = $value;
                        break;
                    case 'TELEPHONE NO.':
                        $data['tel_no'] = $value;
                        break;
                    case 'CELLPHONE NO.':
                        $data['phone_no'] = $value;
                        break;
                    case 'EMAIL ADDRESS':
                        $data['email'] = $value;
                        break;
                    case 'TIN NO.':
                        $data['tin_no'] = $value;
                        break;
                    case 'BANK ACCOUNT NO.':
                        $data['bank_account_no'] = $value;
                        break;
                    default:
                        continue 2;
                }

                $this->rowCounter++;
            }

            if (!empty($data)) {
                ClientInformations::updateOrCreate(
                    ['id' => 1],
                    $data
                );
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
        return $this->rowCounter;
    }

    public function getSkippedRows()
    {
        return $this->skippedRows;
    }

    public function startRow(): int
    {
        return 3;
    }
}
