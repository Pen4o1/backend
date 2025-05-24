<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Auth\GoalController;
use App\Http\Controllers\Auth\DailyMacrosController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\MealPlanerController;
use App\Http\Controllers\FatSecretController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\Auth\ShoppingListController;
use App\Http\Controllers\Auth\ProfileController;
use Tymon\JWTAuth\Http\Middleware\Authenticate as JwtAuthenticate;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\PasswordResetController;


Route::get('/recipes/search', [RecipeController::class, 'search']);
Route::post('/get-meal', [MealPlanerController::class, 'getMeal']);
Route::post('/register', [RegisterController::class, 'Register']);
Route::post('/login', [LoginController::class, 'Login']);
Route::get('/foods/search', [FatSecretController::class, 'search']);
Route::post('/foods/barcode', [FatSecretController::class, 'logFoodIdByBarcode']);

Route::controller(VerificationController::class)->group(function () {
    Route::post('/send/verification/code', 'sendVerificationCode');
    Route::post('/verify/email', 'verifyEmail');
});

Route::controller(PasswordResetController::class)->group(function () {
    Route::post('/password/reset/send/code', 'sendResetCode');
    Route::post('/password/reset', 'resetPassword');
});

Route::post('/validate/token', function (Request $request) {
    try {
        // Retrieve the Bearer token from the Authorization header
        $token = $request->bearerToken();

        if (!$token) {
            throw new \Exception('Token not provided');
        }

        // Authenticate the user with the token
        $user = JWTAuth::setToken($token)->authenticate();

        if (!$user) {
            throw new \Exception('Invalid token or user not found');
        }

        return response()->json([
            'valid' => true,
            'user' => $user,
            'email' => $user->email,
            'compleated' => $user->compleated,
            'email_verified_at' => $user->email_verified_at,
            'isGoogle' => $user->google_id,
        ]);

    } catch (\Exception $e) {
        Log::error("Token validation failed: " . $e->getMessage());

        return response()->json([
            'valid' => false,
            'error' => $e->getMessage(),
        ], 401);
    }
});

Route::middleware([JwtAuthenticate::class])->group(function () {
    Route::get('/profile/status', [ProfileController::class, 'getProfileStatus']);
    Route::get('/user/profile', [ProfileController::class, 'getProfile']);
    Route::post('/update/profile', [ProfileController::class, 'addToProfile']);
    Route::post('/save/goal', [GoalController::class, 'saveGoal']);
    Route::post('/save/daily/macros', [DailyMacrosController::class, 'storeCal']);
    Route::get('/get/daily/macros', [DailyMacrosController::class, 'getDailyMacros']);
    Route::post('/generate/meal/plan', [MealPlanerController::class, 'generateMealPlan']);
    Route::get('/get/meal/plan', [MealPlanerController::class, 'getMealPlan']); 
    Route::get('/get/shopping/list', [ShoppingListController::class, 'getShoppingPlan']);
    Route::post('/change/password', [ProfileController::class, 'changePassword']);
    Route::post('/upload-profile-picture', [ProfileController::class, 'uploadProfilePicture']);
    Route::post('/update/shopping/item', [ShoppingListController::class, 'updateShoppingItem']);
    Route::post('/clear/bought/items', [ShoppingListController::class, 'clearBoughtItems']);
});


Route::controller(GoogleController::class)->group(function () {
    Route::get('auth/google', 'redirectToGoogle');
    Route::get('auth/callback', 'handleGoogleCallback');
    Route::post('web/google-login', 'handleGoogleLogin');
});