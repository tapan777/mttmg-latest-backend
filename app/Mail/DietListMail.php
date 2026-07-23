<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class DietListMail extends Mailable
{
    public function __construct(
        public string  $memberName,
        public string  $dietName,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
    ) {}

    public function build(): static
    {
        $mail = $this->subject('Your Diet Plan — MTTM GYM')
            ->view('emails.diet_list');

        if ($this->attachmentPath && file_exists($this->attachmentPath)) {
            $mail->attach($this->attachmentPath, [
                'as'   => $this->attachmentName ?? 'DietPlan.pdf',
                'mime' => mime_content_type($this->attachmentPath),
            ]);
        }

        return $mail;
    }
}
