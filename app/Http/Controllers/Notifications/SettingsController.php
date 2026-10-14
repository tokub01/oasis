<?php

namespace App\Http\Controllers\Notifications;

use App\Enums\Notifications\NoticeKind;
use App\Http\Controllers\Controller;
use App\Models\Notifications\NotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Plain HTML settings for individual hints and the optional daily email summary. */
class SettingsController extends Controller
{
    /** Missing rows use defaults and do not write during a GET request. */
    public function edit(Request $request): View
    {
        return view('app.notifications.settings', [
            'kinds' => NoticeKind::cases(),
            'preference' => NotificationPreference::firstOrNew(['user_id' => $request->user()->id]),
        ]);
    }

    /** Validate known boolean switches, then persist this account's settings. */
    public function update(Request $request): RedirectResponse
    {
        $rules = ['digest_enabled' => ['sometimes', 'boolean']];
        foreach (NoticeKind::cases() as $kind) {
            $rules['kinds.'.$kind->value] = ['sometimes', 'boolean'];
        }
        $request->validate($rules, ['boolean' => 'Bitte wähle an oder aus.']);
        $kinds = [];
        foreach (NoticeKind::cases() as $kind) {
            $kinds[$kind->value] = $request->boolean('kinds.'.$kind->value);
        }
        NotificationPreference::updateOrCreate(['user_id' => $request->user()->id], [
            'kinds' => $kinds, 'digest_enabled' => $request->boolean('digest_enabled'),
        ]);

        return redirect()->route('notifications.settings')->with('status', 'Deine Hinweis-Einstellungen sind gespeichert.');
    }
}
