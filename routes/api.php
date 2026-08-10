<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminLoginController;
use App\Http\Controllers\AdmissionValueController;
use App\Http\Controllers\AllPaymentController;
use App\Http\Controllers\AdmsController;
use App\Http\Controllers\BalanceSheetController;
use App\Http\Controllers\BiomatricController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\FollowupController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\NonRegistreMemberController;
use App\Http\Controllers\OfferPackageController;
use App\Http\Controllers\PackagesController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TrainerPackageController;
use App\Http\Controllers\TrainerPaymentController;
use App\Http\Controllers\YearlyPackageController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\TrainerController;
use App\Http\Controllers\AdmissionChargesController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\SteamBathController;
use App\Http\Controllers\SalaryController;
use App\Http\Controllers\DietPlanController;
use App\Models\SteamBath;
use App\Http\Controllers\DietUserAssignmentController;
use App\Http\Controllers\ExerciseController;
use App\Http\Controllers\ExerciseUserAssignmentController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\OperationLogController;
use App\Http\Controllers\BiometricBridgeController;
use App\Http\Controllers\BulkEmailController;
use App\Http\Controllers\BulkHolidayWhatsAppController;
use App\Http\Controllers\WhatsappTemplateController;
use App\Http\Controllers\EmployeePunchLogController;
use App\Http\Controllers\ZKTecoController;
use App\Http\Controllers\DueEmailController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:passport')->get('/user', function (Request $request) {
//     return $request->user();
// });
Route::middleware('auth.token', 'operation.log', 'throttle:60,1')->group(function () {


    Route::post('salary/generate', [SalaryController::class, 'generateSalary']);
    //User Api
    Route::post("update-user", [AdminLoginController::class, 'userUpdate']); //Update User
    Route::post("verify-balance-password", [AdminLoginController::class, 'verifyBalancePassword']); //Verify Balance Sheet Page Password
    Route::post("retrive-user", [AdminLoginController::class, 'retriveUser']); //Retrive User 
    Route::post("delete-user", [AdminLoginController::class, 'deleteUser']); //Delete User  
    Route::post("auto-complete-user", [AdminLoginController::class, 'userAutoComplete']); //Auto Complete User
    Route::post("user-status", [AdminLoginController::class, 'activeInactive']); //Auto Complete User 

    //Member Api
    Route::get('/members', [MemberController::class, 'getAllMembers']);
    Route::post("create-member", [MemberController::class, 'createMember']); //create member
    Route::post("retrive-member", [MemberController::class, 'retriveMembers']); //retrive member
    Route::post("update-member", [MemberController::class, 'updateMembers']); //upate member
    Route::post("autocomplete-member", [MemberController::class, 'autoCompleteMember']); //auto complete member
    Route::post("member-status", [MemberController::class, 'activeInactive']); //auto complete member
    Route::post("delete-member", [MemberController::class, 'deleteMember']); //auto complete member
    Route::post('bulk-holiday-whatsapp', [BulkHolidayWhatsAppController::class, 'send']);
    Route::post('bulk-email', [BulkEmailController::class, 'send']);

    // Employee Punch Log
    Route::post('employee-punch-log', [EmployeePunchLogController::class, 'getByEmployee']);
    Route::post('employee-punch-log/by-date', [EmployeePunchLogController::class, 'getByDate']);

    // WhatsApp Template Management
    Route::get('whatsapp-templates', [WhatsappTemplateController::class, 'index']);
    Route::post('whatsapp-templates', [WhatsappTemplateController::class, 'store']);
    Route::post('whatsapp-templates/show', [WhatsappTemplateController::class, 'show']);
    Route::post('whatsapp-templates/update', [WhatsappTemplateController::class, 'update']);
    Route::post('whatsapp-templates/delete', [WhatsappTemplateController::class, 'destroy']);
    Route::post('whatsapp-templates/toggle-active', [WhatsappTemplateController::class, 'toggleActive']);

    // Due Users — trigger emails
    Route::post('due-email/due-reminder',       [DueEmailController::class, 'sendDueReminder']);
    Route::post('due-email/payment-due',        [DueEmailController::class, 'sendPaymentDue']);
    Route::post('due-email/pt-package-due',     [DueEmailController::class, 'sendPtPackageDue']);
    Route::post('due-email/membership-expired', [DueEmailController::class, 'sendMembershipExpired']);
    Route::post('due-email/pt-package-expired', [DueEmailController::class, 'sendPtPackageExpired']);
    Route::post('due-whatsapp/due-reminder',       [DueEmailController::class, 'whatsappDueReminder']);
    Route::post('due-whatsapp/payment-due',        [DueEmailController::class, 'whatsappPaymentDue']);
    Route::post('due-whatsapp/pt-package-due',     [DueEmailController::class, 'whatsappPtPackageDue']);
    Route::post('due-whatsapp/membership-expired', [DueEmailController::class, 'whatsappMembershipExpired']);
    Route::post('due-whatsapp/pt-package-expired', [DueEmailController::class, 'whatsappPtPackageExpired']);
    Route::post("membership-number", [MemberController::class, 'membershipNumber']); //auto complete member
    Route::post("membership-pause", [MemberController::class, 'pause']); //pause member
    Route::post("membership-resume", [MemberController::class, 'resume']); //resume member
    Route::post("retrive/member-by-date", [MemberController::class, 'days_filter']); //retrive member
    Route::post("add-member-to-mechine", [MemberController::class, 'add_biomatric_user']); //retrive member

    //Member Package Api
    Route::post("create-package", [PackagesController::class, 'create_member_package']); //create package
    Route::post("retrive-package", [PackagesController::class, 'retrivePackages']); //retrive package
    Route::post("auto-complete", [PackagesController::class, 'auto_complete']); //auto complete package
    Route::post("update-package", [PackagesController::class, 'update_package']); //update package
    Route::post("delete-package", [PackagesController::class, 'delete_package']); //delete package
    Route::post("package-status", [PackagesController::class, 'active_inactive']); //delete package
    Route::post("drop-down-package", [PackagesController::class, 'drop_down']); //dropdown package    
    Route::post("package-value", [PackagesController::class, 'package_value']); //dropdown package_value
    Route::post("add-yearly-package", [YearlyPackageController::class, 'cr_yr_membership']); //yearly_package_value
    Route::post("add-yearly-package-if-expired", [YearlyPackageController::class, 'add_yearly_package_if_expired']); // yearly only when expired
    Route::post("get-date", [YearlyPackageController::class, 'date_api']); //get-end-date
    Route::post("get-yearly-packages", [YearlyPackageController::class, 'retrive_yr_package']); //get-yearly_packages
    Route::post("auto-complete-yearly-packages", [YearlyPackageController::class, 'autocomplete']); //autocomplete-yearly_packages
    Route::post("renew-package", [PaymentController::class, 'renewPackage']); //renew package
    Route::post("add/same-package", [PaymentController::class, 'repeate_package']); //renew package
    Route::post("get/package/value", [PaymentController::class, 'change_package_dropdown']); //change package dropdown
    Route::post("change-package", [PaymentController::class, 'change_package']); //renew package


    //payments Apis
    Route::post("payment/trainer-due-payment", [TrainerPaymentController::class, 'trainer_due_payment']); //List of due member
    Route::post("check-due-date", [AllPaymentController::class, 'list_payment_due']); //List of due member
    Route::post("list-due-user", [AllPaymentController::class, 'list_member_due']); //List of due member
    Route::post("payment-history", [AllPaymentController::class, 'allPaymentHistory']); //create payment of trainer package
    Route::post("payment-history/delete", [AllPaymentController::class, 'deletePaymentRecord']); //delete a single payment history record (and its linked invoice)
    Route::post("search-payments", [AllPaymentController::class, 'searchPayments']); //create payment of trainer package
    Route::post("all-payments-auto-complete", [AllPaymentController::class, 'autoComplete']); //create payment of trainer package
    Route::post("due-payment", [PaymentController::class, 'duePayment']); //Due payment
    Route::post("member-due-payment", [PaymentController::class, 'members_due']); //Member Due payment
    Route::post("expired-member", [AllPaymentController::class, 'expired_member']); //Check due by day
    Route::post("expired-yearly-membership", [AllPaymentController::class, 'expired_yearly_membership']); //Admin notification list: yearly membership expired/expiring soon
    Route::post("yearly-membership-expired", [AllPaymentController::class, 'list_yearly_membership_expired']); //Yearly membership already expired as of today (List Due Users tab)
    Route::post("payment/due-pt-package", [AllPaymentController::class, 'duePtPackage']); //Check due by day
    Route::post("payment/due-pt-package-autocomplete", [AllPaymentController::class, 'autoComplete_ptPackage_due']); //Auto complete of pt package
    Route::post("payment/due-member-autocomplete", [AllPaymentController::class, 'dueMemberAutoComplete']); //Auto complete of pt package
    Route::post("payment/due-member-by-date-auto-complete", [AllPaymentController::class, 'autoCompleteDueByDate']); //Auto complete of pt package
    Route::post("payment/expired-member-auto-complete", [AllPaymentController::class, 'autoCompleteExpiredMember']); //Auto complete of pt package
    Route::post("payment/expired-pt-package", [AllPaymentController::class, 'expiredPtPackage']); //Auto complete of pt package
    Route::post("payment/expired-pt-package-autocomplete", [AllPaymentController::class, 'autoCompleteExpiredPtPackage']); //Auto complete of pt package
    Route::post("payment/balance-sheet", [BalanceSheetController::class, 'balance_sheet_index']); //Balance Sheet
    Route::post("payment/autocomplete-balance-sheet", [BalanceSheetController::class, 'auto_complete']); //Balance Sheet

    //Trainer Package
    Route::post("create-trainer-package", [TrainerPackageController::class, 'createTrainerPackage']); //create package
    Route::post("retrive-trainer-package", [TrainerPackageController::class, 'retriveTrainerPackages']); //retrive package
    Route::post("auto-complete-trainer", [TrainerPackageController::class, 'autoComplete']); //auto complete package
    Route::post("update-trainer-package", [TrainerPackageController::class, 'updateTrainerPackage']); //update package
    Route::post("delete-trainer-package", [TrainerPackageController::class, 'deleteTrainerPackage']); //delete package
    Route::post("trainer-package-status", [TrainerPackageController::class, 'active_inactive']); //active inactive package
    Route::post("trainer-package-drop-down", [TrainerPackageController::class, 'trainer_package_drop_down']); //dropdown trainer_package
    Route::post("trainer-package-value", [TrainerPackageController::class, 'package_value']); //dropdown trainer_package_value
    Route::post("trainer-package-payment", [TrainerPaymentController::class, 'create_payment']); //create payment of trainer package
    Route::post("get-trainer/drop-down", [EmployeeController::class, 'trainer_dropdown']); //Trainer dropdown
    Route::post("get-trainer/slot-times", [EmployeeController::class, 'trainer_slot_times']); // Morning/evening slot times by trainer id
    Route::post("change-trainer/drop-down", [TrainerPaymentController::class, 'pt_change_package_dropdown']); //change package dropdown for pt
    Route::post("change-trainer/package", [TrainerPaymentController::class, 'change_trainer_package']); //change package  for pt
    Route::post("change-trainer-name", [TrainerPaymentController::class, 'change_trainer']); //change package  for pt
    Route::post("trainer-wise-pt-packages", [TrainerPaymentController::class, 'getTrainerWisePackages']);
    Route::get('/trainer/pt/list', [TrainerPaymentController::class, 'getTrainersWithPT']);


    Route::post('/create/diet-plan', [DietPlanController::class, 'store']);
    Route::post('/diet-plans', [DietPlanController::class, 'index']);
    Route::post('/diet-plan/delete', [DietPlanController::class, 'delete']);
    Route::post('/assign-member-to-diet', [DietUserAssignmentController::class, 'assignMemberToDiet']);
    Route::post('/diet-assignments', [DietUserAssignmentController::class, 'retrieve']); // which members have diet plans

    // Exercise
    Route::post('/create/exercise', [ExerciseController::class, 'store']);
    Route::post('/exercises', [ExerciseController::class, 'index']);
    Route::post('/exercise/delete', [ExerciseController::class, 'delete']);
    Route::post('/exercise-dropdown', [ExerciseController::class, 'dropdown']);
    Route::post('/assign-member-to-exercise', [ExerciseUserAssignmentController::class, 'assignMemberToExercise']);
    Route::post('/exercise-assignments', [ExerciseUserAssignmentController::class, 'retrieve']);
    Route::post('/exercise-unassign', [ExerciseUserAssignmentController::class, 'unassign']);

    //Admission Value
    Route::group([
        'prefix' => 'admission-value'
    ], function () {
        Route::post("/create", [AdmissionValueController::class, 'createadmissionValue']); //create Admission Value
        Route::post("/update", [AdmissionValueController::class, 'updateAdmissionValue']); //create Admission Value
        Route::post("/delete", [AdmissionValueController::class, 'deleteAdmissionValue']); //create Admission Value
        Route::post("/retrive", [AdmissionValueController::class, 'retriveAdmissionValue']); //create Admission Values
        Route::post("/active-inactive", [AdmissionValueController::class, 'active_inactive']); //create Admission Value
        Route::post("/dropdown", [AdmissionValueController::class, 'admission_value_drop_down']); //create Admission Value
        Route::post("/get/value", [AdmissionValueController::class, 'admission_value']); //create Admission Value
        Route::post("/autocomplete", [AdmissionValueController::class, 'autoComplete']); //create Admission Value
    });
    Route::group([
        'prefix' => 'admission-charges'
    ], function () {
        Route::post("/create", [AdmissionChargesController::class, 'createadmissionValue']); //create Admission Value
        Route::post("/update", [AdmissionChargesController::class, 'updateAdmissionValue']); //create Admission Value
        Route::post("/delete", [AdmissionChargesController::class, 'deleteAdmissionValue']); //create Admission Value
        Route::post("/retrive", [AdmissionChargesController::class, 'retriveAdmissionValue']); //create Admission Values
        Route::post("/active-inactive", [AdmissionChargesController::class, 'active_inactive']); //create Admission Value
        Route::post("/dropdown", [AdmissionChargesController::class, 'admission_value_drop_down']); //create Admission Value
        Route::post("/get/value", [AdmissionChargesController::class, 'admission_value']); //create Admission Value
        Route::post("/autocomplete", [AdmissionChargesController::class, 'autoComplete']); //create Admission Value
    });
    //Followup
    Route::group([
        'prefix' => 'follow-up'
    ], function () {
        Route::post("create", [FollowupController::class, 'create_followup']); //Create Followups
        Route::post("update", [FollowupController::class, 'update_followup']); //update Followups
        Route::post("delete", [FollowupController::class, 'delete_followup']); //delete Followups
        Route::post("retrive", [FollowupController::class, 'retrive_followups']); //retrive Followups
        Route::post("autocomplete", [FollowupController::class, 'autocomplete']); //autocomplete Followups
        Route::post("change-status", [FollowupController::class, 'change_status']); //autocomplete Followups
    });

    // Expense
    Route::group([
        'prefix' => 'expense'
    ], function () {
        Route::post("create", [ExpenseController::class, 'create_expense']);
        Route::post("update", [ExpenseController::class, 'update_expense']);
        Route::post("delete", [ExpenseController::class, 'delete_expense']);
        Route::post("retrive", [ExpenseController::class, 'retrive_expenses']);
        Route::post("autocomplete", [ExpenseController::class, 'autocomplete']);
        Route::post("change-status", [ExpenseController::class, 'change_status']);
    });

    //Trainer
    Route::group([
        'prefix' => 'trainer'
    ], function () {
        Route::post("store", [TrainerController::class, 'store_trainer']); //Create Trainers
        Route::post("update", [TrainerController::class, 'update_trainer']); //update Trainers
        Route::post("delete", [TrainerController::class, 'delete_trainer']); //delete Trainers
        Route::post("retrive", [TrainerController::class, 'retriveTrainers']); //retrive Trainers
        Route::post("autocomplete", [TrainerController::class, 'autocomplete']); //autocomplete Trainers
        Route::post("dropdown", [TrainerController::class, 'dropdown']); //dropdown Trainers
        Route::post("advance-payment", [TrainerPaymentController::class, 'advance_payment']); //Trainer Advance Payment

    });
    //invoice Apis
    Route::post("get-invoice-number", [InvoiceController::class, 'getInvoiceNumber']); //get invoice number
    Route::post("get-invoice-by-id", [InvoiceController::class, 'getInvoiceDetailsById']); //get invoice number

    //employee Apis
    Route::post("add-employee", [EmployeeController::class, 'addEmployee']); //Add Employee
    Route::post('delete-employee', [EmployeeController::class, 'deleteEmployee']); // Delete Employee
    Route::post('update-employee', [EmployeeController::class, 'updateEmployees']); // Update Employee
    Route::post('retrive-employee', [EmployeeController::class, 'retriveEmployee']); // Retrive Employee
    Route::post('search-employee', [EmployeeController::class, 'searchEmployee']); // Search Employee
    Route::post('autocomplete-employee', [EmployeeController::class, 'autoComplete']); // Search Employee

    //non-registre user and packages
    Route::post('create-offer-package', [OfferPackageController::class, 'createOfferPackage']); // Create user
    Route::post('retrive-offer-package', [OfferPackageController::class, 'retrive_offer_packages']); // Retrive OFfer package
    Route::post('non-register-due-payment', [NonRegistreMemberController::class, 'due_payment']); // Non Register due payment
    Route::post('update-offer-package', [OfferPackageController::class, 'updateOfferPackage']); // Update OFfer package
    Route::post('delete-offer-package', [OfferPackageController::class, 'deleteOfferPackage']); // Update OFfer package
    Route::post('offer-package-dropdown', [OfferPackageController::class, 'drop_down']); // Offer Package dropdown
    Route::post('offer/auto-complete-offer-package', [OfferPackageController::class, 'autoComplete_retrive_offer_packages']); // Auto Complete OFfer package
    Route::post("non-register/package-value", [OfferPackageController::class, 'package_value']); //dropdown package    

    Route::post('create-non-register-member', [NonRegistreMemberController::class, 'create_nonRegistered_member']); // Create user
    Route::post('update-non-register-member', [NonRegistreMemberController::class, 'update_nonRegistered_member']); // Update user
    Route::post('retrive-non-register-member', [NonRegistreMemberController::class, 'retriveNonRegisterMember']); // Retrive Non-Register Member
    Route::post('non-register-member-auto-complete', [NonRegistreMemberController::class, 'autoCompleteNonRegisterMember']); // Auto Complete Non-Register Member
    Route::post("non-register-membership-number", [NonRegistreMemberController::class, 'non_register_membership_number']); //non-register membership number
    Route::post('delete-non-register-member', [NonRegistreMemberController::class, 'deleteNonRegisterMember']); // Update OFfer package

    //Dashboard APis
    Route::get("all-dashboard-details", [DashboardController::class, 'dashboard_counts']);
    Route::get("dashboard-counts", [DashboardController::class, 'dashboard_counts']);
    Route::post("invoice/by/category", [DashboardController::class, 'invoiceByCategory']);
    Route::post("monthly/payment/summary", [DashboardController::class, 'monthlyPaymentsSummary']);
    Route::get("monthly-payments-summary", [DashboardController::class, 'monthlyPaymentsSummary']);
    Route::post("due/payment/summary", [DashboardController::class, 'duePaymentsSummary']); // Active Member data for dashboard
    Route::post('/memberships/upcoming-expiry', [DashboardController::class, 'getUpcomingMembershipExpiry']);
    Route::post('/members/total-due', [DashboardController::class, 'getTotalDueWithPagination']);
    Route::post('/active-pt-payments', [DashboardController::class, 'getActivePTPayments']);
    Route::post('/get/payment/summary', [DashboardController::class, 'getPaymentSummary']);
    Route::post("biomatric/totalwork-hour", [AttendanceController::class, 'getCurrentMonthWorkHours']);
    Route::post('/monthly-attendance', [AttendanceController::class, 'getMonthlyAttendance']);

    // Operation logs (audit trail)
    Route::post('operation-logs', [OperationLogController::class, 'index']);
    Route::post('operation-logs/users', [OperationLogController::class, 'usersWithLogs']);
    Route::post('operation-logs/delete-old', [OperationLogController::class, 'deleteOldLogs']);

    // Biometric device command logs (add/update/delete user pushed to device)
    Route::post('device-command-logs', [AdmsController::class, 'commandLogs']);
    Route::post('device/unlock-door', [AdmsController::class, 'unlockDoor']);

    //Biomatric

    // Route::get("get/data/from/biomatric", [BiomatricController::class, 'setUser']);

    // steam bath
    Route::post('/steam-bath/store', [SteamBathController::class, 'store_bath'])->name('store.steam.bath');
    Route::post('use/steam-bath', [SteamBathController::class, 'use_bath'])->name('use.steam.bath');
});
Route::get("biomatric/check-connection", [BiomatricController::class, 'checkConnection']);
Route::get("biomatric/authenticate", [BiomatricController::class, 'setBiomatricData']);
Route::get("biomatric/set-user-data", [BiomatricController::class, 'setUserData']);

Route::get("biomatric/get-attendance", [BiomatricController::class, 'get_attendance']);
Route::get("biomatric/store-attendance", [AttendanceController::class, 'store']);
Route::post("biomatric/retrive-attendance", [AttendanceController::class, 'index']);
Route::post("biomatric/mannual-checkout", [AttendanceController::class, 'check_out']);
Route::get("biomatric/checkout-notification", [AttendanceController::class, 'send_notification_for_checkout']);


Route::post('/biomatric/device-push', [BiomatricController::class, 'receiveFromDevice']);

// ADMS interactive command APIs
Route::get('/adms/status', [AdmsController::class, 'deviceStatus']);
Route::post('/adms/add-user', [AdmsController::class, 'addUser']);
Route::post('/adms/delete-user', [AdmsController::class, 'deleteUser']);
Route::post('/adms/clear-attendance', [AdmsController::class, 'clearAttendance']);
Route::post('/adms/reboot', [AdmsController::class, 'rebootDevice']);

// Bridge endpoints — local PC polls these to execute commands via TCP 4370
Route::get('/bridge/pending', [AdmsController::class, 'bridgePending']);
Route::post('/bridge/ack', [AdmsController::class, 'bridgeAck']);


Route::post("add-user", [AdminLoginController::class, 'addUser']);
//User Login and Register Route
Route::post('/admin-login', [AdminLoginController::class, 'admin_login']);
Route::post('/forgot-password', [PasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [PasswordController::class, 'resetPassword']);
Route::post('/set-new-password', [PasswordController::class, 'setNewPassword']);
