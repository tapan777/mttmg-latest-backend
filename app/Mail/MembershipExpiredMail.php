<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class MembershipExpiredMail extends Mailable
{
    public function __construct(
        public string $memberName,
        public string $packageName,
        public string $expiryDate,
    ) {}

    public function build(): static
    {
        return $this->subject('Your Membership Has Expired — MTTM GYM')
            ->view('emails.membership_expired');
    }
}
