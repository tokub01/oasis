<?php

namespace App\Http\Controllers\Notifications;

use App\Models\Notifications\PushSubscription;
use App\Support\Notifications\WebPush;
use App\Support\Privacy\DeviceLabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Switch push hints on or off for the current browser (docs/oasis-pwa.md). Called by the `push` module in
 * resources/js/oasis.js with the browser's PushSubscription as JSON; CSRF-protected like every web route.
 */
class PushSubscriptionController
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(WebPush::enabled(), 404);
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000', 'url:https'],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{86,88}$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22,24}$/'],
        ]);
        if (! WebPush::allowedEndpoint($data['endpoint'])) {
            throw ValidationException::withMessages(['endpoint' => 'Dieser Push-Dienst wird nicht unterstützt.']);
        }

        // An endpoint belongs to one browser; if someone else signed in there before, the browser now belongs to us.
        PushSubscription::updateOrCreate(['endpoint_hash' => PushSubscription::hashEndpoint($data['endpoint'])], [
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'p256dh' => $data['keys']['p256dh'],
            'auth' => $data['keys']['auth'],
            'device' => Str::limit(DeviceLabel::from($request->userAgent()), 77),
        ]);

        return response()->json(['status' => 'Push-Hinweise sind auf diesem Gerät eingeschaltet.']);
    }

    /** Only the own subscription can be removed; an unknown endpoint is not an error (already gone). */
    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);
        PushSubscription::where('user_id', $request->user()->id)
            ->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))->delete();

        return response()->json(['status' => 'Push-Hinweise sind auf diesem Gerät ausgeschaltet.']);
    }
}
