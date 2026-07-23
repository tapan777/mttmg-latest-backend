<?php

namespace App\Http\Controllers;

use App\Models\AdmissionValue;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdmissionValueController extends Controller
{
    //create admission value
    public function createadmissionValue(Request $request)
    {
        $package_details = $request->all();
        $validator = Validator::make($package_details, [
            'name' => 'required',
            'duration' => 'required', // in days
            'admission_value' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors(),
                'code' => 500
            ], 200);
        } else {
            $package_details['status'] = 1;
            $package = AdmissionValue::create($package_details);
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

    //update package
    public function updateAdmissionValue(Request $request)
    {
        $model = AdmissionValue::find($request->admission_value_id);
        if ($model) {
            // Only fillable columns; ignore extras such as auth_user_id from the client.
            $payload = Arr::only($request->all(), (new AdmissionValue())->getFillable());
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

    //delete 
    public function deleteAdmissionValue(Request $request)
    {
        $model = AdmissionValue::find($request->id);
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

    //retrive
    public function retriveAdmissionValue(Request $request)
    {
        $search_text = $request->search_text;
        $start_index = $request->index > 0 ? $request->index : 0;
        $limit = $request->limit > 0 ? $request->limit : 5;
        $package_names = AdmissionValue::get(['id', 'name']);
        $load_data = DB::table('admission_values')
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

    //active inactive
    public function active_inactive(Request $request)
    {
        $model = AdmissionValue::find($request->id);
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
    public function admission_value_drop_down()
    {
        $package_details = AdmissionValue::where('status', 1)->get(['id', 'name']);
        // dd($package_details->isEmpty());
        if ($package_details->isEmpty()) {
            return response()->json([
                'message' => "No Data Found",
                'code' => 500
            ], 200);
        } else {
            return response()->json([
                'data' => $package_details,
                'code' => 200
            ], 200);
        }
    }

    //package value
    public function admission_value(Request $request)
    {
        $package_details = AdmissionValue::find($request->id);
        if ($package_details) {
            return response()->json([
                'data' => $package_details->admission_value,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "Something Went wrong",
                'code' => 500
            ], 200);
        }
    }

         // Auto Complete
         public function autoComplete(Request $request)
         {
             $search_text = $request->search_text;
             $data = DB::table('admission_values')->where('name', 'like', "%$search_text%")
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
}
