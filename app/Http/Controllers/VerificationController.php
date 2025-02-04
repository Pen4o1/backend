<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\VerificationMail;
use App\Models\EmailVerification;
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

        // Generate a random verification code
        $verificationCode = random_int(100000, 999999); 
        $expiresAt = Carbon::now()->addMinutes(10); 

        // Update or create the verification record
        $verification = EmailVerification::updateOrCreate(
            ['email' => $request->email],
            ['verification_code' => $verificationCode, 'expires_at' => $expiresAt]
        );

        // Send the verification email
        Mail::to($request->email)->send(new VerificationMail($verificationCode));

        return response()->json(['message' => 'Verification code sent successfully.']);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'verification_code' => 'required|string',
        ]);

        // Check if the verification code matches
        $verification = EmailVerification::where('email', $request->email)
            ->where('verification_code', $request->verification_code)
            ->first();

        if (!$verification) {
            return response()->json(['message' => 'Invalid verification code.'], 400);
        }

        // Check if the code has expired
        if ($verification->isExpired()) {
            return response()->json(['message' => 'Verification code expired.'], 400);
        }

        // Use the relationship to get the related user
        $user = $verification->user; // This uses the 'user' relationship defined in the EmailVerification model

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Mark the user's email as verified
        $user->email_verified_at = now();
        $user->save();

        // Delete the verification record after successful verification
        $verification->delete();

        return response()->json(['message' => 'Email verified successfully.']);
    }
}
