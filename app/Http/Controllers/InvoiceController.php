<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\OfferPackage;
use App\Models\Package;
use App\Models\TrainerPackage;
use App\Models\YearlyPackage;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function getInvoiceNumber()
    {
        $invoice_number = Invoice::latest()->orderBy('id', 'desc')->first();
        if ($invoice_number) {
            return response()->json([
                'data' => $invoice_number->id + 1,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'data' => "Something went wrong",
                'code' => 500
            ], 200);
        }
    }

    public function getInvoiceDetailsById(Request $request)
    {
        $invoice_id = $request->invoice_number;
        $invoice_data = Invoice::where('id', $invoice_id)->with(['members', 'mainPackagePayments', 'trainerPackagePayments', 'yearlyPackagePayments', 'nonRegisterMembers', 'steamBath'])->first();

        if (!$invoice_data) {
            return response()->json([
                'data' => [],
                'message' => 'Invoice not found',
                'code' => 404,
            ], 200);
        }

        $members = $invoice_data->members;
        $trainerPackagePayments = $invoice_data->trainerPackagePayments;
        $yearlyPackagePayments = $invoice_data->yearlyPackagePayments;
        $mainPackagePayments = $invoice_data->mainPackagePayments;
        // dd($mainPackagePayments);
        $nonRegisterMembers = $invoice_data->nonRegisterMembers;
        $data = [];
        if ($members) {
            $data['invoice_number'] = $invoice_id;
            $data['name'] = $members->name;
            $data['membership_number'] = $members->membership_number;
            $data['phone'] = $members->phone;
            $data['member_id'] = $members->id; // include member_id
        }
        if ($members && $mainPackagePayments) {
            $package_data = Package::where('id', $mainPackagePayments->package_id)->first();
            $yearly_included = (float) ($mainPackagePayments->yearly_membership_included ?? 0);
            $main_amount = $mainPackagePayments->paying_amount - $yearly_included;

            $data['package_name'] = $package_data->name;
            $data['package_value'] = $package_data->package_amount;
            $data['payable_amount'] = $package_data->package_amount;
            $data['paying_amount'] = $mainPackagePayments->paying_amount; // total paid
            $data['due_amount'] = $mainPackagePayments->due;
            $data['payment_mode'] = $mainPackagePayments->mode_of_payment;
            $data['member_id'] = $mainPackagePayments->member_id; // include member_id
            $data['date'] = date('d-m-Y', strtotime($mainPackagePayments->date_of_payment));

            // Line items when yearly membership is included (so invoice can show Yearly + Main Package breakdown)
            if ($yearly_included > 0) {
                $data['line_items'] = [
                    ['name' => 'Yearly Membership', 'amount' => $yearly_included],
                    ['name' => 'Main Package (' . ($package_data->name ?? 'Package') . ')', 'amount' => $main_amount],
                ];
                $data['yearly_membership_amount'] = $yearly_included;
                $data['main_package_amount'] = $main_amount;
                $data['total_amount'] = $mainPackagePayments->paying_amount;
            }
        } elseif ($members && $trainerPackagePayments) {
            $package_data = TrainerPackage::where('id', $trainerPackagePayments->trainer_package_id)->first();
            $data['package_name'] = $package_data->name;
            $data['package_value'] = $package_data->package_amount;
            $data['payable_amount'] = $package_data->package_amount;
            $data['paying_amount'] = $trainerPackagePayments->paying_amount;
            $data['due_amount'] = $trainerPackagePayments->due;
            $data['payment_mode'] = $trainerPackagePayments->mode_of_payment;
            $data['date'] = date('d-m-Y',strtotime($trainerPackagePayments->date_of_payment));
            $data['member_id'] = $trainerPackagePayments->member_id; // include member_id
        } elseif ($members && $yearlyPackagePayments) {
            $package_data = YearlyPackage::where('id', $yearlyPackagePayments->trainer_package_id)->first();
            $data['package_name'] = "Yearly Package";
            $data['package_value'] = $yearlyPackagePayments->package_amount;
            $data['payable_amount'] = $yearlyPackagePayments->package_amount;
            $data['paying_amount'] = $yearlyPackagePayments->package_amount;

            $data['due_amount'] = 0;
            $data['payment_mode'] = $yearlyPackagePayments->payment_mode;

            $data['date'] = date('d-m-Y',strtotime($yearlyPackagePayments->start_date));
            $data['member_id'] = $yearlyPackagePayments->member_id; // include member_id
        } elseif ($members && $invoice_data->steam_bath_id) {
            $steam = $invoice_data->steamBath;
            $data['invoice_number'] = $invoice_id;
            $data['name'] = $members->name;
            $data['membership_number'] = $members->membership_number;
            $data['phone'] = $members->phone;
            $data['member_id'] = $members->id;
            $data['package_name'] = $steam ? $steam->package_name : 'Steam Bath';
            $amt = $invoice_data->steam_bath_amount;
            $data['package_value'] = $amt;
            $data['payable_amount'] = $amt;
            $data['paying_amount'] = $amt;
            $data['due_amount'] = 0;
            $data['payment_mode'] = $invoice_data->steam_bath_mode_of_payment ?? 'Cash';
            $pd = $invoice_data->steam_bath_payment_date ?? $invoice_data->created_at;
            $data['date'] = date('d-m-Y', strtotime((string) $pd));
        } elseif (!$members && $nonRegisterMembers) {
            $package_data = OfferPackage::where('id', $nonRegisterMembers->offer_package_id)->first();
            $data['name'] = $nonRegisterMembers->name;
            $data['membership_number'] = $nonRegisterMembers->membership_number;
            $data['phone'] = $nonRegisterMembers->phone;
            $data['membership_number'] = $nonRegisterMembers->membership_number;
            $data['package_name'] = $package_data->name;
            $data['package_value'] = $package_data->value;
            $data['payable_amount'] = $package_data->value;
            $data['paying_amount'] = $nonRegisterMembers->paying_amount;
            $data['due_amount'] = $nonRegisterMembers->due;
            $data['payment_mode'] = $nonRegisterMembers->mode_of_payment;
            $data['date'] = date('d-m-Y',strtotime($nonRegisterMembers->payment_date));
            $data['member_id'] = $nonRegisterMembers->member_id; // include member_id
        }

        return response()->json([
            'data' => $data,
            'code' => 200
        ], 200);
    }
}
