<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\TrainerPayment;
use App\Models\YearlyPackage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AllPaymentController extends Controller
{
    public function allPaymentHistory(Request $request)
    {
        try {
            $memberId = $request->member_id;
            if ($memberId === null || $memberId === '') {
                return response()->json([
                    'message' => 'member_id is required',
                    'code' => 422,
                ], 200);
            }
            $memberId = (int) $memberId;
            $searchText = $request->input('search_text', '');
            $limit = max(1, (int) $request->input('limit', 10));
            $rawPage = $request->input('page');
            if ($rawPage !== null && $rawPage !== '') {
                $offset = max(0, (int) $rawPage) * $limit;
            } else {
                $offset = max(0, (int) $request->input('index', $request->input('offset', 0)));
            }
            $dateFilter = $request->input('date', null);

            $date_filter_from = '';
            $date_filter_to = '';

            if ($dateFilter && isset($dateFilter['type']) && isset($dateFilter['value'])) {
                if ($dateFilter['type'] == 1) {
                    if ($dateFilter['value'] == 'today') {
                        $date_filter_from = date('Y-m-d');
                        $date_filter_to = date('Y-m-d');
                    } elseif ($dateFilter['value'] == 'yesterday') {
                        $date_filter_from = date('Y-m-d', strtotime('-1 day'));
                        $date_filter_to = date('Y-m-d', strtotime('-1 day'));
                    } elseif ($dateFilter['value'] == 'daybeforeyesterday') {
                        $date_filter_from = date('Y-m-d', strtotime('-2 days'));
                        $date_filter_to = date('Y-m-d', strtotime('-2 days'));
                    } elseif ($dateFilter['value'] == '7days') {
                        $date_filter_from = date('Y-m-d', strtotime('-7 days'));
                        $date_filter_to = date('Y-m-d');
                    } elseif ($dateFilter['value'] == '30days') {
                        $date_filter_from = date('Y-m-d', strtotime('-30 days'));
                        $date_filter_to = date('Y-m-d');
                    } elseif ($dateFilter['value'] == 'thisYear') {
                        $date_filter_from = date('Y-01-01');
                        $date_filter_to = date('Y-12-31');
                    } elseif ($dateFilter['value'] == 'lastYear') {
                        $date_filter_from = date('Y-01-01', strtotime('last year'));
                        $date_filter_to = date('Y-12-31', strtotime('last year'));
                    }
                } elseif ($dateFilter['type'] == 2) {
                    $custom_date_arr = explode(',', $dateFilter['value']);
                    if (count($custom_date_arr) == 2) {
                        $date_filter_from = date('Y-m-d', strtotime($custom_date_arr[0]));
                        $date_filter_to = date('Y-m-d', strtotime($custom_date_arr[1]));
                    }
                }
            }

            $applyDateFilter = function ($query, $tableAlias, $dateColumn) use ($date_filter_from, $date_filter_to) {
                if ($date_filter_from && $date_filter_to) {
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
                    'payments.due as due',
                    'invoices.id as invoice_number',
                    DB::raw('"Main Package" as payment_type'),
                    'payments.date_of_payment as date',
                    'payments.created_at as date_desc',
                    'payments.start_date',
                    'payments.end_date'
                )
                ->join('members', 'members.id', '=', 'payments.member_id')
                ->join('invoices', 'invoices.main_package_payment_id', '=', 'payments.id')
                ->where('payments.member_id', $memberId)
                ->where(function ($query) use ($searchText) {
                    $query->where('payments.mode_of_payment', 'like', "%$searchText%")
                        ->orWhere('members.name', 'like', "%$searchText%")
                        ->orWhere('invoices.id', 'like', "%$searchText%");
                });

            $applyDateFilter($mainPaymentsQuery, 'payments', 'date_of_payment');

            $trainerPaymentsQuery = DB::table('trainer_payments')
                ->select(
                    'trainer_payments.id',
                    'members.name as member_name',
                    'trainer_payments.mode_of_payment',
                    'trainer_payments.paying_amount as amount',
                    'trainer_payments.due as due',
                    'invoices.id as invoice_number',
                    DB::raw('"Trainer Package" as payment_type'),
                    'trainer_payments.date_of_payment as date',
                    'trainer_payments.created_at as date_desc',
                    'trainer_payments.start_date',
                    'trainer_payments.end_date'
                )
                ->join('members', 'members.id', '=', 'trainer_payments.member_id')
                ->join('invoices', 'invoices.trainer_package_payment_id', '=', 'trainer_payments.id')
                ->where('trainer_payments.member_id', $memberId)
                ->where(function ($query) use ($searchText) {
                    $query->where('trainer_payments.mode_of_payment', 'like', "%$searchText%")
                        ->orWhere('members.name', 'like', "%$searchText%")
                        ->orWhere('invoices.id', 'like', "%$searchText%");
                });

            $applyDateFilter($trainerPaymentsQuery, 'trainer_payments', 'date_of_payment');

            $yearlyPackagesQuery = DB::table('yearly_packages')
                ->select(
                    'yearly_packages.id',
                    'members.name as member_name',
                    DB::raw('COALESCE(yearly_packages.payment_mode, "N/A") as mode_of_payment'),
                    'yearly_packages.package_amount as amount',
                    DB::raw('0 as due'),
                    'invoices.id as invoice_number',
                    DB::raw('"Yearly Membership" as payment_type'),
                    DB::raw('COALESCE(yearly_packages.payment_date, yearly_packages.created_at) as date'),
                    DB::raw('COALESCE(yearly_packages.payment_date, yearly_packages.created_at) as date_desc'),
                    'yearly_packages.start_date',
                    'yearly_packages.end_date'
                )
                ->join('members', 'members.id', '=', 'yearly_packages.member_id')
                ->join('invoices', 'invoices.yearly_package_payment_id', '=', 'yearly_packages.id')
                ->where('yearly_packages.member_id', $memberId)
                ->where(function ($query) use ($searchText) {
                    $query->where('yearly_packages.payment_mode', 'like', "%$searchText%")
                        ->orWhere('members.name', 'like', "%$searchText%")
                        ->orWhere('invoices.id', 'like', "%$searchText%");
                });

            $applyDateFilterYearly = function ($query) use ($date_filter_from, $date_filter_to) {
                if ($date_filter_from && $date_filter_to) {
                    $query->whereRaw('(COALESCE(yearly_packages.payment_date, yearly_packages.created_at) BETWEEN ? AND ?)', [$date_filter_from, $date_filter_to . ' 23:59:59']);
                } elseif ($date_filter_from) {
                    $query->whereRaw('DATE(COALESCE(yearly_packages.payment_date, yearly_packages.created_at)) >= ?', [$date_filter_from]);
                } elseif ($date_filter_to) {
                    $query->whereRaw('DATE(COALESCE(yearly_packages.payment_date, yearly_packages.created_at)) <= ?', [$date_filter_to]);
                }
            };
            $applyDateFilterYearly($yearlyPackagesQuery);

            $steamBathInvoicesQuery = DB::table('invoices')
                ->select(
                    'invoices.id',
                    'members.name as member_name',
                    DB::raw('COALESCE(invoices.steam_bath_mode_of_payment, "Cash") as mode_of_payment'),
                    'invoices.steam_bath_amount as amount',
                    DB::raw('0 as due'),
                    'invoices.id as invoice_number',
                    DB::raw('"Steam Bath" as payment_type'),
                    DB::raw('COALESCE(invoices.steam_bath_payment_date, DATE(invoices.created_at)) as date'),
                    'invoices.created_at as date_desc',
                    DB::raw('COALESCE(invoices.steam_bath_payment_date, DATE(invoices.created_at)) as start_date'),
                    DB::raw('COALESCE(invoices.steam_bath_payment_date, DATE(invoices.created_at)) as end_date')
                )
                ->join('members', 'members.id', '=', 'invoices.member_id')
                ->where('invoices.member_id', $memberId)
                ->whereNotNull('invoices.steam_bath_id')
                ->where(function ($query) use ($searchText) {
                    $query->where('members.name', 'like', "%$searchText%")
                        ->orWhere('invoices.steam_bath_mode_of_payment', 'like', "%$searchText%")
                        ->orWhere('invoices.id', 'like', "%$searchText%");
                });

            if ($date_filter_from && $date_filter_to) {
                $steamBathInvoicesQuery->whereRaw(
                    'COALESCE(invoices.steam_bath_payment_date, DATE(invoices.created_at)) BETWEEN ? AND ?',
                    [$date_filter_from, $date_filter_to . ' 23:59:59']
                );
            } elseif ($date_filter_from) {
                $steamBathInvoicesQuery->whereRaw(
                    'DATE(COALESCE(invoices.steam_bath_payment_date, invoices.created_at)) >= ?',
                    [$date_filter_from]
                );
            } elseif ($date_filter_to) {
                $steamBathInvoicesQuery->whereRaw(
                    'DATE(COALESCE(invoices.steam_bath_payment_date, invoices.created_at)) <= ?',
                    [$date_filter_to]
                );
            }

            $combinedQuery = $mainPaymentsQuery
                ->union($trainerPaymentsQuery)
                ->union($yearlyPackagesQuery)
                ->union($steamBathInvoicesQuery);

            $totalCount = DB::table(DB::raw("({$combinedQuery->toSql()}) as combined"))
                ->mergeBindings($combinedQuery)
                ->count();

            $payments = DB::table(DB::raw("({$combinedQuery->toSql()}) as combined"))
                ->mergeBindings($combinedQuery)
                ->orderBy('date_desc', 'desc')
                ->offset($offset)
                ->limit($limit)
                ->get();

            return response()->json([
                'payments' => $payments,
                'code' => 200,
                'total_count' => $totalCount,
            ]);
        } catch (\Exception $e) {
            Log::error('Error in allPaymentHistory: ' . $e->getMessage());

            return response()->json([
                'message' => 'Something went wrong. Please try again later.',
                'error' => $e->getMessage(),
                'code' => 500,
            ], 500);
        }
    }


    public function expiredPtPackage(Request $request)
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10; // Corrected $request->limits to $request->limit
            $offset = $request->offset > 0 ? $request->offset : 0;
            $search_text = $request->search_text;
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            $dateFilter = $request->input('date', null);

            // Main query setup
            $main_query = TrainerPayment::with(['members', 'trainer_packages'])
                ->where('package_status', 1)
                ->where('payment_type', 0)
                ->limit($limit)
                ->offset($offset)
                ->orderBy('id', 'desc');

            // Date filter conditions
            if ($dateFilter && isset($dateFilter['type']) && isset($dateFilter['value'])) {
                if ($dateFilter['type'] == 1 && $dateFilter['value'] != "") {
                    $check_days = $dateFilter['value'];
                    $filter_date = null;

                    if (in_array($check_days, ['today', '7days', '30days'])) {
                        switch ($check_days) {
                            case 'today':
                                $filter_date = Carbon::parse($today)->toDateString(); // Today’s date
                                break;
                            case '7days':
                                $filter_date = Carbon::parse($today)->subDays(7)->toDateString(); // 7 days from today
                                break;
                            case '30days':
                                $filter_date = Carbon::parse($today)->subDays(30)->toDateString(); // 30 days from today
                                break;
                        }
                    }

                    if ($filter_date) {
                        // Apply date range filter
                        $main_query->whereBetween('end_date', [$filter_date, $today]);
                    } else {
                        $main_query->where('end_date', '<=', $today);
                    }
                }

                // * Missing part: Filter for 'thisYear' *
                if ($dateFilter['type'] == 1 && $dateFilter['value'] == "thisYear") {
                    $main_query->whereYear('end_date', '=', date('Y'));
                }
            }

            // Additional date range filter (type 2)
            if ($dateFilter && $dateFilter['type'] == 2) {
                $dates = explode(',', $dateFilter['value']);
                $startDate = \Carbon\Carbon::parse($dates[0])->startOfDay();
                $endDate = \Carbon\Carbon::parse($dates[1])->endOfDay();
                $main_query->whereBetween('end_date', [$startDate, $endDate]);
            }

            // Search by text
            if ($search_text) {
                $main_query->whereHas('members', function ($query) use ($search_text) {
                    $query->where('name', 'like', "%{$search_text}%")
                        ->orWhere('phone', 'like', "%{$search_text}%")
                        ->orWhere('email', 'like', "%{$search_text}%")
                        ->orWhere('sex', 'like', "%{$search_text}%");
                })->orWhereHas('trainer_packages', function ($query) use ($search_text) {
                    $query->where('name', 'like', "%{$search_text}%");
                });
            }

            // Execute the query
            $expired_data = $main_query->get();

            // Process the results
            if ($expired_data->isNotEmpty()) {
                foreach ($expired_data as $payment) {
                    $payment->membership_number = $payment->members ? $payment->members->membership_number : null;
                    $payment->name = $payment->members ? $payment->members->name : null;
                    $payment->phone = $payment->members ? $payment->members->phone : null;
                    $payment->gender = $payment->members ? $payment->members->sex : null;
                    $payment->image = $payment->members ? $payment->members->image : null;
                    $payment->package_name = $payment->trainer_packages ? $payment->trainer_packages->name : null;
                    $payment->package_status = "expired";

                    $payment->unsetRelation('members');
                    $payment->unsetRelation('trainer_packages');
                }

                // Return success response
                return response()->json([
                    'data' => $expired_data,
                    'code' => 200
                ]);
            } else {
                // No data found response
                return response()->json([
                    'message' => "No Data Found",
                    'data' => [],
                    'code' => 200
                ]);
            }
        } catch (\Exception $e) {
            // Handle any errors
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    public function list_member_due(Request $request)
    {
        try {
            // Set default pagination values
            $limit = $request->input('limit', 10);
            $offset = $request->input('index', 0);
            $search_text = $request->input('search_text', null);
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            $dateFilter = $request->input('date', null);
    
            // Base query
            $main_query = Payment::with(['members', 'packages'])
                ->where('package_status', 1)
                ->where('due', '>', 0);
    
            // Apply date filters
            if ($dateFilter && isset($dateFilter['type'], $dateFilter['value'])) {
                if ($dateFilter['type'] == 1) {
                    $check_days = $dateFilter['value'];
                    if (in_array($check_days, ['today', '7days', '30days'])) {
                        $filter_date = match ($check_days) {
                            'today' => Carbon::parse($today)->toDateString(),
                            '7days' => Carbon::parse($today)->subDays(7)->toDateString(),
                            '30days' => Carbon::parse($today)->subDays(30)->toDateString(),
                            default => null
                        };
    
                        if ($filter_date) {
                            $main_query->whereBetween('start_date', [$filter_date, $today]);
                        }
                    } elseif ($check_days === "thisYear") {
                        $main_query->whereYear('start_date', '=', date('Y'));
                    }
                } elseif ($dateFilter['type'] == 2) {
                    $dates = explode(',', $dateFilter['value']);
                    if (count($dates) == 2) {
                        $startDate = Carbon::parse($dates[0])->startOfDay();
                        $endDate = Carbon::parse($dates[1])->endOfDay();
                        $main_query->whereBetween('start_date', [$startDate, $endDate]);
                    }
                }
            }
    
            // Apply search filters
            if ($search_text) {
                $main_query->where(function ($query) use ($search_text) {
                    $query->whereHas('members', function ($subQuery) use ($search_text) {
                        $subQuery->where('name', 'like', "%{$search_text}%")
                            ->orWhere('phone', 'like', "%{$search_text}%")
                            ->orWhere('email', 'like', "%{$search_text}%")
                            ->orWhere('sex', 'like', "%{$search_text}%");
                    })->orWhereHas('packages', function ($subQuery) use ($search_text) {
                        $subQuery->where('name', 'like', "%{$search_text}%");
                    });
                });
            }
    
            // Clone the query for total count
            $total_count_query = clone $main_query;
            $total_count = $total_count_query->count();
    
            // Apply pagination and ordering
            $paginated_query = clone $main_query;
            $main_package_payments = $paginated_query->orderBy('id', 'desc')
                ->skip($offset)
                ->take($limit)
                ->get();
    
            // Process data
            $payments_data = self::get_main_package_payments($main_package_payments);
    
            return response()->json([
                'data' => $payments_data ?: [],
                'total_count' => $total_count,
                'message' => $payments_data ? "Success" : "No Data Found",
                'code' => 200
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500
            ], 500);
        }
    }
    
    //get the user who has due of their main_package_payment
    public function list_payment_due(Request $request)
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $offset = $request->index > 0 ? $request->index : 0;
            $search_text = $request->search_text;
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            $dateFilter = $request->input('date', null);
            $main_query = Payment::with(['members', 'packages'])
                ->where('package_status', 1)
                ->where('end_date', '<=', $today)
                ->where('due', '>', 0)
                ->limit($limit)
                ->offset($offset)
                ->orderBy('id', 'desc');
            if ($dateFilter && isset($dateFilter['type']) && isset($dateFilter['value'])) {
                if ($dateFilter['type'] == 1 && $dateFilter['value'] != "") {
                    $check_days = $dateFilter['value'];
                    $filter_date = null;
                    if (in_array($check_days, ['today', '7days', '30days'])) {
                        switch ($check_days) {
                            case 'today':
                                $filter_date = Carbon::parse($today)->toDateString(); // Today’s date
                                break;
                            case '7days':
                                $filter_date = Carbon::parse($today)->subDays(7)->toDateString(); // 7 days from today
                                break;
                            case '30days':
                                $filter_date = Carbon::parse($today)->subDays(30)->toDateString(); // 30 days from today
                                break;
                        }
                    }
                    if ($filter_date) {
                        // dd($filter_date);
                        $main_query->whereBetween('end_date', [$filter_date, $today]);
                    } else {
                        $main_query->where('end_date', '<=', $today);
                    }
                    if ($search_text) {
                        $main_query->whereHas('members', function ($query) use ($search_text) {
                            $query->where('name', 'like', "%{$search_text}%")
                                ->orWhere('phone', 'like', "%{$search_text}%")
                                ->orWhere('email', 'like', "%{$search_text}%")
                                ->orWhere('sex', 'like', "%{$search_text}%");
                        })->orWhereHas('packages', function ($query) use ($search_text) {
                            $query->where('name', 'like', "%{$search_text}%");
                        });
                    }
                }
            }

            if ($dateFilter && $dateFilter['type'] == 1  && $dateFilter['value'] == "thisYear") {
                // dd("comming to this ");
                $main_query->whereYear('end_date', '=', date('Y'));
            }
            if ($dateFilter && $dateFilter['type'] == 2) {
                $dates = explode(',',  $dateFilter['value']);
                $startDate = \Carbon\Carbon::parse($dates[0])->startOfDay();
                $endDate = \Carbon\Carbon::parse($dates[1])->endOfDay();
                $main_query->whereBetween('end_date', [$startDate, $endDate]);
            }
            $total_count = $main_query->count();
            $main_package_payments = $main_query->get();
            $payments_data = self::get_main_package_payments($main_package_payments);
            if ($payments_data != null) {
                return response()->json([
                    'data' => $payments_data,
                    'total_count' => $total_count,
                    'message' => "success",
                    'code' => 200
                ]);
            } else {
                return response()->json([
                    'data' => [],
                    'message' => "No Data Found",
                    'total_count' => $total_count,
                    'code' => 200
                ]);
            }
        } catch (\Exception $e) {
            // Return error response
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    // Admin notification list: members whose yearly membership has expired or is expiring soon (<=7 days).
    // Only latest yearly_package per member is considered (soft-check only — never blocks payments).
    public function expired_yearly_membership(Request $request)
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $offset = $request->index > 0 ? $request->index : 0;
            $search_text = $request->search_text;
            $today = Carbon::now('Asia/Kolkata')->toDateString();
            $upcoming = Carbon::now('Asia/Kolkata')->addDays(7)->toDateString();

            $latestYearlyIds = YearlyPackage::selectRaw('MAX(id) as id')
                ->groupBy('member_id')
                ->pluck('id');

            $query = YearlyPackage::with('members')
                ->whereIn('id', $latestYearlyIds)
                ->where('end_date', '<=', $upcoming);

            if ($search_text) {
                $query->whereHas('members', function ($q) use ($search_text) {
                    $q->where('name', 'like', "%{$search_text}%")
                        ->orWhere('phone', 'like', "%{$search_text}%")
                        ->orWhere('email', 'like', "%{$search_text}%")
                        ->orWhere('membership_number', 'like', "%{$search_text}%");
                });
            }

            $total_count = (clone $query)->count();

            $data = $query->orderBy('end_date', 'asc')
                ->offset($offset)
                ->limit($limit)
                ->get()
                ->map(function ($yearlyPackage) use ($today) {
                    $member = $yearlyPackage->members;
                    return [
                        'yearly_package_id'  => $yearlyPackage->id,
                        'member_id'           => $yearlyPackage->member_id,
                        'name'                => $member->name ?? null,
                        'membership_number'   => $member->membership_number ?? null,
                        'phone'               => $member->phone ?? null,
                        'email'               => $member->email ?? null,
                        'end_date'            => $yearlyPackage->end_date,
                        'status'              => Carbon::parse($yearlyPackage->end_date)->lt(Carbon::parse($today)) ? 'expired' : 'expiring_soon',
                    ];
                });

            return response()->json([
                'data' => $data,
                'total_count' => $total_count,
                'code' => 200
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    public function get_main_package_payments($main_package_payments)
    {
        if ($main_package_payments->isEmpty()) {
            return null;
        } else {
            foreach ($main_package_payments as $payment) {
                $payment->membership_number = $payment->members->membership_number;
                $payment->name = $payment->members->name;
                $payment->email = $payment->members->email;
                $payment->phone = $payment->members->phone;
                $payment->gender = $payment->members->sex;
                $payment->image = $payment->members->image;
                $payment->package_name = $payment->packages->name;
                $payment->date_of_payment = date('d-m-Y', strtotime($payment->date_of_payment));
                $payment->start_date = date('d-m-Y', strtotime($payment->start_date));
                $payment->end_date = date('d-m-Y', strtotime($payment->end_date));
                $payment->unsetRelation('members');
                $payment->unsetRelation('packages');
            }
            return $main_package_payments;
        }
    }

    public function expired_member(Request $request)
    {
        try {
            $limit = $request->limit > 0 ? $request->limit : 10;
            $offset = $request->index > 0 ? $request->index : 0;
            $search_text = $request->search_text;
            $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
            $dateFilter = $request->input('date', null);
            $main_query = Payment::with(['members', 'packages'])
                ->where('end_date', '<=', $today)
                ->where('package_status', 1)
                ->limit($limit)
                ->offset($offset)
                ->orderBy('id', 'desc');
                $total_count_query = Payment::with(['members', 'packages'])
                ->where('end_date', '<=', $today)
                ->where('package_status', 1)
                ->orderBy('id', 'desc');
                $total_count = $total_count_query->count();

            if ($dateFilter && isset($dateFilter['type']) && isset($dateFilter['value'])) {
                if ($dateFilter['type'] == 1 && $dateFilter['value'] != "") {
                    $check_days = $dateFilter['value'];
                    $filter_date = null;
                    if (in_array($check_days, ['today', '7days', '30days'])) {
                        switch ($check_days) {
                            case 'today':
                                $filter_date = Carbon::parse($today)->toDateString(); // Today’s date
                                break;
                            case '7days':
                                $filter_date = Carbon::parse($today)->subDays(7)->toDateString(); // 7 days from today
                                break;
                            case '30days':
                                $filter_date = Carbon::parse($today)->subDays(30)->toDateString(); // 30 days from today
                                break;
                        }
                    }
                    if ($filter_date) {
                        $main_query->whereBetween('end_date', [$filter_date, $today]);
                    } else {
                        $main_query->where('end_date', '<=', $today);
                    }
                    if ($search_text) {
                        $main_query->whereHas('members', function ($query) use ($search_text) {
                            $query->where('name', 'like', "%{$search_text}%")
                                ->orWhere('phone', 'like', "%{$search_text}%")
                                ->orWhere('email', 'like', "%{$search_text}%")
                                ->orWhere('sex', 'like', "%{$search_text}%");
                        })->orWhereHas('packages', function ($query) use ($search_text) {
                            $query->where('name', 'like', "%{$search_text}%");
                        });
                    }
                }
            }
            if ($dateFilter && $dateFilter['type'] == 1  && $dateFilter['value'] == "thisYear") {
                // dd("comming to this ");
                $main_query->whereYear('end_date', '=', date('Y'));
            }
            if ($dateFilter && $dateFilter['type'] == 2) {
                $dates = explode(',',  $dateFilter['value']);
                $startDate = \Carbon\Carbon::parse($dates[0])->startOfDay();
                $endDate = \Carbon\Carbon::parse($dates[1])->endOfDay();
                $main_query->whereBetween('end_date', [$startDate, $endDate]);
            }
         

            $expired_data = $main_query->get();
            if ($expired_data->isNotEmpty()) {
                foreach ($expired_data as $payment) {
                    $payment->membership_number = $payment->members->membership_number;
                    $payment->name = $payment->members->name;
                    $payment->email = $payment->members->email;
                    $payment->phone = $payment->members->phone;
                    $payment->gender = $payment->members->sex;
                    $payment->image = $payment->members->image;
                    $payment->package_name = $payment->packages->name;

                    $payment->unsetRelation('members');
                    $payment->unsetRelation('packages');
                }

                return response()->json([
                    'data' => $expired_data,
                    'total_count' => $total_count,
                    'code' => 200
                ]);
            } else {
                return response()->json([
                    'data' => [],
                    'message' => "No Data Found",
                    'total_count' => $total_count,
                    'code' => 200
                ]);
            }
        } catch (\Exception $e) {
            // Return error response
            return response()->json([
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
                'code' => 500
            ], 200);
        }
    }

    // public function check_due_by_day(Request $request)
    // {
    //     $limit = $request->limit > 0 ? $request->limit : 10;
    //     $offset = $request->offset > 0 ? $request->offset : 0;
    //     $search_text = $request->search_text;
    //     $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
    //     $check_days = $request->value;
    //     $filter_date = null;
    //     if ($dateFilter['type'] == 1 && $check_days != '') {
    //         // Set the filter_date based on check_days
    //         if (in_array($check_days, ['1', '7', '30', '365'])) {
    //             $filter_date = Carbon::parse($today)->addDays($check_days)->toDateString();
    //         }
    //         // Create the base query
    //         $query = Payment::with(['members', 'packages'])
    //             ->where('due', '>', 0)
    //             ->where('package_status', '=', 1)
    //             ->orderBy('date_of_payment', 'desc')
    //             ->limit($limit)
    //             ->offset($offset);

    //         // Apply filters to the query
    //         if ($search_text) {
    //             $query->orWhereHas('members', function ($query) use ($search_text) {
    //                 $query->where('name', 'like', "%{$search_text}%")
    //                     ->orWhere('phone', 'like', "%{$search_text}%")
    //                     ->orWhere('email', 'like', "%{$search_text}%");
    //             })
    //                 ->orWhereHas('packages', function ($query) use ($search_text) {
    //                     $query->where('name', 'like', "%{$search_text}%");
    //                 });

    //             if ($filter_date) {
    //                 $query->where('end_date', $filter_date);
    //             } else {
    //                 $query->where('end_date', $today);
    //             }
    //         } elseif ($filter_date) {
    //             // dd($filter_date);
    //             $query->whereBetween('end_date', [$today, $filter_date]);
    //         } else {
    //             $query->where('end_date', $today);
    //         }
    //         //             $sql = $query->toSql();
    //         // $bindings = $query->getBindings();
    //         //             $fullQuery = vsprintf(str_replace('?', "'%s'", $sql), $bindings);

    //         // dd($fullQuery);
    //         // Execute the query and get results
    //         $due_payments = $query->get();
    //         // Process results
    //         if ($due_payments->isNotEmpty()) {
    //             foreach ($due_payments as $payment) {
    //                 $payment->membership_number = $payment->members->membership_number;
    //                 $payment->name = $payment->members->name;
    //                 $payment->email = $payment->members->email;
    //                 $payment->phone = $payment->members->phone;
    //                 $payment->gender = $payment->members->sex;
    //                 $payment->package_name = $payment->packages->name;

    //                 $payment->unsetRelation('members');
    //                 $payment->unsetRelation('packages');
    //             }

    //             return response()->json([
    //                 'data' => $due_payments,
    //                 'code' => 200
    //             ]);
    //         } else {
    //             return response()->json([
    //                 'message' => "No Data Found",
    //                 'code' => 500
    //             ]);
    //         }
    //     }
    // }

    public function searchPayments(Request $request)
    {
        $limit = $request->limit > 0 ? $request->limit : 10;
        $offset = $request->offset > 0 ? $request->offset : 0;
        $main_package_payments = Payment::where('total_payble_amount', 'like', "%{$request->search_text}%")
            ->orWhere('mode_of_payment', 'like', "%{$request->search_text}%")
            ->orWhereHas('members', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search_text}%");
            })
            ->orWhereHas('packages', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search_text}%");
            })
            ->limit($limit)
            ->offset($offset)
            ->orderBy('id', 'desc')
            ->get();

        $trainer_payments = TrainerPayment::where('total_payble_amount', 'like', "%{$request->search_text}%")
            ->orWhere('mode_of_payment', 'like', "%{$request->search_text}%")
            ->orWhereHas('members', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search_text}%");
            })
            ->orWhereHas('trainer_packages', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search_text}%");
            })
            ->limit($limit)
            ->offset($offset)
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'trainer_payments' => $trainer_payments,
            'main_package_payments' => $main_package_payments,
            'code' => 200
        ], 200);
    }

    public function autoComplete(Request $request)
    {
        $search_text = $request->search_text;
        $main_package_payments = Payment::with(['members', 'packages'])
            ->where('total_payble_amount', 'like', "%{$request->search_text}%")
            ->orWhere('mode_of_payment', 'like', "%{$request->search_text}%")
            ->orWhereHas('members', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search_text}%");
            })
            ->orWhereHas('packages', function ($query) use ($request) {
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
                } else if (stripos($item->packages->name, $search_text) !== false) {
                    return ['search_text' => $item->packages->name];
                }
            });

        $trainer_payments = TrainerPayment::with(['members', 'trainer_packages'])
            ->where('total_payble_amount', 'like', "%{$request->search_text}%")
            ->orWhere('mode_of_payment', 'like', "%{$request->search_text}%")
            ->orWhereHas('members', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->search_text}%");
            })
            ->orWhereHas('trainer_packages', function ($query) use ($request) {
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
                } else if (stripos($item->trainer_packages->name, $search_text) !== false) {
                    return ['search_text' => $item->trainer_packages->name];
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
    }

    public function duePtPackage(Request $request)
    {
        $limit = $request->limit > 0 ? $request->limit : 10;
        $offset = $request->offset > 0 ? $request->offset : 0;
        $search_text = $request->search_text;
        $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
        $dateFilter = $request->input('date', null);
        $main_query = TrainerPayment::with(['members', 'trainer_packages']) // Use the correct relationship name
            ->where('due', '>', 0)
            ->where('package_status', 1)
            ->where('payment_type', 0)
            ->limit($limit)
            ->offset($offset)
            ->orderBy('id', 'desc');
        if ($dateFilter && isset($dateFilter['type']) && isset($dateFilter['value'])) {
            if ($dateFilter['type'] == 1 && $dateFilter['value'] != [] && $dateFilter['value'] != "") {
                $check_days = $dateFilter['value'];
                $filter_date = null;
                if (in_array($check_days, ['today', '7days', '30days'])) {
                    switch ($check_days) {
                        case 'today':
                            $filter_date = Carbon::parse($today)->toDateString(); // Today’s date
                            break;
                        case '7days':
                            $filter_date = Carbon::parse($today)->subDays(7)->toDateString(); // 7 days from today
                            break;
                        case  '30days':
                            $filter_date = Carbon::parse($today)->subDays(30)->toDateString(); // 30 days from today
                            break;
                    }
                }
                if ($filter_date) {
                    // dd($today,$filter_date);
                    $main_query->whereBetween('start_date', [$filter_date, $today]);
                } else {
                    $main_query->where('start_date', '>=', $today);
                }
                if ($search_text) {
                    $main_query->whereHas('members', function ($query) use ($search_text) {
                        $query->where('name', 'like', "%{$search_text}%")
                            ->orWhere('phone', 'like', "%{$search_text}%")
                            ->orWhere('email', 'like', "%{$search_text}%")
                            ->orWhere('sex', 'like', "%{$search_text}%");
                    })
                        ->orWhereHas('trainer_packages', function ($query) use ($search_text) {
                            $query->where('name', 'like', "%{$search_text}%");
                        });
                }
            }
        }
        if ($dateFilter && $dateFilter['type'] == 1  && $dateFilter['value'] == "thisYear") {
            // dd("comming to this ");
            $main_query->whereYear('start_date', '=', date('Y'));
        }
        if ($dateFilter && $dateFilter['type'] == 2) {
            $dates = explode(',',  $dateFilter['value']);
            $startDate = \Carbon\Carbon::parse($dates[0])->startOfDay();
            $endDate = \Carbon\Carbon::parse($dates[1])->endOfDay();
            $main_query->whereBetween('start_date', [$startDate, $endDate]);
        }

        $expired_data = $main_query->get();
        // dd($expired_data);
        if ($expired_data->isNotEmpty()) {
            foreach ($expired_data as $payment) {
                $payment->membership_number = $payment->members ? $payment->members->membership_number : null;
                $payment->name = $payment->members ? $payment->members->name : null;
                $payment->phone = $payment->members ? $payment->members->phone : null;
                $payment->gender = $payment->members ? $payment->members->sex : null;
                $payment->image = $payment->members ? $payment->members->image : null;
                $payment->package_name = $payment->trainer_packages ? $payment->trainer_packages->name : null;
                $payment->package_status = "expired";
                $payment->unsetRelation('members');
                $payment->unsetRelation('trainer_packages');
            }

            return response()->json([
                'data' => $expired_data,
                'code' => 200
            ]);
        } else {
            return response()->json([
                'data' => [],
                'message' => "No Data Found",
                'code' => 200
            ]);
        }
    }

    public function dueMemberAutoComplete(Request $request)
    {
        $search_text = $request->search_text;

        $main_package_payments = Payment::with(['members', 'packages'])
            ->where('due', '>', 0)
            ->orWhereHas('members', function ($query) use ($search_text) {
                $query->where('name', 'like', "%{$search_text}%")
                    ->orWhere('phone', 'like', "%{$search_text}%")
                    ->orWhere('email', 'like', "%{$search_text}%")
                    ->orWhere('sex', 'like', "%{$search_text}%");
            })
            ->orWhereHas('packages', function ($query) use ($search_text) {
                $query->where('name', 'like', "%{$search_text}%");
            })
            ->orderBy('id', 'desc')
            ->get()
            ->map(function ($item) use ($search_text) {
                // dd($item->members->email);
                if (stripos($item->members->phone, $search_text) !== false) {
                    return ['search_text' => $item->members->phone];
                } else if (stripos($item->members->email, $search_text) !== false) {
                    return ['search_text' => $item->members->email];
                } else if (stripos($item->members->name, $search_text) !== false) {
                    return ['search_text' => $item->members->name];
                } else if (stripos($item->members->sex, $search_text) !== false) {
                    return ['search_text' => $item->members->sex];
                } else if (stripos($item->packages->name, $search_text) !== false) {
                    return ['search_text' => $item->packages->name];
                }
            })->filter();

        // dd($trainer_payments);
        if ($main_package_payments->toArray() != []) {
            // dd($merged_data);
            return response()->json([
                'data' => $main_package_payments,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No Data Found",
                'code' => 500
            ], 200);
        }
    }

    public function autoCompleteDueByDate(Request $request)
    {
        $search_text = $request->search_text;
        $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
        $check_days = $request->day;
        $filter_date = null;

        // Set the filter_date based on check_days
        if (in_array($check_days, ['1', '7', '15', '30'])) {
            $filter_date = Carbon::parse($today)->addDays($check_days)->toDateString();
        }

        // Create the base query
        $query = Payment::with(['members', 'packages'])->orderBy('id', 'desc');

        // Apply filters to the query
        if ($search_text) {
            $query->orWhereHas('members', function ($query) use ($search_text) {
                $query->where('name', 'like', "%{$search_text}%")
                    ->orWhere('phone', 'like', "%{$search_text}%")
                    ->orWhere('email', 'like', "%{$search_text}%")
                    ->orWhere('sex', 'like', "%{$search_text}%");
            })
                ->orWhereHas('packages', function ($query) use ($search_text) {
                    $query->where('name', 'like', "%{$search_text}%");
                });

            if ($filter_date) {
                $query->where('end_date', $filter_date);
            } else {
                $query->where('end_date', $today);
            }
        } elseif ($filter_date) {
            $query->where('end_date', $filter_date);
        } else {
            $query->where('end_date', $today);
        }
        // Execute the query and get results
        $due_payments = $query->get()->map(function ($item) use ($search_text) {
            // dd($item->members->email);
            if (stripos($item->members->phone, $search_text) !== false) {
                return ['search_text' => $item->members->phone];
            } else if (stripos($item->members->email, $search_text) !== false) {
                return ['search_text' => $item->members->email];
            } else if (stripos($item->members->name, $search_text) !== false) {
                return ['search_text' => $item->members->name];
            } else if (stripos($item->members->sex, $search_text) !== false) {
                return ['search_text' => $item->members->sex];
            } else if (stripos($item->packages->name, $search_text) !== false) {
                return ['search_text' => $item->packages->name];
            }
        })->filter();
        if ($due_payments->toArray() != []) {
            // dd($merged_data);
            return response()->json([
                'data' => $due_payments,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No Data Found",
                'code' => 500
            ], 200);
        }
    }

    public function autoCompleteExpiredMember(Request $request)
    {
        $search_text = $request->search_text;
        $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');

        $query = Payment::with(['members', 'packages'])
            ->where('end_date', '<=', $today)
            ->where('package_status', 1)
            ->orderBy('id', 'desc');

        if ($search_text) {
            $query->orWhereHas('members', function ($query) use ($search_text) {
                $query->where('name', 'like', "%{$search_text}%")
                    ->orWhere('phone', 'like', "%{$search_text}%")
                    ->orWhere('email', 'like', "%{$search_text}%")
                    ->orWhere('sex', 'like', "%{$search_text}%");
            })
                ->orWhereHas('packages', function ($query) use ($search_text) {
                    $query->where('name', 'like', "%{$search_text}%");
                });
        }
        $expired_date = $query->get()->map(function ($item) use ($search_text) {
            // dd($item->members->email);
            if (stripos($item->members->phone, $search_text) !== false) {
                return ['search_text' => $item->members->phone];
            } else if (stripos($item->members->email, $search_text) !== false) {
                return ['search_text' => $item->members->email];
            } else if (stripos($item->members->name, $search_text) !== false) {
                return ['search_text' => $item->members->name];
            } else if (stripos($item->members->sex, $search_text) !== false) {
                return ['search_text' => $item->members->sex];
            } else if (stripos($item->packages->name, $search_text) !== false) {
                return ['search_text' => $item->packages->name];
            }
        })->filter();
        if ($expired_date->toArray() != []) {
            // dd($merged_data);
            return response()->json([
                'data' => $expired_date,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No Data Found",
                'code' => 500
            ], 200);
        };
    }
    public function autoComplete_ptPackage_due(Request $request)
    {
        $search_text = $request->search_text;

        $query = TrainerPayment::with(['members', 'trainer_packages'])
            ->where('due', '>', 0)
            ->orderBy('id', 'desc');

        $trainer_payments = $query->orWhere('due', 'like', "%$search_text%")
            ->orWhereHas('members', function ($query) use ($search_text) {
                $query->where('name', 'like', "%{$search_text}%")
                    ->orWhere('phone', 'like', "%{$search_text}%")
                    ->orWhere('email', 'like', "%{$search_text}%")
                    ->orWhere('membership_number', 'like', "%{$search_text}%")
                    ->orWhere('sex', 'like', "%{$search_text}%");
            })
            ->orWhereHas('trainer_packages', function ($query) use ($search_text) {
                $query->where('name', 'like', "%{$search_text}%");
            })
            ->get()->map(function ($item) use ($search_text) {

                if (stripos($item->name, $search_text) !== false) {
                    return ['search_text' => $item->name];
                } else if (stripos($item->phone, $search_text) !== false) {
                    return ['search_text' => $item->phone];
                } else if (stripos($item->email, $search_text) !== false) {
                    return ['search_text' => $item->email];
                } else if (stripos($item->membership_number, $search_text) !== false) {
                    return ['search_text' => $item->membership_number];
                } else if (stripos($item->sex, $search_text) !== false) {
                    return ['search_text' => $item->sex];
                } else if (stripos($item->members->name, $search_text) !== false) {
                    return ['search_text' => $item->members->name];
                } else if (stripos($item->trainer_packages->name, $search_text) !== false) {
                    return ['search_text' => $item->trainer_packages->name];
                } else {
                    return [];
                }
            })->filter();

        // dd($trainer_payments);
        if ($trainer_payments->toArray() != []) {
            // dd($merged_data);
            return response()->json([
                'data' => $trainer_payments,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No Data Found",
                'code' => 500
            ], 200);
        }
    }

    public function autoCompleteExpiredPtPackage(Request $request)
    {
        $search_text = $request->search_text;
        $today = Carbon::now('Asia/Kolkata')->format('Y-m-d');
        $query = TrainerPayment::with(['members', 'trainer_packages'])
            ->where('end_date', '<', $today)
            ->orderBy('id', 'desc');

        if ($search_text) {
            $query->whereHas('members', function ($query) use ($search_text) {
                $query->where('name', 'like', "%{$search_text}%")
                    ->orWhere('phone', 'like', "%{$search_text}%")
                    ->orWhere('email', 'like', "%{$search_text}%");
            })
                ->orWhereHas('trainer_packages', function ($query) use ($search_text) {
                    $query->where('name', 'like', "%{$search_text}%");
                });
        }

        $expired_data = $query->get()->map(function ($item) use ($search_text) {
            // dd($item->members->email);
            if (stripos($item->members->phone, $search_text) !== false) {
                return ['search_text' => $item->members->phone];
            } else if (stripos($item->members->email, $search_text) !== false) {
                return ['search_text' => $item->members->email];
            } else if (stripos($item->members->name, $search_text) !== false) {
                return ['search_text' => $item->members->name];
            } else if (stripos($item->members->sex, $search_text) !== false) {
                return ['search_text' => $item->members->sex];
            }
        })->filter();

        if ($expired_data->toArray() != []) {
            // dd($merged_data);
            return response()->json([
                'data' => $expired_data,
                'code' => 200
            ], 200);
        } else {
            return response()->json([
                'message' => "No Data Found",
                'code' => 500
            ], 200);
        }
    }
}
