<?php

namespace App\Imports;

use App\Models\Admin;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;

class AdminAccountsImport implements ToModel, WithValidation, SkipsOnFailure, WithHeadingRow, SkipsEmptyRows
{
    use SkipsFailures;

    private int $importedCount = 0; // counter

    public function model(array $row)
    {
        $this->importedCount++; // increment for each row

        $normalized = [];
        foreach ($row as $key => $value) {
            $key = strtolower(trim($key));
            $key = str_replace([' ', '-', '.'], '_', $key);
            $normalized[$key] = is_string($value) ? trim($value) : $value;
        }
        $row = $normalized;

        return new Admin([
            'name'      => $row['name'] ?? null,
            'email'     => $row['email'] ?? null,
            'user_type' => $row['user_type'] ?? 'admin',
            'password'  => isset($row['password']) ? bcrypt($row['password']) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name'     => 'required|string',
            'email'    => 'required|email|unique:admins,email',
            'password' => 'required|string|min:6',
        ];
    }

    // getter to retrieve the count after import
    public function getImportedCount(): int
    {
        return $this->importedCount;
    }
}
