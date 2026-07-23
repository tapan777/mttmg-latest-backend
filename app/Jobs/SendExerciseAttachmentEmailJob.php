<?php

namespace App\Jobs;

use App\Mail\ExerciseListMail;
use App\Models\Member;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendExerciseAttachmentEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public array   $memberIds,
        public string  $exerciseName,
        public ?string $tempFilePath = null,
        public ?string $originalFileName = null,
    ) {}

    public function handle(): void
    {
        $members = Member::whereIn('id', $this->memberIds)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'name', 'email']);

        foreach ($members as $member) {
            try {
                Mail::to($member->email)->send(
                    new ExerciseListMail(
                        $member->name,
                        $this->exerciseName,
                        $this->tempFilePath,
                        $this->originalFileName,
                    )
                );
            } catch (\Throwable $e) {
                Log::error('Exercise attachment email failed', [
                    'member_id' => $member->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        // Delete temp file after all emails sent
        if ($this->tempFilePath && file_exists($this->tempFilePath)) {
            @unlink($this->tempFilePath);
        }
    }
}
