<?php

namespace App\Http\Controllers;

use App\Events\SendWhatsAppNotification;
use App\Models\AdmissionValue;
use DateTime;
use Exception;
use Carbon\Carbon;
use App\Models\Package;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\Member;
use App\Models\SmsStatus;
use App\Models\YearlyPackage; 
class PaymentController extends Controller
{
    public static function create_payment($payment_data)
    {
        // dd($payment_data);
        $validator = Validator::make($payment_data, [
            'member_id' => 'required',
            'payble_amount'  => 'required',
            'total_payble_amount'  => 'required',
            'mode_of_payment'  => 'required',
            'paying_amount'  => 'required',
            'date_of_payment'  => 'required',
        ]);

        if ($validator->fails()) {
            return response([
                'error' => $validator->errors()
            ], 200);
        } else {
            $payment = Payment::create($payment_data);

            if ($payment) {
                return response()->json([
                    'message' => $payment,
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Payment Failed",
                    'code' => 500
                ], 200);
            }
        }
    }
    public function daysleft($start_date, $end_date)
    {
        $start_date = Carbon::parse($start_date);  // Parse the ISO 8601 format
        $end_date = Carbon::parse($end_date);      // Parse the ISO 8601 format

        if ($start_date->lessThan($end_date)) {
            return $end_date->diffInDays($start_date);
        } else {
            return 0;
        }
    }
    public function repeate_package(Request $request)
    {
        try {
            $yearly_package =  new YearlyPackageController;
            $check_validation = $yearly_package->checkYearlyValidation($request->member_id); //to check yearly package is active or not
            $previous_packageData = Payment::where('member_id', $request->member_id)
                ->where('payment_type', 0)
                ->latest()
                ->first();
            $package_data = Package::where('id', $previous_packageData->package_id)->first();
            // dd($package_data);
            $start_date = Carbon::now()->format('Y-m-d H:i:s');
            $paying_amount = $package_data->package_amount - $request->offer;
            // if ($check_validation) {
            //     return response()->json([
            //         'message' => "Yearly Package Expired",
            //         'code' => 500
            //     ], 200);
            // }
            $request->merge([
                'member_id' =>  $request->member_id,
                'package_id' => !$request->package_id ? $previous_packageData->package_id : $request->package_id,
                'bill_no' => $request->payble_amount,
                'offer' => $request->offer,
                'total_payble_amount'  => $package_data->package_amount,
                'mode_of_payment'  =>  $request->mode_of_payment,
                'date_of_payment'  => $start_date,
                'start_date' => $previous_packageData->start_date,
                'due' => $previous_packageData->due
            ]);
            if ($request->has('admission_value_id') && $request->admission_value_id != null) {
                $request->merge([
                    'paying_amount'  => $paying_amount + $request->admission_value,
                    'payble_amount'  => $paying_amount + $request->admission_value,
                ]);
            } else {
                $request->merge([
                    'paying_amount'  => $paying_amount,
                    'payble_amount'  => $paying_amount,
                ]);
            }
            $content = self::renewPackage($request)->getContent();
            $result = json_decode($content, true);
            if ($result['code'] == 200) {
                $previous_packageData->package_status = 0; // 0 means inactive
                $previous_packageData->save();
                return response()->json([
                    'message' => 'Package has been added Successfully',
                    'code' => 200
                ], 200);
            }
            return response()->json($result, 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }
    public function change_package_dropdown(Request $request)
    {
        try {
            $package_id = $request->package_id;
            $member_id = $request->member_id;
    
            // Get the member's current package from the latest payment
            $previous_packageData = Payment::where('member_id', $member_id)
                ->with('packages')
                ->where('payment_type', 0)
                ->latest()
                ->first();
    
            if (!$previous_packageData || !$previous_packageData->packages) {
                return response()->json([
                    'message' => 'Member has no previous package information.',
                    'code' => 404
                ], 200);
            }
    
            $current_package_id = $previous_packageData->packages->id;
    
            if ($package_id == $current_package_id) {
                return response()->json([
                    'message' => 'Selected package is the same as the current package.',
                    'code' => 400
                ], 200);
            }
    
            // Get new package amount
            $new_package_amount = (float) Package::where('id', $package_id)->value('package_amount');
    
            // Previous payment: use only main package portion (exclude yearly membership if included)
            $previous_paying_total = (float) $previous_packageData->paying_amount;
            $yearly_included = (float) ($previous_packageData->yearly_membership_included ?? 0);
            $previous_main_paying = $previous_paying_total - $yearly_included;
            $previous_due = (float) ($previous_packageData->due ?? 0);
    
            // Final amount = new package - (main-only paying) + due
            $final_amount = $new_package_amount - $previous_main_paying;
            $final_amount = max($final_amount, 0); // Avoid negative
    
            return response()->json([
                'data' => [
                    'amount_to_pay' => round($final_amount, 2)
                ],
                'code' => 200
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }
    
    
    

    public function change_package(Request $request)
    {
        try {
            $previous_packageData = Payment::where('member_id', $request->member_id)
                ->with('packages')
                ->where('payment_type', 0)
                ->latest()
                ->first();
    
            // Check if selected package is the same as current
            if ($previous_packageData && $previous_packageData->package_id == $request->package_id) {
                return response()->json([
                    'message' => 'Same package cannot be changed.',
                    'code' => 400
                ], 200);
            }
    
            $offer = $request->offer ?? 0;
            $due = ($request->payble_amount - $offer) - $request->paying_amount;
            $payment_date = date('Y-m-d H:i:s', strtotime($request->date_of_payment));
            $package_duration = Package::where('id', $request->package_id)->value('duration');
            $end_date = Carbon::parse($previous_packageData->start_date)->addDays($package_duration)->toDateString();

            // Yearly membership expiry is informational only — do not block the main package change.
            $yearlyPackage = YearlyPackage::where('member_id', $request->member_id)
                ->where('end_date', '>=', Carbon::now()->toDateString())
                ->orderBy('end_date', 'desc')
                ->first();
            $yearlyWarning = null;
            if (!$yearlyPackage) {
                $yearlyWarning = 'Yearly membership not found or already expired. Please renew yearly membership.';
            } elseif (Carbon::parse($yearlyPackage->end_date)->lt(Carbon::parse($end_date))) {
                $yearlyExpiryFormatted = Carbon::parse($yearlyPackage->end_date)->format('d-m-Y');
                $yearlyWarning = "Your yearly membership expires on {$yearlyExpiryFormatted}, before this package period ends. Please renew yearly membership in time.";
            }

            // Block the package change if the yearly membership is expiring the same month this
            // package starts, unless the yearly membership is also being renewed in this request.
            $isRenewingYearly = $request->has('admission_value_id') && $request->admission_value_id != null;
            if (!$isRenewingYearly && $yearlyPackage
                && Carbon::parse($yearlyPackage->end_date)->format('Y-m') === Carbon::parse($previous_packageData->start_date)->format('Y-m')
            ) {
                $yearlyExpiryFormatted = Carbon::parse($yearlyPackage->end_date)->format('d-m-Y');
                return response()->json([
                    'message' => "Yearly membership expires on {$yearlyExpiryFormatted}, in the same month as this package. Please renew the yearly membership first.",
                    'code' => 400
                ], 200);
            }

            $payment_data = [
                'member_id' => $request->member_id,
                'package_id' => $request->package_id,
                'offer' => !$request->offer ? null : $request->offer,
                'payble_amount' => $request->payble_amount,
                'total_payble_amount' => $request->total_payble_amount,
                'mode_of_payment' => $request->mode_of_payment,
                'paying_amount' => $request->paying_amount,
                'due' => $due,
                'payment_type' => 0,
                'date_of_payment' => $payment_date,
                'start_date' => $previous_packageData->start_date,
                'end_date' => $end_date,
                'package_status' => 1
            ];
    
            $payment = Payment::create($payment_data)->fresh();
    
            if ($request->has('admission_value_id') && $request->admission_value_id != null) {
                $yearly_membership = new YearlyPackageController;
                $yearly_membership->cr_yr_membership([
                    'member_id' => $request->member_id,
                    'admission_value_id' => $request->admission_value_id,
                    'package_amount' => $request->admission_value,
                    'start_date' => date('Y-m-d H:i:s', strtotime($request->start_date)),
                    'end_date' => Carbon::parse($request->start_date)->addYear()->toDateString(),
                    'payment_mode' => $request->mode_of_payment
                ]);
            }
    
            if ($payment) {
                $previous_packageData->package_status = 0;
                $previous_packageData->save();
                Member::where('id', $request->member_id)->update(['status' => 1]);
                $sms = SmsStatus::first(); // Assuming only one row tracks the balance

                if ($sms && $sms->amount > 0) {
                $member = Member::select('membership_number', 'phone', 'name')->where('id', $request->member_id)->first();
                $mobileNumber = $member->membership_number ?? '';
                $mbl = $member->phone ?? '';
                $msgname = $member->name ?? '';
                $amount = $request->paying_amount;
                $discount = $request->offer ?? 0;
                $package_name = Package::where('id', $request->package_id)->value('name');
                $pexpdate = Carbon::parse($end_date)->format('d-m-Y');
    
                $key = "at73pzTDNrnkyMeo";
                $senderid = "MTTGYM";
                $templateid = "1307162736820914935";
    
                $message_content = urlencode("Registration successful at MTTMG ! Thankyou ". $msgname ." we have received a payment of " . $amount . " for  addmission + " . $package_name ."Discount :" .$discount. " FROM " . $msgname . $mobileNumber . " Next Due dt " . $pexpdate." payment after Due date will be Rs 50 fine.");
    
                $url = "http://text2india.store/vb/apikey.php?apikey=$key&senderid=$senderid&templateid=$templateid&number=$mbl&message=$message_content";
                file_get_contents($url); // Send to member
    
                }

                // WhatsApp — fire and forget
                event(new SendWhatsAppNotification(
                    'payment_received_confirmation',
                    [$msgname, $amount, $package_name],
                    [$mbl]
                ));

                return response()->json([
                    'message' => "Payment completed, and package successfully added.",
                    'yearly_warning' => $yearlyWarning,
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Payment Failed",
                    'code' => 500
                ], 200);
            }
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }
    
    public function renewPackage(Request $request)
    {
        try {
            if ($request->has('admission_value_id') && $request->admission_value_id != null) {
                $yearly_membership = new YearlyPackageController;
                $result = $yearly_membership->cr_yr_membership([
                    'member_id' => $request->member_id,
                    'admission_value_id' => $request->admission_value_id,
                    'package_amount' => $request->admission_value,
                    'start_date' => date('Y-m-d H:i:s', strtotime($request->start_date . ' +1 day')),
                    'end_date' => Carbon::parse($request->start_date)->addYear()->toDateString(),
                    'payment_mode' => $request->mode_of_payment
                ]);
            }
            $due = ($request->payble_amount - $request->paying_amount);
            $payment_date = date('Y-m-d H:i:s', strtotime($request->date_of_payment));
            $package_data = Package::where('id', $request->package_id)->value('duration');
            $previous_endDate = Payment::where('member_id', $request->member_id)
                ->where('payment_type', 0)
                ->latest()->first();
            $totalDays = $package_data;

            if (!empty($request->start_date)) {
                $start_date = date('Y-m-d H:i:s', strtotime($request->start_date));
                $end_date = Carbon::parse($request->start_date)->addDays($totalDays)->toDateString();
            } else {
                $start_date = date('Y-m-d H:i:s', strtotime($previous_endDate->end_date));
                $end_date = Carbon::parse($previous_endDate->end_date)->addDays($totalDays)->toDateString();
            }

            // Yearly membership expiry is informational only — do not block renewal.
            $yearlyPackage = YearlyPackage::where('member_id', $request->member_id)
                ->where('end_date', '>=', Carbon::now()->toDateString())
                ->orderBy('end_date', 'desc')
                ->first();
            $yearlyWarning = null;
            if (!$yearlyPackage) {
                $yearlyWarning = 'Yearly membership not found or already expired. Please renew yearly membership.';
            } elseif (Carbon::parse($yearlyPackage->end_date)->lt(Carbon::parse($end_date))) {
                $yearlyExpiryFormatted = Carbon::parse($yearlyPackage->end_date)->format('d-m-Y');
                $yearlyWarning = "Your yearly membership expires on {$yearlyExpiryFormatted}, before this package period ends. Please renew yearly membership in time.";
            }

            // Block the renewal if the yearly membership is expiring the same month this
            // package starts, unless the yearly membership is also being renewed in this request.
            $isRenewingYearly = $request->has('admission_value_id') && $request->admission_value_id != null;
            if (!$isRenewingYearly && $yearlyPackage
                && Carbon::parse($yearlyPackage->end_date)->format('Y-m') === Carbon::parse($start_date)->format('Y-m')
            ) {
                $yearlyExpiryFormatted = Carbon::parse($yearlyPackage->end_date)->format('d-m-Y');
                return response()->json([
                    'message' => "Yearly membership expires on {$yearlyExpiryFormatted}, in the same month as this package. Please renew the yearly membership first.",
                    'code' => 400
                ], 200);
            }

            if ($request->has('due')) {
                $due += $request->due; // Add due amount if present
            }
            
            if ($request->has('offer')) {
                $due -= $request->offer; // Subtract offer if present
            }
            if ($due < 0) {
                return response()->json([
                    'message' => 'The paying amount cannot exceed the payable amount.',
                    'code' => 500
                ], 200);
            }
            $payment_data = [
                'member_id' =>  $request->member_id,
                'package_id' => !$request->package_id ? null : $request->package_id,
                'bill_no' => $request->payble_amount,
                'offer' => !$request->offer ? null : $request->offer,
                'payble_amount'  => $request->payble_amount,
                'total_payble_amount'  => $request->total_payble_amount,
                'mode_of_payment'  =>  $request->mode_of_payment,
                'paying_amount'  => $request->paying_amount,
                'due' => $due,
                'payment_type' => 0,
                'date_of_payment'  => $payment_date,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'package_status' => 1
            ];

            $validator = Validator::make($payment_data, [
                'member_id' => 'required',
                'payble_amount'  => 'required',
                'total_payble_amount'  => 'required',
                'mode_of_payment'  => 'required',
                'paying_amount'  => 'required',
                'date_of_payment'  => 'required',
            ]);

            if ($validator->fails()) {
                return response([
                    'message' => $validator->errors(),
                    'code' => 500
                ], 200);
            } else {
                $payment = Payment::create($payment_data)->fresh();
                if ($payment) {
                    $previous_endDate->package_status = 0; // 0 means inactive

                    $previous_endDate->save();
                    Member::where('id', $request->member_id)->update(['status' => 1]);

                    // A member who expired before this renewal may already have been
                    // removed from the biometric device (see SyncDeviceMembership).
                    // Re-add them now that they have an active package again, using
                    // their existing card — there's no reason to issue a new card
                    // just because the device forgot them.
                    $renewedMember = Member::find($request->member_id);
                    if ($renewedMember && Carbon::parse($end_date)->gte(Carbon::today())) {
                        $sn       = config('zkteco.sn', 'HKQ8241900193');
                        $uid      = $renewedMember->id;
                        $safeName = str_replace(' ', '_', $renewedMember->name);
                        $card     = $renewedMember->card_number ?? '0';
                        $cmdId    = time();

                        AdmsController::queueCommand(
                            $sn,
                            $cmdId,
                            "C:{$cmdId}:DATA UPDATE USERINFO\tPIN={$uid}\tName={$safeName}\tCard={$card}\tPri=0"
                        );

                        $renewedMember->update(['on_device' => 1]);
                    }

                    $sms = SmsStatus::first(); // Assuming only one row tracks the balance

                    if ($sms && $sms->amount > 0) {
                    $key = "at73pzTDNrnkyMeo";
                    $pay_value = $request->paying_amount;
                    $package_name = Package::where('id', $request->package_id)->value('name');
                    $discount = $request->offer ?? 0;
                    $member = Member::select('membership_number', 'phone', 'name')->where('id', $request->member_id)->first();
                    $pno = $member->membership_number ?? 'N/A';
                    $mbl = $member->phone ?? 'N/A';
                    $msgname = $member->name ?? 'N/A';
                    $message_content = urlencode("Due Payment Successful at MTTMG! Payment received Rs.$pay_value for admission + $package_name. Discount Rs.$discount FROM $msgname $pno. Payment After Next Due Date will be Rs 50 fine.");
        
                    $senderid = "MTTGYM";
                    $templateid = "1307162753904815925";
                    $url = "http://text2india.store/vb/apikey.php?apikey=$key&senderid=$senderid&templateid=$templateid&number=$mbl&message=$message_content";
        
                    $output = file_get_contents($url); // sends the SMS

                    }

                    // WhatsApp — fire and forget
                    event(new SendWhatsAppNotification(
                        'payment_received_confirmation',
                        [$msgname, $pay_value, $package_name],
                        [$mbl]
                    ));

                    event(new SendWhatsAppNotification(
                        'member_renewal_payment_received',
                        [
                            $msgname,
                            $package_name,
                            $pay_value,
                            Carbon::parse($end_date)->format('F Y'),
                            $discount,
                            $due,
                        ],
                        [$mbl]
                    ));

                    return response()->json([
                        'message' => "Payment Successfull",
                        'yearly_warning' => $yearlyWarning,
                        'code' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'message' => "Payment Failed",
                        'code' => 500
                    ], 200);
                }
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    public function duePayment(Request $request)
    {
        try {
            $due = ($request->payble_amount - $request->offer) - $request->paying_amount;
            $start_date = date('Y-m-d H:i:s', strtotime($request->start_date . ' +1 day'));
            $payment_date = date('Y-m-d H:i:s', strtotime($request->date_of_payment . ' +1 day'));
            $package_data = Package::where('id', $request->package_id)->value('duration');
            $end_date = Carbon::parse($start_date)->addDays($package_data)->toDateString();

            $payment_data = [
                'member_id' =>  $request->member_id,
                'package_id' => !$request->package_id ? null : $request->package_id,
                'offer' => !$request->offer ? null : $request->offer,
                'payble_amount'  => $request->payble_amount,
                'total_payble_amount'  => $request->total_payble_amount,
                'mode_of_payment'  =>  $request->mode_of_payment,
                'paying_amount'  => $request->paying_amount,
                'due' => $due,
                'payment_type' => 1, // 1 is for only duePayment
                'date_of_payment'  => $payment_date,
                'start_date' => $start_date,
                'end_date' => $end_date,
            ];
            $validator = Validator::make($payment_data, [
                'member_id' => 'required',
                'payble_amount'  => 'required',
                'total_payble_amount'  => 'required',
                'mode_of_payment'  => 'required',
                'paying_amount'  => 'required',
                'date_of_payment'  => 'required',
            ]);

            if ($validator->fails()) {
                return response([
                    'error' => $validator->errors()
                ], 200);
            } else {
                $payment_data = Payment::where('member_id', $request->member_id)
                    ->where('package_id', $request->package_id)->latest()->first();
                // dd($payment_data);
                if ($payment_data) {
                    if ($payment_data->due <= 0) {
                        return response()->json([
                            'message' => "Dues has been already paid",
                            'code' => 200
                        ], 200);
                    } else {
                        $payment_data->due = 0;
                        $payment_data->save();
                        $payment = Payment::create($payment_data);
                        if ($payment) {
                            return response()->json([
                                'message' => "Due Payment Successfull",
                                'code' => 200
                            ], 200);
                        } else {
                            return response()->json([
                                'message' => "Due Payment Failed",
                                'code' => 500
                            ], 200);
                        }
                    }
                } else {
                    return response()->json([
                        'message' => "No Payment Data for the member",
                        'code' => 500
                    ], 200);
                }
            }
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    public function members_due(Request $request)
    {
        try {
            $payment_id = $request->payment_id;
            $payment_record = Payment::where('id', $payment_id)->first();
            if (!$payment_record) {
                return response()->json([
                    'message' => "Payment record not found",
                    'code' => 500
                ], 200);
            }
            $paying_amount = (float) ($request->paying_amount ?? 0);
            $discount = (float) ($request->discount ?? 0);
            if ($paying_amount <= 0) {
                return response()->json([
                    'message' => "Paying amount must be greater than 0",
                    'code' => 500
                ], 200);
            }
            if ($discount < 0) {
                return response()->json([
                    'message' => "Discount cannot be negative",
                    'code' => 500
                ], 200);
            }
            $current_due = (float) ($payment_record->due ?? 0);
            if ($paying_amount + $discount > $current_due + 0.01) {
                return response()->json([
                    'message' => "Paying amount plus discount cannot exceed the due amount",
                    'code' => 500
                ], 200);
            }
            $new_due = max(0, $current_due - $paying_amount - $discount);
            $date_of_payment = $request->date_of_payment
                ? date('Y-m-d', strtotime($request->date_of_payment))
                : now()->format('Y-m-d');            // Log::info($payment_record);
            // dd($payment_record->member_id);
            $create_due_payment = [
                'member_id' =>  $payment_record->member_id,
                'package_id' => $payment_record->package_id,
                'bill_no' => $request->bill_no,
                'offer' => $discount,
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

            $data = Payment::create($create_due_payment);
            if ($data) {
                $payment_record->due = $new_due;
                $payment_record->save();

                $member   = Member::select('name', 'phone')->where('id', $payment_record->member_id)->first();
                $pkg_name = \App\Models\Package::where('id', $payment_record->package_id)->value('name') ?? 'Package';

                event(new SendWhatsAppNotification(
                    'payment_received_confirmation',
                    [$member->name, $request->paying_amount, $pkg_name],
                    [$member->phone]
                ));

                return response()->json([
                    'message' => "Due Payment Successful",
                    'code' => 200,
                    'remaining_due' => $new_due,
                ], 200);
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
}
