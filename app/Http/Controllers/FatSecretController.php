<?php

namespace App\Http\Controllers;

use App\Services\FatSecretService;
use Illuminate\Http\Request;

class FatSecretController extends Controller
{
    protected $fatSecretService;

    public function __construct(FatSecretService $fatSecretService)
    {
        $this->fatSecretService = $fatSecretService;
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return response()->json(['error' => 'Query parameter is required'], 400);
        }

        try {
            $result = $this->fatSecretService->searchFoods($query);
            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }   
    
    public function logFoodIdByBarcode(Request $request)
    {
        $barcode = $request->input('barcode');

        if (!$barcode) {
            return response()->json(['error' => 'Barcode parameter is required'], 400);
        }

        try {
            $result = $this->fatSecretService->findFoodByBarcode($barcode);
            \Log::info($result);
            $food_id = $result['food_id']['value'];
            $food = $this->fatSecretService->getFoodById($food_id);
            \Log::info($food);
            return response()->json([
                'food' => $food
            ]);
        } catch (\Exception $e) {
            \Log::error('Error finding food by barcode:', ['message' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
    /*
    public function barcode(Request $request)
    {
        $barcode = $request->input('barcode');
        $result = $this->fatSecretService->getFoodDetails($barcode);
        return response()->json($result);
    }
    */
    
}
