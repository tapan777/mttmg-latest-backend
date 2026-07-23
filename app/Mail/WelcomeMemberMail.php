<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class WelcomeMemberMail extends Mailable
{
    public function __construct(
        public string $memberName,
        public string $packageName,
        public string $payingAmount,
        public string $expiryDate,
    ) {}

    public function build(): static
    {
        return $this->subject('Welcome to MTTM GYM — Registration Successful!')
            ->view('emails.welcome_member');
    }
}
