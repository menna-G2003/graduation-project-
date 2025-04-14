<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'admin',
                'description' => 'Full control over the system',
            ],
            [
                'id' => 2,
                'name' => 'owner',
                'description' => 'Can create and manage property listings',
            ],
            [
                'id' => 3,
                'name' => 'user',
                'description' => 'Can browse and search for properties',
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['id' => $role['id']], $role);
        }
    }
} 