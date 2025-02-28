<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
            'firstname' => 'Admin',
            'lastname' => 'User',
            'middlename' => null,
            'address' => '123 Admin St',
            'contact_no' => null,
            'user_type' => 'admin',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'admin02@gmail.com'],
            [
            'firstname' => 'Admin',
            'lastname' => 'User',
            'middlename' => null,
            'address' => '456 Client St',
            'contact_no' => null,
            'user_type' => 'admin',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'client@gmail.com'],
            [
            'firstname' => 'Client',
            'lastname' => 'User',
            'middlename' => null,
            'address' => '456 Client St',
            'contact_no' => null,
            'user_type' => 'client',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'contract_no' => '654321',
            'contract_date' => '2023-01-01',
            'property_type' => 1,
            'meter_no' => '123',
            'isValidated' => true,
            'created_at' => now(),
            'updated_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'technician@gmail.com'],
            [
            'firstname' => 'Technician',
            'lastname' => 'User',
            'middlename' => null,
            'address' => '123 Client St',
            'contact_no' => null,
            'user_type' => 'technician',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'created_at' => now(),
            'updated_at' => now(),
            ]
        );
    }
}
