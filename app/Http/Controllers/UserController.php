<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->can('view_users')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $query = User::query()->with('roles');

        if ($request->has('search') && !empty($request->input('search'))) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== 'all') {
            $status = $request->input('is_active') === 'active' ? 1 : 0;
            $query->where('is_active', $status);
        }

        $paginate = $request->input('paginate') ?? $_GET['paginate'] ?? null;
        if ($paginate === 'true') {
            $perPage = $request->input('per_page') ?? $_GET['per_page'] ?? 10;
            $page = $request->input('page') ?? $_GET['page'] ?? 1;
            return response()->json($query->paginate($perPage, ['*'], 'page', $page));
        }
        $users = $query->get();
        return response()->json([
            "users" => $users,
        ]);
    }

    public function store(Request $request)
    {
        if (!$request->user()->can('create_users')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        // Validate incoming request
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'password' => 'required|string|min:6|max:255',
            'role' => 'required|string|exists:roles,name',
        ]);

        DB::beginTransaction();
        try {
            // Create new user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            // Assign role
            $user->assignRole($validated['role']);

            DB::commit();

            return response()->json([
                "success" => true,
                "message" => "User created successfully"
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                "message" => "Failed to create user",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function getUser(Request $request, User $user)
    {
        if (!$request->user()->can('view_users')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $repositories = UserRepository::where('user_id', $user->id)->get();
        $user->load('roles');

        $data = [
            'user' => $user,
            'repositories' => $repositories,
        ];

        return response()->json([
            "success" => true,
            "data" => $data,
        ], 200);
    }

    public function storeUserRepo(Request $request)
    {
        if (!$request->user()->can('edit_users')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'repository_id' => 'required|exists:repositories,id',
        ]);

        UserRepository::create($validated);

        return response()->json([
            "success" => true,
            "message" => "User assigned to repository successfully"
        ]);
    }

    public function updateUser(Request $request, User $user)
    {
        if (!$request->user()->can('edit_users')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:6|max:255',
            'repository_ids' => 'nullable|array',
            'repository_ids.*' => 'exists:repositories,id',
            'role' => 'nullable|string|exists:roles,name',
        ]);

        DB::beginTransaction();

        try {
            // Update user data
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            if (!empty($validated['password'])) {
                $user->password = bcrypt($validated['password']);
            }
            $user->save();

            // Sync repositories if provided
            if (isset($validated['repository_ids'])) {
                $user->repositories()->sync($validated['repository_ids']);
            }

            // Sync role if provided
            if (!empty($validated['role'])) {
                // If the user being modified is the ONLY Super Admin, we should protect it
                if ($user->hasRole('Super Admin') && $validated['role'] !== 'Super Admin') {
                    $superAdminCount = User::role('Super Admin')->count();
                    if ($superAdminCount <= 1) {
                        throw new \Exception("Cannot demote the only Super Admin in the system.");
                    }
                }
                $user->syncRoles([$validated['role']]);
            }

            DB::commit();
            return response()->json([
                "success" => true,
                "message" => "User updated successfully"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "success" => false,
                "message" => "Failed to update user: " . $e->getMessage()
            ], 500);
        }
    }

    public function toggleUserStatus(Request $request, User $user)
    {
        if (!$request->user()->can('delete_users')) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }

        // Prevent disabling the only Super Admin
        if ($user->hasRole('Super Admin') && $user->is_active) {
            $superAdminCount = User::role('Super Admin')->where('is_active', true)->count();
            if ($superAdminCount <= 1) {
                return response()->json([
                    "success" => false,
                    "message" => "Cannot deactivate the only active Super Admin"
                ], 400);
            }
        }

        $validated = $request->validate([
            'is_active' => 'required|boolean',
        ]);

        $user->is_active = $validated['is_active'];
        $user->save();

        return response()->json([
            "success" => true,
            "message" => "User status updated successfully",
            "data" => [
                "user_id" => $user->id,
                "is_active" => $user->is_active
            ]
        ]);
    }
}
