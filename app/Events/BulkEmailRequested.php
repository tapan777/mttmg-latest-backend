<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BulkEmailRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string  $subject,
        public string  $body,
        public ?array  $memberIds,
        public ?int    $requestedByUserId,
    ) {}
}
