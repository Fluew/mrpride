<?php

namespace App\Providers;

use App\Models\OrderFulfillment;
use App\Models\Quotation;
use App\Models\Sale;
use App\Observers\OrderFulfillmentEmailObserver;
use App\Observers\QuotationEmailObserver;
use App\Observers\SaleEmailObserver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();

        // Automatic customer emails (quotation approved, sale completed, out for delivery).
        Quotation::observe(QuotationEmailObserver::class);
        Sale::observe(SaleEmailObserver::class);
        OrderFulfillment::observe(OrderFulfillmentEmailObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // One permanent-password policy everywhere (password change, reset, settings): at least 12
        // characters with upper- and lowercase letters, a number and a symbol. In production the
        // password is also checked against known data breaches (needs internet access).
        Password::defaults(function (): Password {
            $rule = Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols();

            return app()->isProduction() ? $rule->uncompromised() : $rule;
        });
    }
}
