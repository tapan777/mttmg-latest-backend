<?php

namespace App\Listeners;

use App\Events\SendSmsNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

class SendSmsListener
{
    public function handle(SendSmsNotification $event)
    {
        $apiKey = "at73pzTDNrnkyMeo";
        $senderId = "MTTGYM";
    
        // URL for the SMS API (you can still keep this in the .env if needed)
        $url = "http://text2india.store/vb/apikey.php";
        
        // Get the message and phone number from the event
        $message = urldecode($event->message);
        $mobileNumber = $event->phone;
    
        // Use the template ID passed from the event
        $templateId = $event->templateId;

        // Send the SMS request to the API
        $response = Http::get("$url?apikey=$apiKey&senderid=$senderId&templateid=$templateId&number=$mobileNumber&message=$message");

        // Check if the response is successful
        if ($response->successful()) {
            // Logic to deduct SMS credits from your database
            $this->deductSmsCredits();
        } else {
            // Handle failed SMS request (optional)
            \Log::error("SMS sending failed: " . $response->body());
        }
    }

    private function deductSmsCredits()
    {
        // Deduct SMS credits from your database
        $smsStatus = \DB::table('sms_status')->first();
        $latestValue = $smsStatus->amount;
        $valSms = $latestValue - 1;

        if ($latestValue > 0) {
            \DB::table('sms_status')->update(['amount' => $valSms]);
        }
    }
}
