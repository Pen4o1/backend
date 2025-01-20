<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Controllers\Controller;
use App\Mail\VerificationMail;
use App\Models\EmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
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
        $verificationCode = Str::random(6); // You can customize this length or format
        $expiresAt = Carbon::now()->addMinutes(10); // Expire the code after 10 minutes

        // Store the verification code in the EmailVerification table
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

        // Mark the user's email as verified
        $user = User::where('email', $request->email)->first();
        $user->email_verified_at = now();
        $user->save();

        // Optionally, delete the verification code after successful verification
        $verification->delete();

        return response()->json(['message' => 'Email verified successfully.']);
    }
}
