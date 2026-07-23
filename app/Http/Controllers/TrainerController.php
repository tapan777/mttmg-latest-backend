<?php

namespace App\Http\Controllers;

use App\Http\Requests\TrainerRequest;
use App\Models\Trainer;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Jmrashed\Zkteco\Lib\ZKTeco;

class TrainerController extends Controller
{
    protected $biomatricdata;
    public function __construct(BiomatricController $biomatricData)
    {
        $this->biomatricdata = $biomatricData;
    }

    //store trainers
    public function store_trainer(TrainerRequest $request)
    {
        DB::beginTransaction();
        try {
            $request->validated();
            $trainer =  Trainer::create($request->all());
            if ($trainer) {
                DB::commit();
                $set_result = $this->biomatricdata->setBiomatricData(
                    $trainer->id,
                    $request->password ?? 12345678,
                    $trainer->name,
                    $request->card_number ?? 0
                );
                if ($set_result) {
                    return response()->json([
                        'message' => "Please Add Fingerprint for the Employee : $trainer->id",
                        'data' => $trainer,
                        'code' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'message' => "Trainer successfully added,Biometric integration is pending.",
                        'data' => $trainer,
                        'code' => 200
                    ], 200);
                }
            } else {
                return response()->json([
                    'code' => 500,
                    'message' => 'Failed to add Employee',
                ], 200);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                "message" => $e->getMessage(),
                'code' => 500
            ]);
        }
    }

    //update trainrers
    public function update_trainer(Request $request)
    {
        try {
            $id = $request->id;
            $payload = $request->all();
            $trainer = Trainer::find($id);
            if (!$trainer) {
                return response()->json([
                    "message" => "Please verify the ID and try again.",
                    "code" => 500
                ]);
            }
            $result = $trainer->update($payload);
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
    public function delete_trainer(Request $request)
    {
        try {
            $id = $request->id;
            $zk = new ZKTeco('192.168.1.201');
            $zk->connect();
            $zk->removeUser($request->id);
            $zk->testVoice();
            $follow_details = Trainer::find($id);
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

    //retrive trainers
    public function retriveTrainers(request $request)
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $index = $request->index > 0 ?  $request->index : 0;
            $search_text = $request->search_text;
            $query = Trainer::limit($limit)
                ->offset($index)
                ->orderBy('id', 'desc');

            if ($search_text) {
                $query->where('name', 'like', "%$search_text%")
                    ->orWhere('phone', 'like', "%$search_text%")
                    ->orWhere('email', 'like', "%$search_text%")
                    ->orWhere('card_number', 'like', "%$search_text%");
            }

            $trainer = $query->get();
            if ($trainer->isEmpty()) {
                return response()->json([
                    "message" => "No data found",
                    "code" => 500
                ]);
            } else {
                return response()->json([
                    "data" => $trainer,
                    "code" => 200
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
        $q = Trainer::orderBy('id', 'desc');
        if ($search_text) {
            $q->where('name', 'like', "%$search_text%")
                ->orWhere('phone', 'like', "%$search_text%");
        }

        $followups = $q->get()->map(function ($item) use ($search_text) {
            if (stripos($item->name, $search_text) !== false) {
                return ['search_text' => $item->name];
            } else if (stripos($item->phone, $search_text) !== false) {
                return ['search_text' => $item->phone];
            } else if (stripos($item->email, $search_text) !== false) {
                return ['search_text' => $item->email];
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

    //trainer dropdown
    public function dropdown()
    {
        $trainer = Trainer::get(['id', 'name']);
        if ($trainer) {
            return response()->json([
                'data' => $trainer,
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
