<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExpenseRequest;
use App\Models\Expense;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    public function create_expense(ExpenseRequest $request)
    {
        DB::beginTransaction();
        try {
            $request->validated();
            $payload = $request->only(['description', 'category', 'amount', 'date']);
            $payload['date'] = date('Y-m-d', strtotime($request->date));
            $payload['status'] = 0; // pending
            $result = Expense::create($payload);

            if ($result) {
                DB::commit();
                return response()->json([
                    'message' => "Expense Added",
                    'code' => 200
                ]);
            } else {
                return response()->json([
                    'message' => "Database Error",
                    'code' => 500
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

    public function update_expense(ExpenseRequest $request)
    {
        try {
            $id = $request->id;
            $payload = $request->only(['description', 'category', 'amount', 'date']);
            $payload['date'] = date('Y-m-d', strtotime($request->date));
            $expense = Expense::find($id);
            if (!$expense) {
                return response()->json([
                    "message" => "Please verify the ID and try again.",
                    "code" => 500
                ]);
            }
            $result = $expense->update($payload);
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

    public function delete_expense(Request $request)
    {
        try {
            $id = $request->id;
            $expense = Expense::find($id);
            if (!$expense) {
                return response()->json([
                    "message" => "No record found in this id",
                    "code" => 500
                ], 200);
            }
            $result = $expense->delete();
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

    public function retrive_expenses(Request $request)
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $index = $request->index > 0 ? $request->index : 0;
            $search_text = $request->search_text;
            $dateFilter = $request->input('date', null);

            $query = Expense::limit($limit)
                ->offset($index)
                ->orderBy('id', 'desc');

            if ($search_text) {
                $query->where(function ($q) use ($search_text) {
                    $q->where('description', 'like', "%$search_text%")
                        ->orWhere('category', 'like', "%$search_text%");
                });
            }

            if ($dateFilter && isset($dateFilter['type']) && isset($dateFilter['value'])) {
                if ($dateFilter['type'] == 1 && $dateFilter['value'] != "") {
                    $check_days = $dateFilter['value'];
                    $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
                    $filter_date = null;

                    if (in_array($check_days, ['today', '7days', '15days', '30days'])) {
                        switch ($check_days) {
                            case 'today':
                                $filter_date = Carbon::parse($today)->toDateString();
                                break;
                            case '7days':
                                $filter_date = Carbon::parse($today)->subDays(7)->toDateString();
                                break;
                            case '15days':
                                $filter_date = Carbon::parse($today)->subDays(15)->toDateString();
                                break;
                            case '30days':
                                $filter_date = Carbon::parse($today)->subDays(30)->toDateString();
                                break;
                        }
                    }

                    if ($filter_date) {
                        $query->whereBetween('date', [$filter_date, $today]);
                    }
                }

                if ($dateFilter && $dateFilter['type'] == 2 && !empty($dateFilter['value'])) {
                    $dates = explode(',', $dateFilter['value']);
                    if (count($dates) >= 2) {
                        $startDate = Carbon::parse(trim($dates[0]))->startOfDay()->format('Y-m-d');
                        $endDate = Carbon::parse(trim($dates[1]))->endOfDay()->format('Y-m-d');
                        $query->whereBetween('date', [$startDate, $endDate]);
                    }
                }
            }

            $expenses = $query->get()->map(function ($item) {
                $item->date = date('d-m-Y', strtotime($item->date));
                $item->amount = (float) $item->amount;
                $item->status_text = $item->status == 1 ? 'completed' : 'pending';
                return $item;
            });

            if ($expenses->isEmpty()) {
                return response()->json([
                    "message" => "No data found",
                    "code" => 500
                ]);
            }

            return response()->json([
                "data" => $expenses,
                "code" => 200
            ]);
        } catch (Exception $e) {
            return response()->json([
                "message" => $e->getMessage(),
                "code" => 500
            ]);
        }
    }

    public function autocomplete(Request $request)
    {
        $search_text = $request->search_text;
        $q = Expense::orderBy('id', 'desc');
        if ($search_text) {
            $q->where('description', 'like', "%$search_text%")
                ->orWhere('category', 'like', "%$search_text%");
        }

        $expenses = $q->get()->map(function ($item) use ($search_text) {
            if ($search_text && stripos($item->description, $search_text) !== false) {
                return ['search_text' => $item->description];
            }
            if ($search_text && stripos($item->category, $search_text) !== false) {
                return ['search_text' => $item->category];
            }
            return [];
        })->filter()->unique('search_text')->values();

        if ($expenses->isNotEmpty()) {
            return response()->json([
                'data' => $expenses,
                'code' => 200
            ], 200);
        }

        return response()->json([
            'message' => "No Data Found",
            'code' => 500
        ], 200);
    }

    /**
     * Change expense status from pending (0) to completed (1).
     */
    public function change_status(Request $request)
    {
        $id = $request->id;
        $expense = Expense::find($id);
        if (!$expense) {
            return response()->json([
                'message' => "Invalid Id",
                'code' => 500
            ], 200);
        }
        $expense->status = 1; // completed
        $result = $expense->save();
        if ($result) {
            return response()->json([
                'message' => "Expense status updated to completed.",
                'code' => 200
            ], 200);
        }
        return response()->json([
            'message' => "No data available to update.",
            'code' => 500
        ], 200);
    }
}
