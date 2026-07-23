<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\DietPlan;
use Exception;


class DietPlanController extends Controller
{
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'diet_name' => 'required|string|max:255',
                'days' => 'nullable|array',
                'days.*' => 'string',
    
                'preworkout' => 'nullable|string',
                'preworkout_time' => 'nullable|string',
                'post_workout' => 'nullable|string',
                'post_workout_time' => 'nullable|string',
                'breakfast' => 'nullable|string',
                'breakfast_time' => 'nullable|string',
                'morning_snaks' => 'nullable|string',
                'morning_snaks_time' => 'nullable|string',
                'evening_snaks1' => 'nullable|string',
                'evening_snaks1_time' => 'nullable|string',
                'evening_snaks2' => 'nullable|string',
                'evening_snaks2_time' => 'nullable|string',
                'dinner' => 'nullable|string',
                'dinner_time' => 'nullable|string',
                'meal1' => 'nullable|string',
                'meal1_time' => 'nullable|string',
                'meal2' => 'nullable|string',
                'meal2_time' => 'nullable|string',
            ]);
    
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'code' => 422
                ], 422);
            }
    
            $data = $validator->validated();
            $data['days'] = json_encode($request->input('days', []));
    
            $dietPlan = DietPlan::create($data);
    
            return response()->json([
                'message' => 'Diet plan saved successfully.',
                'data' => $dietPlan,
                'code' => 200
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }

    public function index(Request $request)
{
    try {
        $limit = $request->input('limit', 10); // default 10 records per page
        $page = $request->input('page', 1); // default to page 1
        $skip = ($page - 1) * $limit;

        $total = DietPlan::count();
        $dietPlans = DietPlan::skip($skip)->take($limit)->get();

        // Decode 'days' JSON string to array
        foreach ($dietPlans as $plan) {
            $plan->days = json_decode($plan->days, true);
        }

        return response()->json([
            'message' => 'Diet plans fetched successfully.',
            'code' => 200,
            'data' => $dietPlans,
            'pagination' => [
                'total' => $total,
                'page' => (int)$page,
                'limit' => (int)$limit,
                'total_pages' => ceil($total / $limit)
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error fetching diet plans.',
            'code' => 500,
            'error' => $e->getMessage()
        ], 500);
    }
}
public function delete(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'id' => 'required|exists:diet_plans,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'code' => 422
            ], 422);
        }

        $dietPlan = DietPlan::find($request->id);
        $dietPlan->delete();

        return response()->json([
            'message' => 'Diet plan deleted successfully.',
            'code' => 200
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error deleting diet plan.',
            'code' => 500,
            'error' => $e->getMessage()
        ], 500);
    }
}

}
