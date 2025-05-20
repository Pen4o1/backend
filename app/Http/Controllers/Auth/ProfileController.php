<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function getProfileStatus(Request $request)
    {
        $user = JWTAuth::user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $requiredFields = ['birthdate', 'kilos', 'height', 'last_name', 'first_name', 'gender'];
        $completedFields = [];
        $incompleteFields = [];
        $profileData = [];

        foreach ($requiredFields as $field) {
            if (!empty($user->$field)) {
                $completedFields[] = $field;
                $profileData[$field] = $user->$field;
            } else {
                $incompleteFields[] = $field;
                $profileData[$field] = ''; 
            }
        }

        return response()->json([
            'completed_fields' => $completedFields,
            'incomplete_fields' => $incompleteFields,
            'profile_data' => $profileData, 
        ]);
    }

    public function addToProfile(Request $request)
    {
        $user = JWTAuth::user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        $validatedData = $request->validate([
            'birthdate' => 'nullable|date',
            'kilos' => 'nullable|numeric|min:1',
            'height' => 'nullable|numeric|min:1',
            'last_name' => 'nullable|string|max:255',
            'first_name' => 'nullable|string|max:255',
            'gender' => 'nullable|in:male,female',
        ]);
        $validatedData['compleated'] = true;

        $user->update($validatedData);

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
            'redirect_url' => '/home',
        ]);
    }

    public function changePassword(Request $request){
        $user = JWTAuth::user();

        $request->validate([
            'password' => 'required|min:8',
        ]);

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password successfuly changed'
        ]);
    }

    public function uploadProfilePicture(Request $request)
    {
        $user = JWTAuth::user();

        if (!$user) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

         $request->validate([
            'profile_picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if (!$request->hasFile('profile_picture')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }

        $file = $request->file('profile_picture');

        $fileName = 'profile_' . time() . '.' . $file->getClientOriginalExtension();

        $path = $file->storeAs('profile_pictures', $fileName, 'public');

        $user->profile_picture = 'storage/' . $path;
        $user->save();

        return response()->json([
            'message' => 'Profile picture uploaded successfully',
            'profile_picture_url' => asset('storage/' . $path),
        ], 200);
    }

    public function getProfile(Request $request) {
        $user = JWTAuth::user();

        return response()->json([
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'profile_picture' => asset($user->profile_picture),
        ]);
    }   
}
