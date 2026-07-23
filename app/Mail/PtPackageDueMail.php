<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PtPackageDueMail extends Mailable
{
    public function __construct(
        public string $memberName,
        public string $packageName,
        public string $expiryDate,
    ) {}

    public function build(): static
    {
        return $this->subject('PT Package Expiry Reminder — MTTM GYM')
            ->view('emails.pt_package_due');
    }
}
