<?php

namespace App\Livewire\Privacy\Concerns;

use App\Actions\Privacy\UpdatePrivacySetting;
use App\Support\Privacy\PrivacyOptions;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * State and immediate saving shared by the privacy center and the guided check: every setting is stored the moment it
 * changes, a refused value is reported at that setting and the stored value is shown again.
 */
trait SavesPrivacySettings
{
    /** @var array<string, string|bool> setting key => current value */
    public array $settings = [];

    /** Confirmation of the last change, announced through role="status"; set by the server only. */
    #[Locked]
    public ?string $status = null;

    /** Current values, or the privacy-by-default ones for members who never changed anything. */
    public function mount(): void
    {
        $privacy = auth()->user()->privacy();
        foreach (PrivacyOptions::keys() as $key) {
            $this->settings[$key] = PrivacyOptions::value($privacy, $key);
        }
    }

    /**
     * Save one setting as soon as it changes. Replacing the whole array (no key) or an unknown key is refused with 422.
     */
    public function updatedSettings(mixed $value, ?string $key = null): void
    {
        abort_unless($key !== null && in_array($key, PrivacyOptions::keys(), true), 422);
        $member = auth()->user();
        $this->resetErrorBag('settings.'.$key);

        try {
            $saved = app(UpdatePrivacySetting::class)->handle($member, $key, $value);
            $this->settings[$key] = PrivacyOptions::value($saved, $key);
            $this->status = 'Gespeichert: '.PrivacyOptions::text($key)['title'].' – '.PrivacyOptions::label($key, $this->settings[$key]);
        } catch (ValidationException) {
            $this->settings[$key] = PrivacyOptions::value($member->privacy(), $key);
            $this->status = null;
            $this->addError('settings.'.$key, 'Diese Auswahl gibt es nicht. Deine bisherige Einstellung bleibt.');
        }
    }
}
