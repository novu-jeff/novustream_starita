<?php

namespace App\Imports;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithStartRow;

class AdminAccountsImport implements ToCollection, SkipsEmptyRows, WithStartRow
{
    protected $rowCounter = 0;
    protected $skippedRows = [];

    public function startRow(): int
    {
        return 2; // skip the first row with IMPORTANT note
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {

            $rowData = [
                'name' => isset($row[0]) ? trim($row[0]) : null,
                'role' => isset($row[1]) ? trim($row[1]) : null,
                'email' => isset($row[2]) ? trim($row[2]) : null,
                'password' => isset($row[3]) ? trim($row[3]) : null,
            ];

            if (empty($rowData['name']) || empty($rowData['email']) || empty($rowData['password'])) {
                $this->skippedRows[] = "Row " . ($index + $this->startRow()) . " skipped: Missing required fields.";
                continue;
            }

            $userType = $rowData['role'] ?? 'admin';

            Admin::updateOrCreate(
                ['email' => $rowData['email']],
                [
                    'name'      => $rowData['name'],
                    'user_type' => $userType,
                    'password'  => Hash::make($rowData['password']),
                ]
            );

            $this->rowCounter++;
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
}
