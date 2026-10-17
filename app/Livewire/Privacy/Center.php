<?php

namespace App\Livewire\Privacy;

use App\Actions\Privacy\LimitPastPosts;
use App\Enums\Network\Visibility;
use App\Livewire\Privacy\Concerns\SavesPrivacySettings;
use App\Models\Post;
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

    /** "Frühere Tropfen begrenzen": all public Tropfen of the member become members-only (asked for in the browser). */
    public function limitPastPosts(LimitPastPosts $limit): void
    {
        $count = $limit->handle(auth()->user());
        $this->status = match ($count) {
            0 => 'Du hattest keine öffentlichen Tropfen.',
            1 => 'Ein Tropfen ist jetzt nur noch für Mitglieder sichtbar.',
            default => $count.' Tropfen sind jetzt nur noch für Mitglieder sichtbar.',
        };
    }

    /** All sections with their settings and the further controls, in the member layout. */
    public function render(): View
    {
        $member = auth()->user();

        return view('livewire.privacy.center', [
            'sections' => PrivacyOptions::SECTIONS,
            'steps' => count(PrivacyOptions::CHECKUP_SECTIONS),
            'publicPosts' => Post::where('user_id', $member->id)->where('visibility', Visibility::Public->value)->count(),
            'hiddenWords' => count($member->privacy()->hiddenWords()),
            'restricted' => count($member->restrictedUserIds()),
            'blocked' => $member->blocks()->count(),
            'muted' => count($member->mutedUserIds()),
        ])
            ->layout('layouts.app', ['title' => 'Privatsphäre', 'active' => 'privatsphaere', 'wide' => true]);
    }
}
