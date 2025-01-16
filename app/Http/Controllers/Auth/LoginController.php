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
        // Validate the request
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        // Attempt to authenticate the user
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();

            try {
                // Generate a JWT token for the authenticated user
                $token = JWTAuth::fromUser($user);
            } catch (JWTException $e) {
                return response()->json([
                    'message' => 'Could not create token.',
                ], 500);
            }

            // Return the token and user details in the response
            return response()->json([
                'message' => 'Login complete',
                'user' => $user,
                'token' => $token, // Include the token in the response
                'redirect_url' => '/home',
            ], 201);
        }

        // If authentication fails, return an error response
        return response()->json([
            'message' => 'Invalid credentials',
        ], 422);
    }
}
