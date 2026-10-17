<?php

namespace App\Livewire\Privacy;

use App\Livewire\Privacy\Concerns\SavesPrivacySettings;
use App\Support\Privacy\PrivacyOptions;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The guided Privatsphäre-Check on /app/privatsphaere/check (route app.privacy.check): one section per step, each
 * choice saved at once (SavesPrivacySettings), and a summary with the further controls (blocked and muted accounts,
 * data export, deleting the account). Only next() and back() move between steps; the step is locked for the client.
 */
class Checkup extends Component
{
    use SavesPrivacySettings;

    /** 1..count(sections) for the sections, one more for the summary. */
    #[Locked]
    public int $step = 1;

    /** Go to the next step (the summary is the last one) and move the focus to its heading. */
    public function next(): void
    {
        $this->goTo($this->step + 1);
    }

    /** Go one step back and move the focus to its heading. */
    public function back(): void
    {
        $this->goTo($this->step - 1);
    }

    /** The current section, or the summary after the last one. */
    public function render(): View
    {
        $sections = array_values(PrivacyOptions::SECTIONS);

        return view('livewire.privacy.checkup', [
            'section' => $sections[$this->step - 1] ?? null,
            'total' => count($sections),
            'summary' => collect(PrivacyOptions::keys())->mapWithKeys(fn (string $key) => [$key => [
                'title' => PrivacyOptions::text($key)['title'],
                'value' => PrivacyOptions::label($key, $this->settings[$key]),
            ]])->all(),
        ])->layout('layouts.app', ['title' => 'Privatsphäre-Check', 'active' => 'privatsphaere', 'wide' => true]);
    }

    /** Clamp to the existing steps; the browser moves the focus to the new heading (oasis.js, event oasis:focus). */
    private function goTo(int $step): void
    {
        $this->step = max(1, min($step, count(PrivacyOptions::SECTIONS) + 1));
        $this->status = null;
        $this->dispatch('oasis:focus', id: 'check-titel');
    }
}
