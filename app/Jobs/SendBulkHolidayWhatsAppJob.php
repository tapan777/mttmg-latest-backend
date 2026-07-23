<?php

namespace App\Jobs;

use App\Models\Member;
use App\Services\Text2IndiaOfficialWhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendBulkHolidayWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    /**
     * @param  list<string>  $templateParams  ordered body variables for the template
     * @param  list<int>|null  $memberIds  if null, all active members with phone
     */
    public function __construct(
        public string $templateKey,
        public array $templateParams,
        public ?array $memberIds = null,
        public ?int $requestedByUserId = null,
    ) {
    }

    public function handle(Text2IndiaOfficialWhatsAppService $whatsapp): void
    {
        $query = Member::query()
            ->where('status', 1)
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if ($this->memberIds !== null && $this->memberIds !== []) {
            $query->whereIn('id', $this->memberIds);
        }

        $sent = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(100, function ($members) use ($whatsapp, &$sent, &$skipped) {
            foreach ($members as $member) {
                $mobile = $whatsapp->normalizeIndianMobile($member->phone);
                if ($mobile === null) {
                    $skipped++;

                    continue;
                }
                try {
                    $result = $whatsapp->sendTemplate($mobile, $this->templateKey, $this->templateParams);
                    if ($result['ok']) {
                        $sent++;
                    } else {
                        Log::warning('Bulk holiday WhatsApp non-success response', [
                            'member_id' => $member->id,
                            'http_status' => $result['status'],
                            'body' => mb_substr($result['body'], 0, 300),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Bulk holiday WhatsApp exception', [
                        'member_id' => $member->id,
                        'error' => $e->getMessage(),
                    ]);
                }
                usleep(120000);
            }
        });

        Log::info('SendBulkHolidayWhatsAppJob finished', [
            'template' => $this->templateKey,
            'sent_ok_count' => $sent,
            'skipped_invalid_phone' => $skipped,
            'requested_by_user_id' => $this->requestedByUserId,
        ]);
    }
}
