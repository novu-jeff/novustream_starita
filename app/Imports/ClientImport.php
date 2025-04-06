<?php

namespace App\Imports;

use App\Models\User;
use App\Models\UserAccounts;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ClientImport implements ToModel, WithHeadingRow, WithValidation
{
    public function model(array $row)
    {
        // Create the user and save to the database
        $user = User::create([
            'name'         => trim($row['name']),
            'contact_no'   => trim($row['contact_no']),
        ]);

        // Ensure the user was created before creating the UserAccount
        if ($user) {
            UserAccounts::create([
                'user_id'         => $user->id,
                'account_no'   => trim($row['account_no']),
                'address'      => trim($row['address']),
                'rate_code'       => trim($row['rate_code']),
                'status'         => trim($row['status']),
                'meter_brand'    => trim($row['meter_brand']),
                'meter_serial_no' => trim($row['meter_serial_no']),
                'sc_no'           => trim($row['sc_no']),
                'date_connected'  => trim($row['date_connected']),
                'sequence_no'     => trim($row['sequence_no']),
            ]);
        }
    }


    public function rules(): array
    {
        return [
            'account_no'       => 'required|string|unique:concessioner_accounts,account_no',
            'name'             => 'required|string',
            'date_connected'   => 'nullable|date',
        ];
    }
}
