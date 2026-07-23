<?php

namespace App\Listeners;

use App\Events\SendWhatsAppNotification;
use App\Services\Text2IndiaOfficialWhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class SendWhatsAppListener implements ShouldQueue
{
    public function handle(SendWhatsAppNotification $event): void
    {
        $whatsapp = app(Text2IndiaOfficialWhatsAppService::class);

        foreach ($event->mobiles as $mobile) {
            $normalized = $whatsapp->normalizeIndianMobile($mobile);
            if (!$normalized) {
                continue;
            }
            try {
                $whatsapp->sendTemplate($normalized, $event->templateKey, $event->params);
            } catch (\Throwable $e) {
                Log::warning('WhatsApp send failed', [
                    'template' => $event->templateKey,
                    'mobile'   => $normalized,
                    'error'    => $e->getMessage(),
                ]);
            }
        }
    }
}
