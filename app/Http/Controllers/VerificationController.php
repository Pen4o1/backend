<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\VerificationMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class VerificationController extends Controller
{
    public function sendVerificationCode(Request $request)
    {
        // Validate the email address
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        // Generate a random verification code
        $verificationCode = random_int(100000, 999999);
        $expiresAt = Carbon::now()->addMinutes(10);

        // Use the relationship to update or create a verification record
        $user->emailVerification()->updateOrCreate(
            ['email' => $user->email],
            [
                'verification_code' => $verificationCode,
                'expires_at' => $expiresAt,
            ]
        );

        // Send the verification email
        Mail::to($user->email)->send(new VerificationMail($verificationCode));

        return response()->json(['message' => 'Verification code sent successfully.']);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'verification_code' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        $verification = $user->emailVerification;

        if (!$verification || $verification->verification_code !== $request->verification_code) {
            return response()->json(['message' => 'Invalid verification code.'], 400);
        }

        if ($verification->isExpired()) {
            return response()->json(['message' => 'Verification code expired.'], 400);
        }

        // Verify the user's email
        $user->email_verified_at = now();
        $user->save();
        $verification->delete();

        return response()->json(['message' => 'Email verified successfully.']);
    }
}

