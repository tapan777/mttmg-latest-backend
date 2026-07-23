<?php

namespace App\Jobs;

use App\Mail\BulkMail;
use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendBulkEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public function __construct(
        public string $subject,
        public string $body,
        public ?array $memberIds = null,
        public ?int   $requestedByUserId = null,
    ) {}

    public function handle(): void
    {
        $query = Member::query()
            ->where('status', 1)
            ->whereNotNull('email')
            ->where('email', '!=', '');

        if ($this->memberIds !== null && $this->memberIds !== []) {
            $query->whereIn('id', $this->memberIds);
        }

        $sent    = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(100, function ($members) use (&$sent, &$skipped) {
            foreach ($members as $member) {
                try {
                    Mail::to($member->email)->send(
                        new BulkMail($this->subject, $this->body, $member->name)
                    );
                    $sent++;
                } catch (\Throwable $e) {
                    Log::error('Bulk email send failed', [
                        'member_id' => $member->id,
                        'email'     => $member->email,
                        'error'     => $e->getMessage(),
                    ]);
                    $skipped++;
                }
                usleep(200000); // 0.2s delay between emails
            }
        });

        Log::info('SendBulkEmailJob finished', [
            'subject'              => $this->subject,
            'sent'                 => $sent,
            'skipped'              => $skipped,
            'requested_by_user_id' => $this->requestedByUserId,
        ]);
    }
}
