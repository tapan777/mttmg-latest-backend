<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppNotification
{
    use Dispatchable, SerializesModels;

    /**
     * @param string   $templateKey  Template name on Text2India Official
     * @param array    $params       Ordered values for {{1}}, {{2}}, ...
     * @param array    $mobiles      One or more 91XXXXXXXXXX numbers
     */
    public function __construct(
        public string $templateKey,
        public array  $params,
        public array  $mobiles,
    ) {}
}
