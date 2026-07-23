<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PtPackageExpiredMail extends Mailable
{
    public function __construct(
        public string $memberName,
        public string $packageName,
        public string $expiryDate,
    ) {}

    public function build(): static
    {
        return $this->subject('Your PT Package Has Expired — MTTM GYM')
            ->view('emails.pt_package_expired');
    }
}
