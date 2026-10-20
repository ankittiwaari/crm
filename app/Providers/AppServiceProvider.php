<?php

namespace App\Providers;

use App\Models\CompanyProfile;
use App\Services\WhatsApp\MetaCloudGateway;
use App\Services\WhatsApp\WhatsAppGateway;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WhatsAppGateway::class, fn (): WhatsAppGateway => MetaCloudGateway::fromProfile(CompanyProfile::current()));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Limits for the mobile API: general use per user, and logins per email and IP address.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('api-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by(Str::lower($request->string('email')->value()).'|'.$request->ip()));
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

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
