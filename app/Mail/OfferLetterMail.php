<?php

namespace App\Mail;

use App\Models\Employee;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OfferLetterMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Employee $employee) {}

    public function build(): static
    {
        $pdf = Pdf::loadView('pdf.offer_letter', ['employee' => $this->employee]);

        return $this->subject('Offer Letter — MTTM GYM')
            ->view('emails.offer_letter')
            ->attachData($pdf->output(), 'OfferLetter_' . str_replace(' ', '_', $this->employee->name) . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
