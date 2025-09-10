<?php

namespace App\Imports;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;

class TechnicianAccountsImport implements ToModel, WithHeadingRow, SkipsOnFailure
{
    use SkipsFailures;

    protected $rowCounter = 0;
    protected $inserted = 0;
    protected $updated = 0;

    public function model(array $row)
    {
        $this->rowCounter++;

        $normalized = [];
        foreach ($row as $key => $value) {
            $key = strtolower(trim($key));
            $key = str_replace([' ', '-', '.'], '_', $key);
            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }
        $row = $normalized;

        $admin = Admin::updateOrCreate(
            ['email' => $row['email']],
            [
                'name'      => $row['name'],
                'password'  => Hash::make($row['password']),
                'user_type' => 'technician',
            ]
        );

        // Track inserted vs updated
        if ($admin->wasRecentlyCreated) {
            $this->inserted++;
        } else {
            $this->updated++;
        }

        return $admin;
    }

    public function getRowCounter()
    {
        return $this->rowCounter;
    }

    public function getInsertedCount()
    {
        return $this->inserted;
    }

    public function getUpdatedCount()
    {
        return $this->updated;
    }
}
