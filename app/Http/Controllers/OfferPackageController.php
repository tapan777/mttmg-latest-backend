<?php

namespace App\Http\Controllers;

use App\Models\OfferPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class OfferPackageController extends Controller
{
    public function createOfferPackage(Request $request){

        $payload = $request->all();
        $data = OfferPackage::create($payload);
        if($data){
            return response()->json([
                'message' => "Offer Package Created Successfully",
                'code' => 200
            ],200);
        }else{
            return response()->json([
                'message' => "Something went wrong",
                'code' => 500
            ],200);
        }
    }

    public function retrive_offer_packages(Request $request)
    {
        $limit = $request->limit > 0 ? $request->limit : 10;
        $offset = $request->index > 0 ? $request->index : 0;
        $search_text = $request->search_text;
    
        $q = OfferPackage::limit($limit)
            ->offset($offset)
            ->orderBy('id', 'desc');
    
        if ($search_text) {
            $q->where('name', 'like', "%{$search_text}%")
                ->orWhere('description', 'like', "%{$search_text}%")
                ->orWhere('value', 'like', "%{$search_text}%");
        }
    
        // Retrieve the paginated data
        $ofr_pckg_data = $q->get();
    
        // Get the total count of records that match the search criteria (without pagination)
        $total_count = OfferPackage::when($search_text, function ($query) use ($search_text) {
            return $query->where('name', 'like', "%{$search_text}%")
                         ->orWhere('description', 'like', "%{$search_text}%")
                         ->orWhere('value', 'like', "%{$search_text}%");
        })->count();
    
        if ($ofr_pckg_data) {
            return response()->json([
                'data' => $ofr_pckg_data,
                'total_count' => $total_count,  // Include the total count in the response
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No Offer Package Data Found",
                'code' => 500
            ], 200);
        }
    }
    
    public function autoComplete_retrive_offer_packages(Request $request){
        
        $search_text = $request->search_text;
        $q = OfferPackage::orderBy('id', 'desc');

        if($search_text){
            $q->where('name', 'like', "%{$search_text}%")
            ->orWhere('description', 'like', "%{$search_text}%")
            ->orWhere('value', 'like', "%{$search_text}%");
        }
        $ofr_pckg_data = $q->get()->map(function ($item) use ($search_text) {
            // dd($item->members->email);
            if (stripos($item->name, $search_text) !== false) {
                return ['search_text' => $item->name];
            } else if (stripos($item->description, $search_text) !== false) {
                return ['search_text' => $item->description];
            } else if (stripos($item->value, $search_text) !== false) {
                return ['search_text' => $item->value];
            }
        })->filter();
        
        if($ofr_pckg_data){
            return response()->json([
                'data' => $ofr_pckg_data,
                'code' => 200
            ],200);
        }else{
            return response()->json([
                'message' => "No Offer Package Data Found",
                'code' => 500
            ],200);
        }
    }

    public function updateOfferPackage(Request $request){

        $payload = $request->all();
        $pkg_data = OfferPackage::find($request->id);
        Arr::forget($payload, 'id');
        if ($pkg_data) {
            $result = $pkg_data->update($payload);
            if ($result) {
                return response()->json([
                    'message' => 'Record has been successfully updated',
                    'code' => 200
                ], 200);
            } else {
                return response('failed');
            }
        } else {
            return response()->json([
                'message' => 'Invalid Id',
                'code' => 500
            ], 200);
        }
    }

    public function deleteOfferPackage(Request $request){

        $pkg_data = OfferPackage::find($request->id);
        if (!$pkg_data) {
            return response()->json([
                'message' => 'Invalid Id',
                'code' => 500
            ], 200);
        }

        // Delete the employee record
        $result = $pkg_data->delete();

        if ($result) {
            // Deletion successful
            return response()->json([
                'message' => 'Offer Package has been deleted',
                'code' => 200
            ], 200);
        } else {
            // Deletion failed
            return response()->json([
                'message' => 'Failed to delete',
                'code' => 500
            ], 500);
        }
    }


    public function drop_down(){
        $package_details = OfferPackage::get(['id', 'name']);
        if ($package_details) {
            return response()->json([
                'data' => $package_details,
                'code' => 200
            ], 200);
        }else{
            return response()->json([
                'message' => "Something Went wrong",
                'code' => 500
            ], 200);
        }
    }

    public function package_value(Request $request)
    {
        $package_details = OfferPackage::where('id', $request->id)->get([
            'value'
        ])->first();
        if ($package_details) {
            return response()->json([
                'data' => $package_details,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No data found for this id",
                'code' => 500
            ], 200);
        }
    }
}
