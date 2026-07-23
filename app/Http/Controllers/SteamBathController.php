<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\SteamBath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SteamBathController extends Controller
{
    public function store_bath(Request $request)
    {
        try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'member_id'       => ['required', 'exists:members,id'],
                'used_bath'       => ['nullable', 'integer', 'min:0'],
                'amount'          => ['required', 'numeric', 'min:0'],
                'payment_date'    => ['required', 'date'],
                'total_bath'      => ['nullable', 'integer', 'min:0'],
                'mode_of_payment' => ['nullable', 'string', 'max:64'],
            ]);

            $paymentDate = date('Y-m-d', strtotime($request->payment_date));
            $modeOfPayment = $request->input('mode_of_payment', 'Cash');

            $package = null;
            $invoice = null;

            DB::transaction(function () use ($request, $paymentDate, $modeOfPayment, &$package, &$invoice) {
                $package = SteamBath::where('member_id', $request->member_id)->first();

                if ($package) {
                    $package->update([
                        'total_bath'   => $request->total_bath,
                        'amount'       => $request->amount,
                        'used_bath'    => 0,
                        'payment_date' => $paymentDate,
                    ]);
                    $package->refresh();
                } else {
                    $package = SteamBath::create([
                        'member_id'     => $request->member_id,
                        'package_name'  => 'Steam Bath',
                        'total_bath'    => $request->total_bath,
                        'used_bath'     => 0,
                        'amount'        => $request->amount,
                        'payment_date'  => $paymentDate,
                    ]);
                }

                // One invoice per payment (renewals keep history via snapshot columns)
                $invoice = Invoice::create([
                    'member_id'                   => $request->member_id,
                    'steam_bath_id'               => $package->id,
                    'steam_bath_amount'           => $request->amount,
                    'steam_bath_payment_date'     => $paymentDate,
                    'steam_bath_mode_of_payment'  => $modeOfPayment,
                ]);
            });

            return response()->json([
                'message' => 'Congratulations',
                'code' => 200,
                'data' => $package,
                'invoice_id' => $invoice?->id,
            ], 200);
        } catch (ValidationException $e) {
            // Return validation errors with 422 status
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ]);
        }
    }

    public function use_bath(Request $request)
    {
        $steamBath = SteamBath::where('member_id', $request->member_id)->first();

        if (!$steamBath) {
            return response()->json([
                'message' => 'Steam bath record not found for the given member.'
            ], 404);
        }

        if ($steamBath->used_bath >= 0 &&  $steamBath->total_bath > $steamBath->used_bath) {
            $steamBath->increment('used_bath', 1);
        } else {
            return response()->json([
                'message' => 'No more used baths left to Use.'
            ], 400);
        }

        return response()->json([
            'message' => 'Steam bath usage has been successfully updated',
            'updated_used_bath' => $steamBath->used_bath,
            'code' => 200
        ], 200);
    }
}
