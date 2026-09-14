<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Uses firstOrCreate (not updateOrCreate) so a password changed later
     * through the app isn't silently reset back to the default on the next
     * deploy - this only ever creates the account once.
     */
    public function run(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        if (! $adminRole) {
            return;
        }

        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@gmail.com')],
            [
                'name' => 'Administrator',
                'password' => env('ADMIN_PASSWORD', '12345678'),
                'status' => 'approved',
                'role_id' => $adminRole->id,
            ]
        );
    }
}
