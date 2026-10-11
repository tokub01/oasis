<?php

use App\Http\Middleware\ThrottleRegistration;
use Laravel\Fortify\Features;

/* Authentication contract shared by backend actions and the Oasis auth/settings views. */
return [
    // Stateful session guard and password-reset broker from config/auth.php.
    'guard' => 'web',
    'passwords' => 'users',
    // Web middleware provides sessions and CSRF; protected endpoints also require auth.
    'middleware' => ['web', ThrottleRegistration::class],
    'auth_middleware' => 'auth',
    // Sign-in accepts email or the fixed public username in the shared login field.
    'username' => 'login',
    'email' => 'email',
    'lowercase_usernames' => true,
    // Own Blade views are registered in FortifyServiceProvider.
    'views' => true,
    // Default destination after registration, sign-in and verification.
    'home' => '/app',
    // Auth routes share the site's domain and have no extra URL prefix.
    'prefix' => '',
    'domain' => null,
    // Named rate limiters: five sign-in/challenge attempts per minute.
    'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'],
    // German public auth URLs; array keys match Fortify's route names.
    'paths' => [
        'login' => '/anmelden',
        'logout' => '/abmelden',
        'register' => '/testen',
        'password' => [
            'request' => '/passwort-vergessen',
            'email' => '/passwort-vergessen',
            'reset' => '/passwort-zuruecksetzen/{token}',
            'update' => '/passwort-zuruecksetzen',
            'confirm' => '/passwort-bestaetigen',
            'confirmation' => '/passwort-bestaetigt',
        ],
        'verification' => [
            'notice' => '/email/bestaetigen',
            'verify' => '/email/bestaetigen/{id}/{hash}',
            'send' => '/email/bestaetigung-senden',
        ],
        'user-profile-information' => ['update' => '/app/konto/profil'],
        'user-password' => ['update' => '/app/konto/passwort'],
        'two-factor' => ['login' => '/zwei-faktor-anmeldung'],
    ],
    // Logout returns to the public site; other responses use the home destination.
    'redirects' => ['logout' => '/'],
    // Optional TOTP requires both recent password confirmation and a valid setup code.
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::updateProfileInformation(),
        Features::updatePasswords(),
        Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true]),
    ],
];
