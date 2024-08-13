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

        if(Auth::attempt(['email'=>$request->email,'password'=>$request->password])){
            $user = Auth::user();
            $token = $user->createToken('MyAppToken')->accessToken;

            return response()->json([
                'success' => true,
                'token' => $token,
                'user' => $user,
            ], 200);
        }else{
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        }

    }
}
