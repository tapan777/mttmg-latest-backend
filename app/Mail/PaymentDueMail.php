<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PaymentDueMail extends Mailable
{
    public function __construct(
        public string $memberName,
        public string $packageName,
        public string $expiryDate,
    ) {}

    public function build(): static
    {
        return $this->subject('Membership Renewal Reminder — MTTM GYM')
            ->view('emails.payment_due');
    }
}
