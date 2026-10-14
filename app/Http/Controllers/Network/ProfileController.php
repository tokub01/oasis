<?php

namespace App\Http\Controllers\Network;

use App\Enums\Network\AvatarColor;
use App\Enums\Network\Visibility;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Queries\Network\PostFeed;
use App\Support\DemoProfiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Profiles: the public/members page /profil/{handle} and editing your own profile. */
class ProfileController extends Controller
{
    /**
     * A member's profile. Handles no verified member has fall back to the sample profiles of the guest preview
     * (while `oasis.demo_pages` is on); a members-only profile is a 404 for guests, so its existence does not leak.
     */
    public function show(Request $request, string $handle, PostFeed $feed): View
    {
        $user = User::where('username', strtolower($handle))->whereNotNull('email_verified_at')->with('profile')->first();
        if ($user === null) {
            return $this->sample($handle);
        }
        $viewer = $request->user();
        abort_if($viewer !== null && $viewer->isBlockedWith($user), 404);
        abort_unless($user->profileOrDefault()->isVisibleTo($viewer), 404);

        return view('app.network.profile.show', [
            'member' => $user,
            'profile' => $user->profileOrDefault(),
            'posts' => $feed->byAuthor($user, $viewer),
            'followers' => $user->followers()->count(),
            'oases' => $user->oases()->orderBy('name')->get()->filter->isVisibleTo($viewer),
            'following' => $viewer?->follows($user) ?? false,
        ]);
    }

    public function edit(Request $request): View
    {
        return view('app.network.profile.edit', ['profile' => $request->user()->profileOrDefault()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'bio' => ['nullable', 'string', 'max:300'],
            'location' => ['nullable', 'string', 'max:80'],
            'visibility' => ['required', Rule::enum(Visibility::class)],
            'avatar_color' => ['required', Rule::enum(AvatarColor::class)],
        ]);
        $request->user()->profile()->updateOrCreate([], $input);

        return redirect()->route('network.profile.edit')->with('status', 'Dein Profil ist gespeichert.');
    }

    private function sample(string $handle): View
    {
        abort_unless(config('oasis.demo_pages'), 404);
        $profile = DemoProfiles::find($handle);
        abort_if($profile === null, 404);

        return view('oasis.profil', ['handle' => strtolower($handle), 'profile' => $profile]);
    }
}
