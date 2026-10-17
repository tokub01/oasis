<?php

use App\Http\Middleware\EnsureStaff;
use App\Http\Middleware\EnsureUserCanParticipate;
use App\Http\Middleware\SecurityHeaders;
use App\Support\Operations\ErrorAlert;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'staff' => EnsureStaff::class,
            'participate' => EnsureUserCanParticipate::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'mollie/webhook',
            // One-click unsubscribe (RFC 8058): mail programs cannot send a CSRF token; the URLs are signed instead.
            'startliste/abmelden/*/ein-klick',
            'hinweise/zusammenfassung/*/ein-klick',
        ]);
        // Signed-in visitors of guest-only pages (/anmelden, /testen) go straight into the app.
        $middleware->redirectUsersTo(fn () => route('app.home'));
        // CSP with per-request nonce and the other security headers on every web response (stage 7).
        $middleware->web(append: [SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Error tracking without a third-party service: mail the operator about unexpected errors (docs/oasis-operations.md).
        $exceptions->report(fn (Throwable $exception) => ErrorAlert::report($exception));
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'app/chat/api/*', 'broadcasting/auth') || $request->expectsJson(),
        );
        $exceptions->respond(function ($response) {
            if (request()->is('app/chat/api/*', 'broadcasting/auth')) {
                $response->headers->set('Cache-Control', 'no-store, private');
            }

            return $response;
        });
    })->create();
