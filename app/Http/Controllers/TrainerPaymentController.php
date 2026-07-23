<?php

namespace App\Http\Controllers;
use App\Models\SmsStatus;
use App\Models\Payment;
use App\Models\TrainerPackage;
use App\Models\TrainerPayment;
use App\Models\YearlyPackage;
use App\Events\SendWhatsAppNotification;
use Illuminate\Http\Request;
use App\Models\Employee;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Validator;
use App\Models\Member;
class TrainerPaymentController extends Controller
{
    public static function create_payment(Request $request)
    {
        try {
            $payload = $request->all();
            $validator = Validator::make($payload, [
                'member_id' => 'required',
                'payble_amount' => 'required',
                'total_payble_amount' => 'required',
                'mode_of_payment' => 'required',
                'paying_amount' => 'required',
                'date_of_payment' => 'required',
                'trainer_package_id' => 'required'
            ]);

            if ($validator->fails()) {
                return response([
                    'error' => $validator->errors(),
                    'code' => 500
                ], 200);
            }

            // Yearly membership is informational only — do not block PT package purchase.
            $today = Carbon::now()->toDateString();
            $yearlyPackage = YearlyPackage::where('member_id', $request->member_id)
                ->orderByDesc('end_date')
                ->first();
            $yearlyWarning = null;
            if (!$yearlyPackage) {
                $yearlyWarning = 'Yearly membership not found for this member. Please add/renew yearly membership.';
            } elseif (Carbon::parse($yearlyPackage->end_date)->lt(Carbon::parse($today))) {
                $yearlyWarning = 'Yearly membership expired on ' . Carbon::parse($yearlyPackage->end_date)->format('d-m-Y') . '. Please renew yearly membership.';
            }

            $mainPayment = Payment::where('member_id', $request->member_id)
                ->where('payment_type', 0)
                ->latest()
                ->first();
            if (!$mainPayment) {
                return response()->json([
                    'message' => 'Cannot add PT package. No active main package found.',
                    'code' => 422
                ], 200);
            }
            if (Carbon::parse($mainPayment->end_date)->lt(Carbon::parse($today))) {
                return response()->json([
                    'message' => 'Cannot add PT package. Main package is expired. Please renew main package first.',
                    'code' => 422
                ], 200);
            }

            // Block if member has PT due not cleared (trainer payment due > 0)
            $ptWithDue = TrainerPayment::where('member_id', $request->member_id)
                ->where('payment_type', 0)
                ->where('due', '>', 0)
                ->exists();
            if ($ptWithDue) {
                return response()->json([
                    'message' => 'Cannot add PT package. Clear existing PT due first, then add next payment.',
                    'code' => 422
                ], 200);
            }

            $package_duration = TrainerPackage::where('id', $request->trainer_package_id)->value('duration');
            $payment_date = date('Y-m-d H:i:s', strtotime($request->date_of_payment));
            $due = ($request->payble_amount - $request->offer) - $request->paying_amount;

            if ($request->start_date) {
                $start_date = date('Y-m-d', strtotime($request->start_date));
            } else {
                $latestPt = TrainerPayment::where('member_id', $request->member_id)
                    ->where('payment_type', 0)
                    ->orderByDesc('end_date')
                    ->first();
                $start_date = $latestPt && $latestPt->end_date
                    ? Carbon::parse($latestPt->end_date)->format('Y-m-d')
                    : Carbon::now()->format('Y-m-d');
            }

            $end_date = null;
            if ($package_duration) {
                $end_date = Carbon::parse($start_date)->addDays($package_duration)->toDateString();

                $payload['start_date'] = $start_date;
                    $payload['end_date'] = $end_date;
                    $payload['date_of_payment'] = $payment_date;
                    $payload['due'] = $due;
                    $payload['payment_type'] = 0; //trainer package payment
                    $payload['package_status'] = 1; //1 is for recent payment else 0
                    $payload['offer'] = $request->offer;
                    TrainerPayment::where('member_id', $request->member_id)
                        ->where('payment_type', 0)
                        ->update(['package_status' => 0]);
                    $trainer_payment = TrainerPayment::create($payload);

                    if ($trainer_payment) {
                        $sms = SmsStatus::first(); // Assuming only one row tracks the balance

                        if ($sms && $sms->amount > 0) {
                        $member = Member::find($request->member_id); // Assuming Member model exists
                        $alt_pno = $member->phone;
                        $mobileNumber = $alt_pno;

                        $name = $member->name ?? 'Member';
                        $pkg_name = TrainerPackage::where('id', $request->trainer_package_id)->value('name') ?? '';
                        $total_paid = $request->paying_amount;
                        $discount_trainer = $request->offer ?? 0;
                        $final_exp_date = $end_date;

                        $key = "at73pzTDNrnkyMeo";
                        $mbl = $mobileNumber;
                        $message_content = urlencode("PT PACKAGE successful at MTTMG! Payment received " . $total_paid . " for admission +" . $pkg_name . " FROM " . $name . "  Discount " . $discount_trainer . " " . $name . " Next due dt " . $final_exp_date . " payment after Due Date will be Rs 50 fine.");
                        $senderid = "MTTGYM";
                        $templateid = "1307162753880015610";

                        $url = "http://text2india.store/vb/apikey.php?apikey=$key&senderid=$senderid&templateid=$templateid&number=$mbl&message=$message_content";
                        @file_get_contents($url); // Use @ to suppress any warnings

                      $sms->decrement('amount', 2);
                    }

                        // WhatsApp — async via event listener
                        event(new SendWhatsAppNotification(
                            'payment_received_confirmation',
                            [$name, $total_paid, $pkg_name],
                            [$mobileNumber]
                        ));

                        event(new SendWhatsAppNotification(
                            'personal_trainer_package',
                            [
                                $total_paid,
                                $request->total_payble_amount,
                                $discount_trainer,
                                $name,
                                Carbon::parse($final_exp_date)->format('d-m-Y'),
                            ],
                            [$mobileNumber]
                        ));

                        return response()->json([
                            'message' => "Payment Done",
                            'yearly_warning' => $yearlyWarning,
                            'code' => 200
                        ], 200);
                    } else {
                        return response()->json([
                            'message' => "Payment Failed",
                            'code' => 500
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'message' => "No package found based on " . $request->trainer_package_id,
                        'code' => 500
                    ], 200);
                }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    //Advance Payment
    public function advance_payment(Request $request)
    {
        try {
            $payload = $request->all();
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            $validator = Validator::make($payload, [
                'member_id' => 'required',
                'payble_amount' => 'required',
                'total_payble_amount' => 'required',
                'mode_of_payment' => 'required',
                'paying_amount' => 'required',
                'date_of_payment' => 'required',
                'trainer_package_id' => 'required'
            ]);

            $is_expired = Payment::where('member_id', $request->member_id)
                ->where('end_date', '<=', $today)
                ->where('package_status', 1)
                ->where('payment_type', 0)->latest()->first();
            if ($is_expired == null) {
                return response([
                    'error' => "Main package required to add Personal Training sessions.",
                    'code' => 500
                ], 200);
            }
            if ($validator->fails()) {
                return response([
                    'error' => $validator->errors(),
                    'code' => 500
                ], 200);
            } else {
                $package_duration = TrainerPackage::where('id', $request->trainer_package_id)->first();
                $payment_date = date('Y-m-d H:i:s', strtotime($request->date_of_payment . ' +1 day'));
                $due = ($request->payble_amount - $request->offer) - $request->paying_amount;

                $end_date = null;
                if ($package_duration) {
                    // Check if there's an existing payment record for the same member
                    $existingPayment = TrainerPayment::where('member_id', $request->member_id)
                        ->where('end_date', '>=', Carbon::now()->toDateString())
                        ->where('package_status', 1)
                        ->latest()
                        ->first();
                    $end_date = Carbon::parse($existingPayment->end_date)->addDays($package_duration->duration)->toDateString();

                    if ($existingPayment) {
                        return response()->json([
                            'message' => "An active payment record already exists for this member.",
                            'code' => 500
                        ], 200);
                    }

                    $payload['start_date'] = $existingPayment->end_date;
                    $payload['end_date'] = $end_date;
                    $payload['date_of_payment'] = $payment_date;
                    $payload['due'] = $due;
                    $payload['payment_type'] = 0; //trainer package payment
                    $payload['package_status'] = 1; //1 is for recent payment else 0
                    $payload['offer'] = $request->offer;
                    $trainer_payment = TrainerPayment::create($payload);

                    if ($trainer_payment) {
                        $existingPayment->package_status = 0; // 0 means inactive
                        $existingPayment->save();
                        return response()->json([
                            'message' => "Payment Done",
                            'code' => 200
                        ], 200);
                    } else {
                        return response()->json([
                            'message' => "Payment Failed",
                            'code' => 500
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'message' => "No package found based on " . $request->trainer_package_id,
                        'code' => 500
                    ], 200);
                }
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    //change package dropdown for pt
    public function pt_change_package_dropdown(Request $request)
    {
        try {
            $package_id = $request->id;
            $member_id =  $request->member_id;
            $amount = TrainerPackage::where('id', $package_id)->value('package_amount');
            $previous_packageData = TrainerPayment::where('member_id', $member_id)
                ->with('trainer_packages')
                ->where('payment_type', 0)
                ->latest()
                ->first();
            $previous_package_amount = $previous_packageData->trainer_packages->package_amount;
            if ($amount < $previous_package_amount) {
                return response()->json([
                    'message' => 'Please choose a higher package to proceed',
                    'code' => 500
                ], 200);
            } else {
                $final_amount = ($amount - $previous_packageData->paying_amount);
                return response()->json([
                    'data' => [
                        "package_amount" => $final_amount
                    ],
                    'code' => 200
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }
    //change package for trainer
    public function change_trainer_package(Request $request)
    {
        $payload = $request->all();
        $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
        $admision_data = YearlyPackage::where('member_id', $request->member_id)->latest()->first();
        $admission_expired = $admision_data->end_date < now();
        $member_data = Payment::where('member_id', $request->member_id)
            ->where('package_status', 1)->latest()->first();
        $is_expired = $member_data->end_date < now();
        if ($is_expired == true && $admission_expired == true) {
            return response([
                'error' => "Main package required to add Personal Training sessions.",
                'code' => 500
            ], 200);
        }

        $due = ($request->payble_amount - $request->paying_amount);
        $payment_date = date('Y-m-d H:i:s', strtotime($request->date_of_payment));
        $package_duration = TrainerPackage::where('id', $request->trainer_package_id)->value('duration');
        $start_date = date('Y-m-d', strtotime($request->start_date . ' +1 day'));
        $previous_packageData = TrainerPayment::where('member_id', $request->member_id)
            ->with('trainer_packages')
            ->where('package_status', 1)
            ->latest()
            ->first();
        $end_date = Carbon::parse($previous_packageData->start_date)->addDays($package_duration)->toDateString();
        $payload['start_date'] = $previous_packageData->start_date;
        $payload['end_date'] = $end_date;
        $payload['date_of_payment'] = $payment_date;
        $payload['due'] = $due;
        $payload['payment_type'] = 0; //trainer package payment
        $payload['package_status'] = 1; //1 is for recent payment else 0
        $payload['offer'] = $request->offer;
        $trainer_payment = TrainerPayment::create($payload);

        if ($trainer_payment) {
            $member   = Member::select('name', 'phone')->where('id', $request->member_id)->first();
            $pkg_name = TrainerPackage::where('id', $request->trainer_package_id)->value('name') ?? '';
            $discount = $request->offer ?? 0;

            event(new SendWhatsAppNotification(
                'payment_received_confirmation',
                [$member->name, $request->paying_amount, $pkg_name],
                [$member->phone]
            ));

            event(new SendWhatsAppNotification(
                'personal_trainer_package',
                [
                    $request->paying_amount,
                    $request->payble_amount,
                    $discount,
                    $member->name,
                    Carbon::parse($end_date)->format('d-m-Y'),
                ],
                [$member->phone]
            ));

            return response()->json([
                'message' => "Payment Done",
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "Payment Failed",
                'code' => 500
            ], 200);
        }
    }
    //trainer due payment
    public function trainer_due_payment(Request $request)
    {
        try {
            $payment_id = $request->payment_id;
            $payment_record = TrainerPayment::where('id', $payment_id)->latest()->first();
            $date_of_payment = date('Y-m-d', strtotime($request->date_of_payment ));
            // Log::info($payment_record);
            // dd($payment_record->trainer_package_id);
            $create_due_payment = [
                'member_id' =>  $payment_record->member_id,
                'trainer_package_id' => $payment_record->trainer_package_id,
                'offer' => $payment_record->offer,
                'payble_amount'  => $request->paying_amount,
                'total_payble_amount'  => $request->paying_amount,
                'mode_of_payment'  =>  $request->mode_of_payment,
                'paying_amount'  => $request->paying_amount,
                'due' => 0,
                'payment_type' => 1, // 1 is for only duePayment
                'date_of_payment'  => $date_of_payment,
                'start_date' => $payment_record->start_date,
                'end_date' => $payment_record->end_date,
            ];

            $data = TrainerPayment::create($create_due_payment);
            if ($data) {
                $model = TrainerPayment::where('member_id', $payment_record->member_id)
                    ->where('payment_type', '!=', 1)->update(['due' => 0]);
                if ($model) {
                    $member   = Member::select('name', 'phone')->where('id', $payment_record->member_id)->first();
                    $pkg_name = TrainerPackage::where('id', $payment_record->trainer_package_id)->value('name') ?? '';

                    event(new SendWhatsAppNotification(
                        'payment_received_confirmation',
                        [$member->name, $request->paying_amount, $pkg_name],
                        [$member->phone]
                    ));

                    return response()->json([
                        'message' => "Due Payment Successfull",
                        'code' => 200
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => "Due Payment Failed",
                    'code' => 500
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    //change trainer
    public function change_trainer(Request $request)
    {
        try {
            $trainer_payment = TrainerPayment::where('id', $request->id)->first();
            $trainer_payment->employee_id = $request->trainer_id;
            $result = $trainer_payment->save();

            if ($result) {
                return response()->json([
                    'message' => "Trainer Changed",
                    'code' => 200
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }


    public function getTrainerWisePackages(Request $request)
    {
        $request->validate([
            'emp_id' => 'required',
            'page' => 'required|integer',
            'limit' => 'required|integer'
        ]);
    
        try {
            $empId = $request->emp_id;
            $page = $request->page ?? 1;
            $limit = $request->limit ?? 10;
    
            $today = now()->format('Y-m-d');
    
            $query = TrainerPayment::with(['members:id,name,membership_number', 'trainer_packages:id,name'])
                ->where('employee_id', $empId)
                ->whereDate('end_date', '>=', $today) // Only active packages
                ->orderBy('date_of_payment', 'desc');
    
            $totalRecords = $query->count();
    
            $payments = $query
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->map(function ($payment) {
                    return [
                        'date_of_payment' => $payment->date_of_payment,
                        'member_name'     => optional($payment->members)->name,
                        'membership_number' => optional($payment->members)->membership_number,
                        'member_id' => optional($payment->members)->id,
                        'package_name'    => optional($payment->trainer_packages)->name,
                        'slot'            => $payment->slot,
                        'paying_amount'   => $payment->paying_amount,
                        'end_date'        => $payment->end_date,
                    ];
                });
    
            return response()->json([
                'message' => 'Active trainer PT package payments fetched successfully.',
                'code' => 200,
                'total_records' => $totalRecords,
                'current_page' => (int)$page,
                'limit' => (int)$limit,
                'data' => $payments
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }
    
public function getTrainersWithPT(Request $request)
{
    try {
        $trainerIds = TrainerPayment::distinct('employee_id')->pluck('employee_id');

        $trainers = Employee::whereIn('id', $trainerIds)
            ->select('id', 'name', 'designation', 'image') // Include image if you want to show
            ->get();

        return response()->json([
            'message' => 'Trainer list fetched successfully.',
            'code' => 200,
            'data' => $trainers
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Something went wrong!',
            'error' => $e->getMessage(),
            'code' => 500
        ], 200);
    }
}

}
