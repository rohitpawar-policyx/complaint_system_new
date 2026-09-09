<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::updateOrCreate(
            ['name' => 'user'],
            ['description' => 'Standard complaint system user']
        );

        Role::updateOrCreate(
            ['name' => 'admin'],
            ['description' => 'Complaint system administrator']
        );
    }
}
