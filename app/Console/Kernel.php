<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:send-package-expiry-notifications')->everyMinute();
        $schedule->command('app:sync-device-membership')->everyMinute();
        $schedule->command('app:sync-device-yearly-membership')->everyMinute();
        $schedule->command('app:auto-checkout-employees')->everyMinute();
        $schedule->command('app:deactivate-expired-members')->everyMinute();
        $schedule->command('app:activate-members-with-active-package')->everyMinute();
        // expire_membership: send on day 0, day 3, day 7 after expiry (exact match, no repeats)
        $schedule->command('app:send-expiry-reminders --days=0')->dailyAt('09:00');
        $schedule->command('app:send-expiry-reminders --days=-3')->dailyAt('09:02');
        $schedule->command('app:send-expiry-reminders --days=-7')->dailyAt('09:04');
        // upcoming expiry reminders
        $schedule->command('app:send-expiry-reminders --days=1')->dailyAt('09:10');
        $schedule->command('app:send-expiry-reminders --days=3')->dailyAt('09:15');
        // due payment reminders
        $schedule->command('app:send-due-reminders')->dailyAt('10:00');
        // yearly membership expiry reminders (soft — never blocks payments)
        $schedule->command('app:send-yearly-expiry-reminders --days=7')->dailyAt('09:20');
        $schedule->command('app:send-yearly-expiry-reminders --days=4')->dailyAt('09:22');
        $schedule->command('app:send-yearly-expiry-reminders --days=2')->dailyAt('09:24');
        $schedule->command('app:send-yearly-expiry-reminders --days=0')->dailyAt('09:26');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
