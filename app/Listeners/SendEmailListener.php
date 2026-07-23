<?php

namespace App\Listeners;

use App\Events\SendEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailListener implements ShouldQueue
{
    public function handle(SendEmailNotification $event): void
    {
        try {
            Mail::to($event->toEmail)->send($event->mailable);
        } catch (\Throwable $e) {
            Log::warning('Email send failed', [
                'to'    => $event->toEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
