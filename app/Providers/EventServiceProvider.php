<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\BulkEmailRequested;
use App\Events\BulkHolidayWhatsAppRequested;
use App\Events\SendEmailNotification;
use App\Events\SendSmsNotification;
use App\Events\SendWhatsAppNotification;
use App\Listeners\QueueBulkEmailListener;
use App\Listeners\QueueBulkHolidayWhatsAppListener;
use App\Listeners\SendEmailListener;
use App\Listeners\SendSmsListener;
use App\Listeners\SendWhatsAppListener;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        SendSmsNotification::class => [
            SendSmsListener::class,
        ],
        BulkHolidayWhatsAppRequested::class => [
            QueueBulkHolidayWhatsAppListener::class,
        ],
        BulkEmailRequested::class => [
            QueueBulkEmailListener::class,
        ],
        SendWhatsAppNotification::class => [
            SendWhatsAppListener::class,
        ],
        SendEmailNotification::class => [
            SendEmailListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
