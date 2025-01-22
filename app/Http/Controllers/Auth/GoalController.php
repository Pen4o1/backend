<?php

namespace App\Http\Controllers\Auth;

use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class GoalController extends Controller
{
    public function saveGoal(Request $request)
    {
        $validated = $request->validate([
            'activityLevel' => 'required|string|in:sedentary,lightly_active,moderately_active,very_active,extra_active',
            'targetWeight' => 'required|numeric|min:1',
        ]);

        $activityLevel = $validated['activityLevel'];
        $targetWeight = $validated['targetWeight'];

        $user = JWTAuth::user();

        $currentWeight = $user->kilos;
        $height = $user->height;
        $birthdate = $user->birthdate;
        $gender = $user->gender;

        if (!$currentWeight || !$height || !$birthdate || !$gender) {
            return response()->json([
                'message' => 'User data (weight, height, birthdate, gender) is incomplete.',
            ], 400);
        }

        $age = Carbon::parse($birthdate)->age;

        // Calculate BMR (Basal Metabolic Rate) using Mifflin-St Jeor formula
        $bmr = $this->calculateBMR($currentWeight, $height, $age, $gender);

        // Activity multiplier based on activity level
        $activityMultiplier = $this->getActivityMultiplier($activityLevel);

        // Maintenance calories (BMR * activity multiplier)
        $maintenanceCalories = $bmr * $activityMultiplier;

        // Determine goal and adjust calories using the provided function
        if ($targetWeight < $currentWeight) {
            $goal = 'lose';
        } elseif ($targetWeight > $currentWeight) {
            $goal = 'gain';
        } else {
            $goal = 'maintain';
        }

        $calories = $this->adjustCaloriesForGoal($maintenanceCalories, $goal);

        // Save the target weight and caloric target in the database
        $user->goal()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'activity_level' => $activityLevel,
                'goal' => $goal,
                'target_weight' => $targetWeight,
                'caloric_target' => $calories,
            ]
        );

        // Calculate time to reach the target weight
        $caloricDifferencePerDay = abs($calories - $maintenanceCalories);
        $weeklyCaloricChange = $caloricDifferencePerDay * 7;

        if ($weeklyCaloricChange > 0) {
            $weightDifference = abs($targetWeight - $currentWeight);
            $caloriesPerKg = 7700; // 1kg of fat = ~7700 calories
            $totalCaloriesNeeded = $weightDifference * $caloriesPerKg;

            $weeksToGoal = $totalCaloriesNeeded / $weeklyCaloricChange;
            $daysToGoal = ceil($weeksToGoal * 7); // Convert weeks to days
        } else {
            $daysToGoal = 0; // No caloric difference; user is already maintaining weight
        }

        return response()->json([
            'message' => 'Target weight saved successfully.',
            'goal' => $goal,
            'calories' => $calories,
            'daysToGoal' => $daysToGoal,
        ]);
    }

    private function calculateBMR($weight, $height, $age, $gender)
    {
        if ($gender === 'male') {
            return 10 * $weight + 6.25 * $height - 5 * $age + 5;
        } else {
            return 10 * $weight + 6.25 * $height - 5 * $age - 161;
        }
    }

    private function getActivityMultiplier($activityLevel)
    {
        $multipliers = [
            'sedentary' => 1.2,
            'lightly_active' => 1.375,
            'moderately_active' => 1.55,
            'very_active' => 1.725,
            'extra_active' => 1.9,
        ];

        return $multipliers[$activityLevel] ?? 1.2;
    }

    private function adjustCaloriesForGoal($maintenanceCalories, $goal)
    {
        if ($goal === 'lose') {
            return $maintenanceCalories - 0.2 * $maintenanceCalories; // 20% caloric deficit
        } elseif ($goal === 'gain') {
            return $maintenanceCalories + 0.2 * $maintenanceCalories; // 20% caloric surplus
        }

        return $maintenanceCalories; // Maintenance level
    }
}
