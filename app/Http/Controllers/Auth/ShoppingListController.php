<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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

        // 🔥 Fetch the existing shopping list for this user (if it exists)
        $existingEntry = $user->shopping_list()->first();
        $existingList = [];
        if ($existingEntry && is_string($existingEntry->shopping_list)) {
            $existingList = json_decode($existingEntry->shopping_list, true);
        }

        // Create a map of old items for lookup
        $boughtMap = [];
        foreach ($existingList as $item) {
            $boughtMap[strtolower($item['name'])] = $item['bought'] ?? false;
        }

        // Attach old bought status if available
        foreach ($combinedIngredients as $key => &$ingredient) {
            $ingredientName = strtolower($ingredient['name']);
            $ingredient['bought'] = $boughtMap[$ingredientName] ?? false;
            $ingredient['id'] = $key + 1;
        }

        $user->shopping_list()->updateOrCreate(
            ['user_id' => $user->id],
            ['shopping_list' => json_encode($combinedIngredients)]
        );

        return response()->json([
            'shopping_list' => $combinedIngredients,
        ]);
    }

    public function clearBoughtItems(Request $request)
    {
        $user = JWTAuth::user();

        // Retrieve the shopping list entry for the user
        $shoppingListEntry = $user->shopping_list()->first();

        if (!$shoppingListEntry) {
            return response()->json(['error' => 'Shopping list not found'], 404);
        }

        // Get and decode the shopping list
        $shoppingList = json_decode($shoppingListEntry->shopping_list, true);

        // Filter out items that are marked as bought
        $filteredList = array_values(array_filter($shoppingList, function ($item) {
            return empty($item['bought']);
        }));

        // Reassign IDs
        foreach ($filteredList as $index => &$item) {
            $item['id'] = $index + 1;
        }

        // Update the shopping list in the database
        $shoppingListEntry->update([
            'shopping_list' => json_encode($filteredList),
        ]);

        return response()->json([
            'message' => 'Bought items cleared successfully.',
            'shopping_list' => $filteredList,
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
            'tsp' => 5,
            'tbsp' => 15,
            'cup' => 240,
            'oz' => 28,
            'lb' => 454,
            'kg' => 1000,
            'g' => 1,
        ];

        if (isset($conversionRates[$fromUnit][$toUnit])) {
            return $quantity * $conversionRates[$fromUnit][$toUnit];
        }

        return $quantity;
    }
}
