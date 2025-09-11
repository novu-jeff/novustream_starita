<?php

namespace App\Imports;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithStartRow;

class TechnicianAccountsImport implements ToCollection, SkipsEmptyRows, WithStartRow
{
    protected $rowCounter = 0;
    protected $skippedRows = [];

    public function startRow(): int
    {
        return 3;
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {

            $rowData = [
                'name'     => isset($row[0]) ? trim($row[0]) : null,
                'email'    => isset($row[1]) ? trim($row[1]) : null,
                'password' => isset($row[2]) ? trim($row[2]) : null,
            ];

            if (empty($rowData['name']) || empty($rowData['email']) || empty($rowData['password'])) {
                $this->skippedRows[] = "Row " . ($index + $this->startRow()) . " skipped: Missing required fields.";
                continue;
            }

            Admin::updateOrCreate(
                ['email' => $rowData['email']],
                [
                    'name'      => $rowData['name'],
                    'user_type' => 'technician',
                    'password'  => Hash::make($rowData['password']),
                ]
            );

            $this->rowCounter++;
        }
    }

    public function getRowCounter(): int
    {
        return $this->rowCounter;
    }

    public function getSkippedRows(): array
    {
        return $this->skippedRows;
    }
}
