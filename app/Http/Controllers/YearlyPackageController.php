<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\YearlyPackage;
use App\Models\Invoice;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class YearlyPackageController extends Controller
{
    public function checkYearlyValidation($member_id)
    {
        if (!$member_id) {
            // If member_id is not provided, return true (assuming this indicates an expired or invalid case).
            return true;
        }

        // Retrieve the latest YearlyPackage record for the member
        $yearlyData = YearlyPackage::where('member_id', $member_id)->latest()->first();

        if ($yearlyData && $yearlyData->end_date) {
            // Check if the current date is greater than the end date of the package
            return Carbon::now()->greaterThan($yearlyData->end_date);
        }

        // If no package is found or end_date is missing, consider it expired.
        return true;
    }

    /**
     * Standalone API: add yearly package. Can add even if one is active; if no start_date given, new package starts from active package end_date.
     * POST body: member_id, package_amount, payment_mode, start_date (optional), admission_value_id (optional)
     */
    public function add_yearly_package_if_expired(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required|integer|exists:members,id',
            'package_amount' => 'required|numeric|min:0',
            'payment_mode' => 'required|string',
            'start_date' => 'nullable|date',
            'admission_value_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'code' => 422,
            ], 200);
        }

        $member_id = (int) $request->member_id;
        if (!Member::where('id', $member_id)->exists()) {
            return response()->json([
                'message' => 'Member not found.',
                'code' => 404,
            ], 200);
        }

        $latest = YearlyPackage::where('member_id', $member_id)
            ->orderByDesc('end_date')
            ->first();

        if ($request->start_date) {
            $start = Carbon::parse($request->start_date)->format('Y-m-d H:i:s');
        } elseif ($latest && $latest->end_date) {
            $start = Carbon::parse($latest->end_date)->format('Y-m-d H:i:s');
        } else {
            $start = Carbon::now()->format('Y-m-d H:i:s');
        }
        $end_date = Carbon::parse($start)->addYear()->toDateString();

        $payload = [
            'member_id' => $member_id,
            'package_amount' => (string) $request->package_amount,
            'start_date' => $start,
            'end_date' => $end_date,
            'payment_mode' => $request->payment_mode,
            'payment_date' => Carbon::now()->format('Y-m-d H:i:s'),
            'included_in_main_payment' => false,
            'admission_value_id' => $request->admission_value_id ?: null,
        ];

        DB::beginTransaction();
        try {
            $yearly = YearlyPackage::create($payload)->fresh();
            DB::commit();
            return response()->json([
                'message' => 'Yearly membership added successfully.',
                'code' => 200,
                'data' => [
                    'id' => $yearly->id,
                    'member_id' => $yearly->member_id,
                    'package_amount' => $yearly->package_amount,
                    'start_date' => date('d-m-Y', strtotime($yearly->start_date)),
                    'end_date' => date('d-m-Y', strtotime($yearly->end_date)),
                    'payment_mode' => $yearly->payment_mode,
                ],
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500,
            ], 200);
        }
    }

    public function cr_yr_membership($payload)
    {
        try {
            // Retrieve the latest membership for the given member_id
            $latestMembership = YearlyPackage::where('member_id', $payload['member_id'])
                ->orderBy('end_date', 'desc')
                ->first();

            // if ($latestMembership) {
            //     // Check if current date is greater than or equal to the end_date of the latest membership
            //     if (now()->greaterThanOrEqualTo($latestMembership->end_date)) {
            //         // Delete related invoices
            //         Invoice::where('yearly_package_payment_id', $latestMembership->id)->delete();

            //         // Delete the previous membership
            //         $latestMembership->delete();
            //     } else {
            //         // If the current membership is still active, deny creating a new package
            //         return response()->json([
            //             'message' => "Cannot create a new package until the current one expires.",
            //             'code' => 400
            //         ], 400);
            //     }
            // }

            // Proceed with creating a new yearly membership package
            $data = YearlyPackage::create($payload)->fresh();
            return $data ?? null;
        } catch (Exception $e) {
            return response()->json([
                'message' =>  $e->getMessage(),
                'code' => 500
            ]);
        }
    }


    public function date_api(Request $request)
    {
        $start_date = date('d-m-Y', strtotime($request->date));
        $end_date = Carbon::parse($start_date)->addMonths(12)->toDateString();
        return response()->json([
            'data' =>  date('d-m-Y', strtotime($end_date)),
            'code' => 200
        ], 200);
    }
    public function retrive_yr_package(Request $request)
    {
        $limit = $request->limit > 0 ? $request->limit : 10;
        $index = $request->index > 0 ? $request->index : 0;

        if ($request->search_text) {
            $data = YearlyPackage::with('members')->where('package_amount', 'like', "%{$request->search_text}%")
                // ->orWhere('start_date', 'like',date('Y-m-d',strtotime($request->search_text)))    
                ->orWhereHas('members', function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search_text}%");
                })
                ->limit($limit)
                ->offset($index)
                ->orderBy('id', 'desc')
                ->get()->map(function ($items) {
                    return [
                        'member_name' => $items->members->name,
                        'package_amount' => $items->package_amount,
                        'start_date' => date('d-m-Y', strtotime($items->start_date)),
                        'end_date' => date('d-m-Y', strtotime($items->end_date)),
                    ];
                });
            return response()->json([
                'data' =>  $data,
                'code' => 200
            ], 200);
        } else {
            $data = YearlyPackage::with('members')->get()->map(function ($items) {
                return [
                    'member_name' => $items->members->name,
                    'package_amount' => $items->package_amount,
                    'start_date' => date('d-m-Y', strtotime($items->start_date)),
                    'end_date' => date('d-m-Y', strtotime($items->end_date)),
                ];
            });

            return response()->json([
                'data' =>  $data,
                'code' => 200
            ], 200);
        }
    }
    public function autocomplete(Request $request)
    {
        $search_text = $request->search_text;
        if ($search_text) {
            $records = YearlyPackage::where('package_amount', 'like', "%$search_text%")
                ->orWhere('start_date', 'like', "%$search_text%")
                ->orWhere('end_date', 'like', "%$search_text%")
                ->orWhereHas('members', function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search_text}%");
                })
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($item) use ($search_text) {
                    // If the name matches, return only the name
                    if (strpos($item->package_amount, $search_text) !== false) {
                        return  ['search_text' => $item->package_amount];
                        // If the duration matches, return only the duration
                    } elseif (stripos($item->start_date, $search_text) !== false) {
                        return ['search_text' => $item->start_date];
                    } elseif (stripos($item->end_date, $search_text) !== false) {
                        return ['search_text' => $item->end_date];
                    } else if (stripos($item->members->name, $search_text) !== false) {
                        return ['search_text' => $item->members->name];
                    }
                })->toArray();

            if ($records) {
                return response()->json([
                    'data' => $records,
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No data on this search ",
                    'code' => 500
                ], 200);
            }
        } else {
            return response()->json([
                'message' => "Search anything first",
                'code' => 500
            ], 200);
        }
    }
}
