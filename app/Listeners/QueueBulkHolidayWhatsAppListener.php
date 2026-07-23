<?php

namespace App\Listeners;

use App\Events\BulkHolidayWhatsAppRequested;
use App\Jobs\SendBulkHolidayWhatsAppJob;

/**
 * Runs synchronously when the event fires; schedules the heavy job to run after the HTTP response is sent.
 */
class QueueBulkHolidayWhatsAppListener
{
    public function handle(BulkHolidayWhatsAppRequested $event): void
    {
        SendBulkHolidayWhatsAppJob::dispatch(
            $event->templateKey,
            $event->templateParams,
            $event->memberIds,
            $event->requestedByUserId,
        )->afterResponse();
    }
}
