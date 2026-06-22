<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->can('view_roles')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $query = Role::withCount('users')->with('permissions');

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        $paginate = $request->input('paginate') ?? $_GET['paginate'] ?? null;
        if ($paginate === 'true') {
            $perPage = $request->input('per_page') ?? $_GET['per_page'] ?? 10;
            $page = $request->input('page') ?? $_GET['page'] ?? 1;
            return response()->json($query->paginate($perPage, ['*'], 'page', $page));
        }

        $roles = $query->get();
        
        return response()->json([
            'success' => true,
            'roles' => $roles
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->can('create_roles')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create(['name' => $validated['name']]);

            if (!empty($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'role' => $role->load('permissions')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()->can('view_roles')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'role' => $role
        ]);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->can('edit_roles')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $role = Role::findOrFail($id);

        if ($role->name === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify Super Admin role'
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,' . $id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name'
        ]);

        DB::beginTransaction();
        try {
            $role->name = $validated['name'];
            $role->save();

            // Sync permissions (except if it's the default Admin role and they try to wipe everything - though they can, Super Admin is the safe backup)
            if (isset($validated['permissions'])) {
                $role->syncPermissions($validated['permissions']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'role' => $role->load('permissions')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->can('delete_roles')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $role = Role::findOrFail($id);

        if ($role->name === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete Super Admin role'
            ], 403);
        }

        // Prevent deleting default Admin or User roles unless they really want to, but at least protect Super Admin.
        // Actually, user says "Defult 1 Role it will cant delete Super Admin, ... other Admin, User".
        // Let's enforce that Super Admin is the one that strictly cannot be deleted.

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }
}
