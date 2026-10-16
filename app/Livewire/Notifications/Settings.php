<?php

namespace App\Livewire\Notifications;

use App\Enums\Notifications\NoticeKind;
use App\Models\Notifications\NotificationPreference;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Hint settings on /app/hinweise/einstellungen (route notifications.settings): one switch per hint kind and the
 * optional daily email summary, which stays off until the member switches it on.
 *
 * Opening the page never writes: a member without a preference row sees the defaults, and the row is created on save.
 */
class Settings extends Component
{
    /** @var array<string, bool> switch per NoticeKind value */
    public array $kinds = [];

    /** Daily email summary at 18:00 (only sent when there are unread hints). */
    public bool $digest = false;

    /** Confirmation after saving, announced through role="status". */
    public ?string $status = null;

    /** Load the saved choices, or the defaults when nothing is saved yet. */
    public function mount(): void
    {
        $preference = NotificationPreference::firstOrNew(['user_id' => auth()->id()]);
        foreach (NoticeKind::cases() as $kind) {
            $this->kinds[$kind->value] = $preference->enabled($kind);
        }
        $this->digest = (bool) $preference->digest_enabled;
    }

    /** Store exactly the known hint kinds for the signed-in account; unknown keys from the client are ignored. */
    public function save(): void
    {
        $rules = ['digest' => ['boolean']];
        foreach (NoticeKind::cases() as $kind) {
            $rules['kinds.'.$kind->value] = ['boolean'];
        }
        $this->validate($rules, ['boolean' => 'Bitte wähle an oder aus.']);

        $kinds = [];
        foreach (NoticeKind::cases() as $kind) {
            $kinds[$kind->value] = (bool) ($this->kinds[$kind->value] ?? false);
        }
        NotificationPreference::updateOrCreate(['user_id' => auth()->id()], ['kinds' => $kinds, 'digest_enabled' => $this->digest]);
        $this->status = 'Deine Hinweis-Einstellungen sind gespeichert.';
    }

    /** One switch per hint kind plus the e-mail summary, in the member layout. */
    public function render(): View
    {
        return view('livewire.notifications.settings', ['kindCases' => NoticeKind::cases()])
            ->layout('layouts.app', ['title' => 'Hinweise einstellen', 'active' => 'hinweise', 'wide' => true]);
    }
}
