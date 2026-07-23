<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DueReminderMail extends Mailable
{
    public function __construct(
        public string $memberName,
        public string $dueAmount,
        public string $packageName,
        public string $expiryDate,
    ) {}

    public function build(): static
    {
        return $this->subject('Payment Due Reminder — MTTM GYM')
            ->view('emails.due_reminder');
    }
}
