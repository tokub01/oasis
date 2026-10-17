<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Responses\NeutralPasswordResetLinkResponse;
use App\Listeners\Privacy\RecordLoginDevice;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

/** Connects Fortify's authentication endpoints to Oasis actions, views and rate limits. */
class FortifyServiceProvider extends ServiceProvider
{
    /** Bind the application actions and German authentication screens. */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);

        // Same answer for known and unknown addresses on "forgot password" (no account enumeration).
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, NeutralPasswordResetLinkResponse::class);

        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));

        // Sign-in accepts the email address or the username in the single field "login" (config fortify.username).
        // Fortify uses this callback for the password check and for its two-factor redirect.
        Fortify::authenticateUsing(function (Request $request): ?Authenticatable {
            $login = Str::lower(trim((string) $request->input('login')));
            $column = str_contains($login, '@') ? 'email' : 'username';
            $provider = Auth::guard('web')->getProvider();
            $credentials = [$column => $login, 'password' => $request->input('password')];

            // Preserve the default Fortify timing floor and password rehashing in the custom callback.
            return (new Timebox)->call(function () use ($provider, $credentials) {
                $user = $provider->retrieveByCredentials($credentials);
                if (! $user || ! $provider->validateCredentials($user, $credentials)) {
                    return null;
                }
                if (config('hashing.rehash_on_login', true)) {
                    $provider->rehashPasswordIfRequired($user, $credentials);
                }

                return $user;
            }, 200000);
        });

        // Fortify would look the user up by the "login" column for password confirmation; there is no such column,
        // and the signed-in user is known anyway, so only the password is checked.
        Fortify::confirmPasswordsUsing(fn (User $user, ?string $password): bool => Hash::check((string) $password, $user->password));

        RateLimiter::for('login', function (Request $request) {
            $login = $request->input('login');

            return Limit::perMinute(5)->by((is_string($login) ? Str::lower(trim($login)) : '').'|'.$request->ip());
        });
        // Login alerts (Privatsphäre → Geräte): remember browser and system of every sign-in.
        Event::listen(Login::class, RecordLoginDevice::class);
        // Fortify has no reset-link limiter setting; matching also covers cached routes.
        Event::listen(RouteMatched::class, function (RouteMatched $event): void {
            if ($event->route->getName() === 'password.email') {
                $event->route->middleware('throttle:password-reset');
            }
        });

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)
            ->by($request->session()->get('login.id').'|'.$request->ip()));

    }
}
