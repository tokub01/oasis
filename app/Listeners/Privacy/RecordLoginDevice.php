<?php

namespace App\Listeners\Privacy;

use App\Mail\Privacy\LoginAlert;
use App\Models\User;
use App\Support\Privacy\DeviceLabel;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Remembers the browser and system of every sign-in and mails the member when one appears that this account has not
 * used before (setting `login_alerts`, on by default). The very first device of an account – usually the registration –
 * is recorded silently.
 */
class RecordLoginDevice
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }
        $label = DeviceLabel::from(request()->userAgent());
        $firstDevice = ! $user->knownDevices()->exists();
        $device = $user->knownDevices()->firstOrNew(['label' => $label]);
        $isNew = ! $device->exists;
        $device->forceFill(['last_seen_at' => now()])->save();

        if ($isNew && ! $firstDevice && $user->privacy()->login_alerts) {
            // A failing mail server must never block the sign-in itself.
            try {
                Mail::to($user->email)->send(new LoginAlert($user, $label, now()));
            } catch (Throwable $failure) {
                report($failure);
            }
        }
    }
}
