<?php

namespace App\Http\Controllers;

use App\Http\Requests\FollowupRequest;
use App\Models\Followup;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FollowupController extends Controller
{
    public function create_followup(FollowupRequest $request)
    {
        DB::beginTransaction();
        try {
            $request->validated();
            $payload = $request->all();
            $payload['visit_date'] = date('Y-m-d', strtotime($request->visit_date . ' +1 day'));
            $payload['followup_date'] = date('Y-m-d', strtotime($request->followup_date . ' +1 day'));
            $payload['status'] = 0;
            $result  = Followup::create($payload);

            if ($result) {
                DB::commit();
                return response()->json([
                    'message' => "Followup Added",
                    'code' => 200
                ]);
            } else {
                return response()->json([
                    'message' => "Database Error",
                    'code' => 200
                ]);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                "message" => $e->getMessage(),
                'code' => 500
            ]);
        }
    }

    //update
    public function update_followup(FollowupRequest $request)
    {
        try {
            $id = $request->id;
            $payload = $request->all();
            $payload['visit_date'] = date('Y-m-d', strtotime($request->visit_date . ' +1 day'));
            $payload['followup_date'] = date('Y-m-d', strtotime($request->followup_date . ' +1 day'));
            $follow = Followup::find($id);
            if (!$follow) {
                return response()->json([
                    "message" => "Please verify the ID and try again.",
                    "code" => 500
                ]);
            }
            $result = $follow->update($payload);
            if ($result) {
                return response()->json([
                    "message" => "Record updated successfully.",
                    "code" => 200
                ], 200);
            } else {
                return response()->json([
                    "message" => "We encountered an issue while updating the record. Please try again",
                    "code" => 500
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                'code' => 500
            ]);
        }
    }

    //delete
    public function delete_followup(Request $request)
    {
        try {
            $id = $request->id;
            $follow_details = Followup::find($id);
            if (!$follow_details) {
                return response()->json([
                    "message" => "No record found in this id",
                    "code" => 500
                ], 200);
            }
            $result = $follow_details->delete();
            if ($result) {
                return response()->json([
                    "message" => "Record Deleted Successfully",
                    "code" => 200
                ]);
            } else {
                return response()->json([
                    "message" => "Record Not found",
                    "code" => 500
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "code" => 500
            ]);
        }
    }

    //retrive
    public function retrive_followups(request $request)
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $index = $request->index > 0 ?  $request->index : 0;
            $search_text = $request->search_text;
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            $dateFilter = $request->input('date', null);
            $query = Followup::orderBy('id', 'desc');

            if ($search_text) {
                $query->where('name', 'like', "%$search_text%")
                    ->orWhere('phone', 'like', "%$search_text%")
                    ->orWhere('status', 'like', "%$search_text%");
            }

            if ($dateFilter && isset($dateFilter['type']) && isset($dateFilter['value'])) {
                if ($dateFilter['type'] == 1 && $dateFilter['value'] != "") {
                    $check_days = $dateFilter['value'];
                    $filter_date = null;

                    if (in_array($check_days, ['today', '7days', '15days', '30days'])) {
                        switch ($check_days) {
                            case 'today':
                                $filter_date = Carbon::parse($today)->toDateString(); // Today’s date
                                break;
                            case '7days':
                                $filter_date = Carbon::parse($today)->addDays(7)->toDateString(); // 7 days from today
                                break;
                            case '15days':
                                $filter_date = Carbon::parse($today)->addDays(15)->toDateString(); // 15 days from today
                                break;
                            case '30days':
                                $filter_date = Carbon::parse($today)->addDays(30)->toDateString(); // 30 days from today
                                break;
                        }
                    }

                    if ($filter_date) {
                        // Apply date range filter
                        $query->whereBetween('visit_date', [$filter_date, $today])
                            ->orWhereBetween('followup_date', [$today, $filter_date]);
                    }
                }

                // Additional date range filter (type 2)
                if ($dateFilter && $dateFilter['type'] == 2) {
                    $dates = explode(',', $dateFilter['value']);
                    $startDate = \Carbon\Carbon::parse($dates[0])->startOfDay();
                    $endDate = \Carbon\Carbon::parse($dates[1])->endOfDay();
                    $query->whereBetween('visit_date', [$startDate, $endDate])
                        ->orWhereBetween('followup_date', [$startDate, $endDate]);
                }
            }

            $total_count = $query->count();
            $followup = $query->limit($limit)->offset($index)->get()->map(function ($item) {
                $item->visit_date = date('d-m-Y', strtotime($item->visit_date));
                $item->followup_date = date('d-m-Y', strtotime($item->followup_date));
                return $item;
            });
            if ($followup->isEmpty()) {
                return response()->json([
                    "message" => "No data found",
                    "code" => 500
                ]);
            } else {
                return response()->json([
                    "data"        => $followup,
                    "total_count" => $total_count,
                    "code"        => 200
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "code" => 500
            ]);
        }
    }

    //auto complete
    public function autocomplete(Request $request)
    {
        $search_text = $request->search_text;
        $q = Followup::orderBy('id', 'desc');
        if ($search_text) {
            $q->where('name', 'like', "%$search_text%")
                ->orWhere('phone', 'like', "%$search_text%");
        }

        $followups = $q->get()->map(function ($item) use ($search_text) {
            if (stripos($item->name, $search_text) !== false) {
                return ['search_text' => $item->name];
            } else if (stripos($item->phone, $search_text) !== false) {
                return ['search_text' => $item->phone];
            } else {
                return [];
            }
        })->filter();

        if ($followups->toArray() != []) {
            // dd($merged_data);
            return response()->json([
                'data' => $followups,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No Data Found",
                'code' => 500
            ], 200);
        }
    }

    //status change after follow
    public function change_status(Request $request)
    {
        $id = $request->id;
        $followup_data = Followup::find($id);
        if (!$followup_data) {
            return response()->json([
                'message' => "Invalid Id",
                'code' => 200
            ], 200);
        }
        $followup_data->status =  1;
        $result = $followup_data->save();
        if ($result) {
            // dd($merged_data);
            return response()->json([
                'message' => "Follow-up successfully updated.",
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' =>  "No data available to update.",
                'code' => 500
            ], 200);
        }
    }
}
