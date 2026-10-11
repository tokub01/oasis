<?php

namespace App\Providers;

use App\Http\Middleware\EnsureStaff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

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

        // Livewire's own update requests (/livewire/update) skip route middleware unless it is persistent. The
        // staff check (role + confirmed 2FA) must also guard every update of the moderation components.
        Livewire::addPersistentMiddleware([EnsureStaff::class]);
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

        // Oasis rule (PLATZHALTER.md): at least 10 characters, the same in every environment, so the hint on the
        // sign-up page is true. No "uncompromised" check: it would send hash prefixes to an external service
        // (Have I Been Pwned), which the privacy policy does not cover.
        Password::defaults(fn (): Password => Password::min(10));
    }
}
