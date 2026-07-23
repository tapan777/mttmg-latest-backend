<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Member;
use App\Models\Package;
use App\Models\Payment;
use App\Models\YearlyPackage;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PackagesController extends Controller
{
    //create packages
    public function create_member_package(Request $request)
    {
        $package_details = $request->all();
        $validator = Validator::make($package_details, [
            'name' => 'required',
            'duration' => 'required', // in days
            'package_amount' => 'required',
            'admission_value' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors(),
                'code' => 500
            ], 200);
        } else {
            $package = Package::create($package_details);
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

    //retrive packages  
    public function retrivePackages(Request $request)
    {
        $search_text = $request->search_text;
        $start_index = $request->index > 0 ? $request->index : 0;
        $limit = $request->limit > 0 ? $request->limit : 5;
    
        // Get package names for dropdown or similar purpose
        $package_names = Package::where('package_type', 0)->get(['id', 'name']);

        // Get total count of matching records with package_type = 0
        $total_count = DB::table('packages')
            ->where('package_type', 0) // Add package_type condition
            ->where(function ($query) use ($search_text) {
                $query->where('name', 'like', "%$search_text%")
                      ->orWhere('duration', 'like', "%$search_text%");
            })
            ->count();
        
        // Fetch paginated data with package_type = 0
        $load_data = DB::table('packages')
            ->where('package_type', 0) // Add package_type condition
            ->where(function ($query) use ($search_text) {
                $query->where('name', 'like', "%$search_text%")
                      ->orWhere('duration', 'like', "%$search_text%");
            })
            ->offset($start_index)
            ->limit($limit)
            ->orderBy('id', 'desc')
            ->get();
    
        return response()->json([
            'name' => $package_names,
            'load_data' => $load_data,
            'total_count' => $total_count, // Include the total count
            'code' => 200
        ], 200);
    }
    

    //package dropdown
    public function drop_down()
    {
        $package_details = Package::where('package_type', 0)->get(['id', 'name']);

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
        $package_details = Package::where('id', $request->id)->get([
            'package_amount',
            'admission_value'
        ])->first();
    
        if (!$package_details) {
            return response()->json([
                'message' => "No data found for this id",
                'code' => 500
            ], 200);
        }
    
        // Default package amount
        $package_amount = $package_details->package_amount;
    
        // Get previous due if any
        if ($request->member_id) {
            $last_payment = Payment::where('member_id', $request->member_id)
                ->where('payment_type', 0)
                ->latest()
                ->first();
    
            if ($last_payment && $last_payment->due > 0) {
                $package_amount += $last_payment->due;
            }
        }
    
        // Check if admission value needs to be added
        $yearly_package = new YearlyPackageController;
        $check_validation = $yearly_package->checkYearlyValidation($request->member_id);
    
        $total_payable_amount = $check_validation
            ? $package_amount + $package_details->admission_value
            : $package_amount;
    
        return response()->json([
            'data' => [
                'package_amount' => $package_amount,
                'total_payble_amount' => $total_payable_amount
            ],
            'code' => 200
        ], 200);
    }
    
    //update package
    public function update_package(Request $request)
    {
        $model = Package::find($request->id);
        if ($model != null) {
            $payload = Arr::only($request->all(), (new Package())->getFillable());
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
    //delete package
    public function delete_package(Request $request)
    {
        $model = Package::find($request->id);
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
    public function auto_complete(Request $request)
    {
        $search_text = $request->search_text;
        $data = DB::table('packages')
        ->where('package_type', 0) // Add the package_type condition
        ->where(function ($query) use ($search_text) {
            $query->where('name', 'like', "%$search_text%")
                  ->orWhere('duration', 'like', "%$search_text%");
        })
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

        $model = Package::find($request->id);
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

    public function check_expired_package()
    {
        $records = Payment::where('end_date', date('Y-m-d', strtotime(Carbon::today())))->get();
        // $records= Payment::where('end_date', '2024-10-11')->get();
        foreach ($records as $record) {
            Member::find($record->member_id)->update(['status' => 2]);
        }
    }
}
