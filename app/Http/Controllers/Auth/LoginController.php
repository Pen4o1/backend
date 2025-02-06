<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginController extends Controller
{   
    public function login(Request $request)
    {    
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:8',
        ]);

        if (JWTAuth::attempt($request->only('email', 'password'))) {
            $user = JWTAuth::user();

            try {
                $token = JWTAuth::fromUser($user);
            } catch (JWTException $e) {
                return response()->json([
                    'message' => 'Could not create token.',
                ], 500);
            }

            return response()->json([
                'message' => 'Login complete',
                'user' => $user,
                'token' => $token, 
                'redirect_url' => '/home',
            ], 201);
        }

        return response()->json([
            'message' => 'Invalid credentials',
        ], 422);
    }
}
