<?php

namespace App\Livewire\Privacy;

use App\Livewire\Privacy\Concerns\SavesPrivacySettings;
use App\Support\Privacy\PrivacyOptions;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * The Privatsphäre-Center on /app/privatsphaere (route app.privacy): every privacy setting with a short explanation,
 * saved the moment it changes (SavesPrivacySettings). The server checks every change against the offered choices
 * (UpdatePrivacySetting) and enforces the settings elsewhere through PrivacySetting::allows().
 */
class Center extends Component
{
    use SavesPrivacySettings;

    /** All sections with their settings, in the member layout. */
    public function render(): View
    {
        return view('livewire.privacy.center', ['sections' => PrivacyOptions::SECTIONS, 'steps' => count(PrivacyOptions::SECTIONS)])
            ->layout('layouts.app', ['title' => 'Privatsphäre', 'active' => 'privatsphaere', 'wide' => true]);
    }
}
