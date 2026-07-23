<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkHolidayWhatsAppRequested
{
    use Dispatchable, SerializesModels;

    /**
     * @param  list<string>  $templateParams
     * @param  list<int>|null  $memberIds
     */
    public function __construct(
        public string $templateKey,
        public array $templateParams,
        public ?array $memberIds,
        public ?int $requestedByUserId,
    ) {
    }
}
