<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\FatSecretService;
use Tymon\JWTAuth\Facades\JWTAuth;


class MealPlanerController extends Controller
{
    protected $fatSecretService;

    public function __construct(FatSecretService $fatSecretService)
    {
        $this->fatSecretService = $fatSecretService;
    }

    public function generateMealPlan(Request $request)
    {
        $mealsPerDay = $request->input('meals_per_day', 3); 

        if (!in_array($mealsPerDay, [3, 4, 5, 6])) {
            return response()->json(['error' => 'Meals per day must be between 3 and 6'], 400);
        }

        $user = JWTAuth::user();

        if (!$user || !$user->goal()->value('caloric_target')) {
            return response()->json(['error' => 'Calorie goal not set for the user'], 400);
        }

        $dailyCalories = $user->goal()->value('caloric_target');
        $mealCalories = $dailyCalories / $mealsPerDay;

        $caloriesFrom = $mealCalories; 
        $caloriesTo = $mealCalories * 1.3;  

        try {
            $mealPlan = $this->getMealPlanByCalories($mealsPerDay, $caloriesFrom, $caloriesTo);

            $user->meal_plan()->updateOrCreate(
                ['user_id' => $user->id],
                ['plan' => $mealPlan]
            );

            return response()->json([
                'message' => 'Meal plan generated successfully',
                'meal_plan' => $mealPlan,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error generating meal plan: ' . $e->getMessage()], 500);
        }
    }

    private function getMealPlanByCalories($mealsPerDay, $caloriesFrom, $caloriesTo)
    {
        $mealPlan = [];
        $usedRecipeIds = [];
        $caloriesFrom = intval(round($caloriesFrom));
        $caloriesTo = intval(round($caloriesTo));
        $filters = [
            'calories.from' => $caloriesFrom,
            'calories.to' => $caloriesTo,
            'sort_by' => 'caloriesPerServingAscending',
        ];

        $recipes = $this->getRecipes($filters);

        for ($i = 0; $i < $mealsPerDay; $i++) {
            \Log::info("Selecting recipe for meal " . ($i + 1), [
                'calories_from' => $caloriesFrom,
                'calories_to' => $caloriesTo,
            ]);

            $recipeAdded = false;

            try {
                // Attempt to add a single recipe
                foreach ($recipes as $recipe) {
                    if (!isset($recipe['recipe_id']) || !isset($recipe['recipe_nutrition']['calories'])) {
                        \Log::warning("Invalid recipe data", ['recipe' => $recipe]);
                        continue;
                    }

                    if (!in_array($recipe['recipe_id'], $usedRecipeIds)) {
                        $mealPlan[] = $recipe;
                        $usedRecipeIds[] = $recipe['recipe_id'];
                        $recipeAdded = true;
                        break;
                    }
                }

                // If single recipe isnt found to attempt to combine recipes
                if (!$recipeAdded) {
                    \Log::info("No single recipe found for meal, attempting to find combined recipes.");

                    $combinedCalories = 0;
                    $selectedRecipes = [];
                    $adjustedFilters = [
                        'calories.from' => intval(round($caloriesFrom / 2)),
                        'calories.to' => intval(round($caloriesTo / 2)),
                        'sort_by' => 'caloriesPerServingAscending',
                    ];

                    while ($combinedCalories < $caloriesFrom) {
                        $additionalRecipes = $this->getRecipes($adjustedFilters);

                        foreach ($additionalRecipes as $additionalRecipe) {
                            if (!isset($additionalRecipe['recipe_id']) || !isset($additionalRecipe['recipe_nutrition']['calories'])) {
                                \Log::warning("Skipping invalid recipe during combination", ['recipe' => $additionalRecipe]);
                                continue;
                            }

                            if (!in_array($additionalRecipe['recipe_id'], $usedRecipeIds)) {
                                $recipeCalories = intval($additionalRecipe['recipe_nutrition']['calories']);
                                $combinedCalories += $recipeCalories;
                                $selectedRecipes[] = $additionalRecipe;
                                $usedRecipeIds[] = $additionalRecipe['recipe_id'];

                                // Break if the combined calories are within the target range
                                if ($combinedCalories >= $caloriesFrom && $combinedCalories <= $caloriesTo) {
                                    break 2;
                                }
                            }
                        }

                        // Adjust calorie range and make another API call if needed
                        $adjustedFilters['calories.from'] = max(1, intval(round($adjustedFilters['calories.from'] / 2)));
                        $adjustedFilters['calories.to'] = max(1, intval(round($adjustedFilters['calories.to'] / 2)));

                        // Stop attempting if the calorie range is too low
                        if ($adjustedFilters['calories.from'] < 50) {
                            \Log::warning("Calorie range too low to find recipes");
                            break;
                        }
                    }

                    // If combined recipes meet the calorie requirements, add them to the plan
                    if ($combinedCalories >= $caloriesFrom && $combinedCalories <= $caloriesTo) {
                        $mealPlan[] = [
                            'combined_recipes' => $selectedRecipes,
                            'total_calories' => $combinedCalories,
                        ];
                        $recipeAdded = true;
                    }
                }

                // Reuse an existing recipe if no new recipes are found
                if (!$recipeAdded && !empty($mealPlan)) {
                    $mealPlan[] = $mealPlan[array_rand($mealPlan)];
                    $recipeAdded = true;
                }
            } catch (\Exception $e) {
                \Log::error("Error adding recipe for meal " . ($i + 1), [
                    'message' => $e->getMessage(),
                    'calories_from' => $caloriesFrom,
                    'calories_to' => $caloriesTo,
                ]);
                continue;
            }

            if (!$recipeAdded) {
                \Log::warning("No recipe found or reused for meal " . ($i + 1));
            }
        }

        if (empty($mealPlan)) {
            throw new \Exception('Unable to generate a meal plan. No suitable recipes found.');
        }

        return $mealPlan;
    }

    private function getRecipes($filters)
    {
        $recipesResponse = $this->fatSecretService->searchRecipes('', $filters);

        \Log::info("Recipes fetched", ['recipesResponse' => $recipesResponse]);

        if (
            !isset($recipesResponse['recipes']['recipe']) ||
            !is_array($recipesResponse['recipes']['recipe']) ||
            empty($recipesResponse['recipes']['recipe'])
        ) {
            \Log::warning("No recipes found for filters", ['filters' => $filters]);
            return [];
        }

        return $recipesResponse['recipes']['recipe'];
    }

    public function getMealPlan()
    {
        $user = JWTAuth::user();
        $mealPlan = $user->meal_plan('user_id')->first();

        if (!$mealPlan) {
            return response()->json(['error' => 'No meal plan found'], 404);
        }

        return response()->json(['meal_plan' => $mealPlan->plan]);
    }
}
