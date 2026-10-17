<?php

namespace App\Livewire\Network;

use App\Actions\Network\UpdateProfile;
use App\Enums\Network\AvatarColor;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;

/** Edits only the authenticated account’s profile fields and keeps optional text nullable. */
class ProfileEdit extends NetworkPage
{
    public string $bio = '';

    public string $location = '';

    public string $avatar_color = 'sun';

    /** Populate only the current account's editable profile fields. */
    public function mount(): void
    {
        $profile = $this->actor()->profileOrDefault();
        $this->bio = $profile->bio ?? '';
        $this->location = $profile->location ?? '';
        $this->avatar_color = $profile->avatar_color->value;
    }

    /** Re-authorize the current account and delegate validated profile data to its domain action. */
    public function save(): void
    {
        $user = $this->actor();
        $data = $this->validate(['bio' => ['nullable', 'string', 'max:300'], 'location' => ['nullable', 'string', 'max:80'],
            'avatar_color' => ['required', Rule::enum(AvatarColor::class)]]);
        $data['bio'] = $data['bio'] === '' ? null : $data['bio'];
        $data['location'] = $data['location'] === '' ? null : $data['location'];
        app(UpdateProfile::class)->handle($user, $data);
        $this->postStatus = 'Dein Profil ist gespeichert.';
    }

    /** Reload the own profile for preview without storing a user model in public state. */
    public function render(): View
    {
        return $this->page('livewire.network.profile-edit', ['profile' => $this->actor()->profileOrDefault()], 'Profil', 'profil');
    }
}
