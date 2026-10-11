<?php

namespace App\Http\Controllers\Network;

use App\Actions\Network\ManageInvitations;
use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Invitation links: members create them (monthly budget) and see which were used; guests open one, which remembers
 * it in the session until they register (accepted in NetworkServiceProvider on the Registered event).
 */
class InvitationController extends Controller
{
    public const SESSION_KEY = 'oasis.invitation';

    public function __construct(private readonly ManageInvitations $invitations) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        return view('app.network.invitations', [
            'invitations' => $user->invitations()->latest()->limit(24)->get(),
            'remaining' => $this->invitations->remainingThisMonth($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('participate');
        $this->invitations->create($request->user());

        return back()->with('status', 'Dein Einladungslink ist bereit.');
    }

    /** Landing page of a link. Invalid links show a neutral page instead of an error. */
    public function show(Request $request, string $token): View
    {
        $invitation = Invitation::open()->where('token', $token)->with('inviter')->first();
        if ($invitation !== null && $request->user() === null) {
            $request->session()->put(self::SESSION_KEY, $token);
        }

        return view('app.network.invitation-landing', ['invitation' => $invitation]);
    }
}
