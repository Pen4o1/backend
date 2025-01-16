<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtBearerMiddleware
{
    public function handle($request, Closure $next)
    {
        $authHeader = $request->header('Authorization');

        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1]; // Extract the token
            try {
                // Set and authenticate token
                JWTAuth::setToken($token);
                $user = JWTAuth::authenticate();
                auth()->setUser($user);
                \Log::info('Authenticated User:', ['user' => $user]); // Debugging logs

            } catch (\Exception $e) {
                \Log::error('Token Authentication Error:', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Unauthorized: Invalid Token'], 401);
            }
        } else {
            return response()->json(['error' => 'Unauthorized: Token Missing'], 401);
        }

        return $next($request);
    }
}
