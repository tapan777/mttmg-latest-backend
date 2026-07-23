<?php

namespace App\Listeners;

use App\Events\BulkEmailRequested;
use App\Jobs\SendBulkEmailJob;

class QueueBulkEmailListener
{
    public function handle(BulkEmailRequested $event): void
    {
        SendBulkEmailJob::dispatch(
            $event->subject,
            $event->body,
            $event->memberIds,
            $event->requestedByUserId,
        )->afterResponse();
    }
}
