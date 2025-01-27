<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth; 
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Facades\JWTException;
use Tymon\JWTAuth\Payload;
use Tymon\JWTAuth\Claims\Collection;
use Tymon\JWTAuth\Claims\Custom;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;
 


class GoogleController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->scopes(['profile', 'https://www.googleapis.com/auth/user.birthday.read', 'https://www.googleapis.com/auth/user.gender.read'])->stateless()->redirect();
    }

    /**
     * Handle the Google OAuth callback and retrieve user details.
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $accessToken = $googleUser->token;

            \Log::info('Generated accesstoken Token: ' . $accessToken);  

            $response = Http::withToken($accessToken)->get('https://people.googleapis.com/v1/people/me', [
                'personFields' => 'birthdays,genders,names',
            ]);

            $additionalData = $response->json();

            $gender = collect($additionalData['genders'] ?? [])
            ->firstWhere('metadata.primary', true)['value'] ?? null;

            $birthdayData = collect($additionalData['birthdays'] ?? [])
                ->firstWhere('metadata.primary', true)['date'] ?? null;

            $birthdate = $birthdayData // to format the birthdate cause they are diff fields
                ? sprintf('%04d-%02d-%02d', $birthdayData['year'], $birthdayData['month'], $birthdayData['day'])
                : null;

                $user = User::where('email', $googleUser->getEmail())->first();

                if ($user) {        
                        $user->update([
                            'first_name' => $givenName ?? $googleUser->user['given_name'] ?? $googleUser->getName(), // if the user hasnt set his name to get his name
                            'last_name' => $familyName ?? $googleUser->user['family_name'] ?? '',
                            'gender' => $gender,
                            'birthdate' => $birthdate,
                            'compleated' => $user->compleated,
                        ]);
                } else {
                    $user = User::create([
                        'email' => $googleUser->getEmail(),
                        'first_name' => $givenName ?? $googleUser->user['given_name'] ?? $googleUser->getName(),
                        'last_name' => $familyName ?? $googleUser->user['family_name'] ?? '',
                        'password' => null,
                        'google_id' => $googleUser->getId(),
                        'compleated' => false,
                        'birthdate' => $birthdate,
                        'gender' => $gender,
                    ]);
                }

                try {
                    $token = JWTAuth::fromUser($user);
                } catch (JWTException $e) {
                    return response()->json([
                        'message' => 'Could not create token.',
                    ], 500);
                }
    
                return response()->json([
                    'message' => 'Login complete',
                    'user' => $user,
                    'token' => $token, 
                    'redirect_url' => '/home',
                ], 201);

        } catch (\Exception $e) {
            return redirect()->away('http://localhost:8100/login')->with('error', 'Unable to authenticate.');
        }
    }

    public function handleMobileGoogleLogin(Request $request)
    {
        $idToken = $request->input('id_token');
        $accessToken = $request->input('access_token');

        // Verify ID token
        $client = new GoogleClient([
            'client_id' => config('services.google.web_client_id') // Fallback client ID
        ]);

        $allowedClientIds = [
            config('services.google.web_client_id'),
            config('services.google.ios_client_id'),
            config('services.google.android_client_id'),
        ];

        $payload = null;
        foreach ($allowedClientIds as $clientId) {
            $client->setClientId($clientId);
            try {
                $payload = $client->verifyIdToken($idToken);
                if ($payload) break;
            } catch (\Exception $e) {
                continue;
            }
        }

        if (!$payload) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        // Get additional user data
        $response = Http::withToken($accessToken)->get('https://people.googleapis.com/v1/people/me', [
            'personFields' => 'birthdays,genders,names',
        ]);

        $additionalData = $response->json();

        // Process user data (same as your existing code)
        $gender = collect($additionalData['genders'] ?? [])
            ->firstWhere('metadata.primary', true)['value'] ?? null;

        $birthdayData = collect($additionalData['birthdays'] ?? [])
            ->firstWhere('metadata.primary', true)['date'] ?? null;

        $birthdate = $birthdayData
            ? sprintf('%04d-%02d-%02d', $birthdayData['year'], $birthdayData['month'], $birthdayData['day'])
            : null;

        // Find or create user
        $user = User::updateOrCreate(
            ['email' => $payload['email']],
            [
                'first_name' => $payload['given_name'] ?? '',
                'last_name' => $payload['family_name'] ?? '',
                'google_id' => $payload['sub'],
                'gender' => $gender,
                'birthdate' => $birthdate,
                'password' => null,
                'compleated' => false,
            ]
        );

        // Generate JWT
        try {
            $token = JWTAuth::fromUser($user);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Could not create token'], 500);
        }

        return response()->json([
            'message' => 'Login complete',
            'user' => $user,
            'token' => $token,
            'redirect_url' => '/home',
        ]);
    }
}