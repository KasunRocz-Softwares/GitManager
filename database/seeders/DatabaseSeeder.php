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

        // 4. Seed default pipeline if pipelines table is empty
        if (\App\Models\Pipeline::count() === 0) {
            \App\Models\Pipeline::create([
                'name' => 'Branch Checkout Pipeline',
                'description' => 'Default pipeline to checkout a branch and pull the latest changes.',
                'stages' => [
                    [
                        'name' => 'Git Fetch',
                        'has_input' => false,
                        'input_label' => '',
                        'input_placeholder' => '',
                        'input_key' => '',
                        'commands' => ['sudo git fetch']
                    ],
                    [
                        'name' => 'Git Reset',
                        'has_input' => false,
                        'input_label' => '',
                        'input_placeholder' => '',
                        'input_key' => '',
                        'commands' => ['sudo git reset --hard']
                    ],
                    [
                        'name' => 'Git Checkout',
                        'has_input' => true,
                        'input_label' => 'Branch Name',
                        'input_placeholder' => 'e.g., main or release/v1.0',
                        'input_key' => 'branch_name',
                        'commands' => ['sudo git checkout {{branch_name}}']
                    ],
                    [
                        'name' => 'Git Pull',
                        'has_input' => false,
                        'input_label' => '',
                        'input_placeholder' => '',
                        'input_key' => '',
                        'commands' => ['sudo git pull']
                    ],
                ]
            ]);
        }
    }
}
