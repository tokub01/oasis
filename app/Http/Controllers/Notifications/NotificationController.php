<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Support\Notifications\HintPresentation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The private, paginated inbox and owner-scoped read actions; no participation requirement. */
class NotificationController extends Controller
{
    /** Newest first, grouped by calendar day/week in Europe/Berlin. */
    public function index(Request $request, HintPresentation $presentation): View
    {
        $user = $request->user();
        $hints = $user->notifications()->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        $items = $presentation->forMany($hints, $user);
        $groups = [];
        foreach ($hints as $hint) {
            $item = $items[$hint->id] ?? null;
            if ($item === null) {
                continue;
            }
            $date = $hint->created_at->copy()->timezone('Europe/Berlin');
            $group = $date->isToday() ? 'Heute' : ($date->greaterThanOrEqualTo(now('Europe/Berlin')->startOfWeek()) ? 'Diese Woche' : 'Früher');
            $groups[$group][] = ['hint' => $hint, 'item' => $item];
        }

        return view('app.notifications.index', compact('hints', 'groups'));
    }

    /** Only the signed-in account may mark its own hint as read. */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back()->with('status', 'Der Hinweis ist gelesen.');
    }

    /** Mark all hints, including older pages, in one owner-scoped update. */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'Alle Hinweise sind gelesen.');
    }
}
