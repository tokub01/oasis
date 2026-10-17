<?php

namespace App\Livewire\Network;

use App\Actions\Network\ManageProfilePhoto;
use App\Actions\Network\UpdateProfile;
use App\Enums\Network\AvatarColor;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Edits only the authenticated account’s profile fields and keeps optional text nullable. */
class ProfileEdit extends NetworkPage
{
    use WithFileUploads;

    public string $bio = '';

    /** Optional profile photo upload (ManageProfilePhoto). */
    public ?TemporaryUploadedFile $photo = null;

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

    /** Store the chosen photo as soon as it is uploaded: square, WebP, without location or camera data. */
    public function updatedPhoto(ManageProfilePhoto $photos): void
    {
        $this->validate(['photo' => ['image', 'mimes:jpeg,png,webp,gif', 'max:8192']],
            ['photo.image' => 'Bitte wähl ein Bild aus.', 'photo.mimes' => 'Bitte ein JPG, PNG, WebP oder GIF.', 'photo.max' => 'Das Bild darf höchstens 8 MB groß sein.']);
        $photos->store($this->actor(), $this->photo);
        $this->photo = null;
        $this->postStatus = 'Dein Profilbild ist gespeichert.';
    }

    public function removePhoto(ManageProfilePhoto $photos): void
    {
        $photos->remove($this->actor());
        $this->postStatus = 'Dein Profilbild ist entfernt. Jetzt zeigen wir wieder deine Initialen.';
    }

    /** Reload the own profile for preview without storing a user model in public state. */
    public function render(): View
    {
        return $this->page('livewire.network.profile-edit', ['profile' => $this->actor()->profileOrDefault()], 'Profil', 'profil');
    }
}
