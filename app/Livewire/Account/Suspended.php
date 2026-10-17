<?php

namespace App\Livewire\Account;

use App\Models\AccountSuspension;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * /app/gesperrt (route app.suspended): what a locked member sees (docs/oasis-moderation.md, "Sperren"). Shows the
 * accusation, the text in question, how it was detected, the strike count and what happens next, and lets the member
 * answer (apologise, explain, object) until staff decide. Unlocked accounts are sent to the app.
 */
class Suspended extends Component
{
    public const STATEMENT_MAX = 3000;

    public string $statement = '';

    public ?string $status = null;

    public function mount(): void
    {
        $suspension = $this->suspension();
        if ($suspension === null) {
            $this->redirectRoute('app.home', navigate: true);

            return;
        }
        $this->statement = (string) $suspension->statement;
    }

    /** Store or update the member's answer; staff read it on the moderation "Sperren" page. */
    public function save(): void
    {
        $suspension = $this->suspension();
        abort_if($suspension === null, 404);
        $this->validate(['statement' => ['required', 'string', 'min:10', 'max:'.self::STATEMENT_MAX]], [
            'statement.required' => 'Schreib bitte ein paar Sätze.',
            'statement.min' => 'Schreib bitte ein paar Sätze.',
            'statement.max' => 'Bitte höchstens '.self::STATEMENT_MAX.' Zeichen.',
        ]);
        $suspension->update(['statement' => trim($this->statement), 'statement_at' => now()]);
        $this->status = 'Danke. Deine Stellungnahme liegt dem Moderationsteam vor.';
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.account.suspended', [
            'suspension' => $this->suspension(),
            'strikes' => $user?->activeStrikeCount() ?? 0,
            'limit' => (int) config('oasis.moderation.strikes_for_ban'),
        ])->layout('layouts.oasis', ['title' => 'Konto gesperrt', 'minimal' => true, 'noindex' => true]);
    }

    private function suspension(): ?AccountSuspension
    {
        return auth()->user()?->activeSuspension();
    }
}
