<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class PasswordResetMail extends Mailable
{
    public function __construct(
        public string $userName,
        public string $resetLink,
    ) {}

    public function build(): static
    {
        return $this->subject('Password Reset Request — MTTM GYM')
            ->view('emails.password_reset');
    }
}
