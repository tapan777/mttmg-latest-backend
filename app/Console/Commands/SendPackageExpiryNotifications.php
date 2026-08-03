<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Member;
use App\Models\Payment;
use App\Models\TrainerPackage;
use App\Events\SendSmsNotification;

class SendPackageExpiryNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-package-expiry-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send notifications for membership and PT package expirations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        
        // Handle membership expiry notifications using the end_date from the Payment model
        $members = Member::all();
        foreach ($members as $member) {
            $payment = Payment::where('member_id', $member->id)->latest()->first();
            
            if ($payment && $payment->end_date) {
                $endDate = Carbon::parse($payment->end_date);
                
                if ($endDate->isToday()) {
                    // Static template ID for membership expiry notification
                    $templateId = '1307162736776619500';  // Static template ID
                    
                    // Define the message content
                    $message = urlencode("MAA TARA TARINI MULTI GYM: Your Membership is going to expire today, Please Renew Your Membership.");
                    
                    // Trigger the event to send SMS
                    event(new SendSmsNotification($member->phone, $message, $templateId));
                }
            }
        }
    
        // Handle PT package status and send messages for upcoming expirations
        $packages = TrainerPackage::all();
        foreach ($packages as $package) {
            $expireDate = Carbon::parse($package->expire_date);
            
            // Send SMS if the PT package is expiring in 3 days
            if ($expireDate->diffInDays($today) == 3) {
                $member = $package->member;
                
                // Static template ID for PT package expiry notification
                $templateId = '1307162736772932274';  // Static template ID
                
                // URL-encoded message for PT package expiry
                $message = urlencode("MAA TARA TARINI MULTI GYM: Your PT Membership is going to expire in 3 days, Please Renew your Membership.");
                
                // Trigger the event to send SMS
                event(new SendSmsNotification($member->phone, $message, $templateId));
            }
    
            // Update package status based on expiration date
            $status = ($expireDate <= $today) ? 0 : 1;
            $package->update(['status' => $status]);
        }
    }
}
