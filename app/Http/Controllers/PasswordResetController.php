<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        $user = User::where('email', $request->email)->firstOrFail();

        $code = rand(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(10);

        // Use the relationship to update or create a password reset record
        $user->passwordReset()->updateOrCreate(
            ['email' => $user->email],
            ['token' => $code, 'expires_at' => $expiresAt]
        );

        // Send the reset email with the code
        Mail::to($user->email)->send(new ResetPasswordMail($code));

        return response()->json(['message' => 'Reset code sent to your email.']);
    }

    /**
     * Reset the user's password using the verification code.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'code' => 'required|digits:6',
            'password' => 'required|min:8',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $resetRequest = $user->passwordReset()
            ->where('token', $request->code)
            ->first();

        if (!$resetRequest) {
            return response()->json(['message' => 'Invalid or expired code.'], 400);
        }

        if ($resetRequest->isExpired()) {
            return response()->json(['message' => 'The code has expired. Please request a new one.'], 400);
        }

        // Reset the user's password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset request after successful password reset
        $resetRequest->delete();

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
