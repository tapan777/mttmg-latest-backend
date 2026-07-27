<?php

namespace App\Http\Controllers;

use App\Models\AdmissionValue;
use App\Models\Member;
use App\Models\Package;
use App\Models\Payment;
use App\Models\TrainerPayment;
use App\Models\Employee;
use App\Models\YearlyPackage;
use App\Models\Invoice;
use App\Models\SteamBath;
use App\Models\ExerciseUserAssignment;
use App\Events\SendEmailNotification;
use App\Events\SendWhatsAppNotification;
use App\Mail\WelcomeMemberMail;
use App\Models\DietUserAssignment;
use App\Models\Followup;

use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Jmrashed\Zkteco\Lib\ZKTeco;

class MemberController extends Controller
{
    protected $biomatricdata;

    public function __construct(BiomatricController $biomatricData)
    {
        $this->biomatricdata = $biomatricData;
    }
    //create members
    public function createMember(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'membership_number' => 'required',
                'package_id' => 'required',
                'name' => 'required',
                'email' => 'required|email|unique:members,email',
                'phone' => 'required',
                // 'image' => 'required', // You may need to adjust this depending on your actual image upload logic
                'dob' => 'required|date',
                'start_date' => 'required|date',
                'sex' => 'required',
                'address' => 'required',
            ]);

            if ($request->paying_amount > $request->payble_amount) {
                return response()->json([
                    'message' => 'The paying amount cannot exceed the payable amount.',
                    'code' => 500
                ], 200);
            }
            $dob =  date('Y-m-d H:i:s', strtotime($request->dob . ' +1 day'));
            $due = ($request->payble_amount - $request->paying_amount);
            $start_date = date('Y-m-d H:i:s', strtotime($request->start_date ));
            $payment_date = date('Y-m-d H:i:s', strtotime($request->date_of_payment));
            $imageUrl = null;
            if ($request->hasFile('image')) {
                $imageName = time() . '.' . $request->image->extension();
                $request->image->move(public_path('images'), $imageName);
                $imageUrl = url('images/' . $imageName);
            }
            // $imageUrl = 'testing';
            if (!$validator->fails()) {

                $package_duration = Package::where('id', $request->package_id)->value('duration');
                // $start_date = !$request->start_gitdate ? $request->start_date : now()->toDateString();
                $end_date = null;
                if ($package_duration) {
                    $end_date = Carbon::parse($start_date)->addDays($package_duration)->toDateString();
                } else {
                    return response()->json([
                        'message' => 'Package not found',
                        'code' => 500
                    ], 200);
                }
                $member = Member::create([
                    'membership_number' => $request->membership_number,
                    'name' => $request->name,
                    'email' => $request->email,
                    'phone' => $request->phone,
                    'alternate_phone' => $request->alternate_phone,
                    'image' => $imageUrl,
                    'dob' => $dob,
                    'height' => $request->height,
                    'weight' => $request->weight,
                    'joining_date' => $start_date,
                    'sex' => $request->sex,
                    'address' => $request->address,
                    'identification_type' => $request->identification_type,
                    'identification_id' => $request->identification_id,
                    'card_number'  => $request->card_number,
                ])->fresh();
                if ($member) {

                    $yearly_membership = new YearlyPackageController;
                    $admission_value = AdmissionValue::where('id', $request->admission_value_id)->first();
                    $result = $yearly_membership->cr_yr_membership([
                        'member_id' => $member->id,
                        'admission_value_id' => $request->admission_value_id,
                        'package_amount' => $admission_value->admission_value,
                        'start_date' => $start_date,
                        'end_date' => Carbon::parse($start_date)->addYear()->toDateString(),
                        'payment_mode' => $request->mode_of_payment,
                        'payment_date' => $payment_date,
                        'included_in_main_payment' => true, // 1000 membership is part of paying_amount, do not double-count
                    ]);
                }
                $yearly_included = $admission_value ? (float) $admission_value->admission_value : 0;
                $payment_data = [
                    'member_id' =>  $member->id,
                    'package_id' => !$request->package_id ? null : $request->package_id,
                    'bill_no' => $request->payble_amount,
                    'offer' => $request->offer,
                    'payble_amount'  => $request->payble_amount,
                    'total_payble_amount'  => $request->total_payble_amount,
                    'mode_of_payment'  =>  $request->mode_of_payment,
                    'paying_amount'  => $request->paying_amount,
                    'yearly_membership_included' => $yearly_included,
                    'due' => $due,
                    'payment_type' => 0, // 0 means mainPackagePayment not due payment
                    'date_of_payment'  => $payment_date,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'package_status' => 1, //1 means active,
                    'remarks' => $request->remarks,
                    'admission_payment' => 'yes'
                ];
                $payment = PaymentController::create_payment($payment_data);
                $invoiceId = Invoice::where('member_id', $member->id)
                    ->whereNotNull('main_package_payment_id') // Ensure the ID is not null
                    ->orderBy('created_at', 'asc')
                    ->first(['id', 'main_package_payment_id']); // Fetch ID and main package ID
                    $package = Package::find($request->package_id);
                    $package_name = $package ? ($package->package_name ?? 'Package') : 'Package';
                    try {
                        $mobileNumber = $member->phone;
                        $msgname = $member->name;
                        $amount = $request->paying_amount;
                        $discount = $request->offer ?? 0;
                        $pexpdate = Carbon::parse($start_date)->addDays($package->duration ?? 30)->toDateString();
                
                        $message = urlencode("Registration successful at MTTMG ! Payment received ₹" . $amount . " for admission + " . $package_name . " FROM " . $msgname . " " . $mobileNumber . " Next Due Dt " . $pexpdate . " Payment After Due Date will be Rs 50 fine.");
                
                        $key = "at73pzTDNrnkyMeo";
                        $senderid = "MTTGYM";
                        $route = 1;
                        $templateid = "1307162736820914935";
                
                        $url = "http://text2india.store/vb/apikey.php?apikey=$key&senderid=$senderid&templateid=$templateid&number=$mobileNumber&message=$message";
                
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_URL, $url);
                        $smsResponse = curl_exec($ch);
                        curl_close($ch);
                    } catch (\Exception $ex) {
                        \Log::error('SMS Sending Failed: ' . $ex->getMessage());
                    }

                    // WhatsApp — fire and forget via event
                    event(new SendWhatsAppNotification(
                        'payment_received_confirmation',
                        [$member->name, $request->paying_amount, $package_name],
                        [$member->phone] // owner number '9937542268' removed — testing done, WhatsApp confirmed working
                    ));

                    event(new SendWhatsAppNotification(
                        'registration_successful',
                        [
                            $request->paying_amount,                                         // {{1}} Payment Received
                            $request->payble_amount,                                         // {{2}} Admission + Charges
                            $member->name,                                                   // {{3}} Received From
                            Carbon::parse($start_date)->addDays($package->duration ?? 30)->format('d-m-Y'), // {{4}} Next Due Date
                        ],
                        [$member->phone] // owner number '9937542268' removed — testing done, WhatsApp confirmed working
                    ));

                    // Welcome email — disabled
                    // if (!empty($member->email)) {
                    //     event(new SendEmailNotification(
                    //         new WelcomeMemberMail(
                    //             $member->name,
                    //             $package_name,
                    //             $request->paying_amount,
                    //             Carbon::parse($start_date)->addDays($package->duration ?? 30)->format('d-m-Y')
                    //         ),
                    //         $member->email
                    //     ));
                    // }


                // print_r($invoiceId);
                if ($payment && $member && $result) {
                    $sn       = config('zkteco.sn', 'HKQ8241900193');
                    $uid      = $member->id;
                    $safeName = str_replace(' ', '_', $member->name);
                    $card     = $request->card_number ?? '0';
                    $cmdId    = time();

                    $params = [
                        "PIN={$uid}",
                        "Name={$safeName}",
                        "Card={$card}",
                        "Pri=0",
                    ];
                    if (!empty($request->password)) {
                        $params[] = "Pass={$request->password}";
                    }

                    AdmsController::queueCommand(
                        $sn,
                        $cmdId,
                        "C:{$cmdId}:DATA UPDATE USERINFO\t" . implode("\t", $params)
                    );

                    $member->update(['on_device' => 1]);

                    return response()->json([
                        'message' => "Member Added Successfully. Biometric device will sync within 10 seconds.",
                        'data' => [
                            'member' => $member,
                            'invoice_id' => $invoiceId->id ?? null,
                            'main_package_payment_id' => $invoiceId->main_package_payment_id ?? null,
                        ],
                        'code' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'message' => 'Payment Failed',
                        'code' => 500
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => $validator->errors(),
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

    public function daysleft($end_date)
    {
        $today = Carbon::now();  // Parse the ISO 8601 format
        $end_date = Carbon::parse($end_date);      // Parse the ISO 8601 format

        if ($today->lessThan($end_date)) {
            return $end_date->diffInDays($today);
        } else {
            return 0;
        }
    }

    /**
     * Human-readable PT time window from trainer payment slot + assigned trainer's schedule.
     */
    private function resolvePtSlotTime(?string $slot, $employee): ?string
    {
        if (!$employee instanceof Employee) {
            return $slot ?: null;
        }
        $slotLower = $slot !== null && $slot !== '' ? strtolower(trim($slot)) : '';
        if ($slotLower === '') {
            return null;
        }
        if (str_contains($slotLower, 'morning') || in_array($slotLower, ['m', '1', 'am'], true)) {
            return $employee->morning_slot ?: null;
        }
        if (str_contains($slotLower, 'evening') || in_array($slotLower, ['e', '2', 'pm'], true)) {
            return $employee->evening_slot ?: null;
        }

        return $slot;
    }

    //to retrive the members
    public function retriveMembers(Request $request)
    {
        try {
            $search_text = $request->search_text;
            $package_id  = $request->package_id;
            $member_id   = $request->id;
            $limit       = $request->limit > 0 ? $request->limit : 15;
            $index       = $request->index > 0 ? $request->index : 0;
            $hasStatus   = $request->has('status') && $request->status !== null && $request->status !== '';

            if ($search_text) {
                $query = Member::query()
                    ->where(function ($q) use ($search_text) {
                        $q->where('membership_number', 'like', "%$search_text%")
                          ->orWhere('name', 'like', "%$search_text%")
                          ->orWhere('email', 'like', "%$search_text%")
                          ->orWhere('phone', 'like', "%$search_text%");
                    });

                if ($hasStatus) {
                    $query->where('status', $request->status);
                }

                $total_count = $query->count();
                $search_data = $query->with('steamBaths')
                    ->offset($index)
                    ->limit($limit)
                    ->orderBy('id', 'desc')
                    ->get();

                if ($search_data->isNotEmpty()) {
                    foreach ($search_data as $member) {
                        // Initialize steam bath data
                        $member->bath_available = "No";
                        $member->used_bath = 0;
                        $member->remaining_bath = 0;
                        $member->total_bath = 0;

                        if ($member->steamBaths->isNotEmpty()) {
                            foreach ($member->steamBaths as $steamBath) {
                                $available_steamBath = $steamBath->total_bath - $steamBath->used_bath;
                                $member->bath_available = $available_steamBath > 0 ? "Yes" : "No";
                                $member->used_bath = $steamBath->used_bath;
                                $member->remaining_bath = $available_steamBath;
                                $member->total_bath = $steamBath->total_bath;
                            }
                        }
                    }
                    return response()->json([
                        'data'        => $search_data,
                        'total_count' => $total_count,
                        'code'        => 200
                    ], 200);
                } else {
                    return response()->json([
                        'message'     => "No data Found",
                        'total_count' => 0,
                        'code'        => 500
                    ], 200);
                }
            } elseif ($member_id) {
                $member_data = Member::with('steamBaths')->find($member_id);
    
                if ($member_data) {
                    $member_data->dob = date('d-m-Y', strtotime($member_data->dob));
                     // Initialize steam bath data
                    $member_data->bath_available = "No";
                    $member_data->used_bath = 0;
                    $member_data->remaining_bath = 0;
                    $member_data->total_bath = 0;
    
                    if ($member_data->steamBaths->isNotEmpty()) {
                        foreach ($member_data->steamBaths as $steamBath) {
                            $available_steamBath = $steamBath->total_bath - $steamBath->used_bath;
                            $member_data->bath_available = $available_steamBath > 0 ? "Yes" : "No";
                            $member_data->used_bath = $steamBath->used_bath;
                            $member_data->remaining_bath = $available_steamBath;
                            $member_data->total_bath = $steamBath->total_bath;
                        }
                    }
    
                    // Calculate due for member payment
                    $member_payment = Payment::where('member_id', $member_id)
                        ->where('payment_type', 0)->with('invoice')->orderBy('id', 'desc')->latest()->first();
                    if ($member_payment) {
                        $admission_endDate = YearlyPackage::where('member_id', $member_id)->latest()->value('end_date');
                        $member_data->payments = $member_payment;
                        $member_payment->member_package = $member_payment->packages->name;
                        $member_payment->member_package_value = $member_payment->packages->package_amount;
                        $member_payment->date_of_payment = !$member_payment->date_of_payment ? null : date('d-m-Y', strtotime($member_payment->date_of_payment));
                        $member_payment->start_date = !$member_payment->start_date ? null : date('d-m-Y', strtotime($member_payment->start_date));
                        $member_payment->end_date = !$member_payment->end_date ? null : date('d-m-Y', strtotime($member_payment->end_date));
                        $member_payment->admission_expired = $admission_endDate && $admission_endDate < now();
                        $expired_data = [];
    
                        if ($member_data->pause && $member_data->resume != null) {
                            $expired_data = self::daysWhenResumed($member_payment->end_date);
                        } elseif ($member_data->pause && $member_data->resume == null) {
                            $expired_data = self::daysWhenPaused($member_payment->start_date, $member_data->pause);
                        } else {
                            $expired_data = self::daysWhenResumed($member_payment->end_date);
                        }
                        $member_payment->days_left = $expired_data['daysleft'];
                        $member_payment->expired = $expired_data['expired'];
                        if (array_key_exists('expired_days', $expired_data)) {
                            $member_payment->expired_days = $expired_data['expired_days'];
                        }
                        foreach ($member_payment['invoice'] as $invoice) {
                            $member_payment['invoice_number'] = $invoice['id'];
                        }
                        $member_payment->unsetRelation('invoice');
                        unset($member_payment['packages']);
                    } else {
                        $member_data->payments = [];
                    }
    
                    // Calculate due for trainer payment
                    $trainer_payment = TrainerPayment::where('member_id', $member_id)->where('payment_type', 0)->with('invoice', 'employee')->orderBy('id', 'desc')->first();
                    $yearly_package_payment = YearlyPackage::where('member_id', $member_id)
                        ->with('invoice')
                        ->latest('created_at')
                        ->first();
    
                    if ($yearly_package_payment) {
                        $start_date = date('d-m-Y', strtotime($yearly_package_payment->start_date));
                        $end_date = date('d-m-Y', strtotime($yearly_package_payment->end_date));
    
                        $yearly_package_payment->package_name = "Yearly Package";
                        $yearly_package_payment->paying_amount = $yearly_package_payment->package_amount;
                        $yearly_package_payment->start_date = $start_date;
                        $yearly_package_payment->end_date = $end_date;
                        unset($yearly_package_payment->package_amount);
                        $yearly_package_payment->due = 0;
    
                        foreach ($yearly_package_payment->invoice as $invoice) {
                            $yearly_package_payment['invoice_number'] = $invoice['id'];
                        }
    
                        $yearly_package_payment->unsetRelation('invoice');
    
                        $member_data->yearly_package_payments = $yearly_package_payment;
                    } else {
                        $member_data->yearly_package_payments = [];
                    }
    
                    if ($trainer_payment) {
                        $trainer_payment->trainer_package = $trainer_payment->trainer_packages->name;
                        $employeeForPt = $trainer_payment->employee
                            ?: ($trainer_payment->employee_id ? Employee::find($trainer_payment->employee_id) : null);
                        $trainer_name = $employeeForPt ? $employeeForPt->name : null;
                        $trainer_payment->setAttribute('trainer_name', $trainer_name);
                        $trainer_payment->setAttribute(
                            'pt_slot_time',
                            $this->resolvePtSlotTime($trainer_payment->slot, $employeeForPt)
                        );
                        $trainer_payment->date_of_payment = !$trainer_payment->date_of_payment ? null : date('d-m-Y', strtotime($trainer_payment->date_of_payment));
                        $trainer_payment->start_date = !$trainer_payment->start_date ? null : date('d-m-Y', strtotime($trainer_payment->start_date));
                        $trainer_payment->end_date = !$trainer_payment->end_date ? null : date('d-m-Y', strtotime($trainer_payment->end_date));
                        $trainer_payment->days_left = self::daysleft($trainer_payment->end_date);
                        foreach ($trainer_payment['invoice'] as $invoice) {
                            $trainer_payment['invoice_number'] = $invoice['id'];
                        }
                        $member_data->pt_data = $trainer_payment;
                        $trainer_payment->unsetRelation('invoice');
                        $trainer_payment->unsetRelation('employee');
                        unset($trainer_payment['trainer_packages']);
                    } else {
                        $member_data->pt_data = [];
                    }
    
                    // Check if this member was previously a followup/enquiry
                    $member_data->from_followup = Followup::where('phone', $member_data->phone)->exists();

                    return response()->json([
                        'data' => $member_data,
                        'code' => 200
                    ], 200);
                } else {
                    return response()->json([
                        'message' => "Invalid Id",
                        'code' => 500
                    ], 200);
                }
            } else {
                $query = Member::query();

                if ($hasStatus) {
                    $query->where('status', $request->status);
                }

                $total_count = $query->count();
                $user_data   = $query->with('steamBaths')
                    ->orderBy('id', 'desc')
                    ->limit($limit)
                    ->offset($index)
                    ->get();

                if ($user_data->isNotEmpty()) {
                    foreach ($user_data as $member) {
                        // Initialize steam bath data for each member
                        $member->bath_available = "No";
                        $member->used_bath      = 0;
                        $member->remaining_bath = 0;
                        $member->total_bath     = 0;

                        if ($member->steamBaths->isNotEmpty()) {
                            foreach ($member->steamBaths as $steamBath) {
                                $available_steamBath    = $steamBath->total_bath - $steamBath->used_bath;
                                $member->bath_available = $available_steamBath > 0 ? "Yes" : "No";
                                $member->used_bath      = $steamBath->used_bath;
                                $member->remaining_bath = $available_steamBath;
                                $member->total_bath     = $steamBath->total_bath;
                            }
                        }
                    }
                    return response()->json([
                        'data'        => $user_data,
                        'total_count' => $total_count,
                        'code'        => 200
                    ], 200);
                } else {
                    return response()->json([
                        'message'     => "No data Found",
                        'total_count' => 0,
                        'code'        => 500
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
    

    //check how many days to be expired 
    // Check how many days until expiration after resuming
    public function daysWhenResumed($expired_date)
    {
        $endDate = DateTime::createFromFormat('d-m-Y', $expired_date);
        $currentDate = new DateTime();

        // If end date is past the current date, calculate expired days
        if ($endDate < $currentDate) {
            $expiredDays = $endDate->diff($currentDate)->days;
            return [
                'daysleft' => 0,
                'expired' => "Yes",
                'expired_days' => $expiredDays
            ];
        }

        // If not expired, calculate remaining days
        $daysLeft = $endDate->diff($currentDate)->days;
        return [
            'daysleft' => $daysLeft,
            'expired' => "No"
        ];
    }

    // Check how many days left when paused
    public function daysWhenPaused($start_date, $pause_date)
    {
        $startDate = DateTime::createFromFormat('d-m-Y', $start_date);
        $pauseDate = DateTime::createFromFormat('Y-m-d', $pause_date);

        // If paused before the start date, return expired
        if ($startDate > $pauseDate) {
            return [
                'daysleft' => 0,
                'expired' => "Yes"
            ];
        }

        // Calculate remaining days between start and pause
        $daysLeft = $startDate->diff($pauseDate)->days;
        return [
            'daysleft' => $daysLeft,
            'expired' => "No"
        ];
    }
    //update member
    public function updateMembers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'id'                  => 'required|exists:members,id',
                'name'                => 'required|string',
                'email'               => 'required|email|unique:members,email,' . $request->id,
                'phone'               => 'required',
                'dob'                 => 'required|date',
                'sex'                 => 'required',
                'address'             => 'required',
                'alternate_phone'     => 'nullable',
                'height'              => 'nullable|numeric',
                'weight'              => 'nullable|numeric',
                'identification_type' => 'nullable|string',
                'identification_id'   => 'nullable|string',
                'image'               => 'nullable|file|mimes:jpg,jpeg,png,webp|max:2048',
                'card_number'         => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'code'    => 500
                ], 200);
            }

            $member = Member::find($request->id);
            $oldCard = $member->card_number;

            $payload = [
                'name'                => $request->name,
                'email'               => $request->email,
                'phone'               => $request->phone,
                'alternate_phone'     => $request->alternate_phone,
                'dob'                 => date('Y-m-d H:i:s', strtotime($request->dob . ' +1 day')),
                'height'              => $request->height,
                'weight'              => $request->weight,
                'sex'                 => $request->sex,
                'address'             => $request->address,
                'identification_type' => $request->identification_type,
                'identification_id'   => $request->identification_id,
            ];

            if ($request->has('card_number')) {
                $payload['card_number'] = $request->card_number;
            }

            if ($request->hasFile('image')) {
                $imageName = time() . '.' . $request->image->extension();
                $request->image->move(public_path('images'), $imageName);
                $payload['image'] = url('images/' . $imageName);
            }

            $result = $member->update($payload);

            if ($result) {
                if ($request->has('card_number') && (string) $request->card_number !== (string) $oldCard) {
                    $sn       = config('zkteco.sn', 'HKQ8241900193');
                    $uid      = $member->id;
                    $safeName = str_replace(' ', '_', $member->fresh()->name);
                    $card     = $member->fresh()->card_number ?? '0';
                    $cmdId    = time();
                    AdmsController::queueCommand(
                        $sn,
                        $cmdId,
                        "C:{$cmdId}:DATA UPDATE USERINFO\tPIN={$uid}\tName={$safeName}\tCard={$card}\tPri=0"
                    );
                }
                return response()->json([
                    'message' => 'Updated Successfully',
                    'data'    => $member->fresh(),
                    'code'    => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Update Failed',
                    'code'    => 500
                ], 200);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code'    => 500
            ], 200);
        }
    }

    //auto complete
    public function autoCompleteMember(Request $request)
    {
        $search_text = strtolower($request->search_text); // Convert search text to lowercase
        $limit = $request->limit > 0 ? $request->limit : 10;
        $index = $request->index > 0 ? $request->index : 0;

        $data = DB::table('members')
            ->where(DB::raw('LOWER(membership_number)'), 'like', "%$search_text%")
            ->orWhere(DB::raw('LOWER(name)'), 'like', "%$search_text%")
            ->orWhere(DB::raw('LOWER(email)'), 'like', "%$search_text%")
            ->orWhere(DB::raw('LOWER(phone)'), 'like', "%$search_text%")
            ->offset($index)
            ->limit($limit)
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) {
                $parts = array_filter([
                    trim($item->name ?? ''),
                    trim($item->membership_number ?? ''),
                    trim($item->phone ?? ''),
                    trim($item->email ?? ''),
                ]);
                $search_data = implode(' | ', $parts);
                return [
                    'search_data' => $search_data,
                    'id' => $item->id,
                    'name' => $item->name,
                    'membership_number' => $item->membership_number,
                    'phone' => $item->phone,
                    'email' => $item->email,
                ];
            })
            ->values()
            ->toArray();

        if (!empty($data)) {
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


    //active-inactive
    public function activeInactive(Request $request)
    {
        $model = Member::find($request->id);
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

    //delete user
    public function deleteMember(Request $request)
    {
        try {
            $model = Member::find($request->id);
            if ($model != null) {
                // Fingerprint machine code commented out
                // $zk = new ZKTeco('192.168.1.201');
                // $zk->connect();
                // $zk->removeUser($request->id);
                // $zk->testVoice();

                $memberId = $model->id;

                // Delete all member-related and payment data
                Payment::where('member_id', $memberId)->delete();
                TrainerPayment::where('member_id', $memberId)->delete();
                YearlyPackage::where('member_id', $memberId)->delete();
                Invoice::where('member_id', $memberId)->delete();
                SteamBath::where('member_id', $memberId)->delete();
                ExerciseUserAssignment::where('member_id', $memberId)->delete();
                DietUserAssignment::where('member_id', $memberId)->delete();

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
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    public function membershipNumber()
    {
        //MTTMG240411004544
        $last_records = Member::latest('id')->first();
        $date = date('ymd', strtotime(now()->toDateString()));
        if ($last_records) {
            $number = $last_records->id + 1;
            $membership_number = "MTTMG" . $date . $number;
            return response()->json([
                'data' =>  $membership_number,
                'code' => 200
            ], 200);
        } else {
            $membership_number = "MTTMG" . $date . 01;
            return response()->json([
                'data' =>  $membership_number,
                'code' => 200
            ], 200);
        }
    }

    public function pause(Request $request)
    {
        $date = Carbon::parse($request->resume_date);
        $pause_date = $date->format('Y-m-d');
        $pause_date = Carbon::createFromFormat('Y-m-d', $pause_date)->addDay()->toDateString();

        $member_id = $request->member_id;
        $record = Member::find($member_id);

        if ($record) {
            $record->pause = $pause_date;
            $result = $record->save();

            if ($result) {
                return response()->json([
                    'message' => "Membership is stopped",
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "Something went wrong",
                    'code' => 500
                ], 200);
            }
        } else {
            return response()->json([
                'message' => "Invalid Member Id",
                'code' => 500
            ], 200);
        }
    }

    public function resume(Request $request)
    {
        $date = Carbon::parse($request->resume_date);
        $member_id = $request->member_id;
        $resume_date = $date->format('Y-m-d');
        $resume_date = Carbon::parse($date)->addDay(1)->toDateString();

        $record = Member::find($member_id);
        if ($record) {
            $record->resume = $resume_date;
            $result = $record->save();
            // dd($record);

            if ($result) {
                $payment_data = Payment::where('member_id', $member_id)
                    ->where('payment_type', 0)->latest()->first();
                // dd($payment_data->start_date);
                if ($payment_data) {
                    $package_start_date = Carbon::parse($payment_data->start_date);
                    $package_end_date = Carbon::parse($payment_data->end_date);
                    $package_pause_date = Carbon::parse($record->pause);
                    $package_resume_date = Carbon::parse($record->resume);
                    Log::info([$package_start_date, $package_end_date, $package_pause_date, $package_resume_date]);

                    // Calculate the duration of pause
                    $pause_duration = $package_pause_date->diffInDays($package_resume_date);

                    // Calculate the new end date
                    $end_date = $package_end_date->addDays($pause_duration)->toDateString();
                    $payment_data->end_date = $end_date;
                    $payment_result = $payment_data->save();
                    if ($payment_result) {
                        return response()->json([
                            'message' => "Membership is Resumed",
                            'code' => 200
                        ], 200);
                    } else {
                        return response()->json([
                            'message' => "Failed to update expired date",
                            'code' => 500
                        ], 200);
                    }
                } else {
                    return response()->json([
                        'message' => "No Payment data found",
                        'code' => 500
                    ], 200);
                }
            } else {
                return response()->json([
                    'message' => "Something went wrong",
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


    //add member to the biomatric
    public function add_biomatric_user(Request $request)
    {
        $set_result = $this->biomatricdata->setBiomatricData(
            $request->member_id,
            12345678,
            $request->name,
            1482056
        );
        if ($set_result) {
            return response()->json([
                'message' => "Please Add Fingerprint for the User : $request->member_id",
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "Set user Failed",
                'code' => 200
            ], 200);
        }
    }

    public function getAllMembers()
    {
        try {
            $members = Member::select('id', 'name', 'membership_number', 'phone')->get();
            
            // Concatenate 'name', 'membership_number', and 'phone' into 'membership_info'
            $members = $members->map(function ($member) {
                $member->membership_info = $member->name . ' - ' . $member->membership_number . ' - ' . $member->phone;
                unset($member->name); // Remove old keys if needed
                unset($member->membership_number); // Remove old keys if needed
                unset($member->phone); // Remove old keys if needed
                return $member;
            });

            return response()->json([
                'code' => 200,
                'message' => 'Members fetched successfully.',
                'data' => $members
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'message' => 'Error fetching members.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
