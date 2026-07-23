<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class ExerciseListMail extends Mailable
{
    public function __construct(
        public string  $memberName,
        public string  $exerciseName,
        public ?string $attachmentPath = null,
        public ?string $attachmentName = null,
    ) {}

    public function build(): static
    {
        $mail = $this->subject('Your Exercise Plan — MTTM GYM')
            ->view('emails.exercise_list');

        if ($this->attachmentPath && file_exists($this->attachmentPath)) {
            $mail->attach($this->attachmentPath, [
                'as'   => $this->attachmentName ?? 'ExercisePlan.pdf',
                'mime' => mime_content_type($this->attachmentPath),
            ]);
        }

        return $mail;
    }
}
