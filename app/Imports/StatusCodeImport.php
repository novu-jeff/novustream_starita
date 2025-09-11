<?php

namespace App\Imports;

use App\Models\StatusCode;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class StatusCodeImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    SkipsEmptyRows,
    SkipsOnFailure,
    WithChunkReading
{
    use SkipsFailures;

    protected array $skippedRows = [];
    protected int $rowCounter = 3;
    protected string $sheetName;

    public function __construct(string $sheetName = 'Status Codes')
    {
        $this->sheetName = $sheetName;
    }

    public function rules(): array
    {
        return [
            'code' => ['required'],
            'name' => ['required'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'code.required' => 'Missing required field: code',
            'name.required' => 'Missing required field: name',
        ];
    }

    public function model(array $row)
    {
        $rowNum = $this->rowCounter++;

        $normalized = [];
        foreach ($row as $key => $value) {
            $cleanKey = strtolower(str_replace([' ', '-', '.'], '_', $key));
            $normalized[$cleanKey] = is_string($value) ? trim($value) : $value;
        }
        $row = $normalized;

        try {
            return StatusCode::updateOrCreate(
                ['code' => $row['code'] ?? null],
                [
                    'code' => $row['code'] ?? null,
                    'name' => $row['name'] ?? null,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Import error in {$this->sheetName}", [
                'error' => $e->getMessage(),
                'row'   => $row,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->skippedRows[] = "Row {$rowNum} skipped ({$this->sheetName}): " . $e->getMessage();
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

    public function getSkippedRows(): array
    {
        return $this->skippedRows;
    }

    public function getRowCounter(): int
    {
        return $this->rowCounter;
    }
}
