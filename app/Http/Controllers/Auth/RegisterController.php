<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function Register(Request $request)
    {
        $first_name_validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z]+$/']
        ]);
        
        $last_name_validator = Validator::make($request->all(), [
            'last_name' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z]+$/']
        ]);

        $password_validator = Validator::make($request->all(), [
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[a-z]/', 
                'regex:/[A-Z]/', 
                'regex:/[0-9]/', 
                'regex:/[@$!%*#?&]/', 
            ]
        ]);

        $email_format_validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255']
        ]);
        
        $email_unique_validator = Validator::make($request->all(), [
            'email' => 'unique:users'
        ]);

        $birthday_validator = Validator::make($request->all(), [
            'birthdate' => 'required|date'
        ]);
        
        $kilos_validator = Validator::make($request->all(), [
            'kilos' => 'required|numeric'
        ]);
        
        $height_validator = Validator::make($request->all(), [
            'height' => 'required|numeric'
        ]);
        
        $gender_validator = Validator::make($request->all(), [
            'gender' => 'nullable|in:male,female' 
        ]);

        if ($first_name_validator->fails()) {
            return response()->json([
                'message' => 'Invalid first name',
                'errors' => $first_name_validator->errors()
            ], 422); 
        }

        if ($last_name_validator->fails()) {
            return response()->json([
                'message' => 'Invalid last name',
                'errors' => $last_name_validator->errors()
            ], 422); 
        }

        if ($password_validator->fails()) {
            return response()->json([
                'message' => 'Invalid password',
                'errors' => $password_validator->errors()
            ], 422);
        }
        
        if ($email_format_validator->fails()) {
            return response()->json([
                'message' => 'Invalid email format',
                'errors' => $email_format_validator->errors()
            ], 422);
        }

        if ($email_unique_validator->fails()) {
            return response()->json([
                'message' => 'Email already exists',
                'errors' => $email_unique_validator->errors()
            ], 422);
        }

        if ($birthday_validator->fails()) {
            return response()->json([
                'message' => 'The birthday must be a valid date',
                'errors' => $birthday_validator->errors()
            ], 422);
        }
        
        if ($kilos_validator->fails()) {
            return response()->json([
                'message' => 'The kilograms must be a valid number',
                'errors' => $kilos_validator->errors()
            ], 422);
        }
        
        if ($height_validator->fails()) {
            return response()->json([
                'message' => 'The height must be a valid number',
                'errors' => $height_validator->errors()
            ], 422);
        }

        if ($gender_validator->fails()) {
            return response()->json([
                'message' => 'Gender must be either male or female',
                'errors' => $gender_validator->errors()
            ], 422);
        }

        $profile_picture_path = null;
        if ($request->hasFile('profile_picture')) {
            $request->validate([
                'profile_picture' => 'image|mimes:jpeg,png,jpg|max:2048',
            ]);

            $file = $request->file('profile_picture');
            $fileName = 'profile_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('profile_pictures', $fileName, 'public');
            $profile_picture_path = 'storage/' . $path;
        }

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password), 
            'birthdate' => $request->birthdate,
            'kilos' => $request->kilos,
            'height' => $request->height,
            'gender' => $request->gender, 
            'compleated' => true,
            'profile_picture' => $profile_picture_path,
        ]);
        
        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            return response()->json([
                'message' => 'Could not create token.',
            ], 500);
        }

        return response()->json([
            'message' => 'Registration complete',
            'user' => $user,
            'token' => $token, 
        ], 201);
    }
}
