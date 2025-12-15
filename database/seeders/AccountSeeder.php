<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Admin User',
                'email' => 'admin@gmail.com',
                'user_type' => 'admin',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Admin::updateOrCreate(
            ['email' => 'cashier@gmail.com'],
            [
                'name' => 'Cashier User',
                'email' => 'cashier@gmail.com',
                'user_type' => 'cashier',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Admin::updateOrCreate(
            ['email' => 'technician@gmail.com'],
            [
                'name' => 'Technician User',
                'email' => 'technician@gmail.com',
                'user_type' => 'technician',
                'email_verified_at' => now(),
                'password' => Hash::make('password'),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
