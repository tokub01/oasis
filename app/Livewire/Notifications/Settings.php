<?php

namespace App\Livewire\Notifications;

use App\Enums\Notifications\NoticeKind;
use App\Models\Notifications\NotificationPreference;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Hint settings on /app/hinweise/einstellungen (route notifications.settings): one switch per hint kind, the optional
 * daily email summary (off until switched on), quiet hours and a pause (no hint mails or pushes, hints still collect).
 *
 * Opening the page never writes: a member without a preference row sees the defaults, and the row is created on save.
 */
class Settings extends Component
{
    /** @var array<string, bool> switch per NoticeKind value */
    public array $kinds = [];

    /** Daily email summary at 18:00 (only sent when there are unread hints). */
    public bool $digest = false;

    /** Quiet hours in German time; may span midnight. */
    public bool $quietEnabled = false;

    public string $quietFrom = '22:00';

    public string $quietUntil = '07:00';

    /** Pause lengths offered, in days. */
    public const PAUSE_DAYS = [1, 3, 7];

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
        $this->quietEnabled = (bool) $preference->quiet_enabled;
        $this->quietFrom = substr($preference->quiet_from ?? '22:00', 0, 5);
        $this->quietUntil = substr($preference->quiet_until ?? '07:00', 0, 5);
    }

    /** Store exactly the known hint kinds for the signed-in account; unknown keys from the client are ignored. */
    public function save(): void
    {
        $rules = ['digest' => ['boolean'], 'quietEnabled' => ['boolean'],
            'quietFrom' => ['required', 'date_format:H:i'], 'quietUntil' => ['required', 'date_format:H:i', 'different:quietFrom']];
        foreach (NoticeKind::cases() as $kind) {
            $rules['kinds.'.$kind->value] = ['boolean'];
        }
        $this->validate($rules, ['boolean' => 'Bitte wähle an oder aus.', 'date_format' => 'Bitte gib eine Uhrzeit wie 22:00 ein.',
            'quietUntil.different' => 'Beginn und Ende der Ruhezeit müssen sich unterscheiden.']);

        $kinds = [];
        foreach (NoticeKind::cases() as $kind) {
            $kinds[$kind->value] = (bool) ($this->kinds[$kind->value] ?? false);
        }
        NotificationPreference::updateOrCreate(['user_id' => auth()->id()], ['kinds' => $kinds, 'digest_enabled' => $this->digest,
            'quiet_enabled' => $this->quietEnabled, 'quiet_from' => $this->quietFrom.':00', 'quiet_until' => $this->quietUntil.':00']);
        $this->status = 'Deine Hinweis-Einstellungen sind gespeichert.';
    }

    /** Pause hint mails and pushes for one of PAUSE_DAYS; the offered values come from the buttons, others 422. */
    public function pause(int $days): void
    {
        abort_unless(in_array($days, self::PAUSE_DAYS, true), 422);
        $until = now()->addDays($days);
        NotificationPreference::updateOrCreate(['user_id' => auth()->id()], ['paused_until' => $until]);
        $this->status = 'Pause bis '.$until->setTimezone(NotificationPreference::TIMEZONE)->settings(['locale' => 'de'])->isoFormat('dddd, D. MMMM, HH:mm').' Uhr.';
    }

    public function resume(): void
    {
        NotificationPreference::where('user_id', auth()->id())->update(['paused_until' => null]);
        $this->status = 'Die Pause ist beendet.';
    }

    /** One switch per hint kind plus the e-mail summary, in the member layout. */
    public function render(): View
    {
        $preference = NotificationPreference::where('user_id', auth()->id())->first();

        return view('livewire.notifications.settings', ['kindCases' => NoticeKind::cases(),
            'pausedUntil' => $preference?->isPaused() ? $preference->paused_until : null, 'pauseDays' => self::PAUSE_DAYS])
            ->layout('layouts.app', ['title' => 'Hinweise einstellen', 'active' => 'hinweise', 'wide' => true]);
    }
}
