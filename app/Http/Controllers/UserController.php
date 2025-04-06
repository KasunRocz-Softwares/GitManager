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
    public function index()
    {
        $users = User::all();
        return response()->json([
            "users" => $users,
        ]);
    }

    public function store(Request $request)
    {
        if (Auth::user()->is_admin) {
            // Validate incoming request
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users,email',
                'password' => 'required|string|min:6|max:255',
            ]);

            // Create new user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);

            return response()->json([
                "success" => true,
                "message" => "User created successfully"
            ], 201);
        } else {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }
    }

    public function getUser(User $user)
    {
        if (Auth::user()->is_admin) {

            $repositories = UserRepository::where('user_id', $user->id)->get();

            $data = [
                'user' => $user,
                'repositories' => $repositories,
            ];

            return response()->json([
                "success" => true,
                "data"=>$data,
            ], 200);
        } else {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
        }
    }


    public function storeUserRepo(Request $request)
    {
        if (Auth::user()->is_admin) {
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

        return response()->json([
            "success" => false,
            "message" => "Access denied"
        ], 403);
    }

    public function updateUser(Request $request, User $user)
    {
        if (!Auth::user()->is_admin) {
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

            if (isset($validated['repository_ids'])) {
                $user->repositories()->sync($validated['repository_ids']);
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
                "message" => "Failed to update user",
                "error" => $e->getMessage()
            ], 500);
        }
    }

    public function toggleUserStatus(Request $request, User $user)
    {
        if (!Auth::user()->is_admin) {
            return response()->json([
                "success" => false,
                "message" => "Access denied"
            ], 403);
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
