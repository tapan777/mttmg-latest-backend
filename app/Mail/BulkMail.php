<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class BulkMail extends Mailable
{
    public function __construct(
        public string $emailSubject,
        public string $emailBody,
        public string $memberName,
    ) {}

    public function build(): static
    {
        return $this->subject($this->emailSubject)
            ->view('emails.bulk_email');
    }
}
