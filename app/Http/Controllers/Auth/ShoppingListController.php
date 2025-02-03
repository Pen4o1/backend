<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;

class ShoppingListController extends Controller
{
    public function updateShoppingItem(Request $request)
{
    // Get the authenticated user
    $user = JWTAuth::user();

    // Get the item ID and bought status from the request
    $itemId = $request->input('id');  // The ID of the item to update
    $boughtStatus = $request->input('bought');  // The new "bought" status

    // Retrieve the shopping list entry for the user
    $shoppingListEntry = $user->shopping_list()->first();

    // Check if the shopping list exists
    if (!$shoppingListEntry) {
        return response()->json(['error' => 'Shopping list not found'], 404);
    }

    // Get the existing shopping list
    $shoppingList = $shoppingListEntry->shopping_list;

    // Ensure it's an array (in case it was stored as JSON)
    if (!is_array($shoppingList)) {
        $shoppingList = json_decode($shoppingList, true);
    }

    // Find the item and update its "bought" status
    $itemFound = false;
    foreach ($shoppingList as &$item) {
        if ($item['id'] == $itemId) {
            $item['bought'] = $boughtStatus;  // Update the bought status
            $itemFound = true;
            break;
        }
    }

    // If the item wasn't found, return an error
    if (!$itemFound) {
        return response()->json(['error' => 'Item not found in shopping list'], 404);
    }

    // Sort the shopping list so that bought items appear at the top
    usort($shoppingList, function ($a, $b) {
        return $b['bought'] - $a['bought']; // Sort by "bought" status, bought items come first
    });

    // Update the shopping list entry in the database
    $shoppingListEntry->update([
        'shopping_list' => json_encode($shoppingList), // Save the updated list as JSON
    ]);

    // Return a success response with the updated shopping list
    return response()->json([
        'message' => 'Shopping item updated successfully',
        'shopping_list' => $shoppingList,
    ]);
}



    public function getShoppingPlan()
    {
        $user = JWTAuth::user();

        $mealPlan = $user->meal_plan()->value('plan');

        if (!$mealPlan) {
            return response()->json(['error' => 'No meal plan found'], 404);
        }

        $shoppingList = [];

        foreach ($mealPlan as $meal) {
            if (isset($meal['recipe_ingredients']['ingredient'])) {
                $this->processIngredients($meal['recipe_ingredients']['ingredient'], $shoppingList);
            } elseif (isset($meal['combined_recipes'])) {
                foreach ($meal['combined_recipes'] as $recipe) {
                    if (isset($recipe['recipe_ingredients']['ingredient'])) {
                        $this->processIngredients($recipe['recipe_ingredients']['ingredient'], $shoppingList);
                    }
                }
            }
        }

        $combinedIngredients = $this->combineIngredients($shoppingList);

        // Add unique IDs to each ingredient
        foreach ($combinedIngredients as $key => &$ingredient) {
            // If 'bought' is not set or false, set it to false
            if (!isset($ingredient['bought']) || $ingredient['bought'] === false) {
                $ingredient['bought'] = false;
            }
            // No change needed if 'bought' is true
            $ingredient['id'] = $key + 1; // Add a unique id (you can replace this with a UUID for better uniqueness)
        }

        $user->shopping_list()->updateOrCreate(
            ['user_id' => $user->id],
            ['shopping_list' => $combinedIngredients]
        );

        return response()->json([
            'shopping_list' => $combinedIngredients,
        ]);
    }

    private function processIngredients(array $ingredients, array &$ingredientList)
    {
        foreach ($ingredients as $ingredient) {
            $ingredientList[] = $this->parseIngredient($ingredient);
        }
    }

    private function parseIngredient(string $ingredient)
    {
        // regex to extract quantity, unit if exists
        $pattern = '/^([\d\/.,\s]+)?\s*(\b(?:tsp|tbsp|cup|oz|g|ml|l|lb|kg)\b)?\s*(.*)$/i';
        if (preg_match($pattern, $ingredient, $matches)) {
            $quantity = $matches[1] ?? '';
            $unit = $matches[2] ?? '';
            $name = $matches[3] ?? $ingredient;

            return [
                'name' => trim(strtolower($name)),
                'quantity' => $this->convertQuantityToFloat($quantity),
                'unit' => $quantity && $unit ? strtolower($unit) : '',
            ];
        }

        return [
            'name' => trim(strtolower($ingredient)),
            'quantity' => 0,
            'unit' => '',
        ];
    }

    private function convertQuantityToFloat(string $quantity)
    {
        if (str_contains($quantity, '/')) {
            $parts = explode('/', $quantity);
            return count($parts) === 2 ? floatval($parts[0]) / floatval($parts[1]) : floatval($quantity);
        }

        return floatval(str_replace([' ', ','], '', $quantity));
    }

    private function combineIngredients(array $ingredientList)
    {
        $aggregated = [];

        foreach ($ingredientList as $ingredient) {
            $name = $ingredient['name'];
            $unit = $ingredient['unit'];

            if (!isset($aggregated[$name])) {
                $aggregated[$name] = [
                    'name' => $name,
                    'quantity' => 0,
                    'unit' => $unit,
                ];
            }

            if ($aggregated[$name]['unit'] === $unit || !$unit) {
                $aggregated[$name]['quantity'] += $ingredient['quantity'];
            } else {
                $convertedQuantity = $this->convertUnit($ingredient['quantity'], $ingredient['unit'], $aggregated[$name]['unit']);
                $aggregated[$name]['quantity'] += $convertedQuantity;
            }
        }

        return array_values($aggregated);
    }

    private function convertUnit($quantity, $fromUnit, $toUnit)
    {
        $conversionRates = [
            'tsp' => ['tbsp' => 1 / 3, 'cup' => 1 / 48],
            'tbsp' => ['tsp' => 3, 'cup' => 1 / 16],
            'cup' => ['tsp' => 48, 'tbsp' => 16],
            'oz' => ['g' => 28.3495],
            'g' => ['oz' => 1 / 28.3495],
        ];

        if (isset($conversionRates[$fromUnit][$toUnit])) {
            return $quantity * $conversionRates[$fromUnit][$toUnit];
        }

        return $quantity;
    }
}
