<?php

namespace App\Http\Controllers;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Followup;
use App\Models\Member;
use App\Models\NonRegistreMember;
use App\Models\Payment;
use App\Models\SmsStatus;
use App\Models\TrainerPayment;
use App\Models\YearlyPackage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    //active member
    public function active_member()
    {
        $members = Member::where('status', 1)->get();
        // dd(count($members));
        return response()->json([
            'data' => count($members),
            'code' => 200
        ]);
    }

    //get total employee
    public function total_employee()
    {
        $employees = Employee::all();
        return response()->json([
            'data' => count($employees),
            'code' => 200
        ]);
    }

    //get total member
    public function total_members()
    {
        $members = Member::all();
        return response()->json([
            'data' => count($members),
            'code' => 200
        ]);
    }

    //get new member
    public function new_members()
    {
        // Get the current month and year
        $currentMonth = now()->month;
        $currentYear = now()->year;
        // Retrieve members created in the current month
        $members = Member::whereYear('created_at', $currentYear)
            ->whereMonth('created_at', $currentMonth)
            ->get();

        return response()->json([
            'data' => count($members),
            'code' => 200
        ]);
    }

    public function dashboard_counts(Request $request)
    {
        $today       = Carbon::today();
        $filterType  = $request->input('filter_type', 'monthly'); // 'monthly' | 'yearly'
        $filterYear  = (int) $request->input('year', now()->year);
        $filterMonth = (int) $request->input('month', now()->month);

        // ── Period scope helper ──
        $applyPeriod = function ($query, string $column = 'created_at') use ($filterType, $filterYear, $filterMonth) {
            if ($filterType === 'yearly') {
                return $query->whereYear($column, $filterYear);
            }
            return $query->whereYear($column, $filterYear)->whereMonth($column, $filterMonth);
        };

        // ── All-time totals ──
        $active_members           = Member::where('status', 1)->count();
        $total_employees          = Employee::count();
        $total_members            = Member::count();
        $main_package_payments    = Payment::sum('paying_amount');
        $non_register_payments    = NonRegistreMember::sum('paying_amount');
        $yearly_payments          = YearlyPackage::where('included_in_main_payment', false)->sum('package_amount');
        $trainer_package_payments = TrainerPayment::sum('paying_amount');
        $steam_bath_payments      = (float) DB::table('invoices')->whereNotNull('steam_bath_id')->sum('steam_bath_amount');
        $total_payments           = $main_package_payments + $trainer_package_payments + $non_register_payments + $yearly_payments + $steam_bath_payments;

        $payment_due         = Payment::where('due', '>', 0)->sum('due');
        $trainer_payment_due = TrainerPayment::where('due', '>', 0)->sum('due');
        $nr_payment_due      = NonRegistreMember::where('due', '>', 0)->sum('due');
        $total_due           = $payment_due + $trainer_payment_due + $nr_payment_due;

        // ── Period totals (monthly or yearly) ──
        $period_new_members  = $applyPeriod(Payment::where('payment_type', 0), 'start_date')
            ->distinct('member_id')->count('member_id');
        $period_new_pt       = $applyPeriod(TrainerPayment::where('payment_type', 0))->count();
        $period_enquiries    = $applyPeriod(Followup::query())->count();

        $period_main_pay     = $applyPeriod(Payment::query(), 'date_of_payment')->sum('paying_amount');
        $period_trainer_pay  = $applyPeriod(TrainerPayment::query(), 'date_of_payment')->sum('paying_amount');
        $period_nr_pay       = $applyPeriod(NonRegistreMember::query())->sum('paying_amount');
        $period_yearly_pay   = $applyPeriod(YearlyPackage::where('included_in_main_payment', false))->sum('package_amount');

        if ($filterType === 'yearly') {
            $period_steam_pay = (float) DB::table('invoices')
                ->whereNotNull('steam_bath_id')
                ->whereRaw('YEAR(COALESCE(steam_bath_payment_date, created_at)) = ?', [$filterYear])
                ->sum('steam_bath_amount');
        } else {
            $period_steam_pay = (float) DB::table('invoices')
                ->whereNotNull('steam_bath_id')
                ->whereRaw('YEAR(COALESCE(steam_bath_payment_date, created_at)) = ?', [$filterYear])
                ->whereRaw('MONTH(COALESCE(steam_bath_payment_date, created_at)) = ?', [$filterMonth])
                ->sum('steam_bath_amount');
        }

        $period_total_payments = $period_main_pay + $period_trainer_pay + $period_nr_pay + $period_yearly_pay + $period_steam_pay;

        // Attendance in period (member attendance by date column)
        $period_attendance = $applyPeriod(Attendance::query(), 'date')->count();

        // ── Today totals ──
        $today_main_pay    = Payment::whereDate('date_of_payment', $today)->sum('paying_amount');
        $today_trainer_pay = TrainerPayment::whereDate('date_of_payment', $today)->sum('paying_amount');
        $today_nr_pay      = NonRegistreMember::whereDate('created_at', $today)->sum('paying_amount');
        $today_yearly_pay  = YearlyPackage::where('included_in_main_payment', false)->whereDate('created_at', $today)->sum('package_amount');
        $today_steam_pay   = (float) DB::table('invoices')
            ->whereNotNull('steam_bath_id')
            ->whereRaw('DATE(COALESCE(steam_bath_payment_date, created_at)) = ?', [$today->toDateString()])
            ->sum('steam_bath_amount');
        $today_total_payments = $today_main_pay + $today_trainer_pay + $today_nr_pay + $today_yearly_pay + $today_steam_pay;
        $today_attendance     = Attendance::whereDate('date', $today)->count();
        $today_new_members    = Payment::where('payment_type', 0)
            ->whereDate('start_date', $today)
            ->distinct('member_id')->count('member_id');

        // ── Upcoming renewals in period ──
        $renewalQ = Payment::where('payment_type', 0)->where('package_status', 1);
        $renewalQ = $applyPeriod($renewalQ, 'end_date');
        $upcoming_renewal_count   = (clone $renewalQ)->count();
        $upcoming_renewal_amount  = (clone $renewalQ)->sum('total_payble_amount');

        // ── Expired members (end_date < today, still package_status=1) ──
        $expired_members = Payment::where('payment_type', 0)
            ->where('package_status', 1)
            ->whereDate('end_date', '<', $today)
            ->distinct('member_id')
            ->count('member_id');

        // ── Active PT members (end_date >= today) ──
        $active_pt_members = TrainerPayment::where('payment_type', 0)
            ->where('package_status', 1)
            ->whereDate('end_date', '>=', $today)
            ->distinct('member_id')
            ->count('member_id');

        $sms_balance = SmsStatus::latest()->value('amount') ?? 0;

        return response()->json([
            // filter applied
            'filter_type'  => $filterType,
            'filter_year'  => $filterYear,
            'filter_month' => $filterType === 'monthly' ? $filterMonth : null,

            // all-time
            'active_members'           => $active_members,
            'employees'                => $total_employees,
            'members'                  => $total_members,
            'main_package_payments'    => $main_package_payments,
            'trainer_package_payments' => $trainer_package_payments,
            'steam_bath_payments'      => $steam_bath_payments,
            'total_payments'           => $total_payments,
            'total_due'                => $total_due,
            'expired_members'          => $expired_members,
            'active_pt_members'        => $active_pt_members,

            // period (monthly/yearly filtered)
            'period_new_members'       => $period_new_members,
            'period_new_pt_signups'    => $period_new_pt,
            'period_enquiries'         => $period_enquiries,
            'period_attendance'        => $period_attendance,
            'period_main_pay'          => $period_main_pay,
            'period_trainer_pay'       => $period_trainer_pay,
            'period_steam_pay'         => $period_steam_pay,
            'period_total_payments'    => $period_total_payments,
            'upcoming_renewal_count'   => $upcoming_renewal_count,
            'upcoming_renewal_amount'  => $upcoming_renewal_amount,

            // today
            'today_total_payments'     => $today_total_payments,
            'today_main_pay'           => $today_main_pay,
            'today_trainer_pay'        => $today_trainer_pay,
            'today_steam_pay'          => $today_steam_pay,
            'today_attendance'         => $today_attendance,
            'today_new_members'        => $today_new_members,

            'sms_balance' => $sms_balance,
            'code'        => 200,
        ], 200);
    }

    // Underlying payment records behind a Quick Stats card on the dashboard —
    // 'source' picks which payment table(s) to pull from and 'scope' picks the
    // same today/period date window dashboard_counts() used to compute the sum,
    // so the total shown here always matches the figure the card was clicked from.
    public function paymentBreakdown(Request $request)
    {
        $source      = $request->input('source', 'main'); // main | trainer | nr | yearly | steam | total
        $scope       = $request->input('scope', 'today'); // today | period
        $filterType  = $request->input('filter_type', 'monthly');
        $filterYear  = (int) $request->input('year', now()->year);
        $filterMonth = (int) $request->input('month', now()->month);
        $limit       = (int) $request->input('limit', 100);
        $index       = (int) $request->input('index', 0);
        $today       = Carbon::today();

        $applyDateWindow = function ($query, string $column = 'created_at') use ($scope, $filterType, $filterYear, $filterMonth, $today) {
            if ($scope === 'today') {
                return $query->whereDate($column, $today);
            }
            if ($filterType === 'yearly') {
                return $query->whereYear($column, $filterYear);
            }
            return $query->whereYear($column, $filterYear)->whereMonth($column, $filterMonth);
        };

        $rows = collect();

        if (in_array($source, ['main', 'total'])) {
            $mainPayments = $applyDateWindow(Payment::with('members:id,name'), 'date_of_payment')->get();

            $rows = $rows->merge(
                $mainPayments->map(fn ($p) => [
                    'name'   => $p->members->name ?? 'N/A',
                    // A yearly membership fee bundled into the same payment is included in
                    // paying_amount but tracked separately in yearly_membership_included —
                    // subtract it here so this row reflects only the Main Package portion
                    // (matches BalanceSheetController/AllPaymentController).
                    'amount' => (float) $p->paying_amount - (float) ($p->yearly_membership_included ?? 0),
                    'date'   => date('d-m-Y', strtotime($p->date_of_payment)),
                    'mode'   => $p->mode_of_payment,
                    'type'   => 'Main Package',
                ])
            );

            $rows = $rows->merge(
                $mainPayments
                    ->filter(fn ($p) => (float) ($p->yearly_membership_included ?? 0) > 0)
                    ->map(fn ($p) => [
                        'name'   => $p->members->name ?? 'N/A',
                        'amount' => (float) $p->yearly_membership_included,
                        'date'   => date('d-m-Y', strtotime($p->date_of_payment)),
                        'mode'   => $p->mode_of_payment,
                        'type'   => 'Yearly Membership',
                    ])
            );
        }

        if (in_array($source, ['trainer', 'total'])) {
            $rows = $rows->merge(
                $applyDateWindow(TrainerPayment::with('members:id,name'), 'date_of_payment')
                    ->get()
                    ->map(fn ($p) => [
                        'name'   => $p->members->name ?? 'N/A',
                        'amount' => (float) $p->paying_amount,
                        'date'   => date('d-m-Y', strtotime($p->date_of_payment)),
                        'mode'   => $p->mode_of_payment,
                        'type'   => 'PT Package',
                    ])
            );
        }

        if ($source === 'total') {
            $rows = $rows->merge(
                $applyDateWindow(NonRegistreMember::query())
                    ->get()
                    ->map(fn ($p) => [
                        'name'   => $p->name ?? 'N/A',
                        'amount' => (float) $p->paying_amount,
                        'date'   => date('d-m-Y', strtotime($p->created_at)),
                        'mode'   => $p->mode_of_payment,
                        'type'   => 'Non-Member',
                    ])
            );

            $rows = $rows->merge(
                $applyDateWindow(YearlyPackage::where('included_in_main_payment', false)->with('members:id,name'))
                    ->get()
                    ->map(fn ($p) => [
                        'name'   => $p->members->name ?? 'N/A',
                        'amount' => (float) $p->package_amount,
                        'date'   => date('d-m-Y', strtotime($p->created_at)),
                        'mode'   => $p->payment_mode ?? '—',
                        'type'   => 'Yearly Membership',
                    ])
            );

            $steamQuery = DB::table('invoices')
                ->whereNotNull('steam_bath_id')
                ->select('steam_bath_amount', 'steam_bath_payment_date', 'created_at', 'member_id');
            if ($scope === 'today') {
                $steamQuery->whereRaw('DATE(COALESCE(steam_bath_payment_date, created_at)) = ?', [$today->toDateString()]);
            } elseif ($filterType === 'yearly') {
                $steamQuery->whereRaw('YEAR(COALESCE(steam_bath_payment_date, created_at)) = ?', [$filterYear]);
            } else {
                $steamQuery->whereRaw('YEAR(COALESCE(steam_bath_payment_date, created_at)) = ?', [$filterYear])
                    ->whereRaw('MONTH(COALESCE(steam_bath_payment_date, created_at)) = ?', [$filterMonth]);
            }
            $memberNames = Member::whereIn('id', $steamQuery->pluck('member_id'))->pluck('name', 'id');
            $rows = $rows->merge(
                $steamQuery->get()->map(fn ($p) => [
                    'name'   => $memberNames[$p->member_id] ?? 'N/A',
                    'amount' => (float) $p->steam_bath_amount,
                    'date'   => date('d-m-Y', strtotime($p->steam_bath_payment_date ?? $p->created_at)),
                    'mode'   => '—',
                    'type'   => 'Steam Bath',
                ])
            );
        }

        $rows = $rows->sortByDesc('date')->values();
        $total = $rows->sum('amount');
        $count = $rows->count();
        $paged = $rows->slice($index, $limit)->values();

        return response()->json([
            'data'        => $paged,
            'total_count' => $count,
            'total_amount' => $total,
            'code'        => 200,
        ], 200);
    }

    public function invoiceByCategory()
    {
        try {
            // Query invoices and count by categories
            $mainPackageCount = \DB::table('invoices')->whereNotNull('main_package_payment_id')->count();
            $trainerPackageCount = \DB::table('invoices')->whereNotNull('trainer_package_payment_id')->count();
            $yearlyPackageCount = \DB::table('invoices')->whereNotNull('yearly_package_payment_id')->count();
            $NonRegisterMemberCount = \DB::table('invoices')->whereNotNull('non_registre_member_id')->count();
            $steamBathCount = \DB::table('invoices')->whereNotNull('steam_bath_id')->count();
            // Prepare response data for the pie chart
            $data = [
                [
                    'name' => 'Main Package Payment',
                    'value' => $mainPackageCount
                ],
                [
                    'name' => 'Trainer Package Payment',
                    'value' => $trainerPackageCount
                ],
                [
                    'name' => 'Yearly Package Payment',
                    'value' => $yearlyPackageCount
                ],
                [
                    'name' => 'Non Register Payment',
                    'value' => $NonRegisterMemberCount
                ],
                [
                    'name' => 'Steam Bath Payment',
                    'value' => $steamBathCount
                ],
            ];

            return response()->json([
                'message' => "Data Retrived Successfully",
                'data' => $data,
                'code' => 200
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 500
            ]);
        }
    }

    public function duePaymentsSummary()
    {
        // Initialize an array for dynamic categories with default totals as 0
        $categoryData = [
            'package_payment' => ['name' => 'Package Payment', 'total' => 0],
            'pt_payment' => ['name' => 'PT Payment', 'total' => 0],
            'non_registered_member_payment' => ['name' => 'Non-Member Payment', 'total' => 0],
        ];

        // Get total due amounts from the 'payments' table grouped by category
        $paymentData = \DB::table('payments')
            ->selectRaw('SUM(due) as total')
            ->where('payment_type', 0)
            ->first();

        if ($paymentData) {
            $categoryData['package_payment']['total'] += $paymentData->total;
        }

        // Get total due amounts from the 'trainer_payments' table for PT Payments
        $trainerPaymentData = \DB::table('trainer_payments')
            ->selectRaw('SUM(due) as total')
            ->where('payment_type', 0)
            ->first();

        if ($trainerPaymentData) {
            $categoryData['pt_payment']['total'] += $trainerPaymentData->total;
        }

        // Get total due amounts from the 'non_registre_members' table for Non-Registered Member Payments
        $nonRegisteredPaymentsData = \DB::table('non_registre_members')
            ->selectRaw('SUM(due) as total') // Assuming 'payble_amount' is the field for due amounts
            ->first();

        if ($nonRegisteredPaymentsData) {
            $categoryData['non_registered_member_payment']['total'] += $nonRegisteredPaymentsData->total;
        }

        // Prepare the data for the pie chart
        $pieChartData = [];
        foreach ($categoryData as $data) {
            $pieChartData[] = [
                'name' => $data['name'],
                'total' => $data['total'],
            ];
        }

        return response()->json([
            'data' => $pieChartData,
            'code' => 200,
        ]);
    }

    public function monthlyPaymentsSummary(Request $request)
    {
        $year = (int) $request->input('year', now()->year);

        $combinedData = [];
        for ($month = 1; $month <= 12; $month++) {
            $combinedData[$month] = ['month' => $month, 'payments' => 0, 'pt' => 0, 'attendance' => 0, 'new_members' => 0, 'total' => 0];
        }

        $rows = \DB::table('payments')
            ->selectRaw('MONTH(date_of_payment) as month, SUM(paying_amount) as total')
            ->whereYear('date_of_payment', $year)->groupBy('month')->get();
        foreach ($rows as $r) { $combinedData[$r->month]['payments'] += $r->total; }

        $rows = \DB::table('trainer_payments')
            ->selectRaw('MONTH(date_of_payment) as month, SUM(paying_amount) as total')
            ->whereYear('date_of_payment', $year)->groupBy('month')->get();
        foreach ($rows as $r) { $combinedData[$r->month]['pt'] += $r->total; }

        $rows = \DB::table('yearly_packages')
            ->where('included_in_main_payment', false)
            ->selectRaw('MONTH(created_at) as month, SUM(package_amount) as total')
            ->whereYear('created_at', $year)->groupBy('month')->get();
        foreach ($rows as $r) { $combinedData[$r->month]['total'] += $r->total; }

        $rows = \DB::table('non_registre_members')
            ->selectRaw('MONTH(created_at) as month, SUM(payble_amount) as total')
            ->whereYear('created_at', $year)->groupBy('month')->get();
        foreach ($rows as $r) { $combinedData[$r->month]['total'] += $r->total; }

        $rows = \DB::table('invoices')
            ->whereNotNull('steam_bath_id')
            ->selectRaw('MONTH(COALESCE(steam_bath_payment_date, created_at)) as month, SUM(steam_bath_amount) as total')
            ->whereRaw('YEAR(COALESCE(steam_bath_payment_date, created_at)) = ?', [$year])
            ->groupBy('month')->get();
        foreach ($rows as $r) { if (!empty($r->month)) $combinedData[(int)$r->month]['total'] += (float)$r->total; }

        // Attendance per month
        $rows = \DB::table('attendances')
            ->selectRaw('MONTH(date) as month, COUNT(*) as total')
            ->whereYear('date', $year)->groupBy('month')->get();
        foreach ($rows as $r) { $combinedData[$r->month]['attendance'] += $r->total; }

        // New members per month
        $rows = \DB::table('members')
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->whereYear('created_at', $year)->groupBy('month')->get();
        foreach ($rows as $r) { $combinedData[$r->month]['new_members'] += $r->total; }

        $chartData = [];
        foreach ($combinedData as $data) {
            $chartData[] = [
                'month'       => date('F', mktime(0, 0, 0, $data['month'], 10)),
                'month_no'    => $data['month'],
                'payments'    => $data['payments'] + $data['pt'] + $data['total'],
                'attendance'  => $data['attendance'],
                'new_members' => $data['new_members'],
            ];
        }

        return response()->json([
            'year' => $year,
            'data' => $chartData,
            'code' => 200,
        ]);
    }

    public function getActivePTPayments(Request $request)
    {
        try {
            // Pagination and search variables from the request
            $perPage = $request->input('per_page', 10); // Default to 10 items per page
            $searchText = $request->input('search_text', '');

            // Current date for filtering
            $currentDate = now(); // Get the current date and time

            // Base query to get latest active trainer payments
            $subQuery = DB::table('trainer_payments')
                ->select('member_id', DB::raw('MAX(end_date) as max_end_date'))
                ->where('end_date', '>=', $currentDate)
                ->groupBy('member_id');

            // Main query to get member details along with latest payment info
            $baseQuery = DB::table('trainer_payments')
                ->join('members', 'trainer_payments.member_id', '=', 'members.id')
                ->leftJoin('employees', 'trainer_payments.employee_id', '=', 'employees.id')
                ->joinSub($subQuery, 'latest_payments', function ($join) {
                    $join->on('trainer_payments.member_id', '=', 'latest_payments.member_id')
                        ->on('trainer_payments.end_date', '=', 'latest_payments.max_end_date');
                })
                ->select(
                    'members.name',
                    'members.membership_number',
                    'members.phone',
                    'members.image',
                    'members.email',
                    'employees.name as trainer_name',
                    'trainer_payments.end_date as expire_date',
                    'trainer_payments.payment_type'
                );

            // Add search functionality
            if (!empty($searchText)) {
                $baseQuery->where(function ($q) use ($searchText) {
                    $q->where('members.membership_number', 'LIKE', "%{$searchText}%")
                        ->orWhere('members.phone', 'LIKE', "%{$searchText}%")
                        ->orWhere('members.name', 'LIKE', "%{$searchText}%")
                        ->orWhere('members.email', 'LIKE', "%{$searchText}%");
                });
            }

            // Filter for active payments (payment_type = 0)
            $query = $baseQuery->where('trainer_payments.payment_type', '=', 0)->paginate($perPage);

            // Return the data, total count, and code
            return response()->json([
                'data' => $query->items(), // Return paginated data items
                'total' => $query->total(), // Return total count of items from pagination
                'code' => 200
            ], 200);
        } catch (\Exception $e) {
            // Handle any errors and return a 500 response
            return response()->json([
                'data' => [],
                'total' => 0,
                'code' => 500,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTotalDueWithPagination(Request $request)
    {
        try {
            // Pagination and search variables from the request body
            $perPage = $request->input('per_page', 10); // Default to 10 items per page
            $searchText = $request->input('search_text', '');

            // Base query to get member dues from payments
            $baseQuery = DB::table('members')
                ->leftJoin('payments', 'members.id', '=', 'payments.member_id')
                ->leftJoin('trainer_payments', 'members.id', '=', 'trainer_payments.member_id')
                ->select(
                    'members.id as member_id',
                    'members.membership_number',
                    'members.phone',
                    'members.name', // Include member's name
                    DB::raw('IFNULL(SUM(payments.due), 0) AS total_due_payments'),
                    DB::raw('IFNULL(SUM(trainer_payments.due), 0) AS total_due_trainer_payments'),
                    DB::raw('IFNULL(SUM(payments.due), 0) + IFNULL(SUM(trainer_payments.due), 0) AS total_due')
                )
                ->groupBy('members.id', 'members.membership_number', 'members.phone', 'members.name')
                ->having('total_due', '>', 0); // Only include members with total_due greater than zero

            // Add search functionality
            if (!empty($searchText)) {
                $baseQuery->where(function ($q) use ($searchText) {
                    $q->where('members.membership_number', 'LIKE', "%{$searchText}%")
                        ->orWhere('members.phone', 'LIKE', "%{$searchText}%")
                        ->orWhere('members.name', 'LIKE', "%{$searchText}%"); // Optional: Search by member's name
                });
            }

            // Execute the query and paginate results
            $query = $baseQuery->paginate($perPage);

            // Check if data is found
            if ($query->isEmpty()) {
                return response()->json([
                    'data' => [], // Return an empty array if no data found
                    'total' => 0, // Total count as 0
                    'code' => 200
                ], 200);
            }

            // Return the data, total count, and code
            return response()->json([
                'data' => $query->items(), // Return paginated data items
                'total' => $query->total(), // Return total count of items from pagination
                'code' => 200
            ], 200);
        } catch (\Exception $e) {
            // Handle any errors and return a 500 response
            return response()->json([
                'data' => [], // Return an empty array on error
                'total' => 0, // Total count as 0 on error
                'code' => 500,
                'error' => $e->getMessage(), // Optional: Include the error message
            ], 500);
        }
    }

    public function getUpcomingMembershipExpiry(Request $request)
    {
        try {
            // Get the current date (today's date)
            $today = Carbon::now()->format('Y-m-d');

            // Pagination and search variables from the request body
            $perPage = $request->input('per_page', 10); // Default to 10 items per page
            $searchText = $request->input('search_text', '');

            // Base query for counting total memberships
            $baseQuery = \DB::table('members')
                ->join('payments', 'members.id', '=', 'payments.member_id')
                ->where('payments.end_date', '>', $today) // Upcoming expiry dates only
                ->where(function ($q) use ($searchText) {
                    if (!empty($searchText)) {
                        // Search by membership_number or phone
                        $q->where('members.membership_number', 'LIKE', "%{$searchText}%")
                            ->orWhere('members.phone', 'LIKE', "%{$searchText}%");
                    }
                });

            // Use the base query to group by membership_number and select the max expire_date
            $query = $baseQuery
                ->select(
                    'members.membership_number',
                    'members.phone',
                    'members.image',
                    'members.name', // Include the member's name
                    \DB::raw('MAX(payments.end_date) as expire_date')
                )
                ->groupBy('members.membership_number', 'members.phone', 'members.image', 'members.name') // Group by name as well
                ->orderBy('expire_date', 'asc') // Sort by expire_date (ascending)
                ->paginate($perPage); // Paginate results

            // Check if data is found
            if ($query->isEmpty()) {
                return response()->json([
                    'data' => [], // Return an empty array if no data found
                    'total' => 0, // Total count as 0
                    'code' => 200
                ], 200);
            }

            // Return the data, total count, and code
            return response()->json([
                'data' => $query->items(), // Return paginated data items
                'total' => $query->total(), // Return total count of items from pagination
                'code' => 200
            ], 200);
        } catch (\Exception $e) {
            // Handle any errors and return a 500 response
            return response()->json([
                'data' => [], // Return an empty array on error
                'total' => 0, // Total count as 0 on error
                'code' => 500
            ], 500);
        }
    }

    public function getPaymentSummary(Request $request)
    {
        try {
            // Get current date and optional start date for the range
            $currentDate = Carbon::now()->format('Y-m-d');
            $startDate = $request->input('start_date'); // Optional: Pass a start date if needed
    
            // Set default limit and offset for pagination
            $limit = $request->input('limit', 10); // Default limit of 10
            $offset = $request->input('offset', 0); // Default offset of 0
    
            // Get search text if provided
            $searchText = $request->input('search');
    
            // Base queries for each table
            $basePaymentsQuery = Payment::select('mode_of_payment')
                ->selectRaw('SUM(paying_amount) as total_payment')
                ->groupBy('mode_of_payment');
    
            $baseTrainerPaymentsQuery = TrainerPayment::select('mode_of_payment')
                ->selectRaw('SUM(paying_amount) as total_payment')
                ->groupBy('mode_of_payment');
    
            $baseYearlyPackagesQuery = YearlyPackage::select('payment_mode as mode_of_payment')
                ->selectRaw('SUM(package_amount) as total_payment')
                ->groupBy('payment_mode');
    
            // Apply date range
            if ($startDate) {
                $basePaymentsQuery->whereBetween('date_of_payment', [$startDate, $currentDate]);
                $baseTrainerPaymentsQuery->whereBetween('date_of_payment', [$startDate, $currentDate]);
                $baseYearlyPackagesQuery->whereBetween('start_date', [$startDate, $currentDate]);
            } else {
                $basePaymentsQuery->whereDate('date_of_payment', '<=', $currentDate);
                $baseTrainerPaymentsQuery->whereDate('date_of_payment', '<=', $currentDate);
                $baseYearlyPackagesQuery->whereDate('start_date', '<=', $currentDate);
            }
    
            // Apply search filter
            if ($searchText) {
                $basePaymentsQuery->where(function ($query) use ($searchText) {
                    $query->where('member_id', 'like', "%$searchText%")
                        ->orWhere('bill_no', 'like', "%$searchText%")
                        ->orWhere('package_id', 'like', "%$searchText%");
                });
    
                $baseTrainerPaymentsQuery->where(function ($query) use ($searchText) {
                    $query->where('member_id', 'like', "%$searchText%")
                        ->orWhere('trainer_package_id', 'like', "%$searchText%");
                });
    
                $baseYearlyPackagesQuery->where(function ($query) use ($searchText) {
                    $query->where('member_id', 'like', "%$searchText%");
                });
            }
    
            // Clone queries for total record count
            $totalRecordsPayments = clone $basePaymentsQuery;
            $totalRecordsTrainerPayments = clone $baseTrainerPaymentsQuery;
            $totalRecordsYearlyPackages = clone $baseYearlyPackagesQuery;
    
            // Get total record count
            $totalRecords = $totalRecordsPayments->count() + $totalRecordsTrainerPayments->count() + $totalRecordsYearlyPackages->count();
    
            // Apply limit and offset
            $payments = $basePaymentsQuery->limit($limit)->offset($offset)->get();
            $trainerPayments = $baseTrainerPaymentsQuery->limit($limit)->offset($offset)->get();
            $yearlyPackages = $baseYearlyPackagesQuery->limit($limit)->offset($offset)->get();
    
            // Combine results
            $allPayments = collect($payments)
                ->merge($trainerPayments)
                ->merge($yearlyPackages);
    
            // Calculate totals
            $cashPayment = $allPayments->where('mode_of_payment', 'cash')->sum('total_payment');
            $onlinePayment = $allPayments->where('mode_of_payment', 'online')->sum('total_payment');
            $totalPayment = $allPayments->sum('total_payment');
    
            // Return success response
            return response()->json([
                'status' => 200,
                'message' => 'Payment summary fetched successfully.',
                'data' => [
                    'cashPayment' => $cashPayment,
                    'onlinePayment' => $onlinePayment,
                    'totalPayment' => $totalPayment,
                    'totalRecords' => $totalRecords,
                ],
            ], 200);
        } catch (\Exception $e) {
            // Return error response
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while fetching the payment summary.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
}
