<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Models\Member;
use App\Models\Payment;
use App\Models\TrainerPayment;
use App\Mail\DueReminderMail;
use App\Mail\PaymentDueMail;
use App\Mail\PtPackageDueMail;
use App\Mail\MembershipExpiredMail;
use App\Mail\PtPackageExpiredMail;
use App\Events\SendWhatsAppNotification;

class DueEmailController extends Controller
{
    // Tab1 — Due Users (outstanding balance)
    public function sendDueReminder(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);

        $member = Member::find($request->member_id);
        if (!$member || !$member->email) {
            return response()->json(['message' => 'Member not found or has no email', 'code' => 500]);
        }

        $payment = Payment::where('member_id', $member->id)
            ->where('due', '>', 0)
            ->with('packages')
            ->latest()
            ->first();

        $packageName = $payment && $payment->packages ? $payment->packages->name : 'N/A';
        $dueAmount   = $payment ? $payment->due : '0';
        $expiryDate  = $payment ? date('d-m-Y', strtotime($payment->end_date)) : 'N/A';

        try {
            Mail::to($member->email)->send(new DueReminderMail(
                $member->name,
                (string) $dueAmount,
                $packageName,
                $expiryDate,
            ));
            return response()->json(['message' => 'Due reminder email sent to ' . $member->email, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send email: ' . $e->getMessage(), 'code' => 500]);
        }
    }

    // Tab2 — Payment Due (membership expiring soon)
    public function sendPaymentDue(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);

        $member = Member::find($request->member_id);
        if (!$member || !$member->email) {
            return response()->json(['message' => 'Member not found or has no email', 'code' => 500]);
        }

        $payment = Payment::where('member_id', $member->id)
            ->with('packages')
            ->latest()
            ->first();

        $packageName = $payment && $payment->packages ? $payment->packages->name : 'N/A';
        $expiryDate  = $payment ? date('d-m-Y', strtotime($payment->end_date)) : 'N/A';

        try {
            Mail::to($member->email)->send(new PaymentDueMail(
                $member->name,
                $packageName,
                $expiryDate,
            ));
            return response()->json(['message' => 'Payment due email sent to ' . $member->email, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send email: ' . $e->getMessage(), 'code' => 500]);
        }
    }

    // Tab3 — PT Package Due (PT package expiring soon)
    public function sendPtPackageDue(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);

        $member = Member::find($request->member_id);
        if (!$member || !$member->email) {
            return response()->json(['message' => 'Member not found or has no email', 'code' => 500]);
        }

        $payment = TrainerPayment::where('member_id', $member->id)
            ->with('trainer_packages')
            ->latest()
            ->first();

        $packageName = $payment && $payment->trainer_packages ? $payment->trainer_packages->name : 'N/A';
        $expiryDate  = $payment ? date('d-m-Y', strtotime($payment->end_date)) : 'N/A';

        try {
            Mail::to($member->email)->send(new PtPackageDueMail(
                $member->name,
                $packageName,
                $expiryDate,
            ));
            return response()->json(['message' => 'PT package due email sent to ' . $member->email, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send email: ' . $e->getMessage(), 'code' => 500]);
        }
    }

    // Tab4 — Membership Expired
    public function sendMembershipExpired(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);

        $member = Member::find($request->member_id);
        if (!$member || !$member->email) {
            return response()->json(['message' => 'Member not found or has no email', 'code' => 500]);
        }

        $payment = Payment::where('member_id', $member->id)
            ->with('packages')
            ->latest()
            ->first();

        $packageName = $payment && $payment->packages ? $payment->packages->name : 'N/A';
        $expiryDate  = $payment ? date('d-m-Y', strtotime($payment->end_date)) : 'N/A';

        try {
            Mail::to($member->email)->send(new MembershipExpiredMail(
                $member->name,
                $packageName,
                $expiryDate,
            ));
            return response()->json(['message' => 'Membership expired email sent to ' . $member->email, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send email: ' . $e->getMessage(), 'code' => 500]);
        }
    }

    // Tab5 — PT Package Expired
    public function sendPtPackageExpired(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);

        $member = Member::find($request->member_id);
        if (!$member || !$member->email) {
            return response()->json(['message' => 'Member not found or has no email', 'code' => 500]);
        }

        $payment = TrainerPayment::where('member_id', $member->id)
            ->with('trainer_packages')
            ->latest()
            ->first();

        $packageName = $payment && $payment->trainer_packages ? $payment->trainer_packages->name : 'N/A';
        $expiryDate  = $payment ? date('d-m-Y', strtotime($payment->end_date)) : 'N/A';

        try {
            Mail::to($member->email)->send(new PtPackageExpiredMail(
                $member->name,
                $packageName,
                $expiryDate,
            ));
            return response()->json(['message' => 'PT package expired email sent to ' . $member->email, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send email: ' . $e->getMessage(), 'code' => 500]);
        }
    }

    private function dispatchWhatsApp(string $template, array $params, string $memberPhone): void
    {
        event(new SendWhatsAppNotification($template, $params, [$memberPhone]));
    }

    // Tab1 — Due Users WhatsApp
    public function whatsappDueReminder(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);
        $member = Member::find($request->member_id);
        if (!$member || !$member->phone) {
            return response()->json(['message' => 'Member not found or has no phone', 'code' => 500]);
        }
        $payment = Payment::where('member_id', $member->id)->where('due', '>', 0)->latest()->first();
        $dueAmount = $payment ? $payment->due : '0';
        try {
            $this->dispatchWhatsApp('due_reminder_1', [$member->name, $dueAmount], $member->phone);
            return response()->json(['message' => 'WhatsApp due reminder sent to ' . $member->phone, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage(), 'code' => 500]);
        }
    }

    // Tab2 — Payment Due WhatsApp
    public function whatsappPaymentDue(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);
        $member = Member::find($request->member_id);
        if (!$member || !$member->phone) {
            return response()->json(['message' => 'Member not found or has no phone', 'code' => 500]);
        }
        $payment = Payment::where('member_id', $member->id)->with('packages')->latest()->first();
        $packageName = $payment && $payment->packages ? $payment->packages->name : 'N/A';
        $expiryDate  = $payment ? date('d-m-Y', strtotime($payment->end_date)) : 'N/A';
        try {
            $this->dispatchWhatsApp('renewal_reminder', [$member->name, $packageName, $expiryDate], $member->phone);
            return response()->json(['message' => 'WhatsApp renewal reminder sent to ' . $member->phone, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage(), 'code' => 500]);
        }
    }

    // Tab3 — PT Package Due WhatsApp
    public function whatsappPtPackageDue(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);
        $member = Member::find($request->member_id);
        if (!$member || !$member->phone) {
            return response()->json(['message' => 'Member not found or has no phone', 'code' => 500]);
        }
        $payment = TrainerPayment::where('member_id', $member->id)->with('trainer_packages')->latest()->first();
        $expiryDate = $payment ? date('d-m-Y', strtotime($payment->end_date)) : 'N/A';
        // pt_package_expire_reminder: Member Name, Trainer Name, Expiry Date
        $trainerName = $payment && $payment->trainer_packages ? $payment->trainer_packages->name : 'Your Trainer';
        try {
            $this->dispatchWhatsApp('pt_package_expire_reminder', [$member->name, $trainerName, $expiryDate], $member->phone);
            return response()->json(['message' => 'WhatsApp PT package due sent to ' . $member->phone, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage(), 'code' => 500]);
        }
    }

    // Tab4 — Membership Expired WhatsApp
    public function whatsappMembershipExpired(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);
        $member = Member::find($request->member_id);
        if (!$member || !$member->phone) {
            return response()->json(['message' => 'Member not found or has no phone', 'code' => 500]);
        }
        $payment = Payment::where('member_id', $member->id)->with('packages')->latest()->first();
        $packageName = $payment && $payment->packages ? $payment->packages->name : 'N/A';
        $expiryDate  = $payment ? date('d-m-Y', strtotime($payment->end_date)) : 'N/A';
        try {
            $this->dispatchWhatsApp('expire_membership', [$member->name, $packageName, $expiryDate], $member->phone);
            return response()->json(['message' => 'WhatsApp membership expired sent to ' . $member->phone, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage(), 'code' => 500]);
        }
    }

    // Tab5 — PT Package Expired WhatsApp
    public function whatsappPtPackageExpired(Request $request)
    {
        $request->validate(['member_id' => 'required|integer']);
        $member = Member::find($request->member_id);
        if (!$member || !$member->phone) {
            return response()->json(['message' => 'Member not found or has no phone', 'code' => 500]);
        }
        $payment = TrainerPayment::where('member_id', $member->id)->with('trainer_packages')->latest()->first();
        $expiryDate  = $payment ? date('d-m-Y', strtotime($payment->end_date)) : 'N/A';
        $trainerName = $payment && $payment->trainer_packages ? $payment->trainer_packages->name : 'Your Trainer';
        try {
            $this->dispatchWhatsApp('pt_package_expire_reminder', [$member->name, $trainerName, $expiryDate], $member->phone);
            return response()->json(['message' => 'WhatsApp PT package expired sent to ' . $member->phone, 'code' => 200]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage(), 'code' => 500]);
        }
    }
}
