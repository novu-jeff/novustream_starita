<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
{
    User::updateOrCreate(
        ['email' => 'sample1@gmail.com'], // search by unique email
        [
            'name' => 'sample',
            'contact_no' => '1234567890',
            'password' => bcrypt('password123'),
            'isActive' => 1,
        ]
    );

    User::factory()->count(100)->create();
}

}
