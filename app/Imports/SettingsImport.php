<?php

namespace App\Imports;

use App\Models\Ruling;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class SettingsImport implements ToCollection, WithStartRow, SkipsEmptyRows, SkipsOnFailure
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

            foreach ($rows as $row) {

                $key = strtoupper(trim($row[0] ?? ''));
                $value = trim($row[1] ?? '');

                if ($key === '' || $value === '') {
                    $this->skippedRows[] = "Row skipped: Empty key or value.";
                    continue;
                }

                switch ($key) {
                    case 'DUE DATE':
                        $data['due_date'] = $value;
                        break;
                    case 'DISCONNECTION DATE':
                        $data['disconnection_date'] = $value;
                        break;
                    case 'DISCONNECTION RULE':
                        $data['disconnection_rule'] = $value;
                        break;
                    case 'SENIOR DISCOUNT LIMIT':
                        $data['snr_dc_rule'] = $value;
                        break;
                    default:
                        $this->skippedRows[] = "Row skipped: Empty key or value.";
                        break;
                }

            }

            $this->rowCounter = count($data);

            if (!empty($data)) {
                Ruling::updateOrCreate(['id' => 1], $data);
            }

        } catch (\Exception $e) {
            Log::error('Import error in Settings Sheet', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function startRow(): int
    {
        return 3;
    }

    public function getRowCounter()
    {
        return $this->rowCounter; 
    }

    public function getSkippedRows()
    {
        return $this->skippedRows;
    }
}
