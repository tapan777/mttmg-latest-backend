<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\TrainerPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BalanceSheetController extends Controller
{
    public function balance_sheet_index(Request $request)
    {
        try {
            $limit = $request->input('limit', 10);
            $index = $request->input('index', 0);
            $searchText = $request->input('search_text', '');
            $dateFilter = $request->input('date', null);
    
            $date_filter_from = '';
            $date_filter_to = '';
    
            if ($dateFilter && isset($dateFilter['type']) && isset($dateFilter['value'])) {
                if ($dateFilter['type'] == 1) {
                    if ($dateFilter['value'] == 'today') {
                        $date_filter_from = date('Y-m-d');
                        $date_filter_to = date('Y-m-d');
                    } 
                    else if ($dateFilter['value'] == 'yesterday') {
                        $date_filter_from = date('Y-m-d', strtotime('-1 day'));
                        $date_filter_to = date('Y-m-d', strtotime('-1 day'));
                    } else if ($dateFilter['value'] == 'daybeforeyesterday') {
                        $date_filter_from = date('Y-m-d', strtotime('-2 days'));
                        $date_filter_to = date('Y-m-d', strtotime('-2 days'));
                    }else if ($dateFilter['value'] == '7days') {
                        $date_filter_from = date('Y-m-d', strtotime('-7 days'));
                        $date_filter_to = date('Y-m-d');
                    } else if ($dateFilter['value'] == '30days') {
                        $date_filter_from = date('Y-m-d', strtotime('-30 days'));
                        $date_filter_to = date('Y-m-d');
                    } else if ($dateFilter['value'] == 'thisYear') {
                        $date_filter_from = date('Y-01-01');
                        $date_filter_to = date('Y-12-31');
                    } else if ($dateFilter['value'] == 'lastYear') {
                        $date_filter_from = date('Y-01-01', strtotime('last year'));
                        $date_filter_to = date('Y-12-31', strtotime('last year'));
                    }
                } else if ($dateFilter['type'] == 2) {
                    $custom_date_arr = explode(',', $dateFilter['value']);
                    if (count($custom_date_arr) == 2) {
                        $date_filter_from = date('Y-m-d', strtotime($custom_date_arr[0]));
                        $date_filter_to = date('Y-m-d', strtotime($custom_date_arr[1]));
                    }
                }
            }
    
            $applyDateFilter = function ($query, $tableAlias, $dateColumn) use ($date_filter_from, $date_filter_to) {
                if ($date_filter_from && $date_filter_to) {
                    // Use end-of-day for upper bound so "today" includes full day (for datetime columns)
                    $query->whereBetween("$tableAlias.$dateColumn", [$date_filter_from, $date_filter_to . ' 23:59:59']);
                } elseif ($date_filter_from) {
                    $query->whereDate("$tableAlias.$dateColumn", '>=', $date_filter_from);
                } elseif ($date_filter_to) {
                    $query->whereDate("$tableAlias.$dateColumn", '<=', $date_filter_to);
                }
            };
    
            $mainPaymentsQuery = DB::table('payments')
                ->select(
                    'payments.id',
                    'members.name as member_name',
                    'payments.mode_of_payment',
                    DB::raw('(payments.paying_amount - COALESCE(payments.yearly_membership_included, 0)) as amount'),
                    'invoices.id as invoice_number',
                    DB::raw('"Main Package" as payment_type'),
                    'payments.date_of_payment as date',
                    'payments.created_at as created_at'
                )
                ->join('members', 'members.id', '=', 'payments.member_id')
                ->join('invoices', 'invoices.main_package_payment_id', '=', 'payments.id')
                ->where(function ($query) use ($searchText) {
                    $query->where('payments.mode_of_payment', 'like', "%$searchText%")
                        ->orWhere('members.name', 'like', "%$searchText%")
                        ->orWhere('invoices.id', 'like', "%$searchText%");
                });
    
            $applyDateFilter($mainPaymentsQuery, 'payments', 'created_at');
    
            $trainerPaymentsQuery = DB::table('trainer_payments')
                ->select(
                    'trainer_payments.id',
                    'members.name as member_name',
                    'trainer_payments.mode_of_payment',
                    'trainer_payments.paying_amount as amount',
                    'invoices.id as invoice_number',
                    DB::raw('"Trainer Package" as payment_type'),
                    'trainer_payments.date_of_payment as date',
                    'trainer_payments.created_at as created_at'
                )
                ->join('members', 'members.id', '=', 'trainer_payments.member_id')
                ->join('invoices', 'invoices.trainer_package_payment_id', '=', 'trainer_payments.id')
                ->where(function ($query) use ($searchText) {
                    $query->where('trainer_payments.mode_of_payment', 'like', "%$searchText%")
                        ->orWhere('members.name', 'like', "%$searchText%")
                        ->orWhere('invoices.id', 'like', "%$searchText%");
                });
    
            $applyDateFilter($trainerPaymentsQuery, 'trainer_payments', 'created_at');
    
            $yearlyPackagesQuery = DB::table('yearly_packages')
                ->select(
                    'yearly_packages.id',
                    'members.name as member_name',
                    DB::raw('COALESCE(yearly_packages.payment_mode, "N/A") as mode_of_payment'),
                    'yearly_packages.package_amount as amount',
                    'invoices.id as invoice_number',
                    DB::raw('"Yearly Membership" as payment_type'),
                    DB::raw('COALESCE(yearly_packages.payment_date, yearly_packages.created_at) as date'),
                    DB::raw('yearly_packages.created_at as created_at')
                )
                ->join('members', 'members.id', '=', 'yearly_packages.member_id')
                ->join('invoices', 'invoices.yearly_package_payment_id', '=', 'yearly_packages.id')
                ->where(function ($query) use ($searchText) {
                    $query->where('yearly_packages.payment_mode', 'like', "%$searchText%")
                        ->orWhere('members.name', 'like', "%$searchText%")
                        ->orWhere('invoices.id', 'like', "%$searchText%");
                });
    
            $applyDateFilter($yearlyPackagesQuery, 'yearly_packages', 'created_at');
    
            $nonRegMembersQuery = DB::table('non_registre_members')
                ->select(
                    'non_registre_members.id',
                    'non_registre_members.name as member_name',
                    DB::raw('"Cash" as mode_of_payment'),
                    'non_registre_members.paying_amount as amount',
                    'invoices.id as invoice_number',
                    DB::raw('"Non-Member Package" as payment_type'),
                    'non_registre_members.payment_date as date',
                    'non_registre_members.created_at as created_at'
                )
                ->join('invoices', 'invoices.non_registre_member_id', '=', 'non_registre_members.id')
                ->where(function ($query) use ($searchText) {
                    $query->where('non_registre_members.name', 'like', "%$searchText%")
                        ->orWhere('non_registre_members.email', 'like', "%$searchText%");
                });
    
            $applyDateFilter($nonRegMembersQuery, 'non_registre_members', 'created_at');

            $steamBathInvoicesQuery = DB::table('invoices')
                ->select(
                    'invoices.id',
                    'members.name as member_name',
                    DB::raw('COALESCE(invoices.steam_bath_mode_of_payment, "Cash") as mode_of_payment'),
                    'invoices.steam_bath_amount as amount',
                    'invoices.id as invoice_number',
                    DB::raw('"Steam Bath" as payment_type'),
                    DB::raw('COALESCE(invoices.steam_bath_payment_date, DATE(invoices.created_at)) as date'),
                    'invoices.created_at as created_at'
                )
                ->join('members', 'members.id', '=', 'invoices.member_id')
                ->whereNotNull('invoices.steam_bath_id')
                ->where(function ($query) use ($searchText) {
                    $query->where('members.name', 'like', "%$searchText%")
                        ->orWhere('invoices.steam_bath_mode_of_payment', 'like', "%$searchText%")
                        ->orWhere('invoices.id', 'like', "%$searchText%");
                });

            $applyDateFilter($steamBathInvoicesQuery, 'invoices', 'created_at');
    
            $combinedQuery = $mainPaymentsQuery
                ->union($trainerPaymentsQuery)
                ->union($yearlyPackagesQuery)
                ->union($nonRegMembersQuery)
                ->union($steamBathInvoicesQuery);
                $totalCount = DB::table(DB::raw("({$combinedQuery->toSql()}) as combined"))
                ->mergeBindings($combinedQuery)
                ->count();
            $payments = DB::table(DB::raw("({$combinedQuery->toSql()}) as combined"))
                ->mergeBindings($combinedQuery)
                ->orderBy('created_at', 'desc')
                ->offset($index)
                ->limit($limit)
                ->get();
    
            return response()->json([
                'payments' => $payments,
                'code' => 200,
                'total_count' => $totalCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in balance_sheet_index: ' . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Please try again later.',
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }
    
    public function auto_complete(Request $request)
    {
        $search_text = $request->search_text;
        if ($search_text) {
            $main_package_payments = Payment::with('members')
                ->where('mode_of_payment', 'like', "%{$request->search_text}%")
                ->orWhereHas('members', function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search_text}%");
                })
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($item) use ($search_text) {
                    // dd($item->members->name); 
                    if (stripos($item->total_payble_amount, $search_text) !== false) {
                        return ['search_text' => $item->total_payble_amount];
                    } else if (stripos($item->mode_of_payment, $search_text) !== false) {
                        return ['search_text' => $item->mode_of_payment];
                    } else if (stripos($item->members->name, $search_text) !== false) {
                        return ['search_text' => $item->members->name];
                    }
                });

            $trainer_payments = TrainerPayment::with('members')
                ->where('mode_of_payment', 'like', "%{$request->search_text}%")
                ->orWhereHas('members', function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search_text}%");
                })
                ->orderBy('id', 'desc')
                ->get()
                ->map(function ($item) use ($search_text) {
                    if (stripos($item->total_payble_amount, $search_text) !== false) {
                        return ['search_text' => $item->total_payble_amount];
                    } else if (stripos($item->mode_of_payment, $search_text) !== false) {
                        return ['search_text' => $item->mode_of_payment];
                    } else if (stripos($item->members->name, $search_text) !== false) {
                        return ['search_text' => $item->members->name];
                    }
                });

            $merged_data = array_merge($main_package_payments->toArray(), $trainer_payments->toArray());

            if ($merged_data) {
                // dd($merged_data);
                return response()->json([
                    'data' => $merged_data,
                    'code' => 200
                ], 200);
            } else {
                return response()->json([
                    'message' => "No Data Found",
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
