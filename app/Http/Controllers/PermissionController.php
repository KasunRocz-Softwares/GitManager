<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->can('view_roles')) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied'
            ], 403);
        }

        $permissions = Permission::all();
        
        // Group permissions by category
        $grouped = $permissions->groupBy('category');

        return response()->json([
            'success' => true,
            'permissions' => $grouped
        ]);
    }
}
