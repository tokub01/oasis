<?php

namespace App\Support\Operations;

use App\Mail\Operations\ErrorAlertMail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Error tracking without a third-party service (docs/oasis-operations.md): an unexpected exception in production mails
 * the operator inbox – class, file, line and request path, never the message, input or user, which may hold personal
 * data. The same exception mails at most once per hour; the full trace stays in the server log.
 */
final class ErrorAlert
{
    public const THROTTLE_MINUTES = 60;

    public static function report(Throwable $exception): void
    {
        if (! app()->environment('production') || config('oasis.operations.error_mails') !== true) {
            return;
        }
        $signature = $exception::class.'@'.$exception->getFile().':'.$exception->getLine();
        try {
            if (! Cache::add('error-alert:'.sha1($signature), true, now()->addMinutes(self::THROTTLE_MINUTES))) {
                return;
            }
            Mail::to(config('oasis.mail.operator'))->send(new ErrorAlertMail(
                $exception::class,
                str_replace(base_path().DIRECTORY_SEPARATOR, '', $exception->getFile()).':'.$exception->getLine(),
                app()->runningInConsole() ? 'Konsole' : '/'.ltrim(request()->path(), '/'),
            ));
        } catch (Throwable) {
            // Alerting must never hide the original error; it is logged anyway.
        }
    }
}
