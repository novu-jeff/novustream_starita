<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TechnicianAccountsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        return new User([
            'name'       => $row['name'],
            'email'      => $row['email'],
            'contact_no' => $row['contact_no'],
            'password'   => Hash::make($row['password']),
            'isActive'   => $row['isactive'] ?? 1,
        ]);
    }
}
