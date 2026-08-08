<?php

namespace App\Http\Controllers;

use App\Models\NonRegistreMember;
use App\Models\OfferPackage;
use App\Models\Payment;
use App\Observers\NonRegisterMemberObserver;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NonRegistreMemberController extends Controller
{
    // Non-registered (walk-in) members use PINs starting at 100000 on the biometric
    // device so they never collide with real Member IDs, which are small sequential integers.
    const DEVICE_PIN_OFFSET = 100000;

    public function create_nonRegistered_member(Request $request)
    {
        try {
            $payload = $request->all();
            $validator = Validator::make($payload, [
                'name' => 'required',
                'membership_number' => 'required',
                'email' => 'required',
                'payble_amount' => 'required',
                'paying_amount' => 'required',
                'payment_date' => 'required',
                'phone' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    "message" => $validator->errors(),
                    "code" => 500
                ], 200);
            } else {
                $payload['due'] = $request->payble_amount - $request->paying_amount - $request->offer;
                $payload['payment_date'] = date('Y-m-d', strtotime($request->payment_date . ' +1 day'));
                $start_date = date('Y-m-d', strtotime($request->payment_date . ' +1 day'));
                $payload['start_date']  = $start_date;
                $package_duration = OfferPackage::where('id', $request->offer_package_id)->value('duration');
                $end_date = Carbon::parse($start_date)->addDays($package_duration)->toDateString();
                $payload['end_date'] = $end_date;
                $payload['offer'] = $request->offer;
                $data = NonRegistreMember::create($payload);
                if ($data) {
                    if ($request->has('card_number') && $request->card_number && Carbon::parse($end_date)->gte(Carbon::today())) {
                        $sn       = config('zkteco.sn', 'HKQ8241900193');
                        $pin      = self::DEVICE_PIN_OFFSET + $data->id;
                        $safeName = str_replace(' ', '_', $data->name);
                        $card     = $request->card_number;
                        $cmdId    = time();
                        AdmsController::queueCommand(
                            $sn,
                            $cmdId,
                            "C:{$cmdId}:DATA UPDATE USERINFO\tPIN={$pin}\tName={$safeName}\tCard={$card}\tPri=0"
                        );
                        $data->update(['on_device' => 1]);
                    }
                    return response()->json([
                        'message' => "Member Created Successfully",
                        'code' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'message' => "Something went wrong",
                        'code' => 500
                    ], 200);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    public function update_nonRegistered_member(Request $request)
    {
        // Personal info only — package/payment fields (offer_package_id, offer,
        // payble_amount, paying_amount, due, start_date, end_date, payment_date)
        // are intentionally not accepted here so this endpoint can never alter payments.
        $payload = $request->only(['name', 'phone', 'email', 'card_number', 'membership_number']);
        $member = NonRegistreMember::find($request->id);

        if ($member) {
            $result = $member->update($payload);
            if ($result) {
                return response()->json([
                    'message' => 'Updated Successfully',
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

    public function autoCompleteNonRegisterMember(Request $request)
    {
        $search_text = $request->search_text;
        $q = NonRegistreMember::with('invoice')->orderBy('id', 'desc');
        if ($search_text) {
            $q->whereHas('members', function ($query) use ($search_text) {
                $query->where('membership_number', 'like', "%{$search_text}%")
                    ->orWhere('name', 'like', "%{$search_text}%")
                    ->orWhere('phone', 'like', "%{$search_text}%")
                    ->orWhere('email', 'like', "%{$search_text}%");
            })->orWhereHas('invoice', function ($query) use ($search_text) {
                $query->where('id', 'like', "%{$search_text}%");
            });
        }

        $members_data = $q->get()->map(function ($item) use ($search_text) {
            if (stripos($item->name, $search_text) !== false) {
                return ['search_text' => $item->name];
            } else if (stripos($item->phone, $search_text) !== false) {
                return ['search_text' => $item->phone];
            } else if (stripos($item->email, $search_text) !== false) {
                return ['search_text' => $item->email];
            } else if (stripos($item->membership_number, $search_text) !== false) {
                return ['search_text' => $item->membership_number];
            } else if (stripos($item->invoice->id, $search_text) !== false) {
                return ['search_text' => $item->invoice->id];
            } else {
                return [];
            }
        })->filter();

        if ($members_data->toArray() != []) {
            // dd($merged_data);
            return response()->json([
                'data' => $members_data,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No Data Found",
                'code' => 500
            ], 200);
        }
    }
    public function retriveNonRegisterMember(Request $request)
    {
        try {
            $search_text = $request->search_text;
            $member_id = $request->id;
            $limit = $request->limit > 0 ? $request->limit : 10;
            $index = $request->index > 0 ?  $request->index : 0;
    
            // Prepare the query builder
            $q = NonRegistreMember::with(['invoice', 'offerPackages'])
                ->offset($index)
                ->limit($limit)
                ->orderBy('id', 'desc');
    
            // Apply search filters
            if ($search_text) {
                $q->where('name', 'like', "%$search_text%")
                    ->orWhere('email', 'like', "%$search_text%")
                    ->orWhere('phone', 'like', "%$search_text%")
                    ->whereHas('offerPackages', function ($query) use ($search_text) {
                        $query->where('name', 'like', "%{$search_text}%");
                    });
            }
    
            // If a member ID is provided, filter by it
            if ($member_id) {
                $q->where('id', $member_id);
            }
    
            // Get the members data
            $members_data = $q->get();
    
            // Get the total count of members for pagination
            $total_count = NonRegistreMember::with(['invoice', 'offerPackages'])
                ->where('id', '>', 0) // Make sure it counts all records, can be adjusted as needed
                ->count();
    
            if (!$members_data->isEmpty()) {
                foreach ($members_data as $member_data) {
    
                    $member_data->payment_date = date('d-m-Y', strtotime($member_data->payment_date));
                    $member_data->start_date = date('d-m-Y', strtotime($member_data->start_date));
                    $member_data->end_date = date('d-m-Y', strtotime($member_data->end_date));
    
                    // Handle `invoice` relationship
                    if ($member_data['invoice'] instanceof \Illuminate\Support\Collection) {
                        if ($member_data['invoice']->isEmpty()) {
                            $member_data->invoice_number = "N/A";
                        } else {
                            $member_data->invoice_number = $member_data['invoice']->first()->id;
                        }
                    } else {
                        if (is_null($member_data['invoice'])) {
                            $member_data->invoice_number = "N/A";
                        } else {
                            $member_data->invoice_number = $member_data->invoice->id;
                        }
                    }
    
                    // Handle `offerPackages` relationship
                    if ($member_data['offerPackages'] instanceof \Illuminate\Support\Collection) {
                        if ($member_data['offerPackages']->isEmpty()) {
                            $member_data->package_name = "N/A";
                        } else {
                            $member_data->package_name = $member_data['offerPackages']->first()->name;
                        }
                    } else {
                        if (is_null($member_data['offerPackages'])) {
                            $member_data->package_name = "N/A";
                        } else {
                            $member_data->package_name = $member_data->offerPackages->name;
                        }
                    }
    
                    // Unset the relationships to avoid sending them in the response if needed
                    $member_data->unsetRelation('invoice');
                    $member_data->unsetRelation('offerPackages');
                }
                
                // Return the response with the data and total count
                return response()->json([
                    'data' => $members_data,
                    'total_count' => $total_count, // Include total count here
                    'code' => 200
                ]);
            } else {
                return response()->json([
                    'data' => [],
                    'message' => 'No Data found',
                    'code' => 200
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }
    

    public function deleteNonRegisterMember(Request $request)
    {

        $mbr_data = NonRegistreMember::find($request->id);
        if (!$mbr_data) {
            return response()->json([
                'message' => 'Invalid Id',
                'code' => 500
            ], 200);
        }

        if ($mbr_data->on_device) {
            $sn    = config('zkteco.sn', 'HKQ8241900193');
            $pin   = self::DEVICE_PIN_OFFSET + $mbr_data->id;
            $cmdId = time();
            AdmsController::queueCommand(
                $sn,
                $cmdId,
                "C:{$cmdId}:DATA DELETE USERINFO\tPIN={$pin}"
            );
        }

        // Delete the employee record
        $result = $mbr_data->delete();

        if ($result) {
            // Deletion successful
            return response()->json([
                'message' => 'Member has been deleted',
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

    public function non_register_membership_number()
    {
        //MTTMG240411004544
        $last_records = NonRegistreMember::latest('id')->first();
        if ($last_records) {
            $number = $last_records->id + 1;
            $date = date('ymd', strtotime(now()->toDateString()));
            $membership_number = "NONREGMTTMG" . $date . $number;
            return response()->json([
                'data' =>  $membership_number,
                'code' => 200
            ], 200);
        } else {
            $date = date('ymd', strtotime(now()->toDateString()));
            $membership_number = "NONREGMTTMG" . $date . 01;
            return response()->json([
                'data' =>  $membership_number,
                'code' => 200
            ], 200);
        }
    }

    public function due_payment(Request $request)
    {
        try {
            $due_amount = $request->due;
            $member_id = $request->id;

            $nonRegisterMember = NonRegistreMember::where('id', $member_id)->first();

            if ($nonRegisterMember) {
                // Check if the updated due amount is less than 0
                if ($due_amount > $nonRegisterMember->due) {
                    return response()->json([
                        'message' => 'The  due amount cannot be more than existing due.',
                        'code' => '500'
                    ], 200);  // 400 Bad Request
                }
                $updated_due_amount = $nonRegisterMember->due - $due_amount;

                $nonRegisterMember->due = $updated_due_amount;  // Update the due amount field
                $nonRegisterMember->save();  // Save the changes
                return response()->json([
                    'message' => 'Due Paid',
                    'code' => '200',
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Member not found',
                    'code' => '500',
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }
}
