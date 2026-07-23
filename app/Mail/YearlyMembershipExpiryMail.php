<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class YearlyMembershipExpiryMail extends Mailable
{
    public function __construct(
        public string $memberName,
        public string $expiryDate,
        public bool $alreadyExpired,
        public int $daysRemaining,
    ) {}

    public function build(): static
    {
        $subject = $this->alreadyExpired
            ? 'Your Yearly Membership Has Expired — MTTM GYM'
            : "Your Yearly Membership Expires in {$this->daysRemaining} Day(s) — MTTM GYM";

        return $this->subject($subject)
            ->view('emails.yearly_membership_expiry');
    }
}
