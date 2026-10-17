<?php

namespace App\Livewire\Onboarding;

use App\Actions\Network\ManageFollows;
use App\Actions\Network\UpdateProfile;
use App\Actions\Privacy\UpdatePrivacySetting;
use App\Enums\Network\AvatarColor;
use App\Enums\Network\Visibility;
use App\Models\Oasis;
use App\Models\User;
use App\Support\Privacy\PrivacyOptions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Einstieg für neue Mitglieder on /app/willkommen (route app.welcome, docs/oasis-network.md "Einstieg"): four short
 * steps – about you, who sees you, oases, people. Every step can be skipped, the whole tour too ("Später"); finishing
 * or skipping sets users.onboarded_at, so the start page stops leading here. It can be opened again from the settings.
 */
class Welcome extends Component
{
    public const STEPS = ['profil' => 'Über dich', 'sichtbarkeit' => 'Wer dich sieht', 'oasen' => 'Oasen finden', 'menschen' => 'Menschen finden'];

    /** 1..count(STEPS); only next(), skipStep() and back() move it. */
    #[Locked]
    public int $step = 1;

    public string $bio = '';

    public string $location = '';

    public string $avatar_color = 'sun';

    public string $profile_audience = 'members';

    public string $default_post_visibility = 'members';

    #[Locked]
    public ?string $status = null;

    public function mount(): void
    {
        $member = $this->member();
        $profile = $member->profileOrDefault();
        $this->bio = $profile->bio ?? '';
        $this->location = $profile->location ?? '';
        $this->avatar_color = $profile->avatar_color->value;
        // Audience settings are never switches, so their value is always a string.
        $this->profile_audience = (string) PrivacyOptions::value($member->privacy(), 'profile_audience');
        $this->default_post_visibility = (string) PrivacyOptions::value($member->privacy(), 'default_post_visibility');
    }

    /** Save the current step (oases and people save on click) and go on. */
    public function next(UpdateProfile $profiles, UpdatePrivacySetting $privacy): void
    {
        $member = $this->member();
        if ($this->step === 1) {
            $data = $this->validate(['bio' => ['nullable', 'string', 'max:300'], 'location' => ['nullable', 'string', 'max:80'],
                'avatar_color' => ['required', Rule::enum(AvatarColor::class)]]);
            $profiles->handle($member, ['bio' => $data['bio'] ?: null, 'location' => $data['location'] ?: null, 'avatar_color' => $data['avatar_color']]);
        }
        if ($this->step === 2) {
            $this->validate([
                'profile_audience' => PrivacyOptions::rule('profile_audience', $member),
                'default_post_visibility' => PrivacyOptions::rule('default_post_visibility', $member),
            ], ['in' => 'Bitte wähle eine der Möglichkeiten.']);
            $privacy->handle($member, 'profile_audience', $this->profile_audience);
            $privacy->handle($member, 'default_post_visibility', $this->default_post_visibility);
        }
        $this->goTo($this->step + 1);
    }

    public function skipStep(): void
    {
        $this->goTo($this->step + 1);
    }

    public function back(): void
    {
        $this->goTo($this->step - 1);
    }

    /** Join or leave a public oasis right from the list (joining = following). */
    public function toggleOasis(int $oasisId, ManageFollows $follows): void
    {
        $member = $this->member();
        Gate::authorize('participate');
        $oasis = $this->oases()->firstWhere('id', $oasisId) ?? abort(404);
        $member->follows($oasis) ? $follows->unfollow($member, $oasis) : $follows->follow($member, $oasis);
    }

    /** Finish or skip the whole tour: the start page no longer leads here. */
    public function finish(): void
    {
        $this->member()->forceFill(['onboarded_at' => now()])->save();
        session()->flash('status', 'Willkommen bei Oasis! Schön, dass du da bist.');
        $this->redirectRoute('app.home', navigate: true);
    }

    public function render(): View
    {
        $member = $this->member();

        return view('livewire.onboarding.welcome', [
            'steps' => self::STEPS,
            'key' => array_keys(self::STEPS)[$this->step - 1],
            'oases' => $this->step === 3 ? $this->oases() : new Collection,
            'joined' => $this->step === 3 ? $member->oases()->pluck('oases.id')->all() : [],
            'audiences' => PrivacyOptions::choices('profile_audience', $member),
            'visibilities' => PrivacyOptions::choices('default_post_visibility', $member),
            'colors' => AvatarColor::cases(),
            'public' => Visibility::Public->value,
        ])->layout('layouts.app', ['title' => 'Willkommen', 'active' => 'quelle', 'wide' => true]);
    }

    /**
     * Public oases to start with: the ones named in `oasis.onboarding.featured_oases` (hand-picked, no algorithm),
     * otherwise the six with the most members.
     *
     * @return Collection<int, Oasis>
     */
    private function oases(): Collection
    {
        $featured = config('oasis.onboarding.featured_oases', []);
        $query = Oasis::where('visibility', Visibility::Public->value)->withCount('members');

        return $featured !== []
            ? $query->whereIn('slug', $featured)->get()->sortBy(fn (Oasis $oasis) => array_search($oasis->slug, $featured, true))->values()
            : $query->orderByDesc('members_count')->orderBy('name')->limit(6)->get();
    }

    private function goTo(int $step): void
    {
        $this->step = max(1, min($step, count(self::STEPS)));
        $this->resetErrorBag();
        $this->dispatch('oasis:focus', id: 'willkommen-titel');
    }

    private function member(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->hasVerifiedEmail(), 403);

        return $user;
    }
}
