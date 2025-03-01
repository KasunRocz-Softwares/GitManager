<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
}
