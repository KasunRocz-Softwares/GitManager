<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Categorized permissions
        $permissionsByCategory = [
            'Dashboard' => [
                'view_dashboard' => 'View dashboard metrics and charts',
            ],
            'Projects' => [
                'view_projects' => 'View projects',
                'create_projects' => 'Create new projects',
                'edit_projects' => 'Edit existing projects',
                'delete_projects' => 'Delete projects',
            ],
            'Repositories' => [
                'view_repositories' => 'View repositories',
                'create_repositories' => 'Create new repositories',
                'edit_repositories' => 'Edit existing repositories',
                'delete_repositories' => 'Delete repositories',
            ],
            'Git Operations' => [
                'view_branches' => 'View Git branches',
                'checkout_branch' => 'Checkout Git branches',
                'run_git_commands' => 'Run Git commands',
            ],
            'Users' => [
                'view_users' => 'View system users',
                'create_users' => 'Create new system users',
                'edit_users' => 'Edit existing system users',
                'delete_users' => 'Delete/deactivate system users',
            ],
            'Roles & Permissions' => [
                'view_roles' => 'View system roles and permissions',
                'create_roles' => 'Create new roles',
                'edit_roles' => 'Edit role permissions',
                'delete_roles' => 'Delete roles',
            ],
        ];

        // Seed permissions
        $allPermissionNames = [];
        foreach ($permissionsByCategory as $category => $permissions) {
            foreach ($permissions as $name => $description) {
                Permission::updateOrCreate(
                    ['name' => $name],
                    ['category' => $category, 'description' => $description]
                );
                $allPermissionNames[] = $name;
            }
        }

        // Create/retrieve default roles
        $superAdmin = Role::updateOrCreate(['name' => 'Super Admin']);
        // Super Admin gets all permissions
        $superAdmin->syncPermissions($allPermissionNames);

        $admin = Role::updateOrCreate(['name' => 'Admin']);
        // Admin gets all permissions by default (can be customized later)
        $admin->syncPermissions($allPermissionNames);

        $userRole = Role::updateOrCreate(['name' => 'User']);
        // User gets standard view permissions
        $userRole->syncPermissions([
            'view_dashboard',
            'view_projects',
            'view_repositories',
            'view_branches',
        ]);
    }
}
