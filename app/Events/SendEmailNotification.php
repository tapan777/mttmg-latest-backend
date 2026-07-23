<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendEmailNotification
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Mailable $mailable,
        public string   $toEmail,
    ) {}
}
