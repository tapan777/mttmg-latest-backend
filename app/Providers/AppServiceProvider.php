<?php

namespace App\Providers;

use App\Models\NonRegistreMember;
use App\Models\Payment;
use App\Models\TrainerPayment;
use App\Models\YearlyPackage;
use App\Observers\MainPackagePaymentObserver;
use App\Observers\NonRegisterMemberObserver;
use App\Observers\TrainerPackagePaymentObserver;
use App\Observers\YearlyPackagePaymentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Payment::observe(MainPackagePaymentObserver::class);
        TrainerPayment::observe(TrainerPackagePaymentObserver::class);
        YearlyPackage::observe(YearlyPackagePaymentObserver::class);
        NonRegistreMember::observe(NonRegisterMemberObserver::class);
    }
}
