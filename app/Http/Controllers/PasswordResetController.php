<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PasswordReset;
use App\Mail\ResetPasswordMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    /**
     * Send the reset code to the user's email.
     */
    public function sendResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        
        $code = rand(100000, 999999);

        // Set the expiration time for the code (e.g., 10 minutes)
        $expiresAt = Carbon::now()->addMinutes(10);

        PasswordReset::updateOrCreate(
            [ 'email' => $request->email],
            [
                'token' => $code,
                'created_at' => Carbon::now(),
                'expires_at' => $expiresAt,  // Set expiration time
            ]
        );

        // Send the reset email with the code
        Mail::to($request->email)->send(new ResetPasswordMail($code));

        return response()->json(['message' => 'Reset code sent to your email.']);
    }

    /**
     * Reset the user's password using the verification code.
     */
    public function resetPassword(Request $request)
    {
        // Validate the request
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|digits:6',
            'password' => 'required|min:8',
        ]);

        // Retrieve the reset request based on email and code
        $resetRequest = PasswordReset::where('email', $request->email)
            ->where('token', $request->code)
            ->first();
        
        if (!$resetRequest) {
            return response()->json(['message' => 'Invalid or expired code.'], 400);
        }

        if ($resetRequest->isExpired()) {
            return response()->json(['message' => 'The code has expired. Please request a new one.'], 400);
        }

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        $resetRequest->delete();

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
