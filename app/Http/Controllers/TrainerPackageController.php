<?php

namespace App\Http\Controllers;

use App\Models\TrainerPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TrainerPackageController extends Controller
{
    public function createTrainerPackage(Request $request)
    {
        $package_details = $request->all();
        $validator = Validator::make($package_details, [
            'name' => 'required',
            'duration' => 'required', // in days
            'package_amount' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors(),
                'code' => 500
            ], 200);
        } else {
            $package = TrainerPackage::create($package_details);
            if ($package) {
                return response()->json([
                    'message' => 'Package Added Successfully',
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Failed to add Package',
                    'code' => 500
                ], 200);
            }
        }
    }

    public function retriveTrainerPackages(Request $request)
    {
        $search_text = $request->search_text;
        $start_index = $request->index > 0 ? $request->index : 0;
        $limit = $request->limit > 0 ? $request->limit : 5;
        $package_names = TrainerPackage::get(['id', 'name']);
        $load_data = DB::table('trainer_packages')
            ->where('name', 'like', "%$search_text%")
            ->orWhere('duration', 'like', "%$search_text%")
            ->offset($start_index)
            ->limit($limit)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'name' => $package_names,
            'load_data' => $load_data,
            'code' => 200
        ], 200);
    }

    //update package
    public function updateTrainerPackage(Request $request)
    {
        $model = TrainerPackage::find($request->package_id);
        if ($model) {
            $payload = Arr::only($request->all(), (new TrainerPackage())->getFillable());
            if ($payload === []) {
                return response()->json([
                    'message' => 'No valid fields to update',
                    'code' => 422,
                ], 200);
            }

            $result = $model->update($payload);
            if ($result) {
                return response()->json([
                    'message' => "Data Updated Successfully",
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Something went Wrong",
                    'code' => 500
                ], 200);
            }
        } else {
            return response()->json([
                'message' => "Invalid Id",
                'code' => 500
            ], 200);
        }
    }

    public function deleteTrainerPackage(Request $request)
    {
        $model = TrainerPackage::find($request->id);
        if ($model != null) {
            if ($model->delete()) {
                return response()->json([
                    'message' => "Delete Successfull",
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Failed to Delete",
                    'code' => 500
                ], 200);
            }
        } else {
            return response()->json([
                'message' => "Invalid Id",
                'code' => 500,
            ], 200);
        }
    }

    // Auto Complete
    public function autoComplete(Request $request)
    {
        $search_text = $request->search_text;
        $data = DB::table('trainer_packages')->where('name', 'like', "%$search_text%")
            ->orWhere('duration', 'like', "%$search_text%")
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) use ($search_text) {

                // If the name matches, return only the name
                if (strpos($item->name, $search_text) !== false) {
                    return ['name' => $item->name];
                    // If the duration matches, return only the duration
                } elseif (stripos($item->duration, $search_text) !== false) {
                    return ['name' => $item->duration];
                }
            })->toArray();
        if ($data != []) {
            return response()->json([
                'data' => $data,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'code' => 500,
                'message' => "No Record Found"
            ], 200);
        }
    }
    //active inactive
    public function active_inactive(Request $request)
    {

        $model = TrainerPackage::find($request->id);
        if ($model != null) {
            $update_status = $model->update($request->all());
            if ($update_status) {
                return response()->json([
                    'message' => "Status Updated Successfully",
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Something went Wrong",
                    'code' => 500
                ], 200);
            }
        } else {
            return response()->json([
                'message' => "Invalid Id",
                'code' => 500
            ], 200);
        }
    }

    //package dropdown
    public function trainer_package_drop_down()
    {
        $package_details = TrainerPackage::get(['id', 'name', 'duration']);
        if ($package_details) {
            return response()->json([
                'data' => $package_details,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "Something Went wrong",
                'code' => 500
            ], 200);
        }
    }

    //package value
    public function package_value(Request $request)
    {
        $package_details = TrainerPackage::find($request->id);
        if ($package_details) {
            return response()->json([
                'data' => $package_details->package_amount,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "Something Went wrong",
                'code' => 500
            ], 200);
        }
    }
}
