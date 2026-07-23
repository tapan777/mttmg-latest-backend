<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendSmsNotification
{
    use Dispatchable, SerializesModels;

    public $phone;
    public $message;
    public $templateId;  // Add templateId property

    /**
     * Create a new event instance.
     *
     * @param string $phone_no
     * @param string $message
     * @param string $templateId
     */
    public function __construct($phone, $message, $templateId)
    {
        $this->phone = $phone;
        $this->message = $message;
        $this->templateId = $templateId;  // Assign templateId
    }
}
