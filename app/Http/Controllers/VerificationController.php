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

        $verificationCode = random_int(100000, 999999); 
        $expiresAt = Carbon::now()->addMinutes(10); 

        $verification = EmailVerification::updateOrCreate(
            ['email' => $request->email],
            ['verification_code' => $verificationCode, 'expires_at' => $expiresAt]
        );

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

        $verification->delete();

        return response()->json(['message' => 'Email verified successfully.']);
    }
}
