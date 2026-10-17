<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\Notifications\NotificationPreference;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;

/**
 * Switches the daily summary off from the mail itself, without signing in (signed URLs, routes/notifications.php):
 * - show(): the link in the mail opens a page with a button (mail scanners open GET links, so GET changes nothing);
 * - confirm(): that button, CSRF-protected;
 * - oneClick(): the POST mail programs send for the List-Unsubscribe header (RFC 8058), without CSRF token.
 */
class DigestUnsubscribeController extends Controller
{
    public function show(User $user): View
    {
        return view('notifications.unsubscribe', ['user' => $user]);
    }

    public function confirm(User $user): View
    {
        $this->switchOff($user);

        return view('notifications.unsubscribed');
    }

    public function oneClick(User $user): Response
    {
        $this->switchOff($user);

        return response('Die tägliche Zusammenfassung ist ausgeschaltet.', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private function switchOff(User $user): void
    {
        NotificationPreference::where('user_id', $user->id)->update(['digest_enabled' => false]);
    }
}
