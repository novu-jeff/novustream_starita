<?php

namespace App\Imports;

use App\Models\Zones;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ZoneImport implements
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
            'zone' => ['required'],
            'area' => ['required'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'zone.required' => 'Missing required field: zone',
            'area.required' => 'Missing required field: area',
        ];
    }

    public function model(array $row)
    {
        $rowNum = $this->rowCounter++;

        $normalized = [];
        foreach ($row as $key => $value) {
            $key = strtolower(trim($key));
            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }
        $row = $normalized;

        try {
            Zones::updateOrCreate(
                ['zone' => $row['zone']],
                [
                    'zone' => $row['zone'],
                    'area' => $row['area'],
                ]
            );
        } catch (\Exception $e) {
            Log::error('Import error in Zones Sheet', [
                'row_num' => $rowNum,
                'error'   => $e->getMessage(),
                'row'     => $row,
                'trace'   => $e->getTraceAsString(),
            ]);

            $this->skippedRows[] = "Row $rowNum skipped: Exception - " . $e->getMessage();
            return null;
        }
    }

    public function headingRow(): int
    {
        return 2;
    }

    public function chunkSize(): int
    {
        return 100;
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
