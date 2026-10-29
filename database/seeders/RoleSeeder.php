<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Roles;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'Administrator role'],
            ['name' => 'technician', 'description' => 'Technician role'],
            ['name' => 'cashier', 'description' => 'Cashier role'],
            // ['name' => 'inspector', 'description' => 'Inspector role'],
        ];

        foreach ($roles as $role) {
            Roles::updateOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }
    }
}
