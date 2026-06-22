<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validation = Validator::make($request->input(), [
            'username' => 'nullable|string|required_without:email',
            'email' => 'nullable|string|required_without:username',
            'password' => 'required|string',
            'isRemember'=>'null|boolean'
        ]);

        if ($validation->fails()) {
            // Handle the validation errors
            return response()->json([
                'success' => false,
                'errors' => $validation->errors()
            ], 422);
        }

        $credentials = [
//            'username'=>$request->username,
            'email'=>$request->email,
            'password'=>$request->password,
//            'isRemember'=>$request->isRemember
        ];

        if(Auth::attempt(['email'=>$request->email,'password'=>$request->password, 'is_active'=> true])){
            $user = Auth::user();
            $token = $user->createToken('MyAppToken')->accessToken;

            $role = $user->roles()->first()?->name ?? 'User';
            $permissions = $user->hasRole('Super Admin')
                ? \Spatie\Permission\Models\Permission::pluck('name')->toArray()
                : $user->getAllPermissions()->pluck('name')->toArray();

            $userArray = $user->toArray();
            $userArray['role'] = $role;
            $userArray['permissions'] = $permissions;
            // Keep is_admin for brief backwards compatibility or fallback checks
            $userArray['is_admin'] = $user->hasRole('Super Admin');

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $userArray,
            ], 200);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

    }
}
