<?php

namespace App\Livewire\Safety;

use App\Actions\Oasis\RecordContentReport;
use App\Enums\Moderation\ReportCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * /app/melden (route app.report): reporting for members in a few taps – pick what is wrong, add a comment if you
 * like. Name and e-mail come from the account, so the report is a full notice (DSA Art. 16) without a legal form.
 * Afterwards: thanks, the promise that the reported person does not learn who reported, and where to find help.
 * Guests use the formal form on /inhalte-melden.
 */
class QuickReport extends Component
{
    /** Link to the reported Tropfen, profile or oasis; only links to Oasis itself are accepted. */
    #[Url]
    #[Locked]
    public string $url = '';

    public string $category = '';

    public string $comment = '';

    /** True once sent: the page shows the thanks and the help contacts. */
    public bool $sent = false;

    public function mount(): void
    {
        abort_unless($this->isOasisLink($this->url), 404);
    }

    public function send(RecordContentReport $record): void
    {
        $user = auth()->user();
        $this->validate([
            'category' => ['required', Rule::enum(ReportCategory::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], ['category.required' => 'Wähl bitte aus, was nicht stimmt.', 'comment.max' => 'Bitte höchstens 2000 Zeichen.']);
        if (! RateLimiter::attempt('quick-report:'.$user->id, 20, fn () => true, 3600)) {
            $this->addError('category', 'Du hast in der letzten Stunde schon sehr viel gemeldet. Versuch es bitte etwas später.');

            return;
        }
        $category = ReportCategory::from($this->category);
        $record->handle([
            'url' => $this->url,
            'reason' => 'Meldung eines Mitglieds: '.$category->label().(trim($this->comment) === '' ? '' : "\n\n".trim($this->comment)),
            'name' => $user->display_name.' (@'.$user->username.')',
            'email' => $user->email,
        ]);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.safety.quick-report', ['categories' => ReportCategory::cases()])
            ->layout('layouts.app', ['title' => 'Melden']);
    }

    private function isOasisLink(string $url): bool
    {
        $base = rtrim(url('/'), '/');

        return $url !== '' && strlen($url) <= 2048 && ($url === $base || str_starts_with($url, $base.'/'));
    }
}
