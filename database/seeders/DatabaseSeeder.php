<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Run Roles and Permissions Seeder
        $this->call(RolesAndPermissionsSeeder::class);

        // 2. Create a default Super Admin if database is empty
        if (User::count() === 0) {
            User::create([
                'name' => 'Admin User',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('password'),
                'is_active' => true,
                'is_admin' => true,
            ]);
        }

        // 3. Migrate all database users to roles based on is_admin column values
        User::chunk(100, function ($users) {
            foreach ($users as $user) {
                if ($user->is_admin) {
                    $user->syncRoles(['Super Admin']);
                } else {
                    $user->syncRoles(['User']);
                }
            }
        });

    }
}
