<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Google\Client as GoogleClient;
use Tymon\JWTAuth\Facades\JWTAuth;
use Log;

class GoogleController extends Controller
{
    public function handleGoogleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required',
            'access_token' => 'required'
        ]);

        $idToken = $request->input('id_token');
        $accessToken = $request->input('access_token');

        // Verify ID token with multiple client IDs
        $client = new GoogleClient();
        $allowedClientIds = [
            config('services.google.web_client_id'),
            config('services.google.ios_client_id'),
            config('services.google.android_client_id')
        ];

        $payload = null;
        foreach ($allowedClientIds as $clientId) {
            $client->setClientId($clientId);
            try {
                $payload = $client->verifyIdToken($idToken);
                if ($payload) break;
            } catch (\Exception $e) {
                Log::error("Google token verification failed for $clientId: " . $e->getMessage());
            }
        }

        if (!$payload) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        // Get additional user data
        $personData = $this->getGooglePersonData($accessToken);

        $user = User::where('email', $payload['email'])->first();

        $user = User::updateOrCreate(
            ['email' => $payload['email']],
            array_merge(
                $this->mapUserData($payload, $personData),
                ['email_verified_at' => $user ? $user->email_verified_at : now()]
            )
        );

        // Generate JWT
        try {
            $token = JWTAuth::fromUser($user);
        } catch (\Exception $e) {
            Log::error('JWT generation failed: ' . $e->getMessage());
            return response()->json(['message' => 'Could not create token'], 500);
        }

        \Log::info('User logged in with Google:', ['user' => $user]);

        return response()->json([
            'token' => $token,
            'user' => $user,
            'redirect_url' => '/home',
        ]);
    }

    private function getGooglePersonData($accessToken)
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get('https://people.googleapis.com/v1/people/me', [
                    'personFields' => 'birthdays,genders,names'
                ]);

            return $response->successful() ? $response->json() : [];
        } catch (\Exception $e) {
            Log::error('People API error: ' . $e->getMessage());
            return [];
        }
    }

    private function mapUserData($payload, $personData)
    {
        return [
            'first_name' => $payload['given_name'] ?? $this->extractFirstName($personData),
            'last_name' => $payload['family_name'] ?? $this->extractLastName($personData),
            'google_id' => $payload['sub'],
            'gender' => $this->extractGender($personData),
            'birthdate' => $this->extractBirthdate($personData),
            'password' => null,
            'compleated' => $this->shouldCompleteProfile($payload, $personData),
        ];
    }

    private function extractFirstName($personData)
    {
        return collect($personData['names'] ?? [])
            ->firstWhere('metadata.primary', true)['givenName'] ?? '';
    }

    private function extractLastName($personData)
    {
        return collect($personData['names'] ?? [])
            ->firstWhere('metadata.primary', true)['familyName'] ?? '';
    }

    private function extractGender($personData)
    {
        return collect($personData['genders'] ?? [])
            ->firstWhere('metadata.primary', true)['value'] ?? null;
    }

    private function extractBirthdate($personData)
    {
        $birthdayData = collect($personData['birthdays'] ?? [])
            ->firstWhere('metadata.primary', true)['date'] ?? null;

        return $birthdayData ? sprintf('%04d-%02d-%02d', 
            $birthdayData['year'], 
            $birthdayData['month'], 
            $birthdayData['day']
        ) : null;
    }

    private function shouldCompleteProfile($payload, $personData)
    {
        $user = User::where('email', $payload['email'])->first();

        return isset($payload['given_name']) && 
               isset($payload['family_name']) && 
               $this->extractBirthdate($personData) &&
               $this->extractGender($personData) &&
               $user && 
               !empty($user->kilos) && 
               !empty($user->height);
    }
}