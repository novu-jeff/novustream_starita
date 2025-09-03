<?php

namespace App\Imports;

use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AdminAccountsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new Admin([
            'name'      => $row['name'],
            'email'     => $row['email'],
            'user_type' => $row['user_type'] ?? 'admin',
            'password'  => Hash::make($row['password']),
        ]);
    }
}
