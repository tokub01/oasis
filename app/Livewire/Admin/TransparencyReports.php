<?php

namespace App\Livewire\Admin;

use App\Actions\Admin\PublishTransparencyReport;
use App\Models\TransparencyReport;
use App\Queries\Membership\MembershipStats;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Publishing the monthly figures of the public Transparenz page on /admin/transparenz
 * (route admin.transparency.index), admins only. The form starts with the live number of paying members and
 * recurring income and the cost items of the previous report (or the config labels), plus two empty rows.
 */
class TransparencyReports extends Component
{
    /** Month as Y-m. */
    public string $month = '';

    public int|string $members = 0;

    public int|string $sponsored_seats = 0;

    public float|int|string $income = 0;

    /** @var list<array{label: ?string, amount: mixed}> cost rows; rows without a name are ignored */
    public array $items = [];

    /** Confirmation after publishing, announced through role="status". */
    public ?string $status = null;

    /** Prefill from the live membership figures and the latest report. */
    public function mount(MembershipStats $membership): void
    {
        Gate::authorize('administrate');
        $latest = TransparencyReport::orderByDesc('month')->first();
        $this->month = CarbonImmutable::now()->subMonth()->format('Y-m');
        $this->members = $membership->activeMembersCount();
        $this->sponsored_seats = $latest->sponsored_seats ?? 0;
        $this->income = round($membership->monthlyRecurringRevenue(), 2);
        foreach (array_keys($latest->cost_items ?? config('oasis.transparency.cost_items')) as $label) {
            $this->items[] = ['label' => (string) $label, 'amount' => $latest->cost_items[$label] ?? null];
        }
        array_push($this->items, ['label' => null, 'amount' => null], ['label' => null, 'amount' => null]);
    }

    /** Validate and publish; a month that already exists is corrected. */
    public function publish(PublishTransparencyReport $action): void
    {
        Gate::authorize('administrate');
        $input = $this->validate([
            'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'members' => ['required', 'integer', 'min:0'],
            'sponsored_seats' => ['required', 'integer', 'min:0'],
            'income' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.label' => ['nullable', 'string', 'max:80', 'required_with:items.*.amount'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'required_with:items.*.label'],
        ]);
        if (! $action->handle(auth()->user(), $input, $input['items'])) {
            $this->addError('items', 'Bitte trage mindestens einen Kostenposten ein.');

            return;
        }
        $this->status = 'Die Zahlen sind auf der Transparenz-Seite veröffentlicht.';
    }

    /** Re-checked on every Livewire request, so a revoked admin role closes the page at once. */
    public function render(): View
    {
        Gate::authorize('administrate');

        return view('livewire.admin.transparency-reports', [
            'reports' => TransparencyReport::orderByDesc('month')->limit(24)->get(),
        ])->layout('layouts.staff', ['title' => 'Transparenz', 'active' => 'transparency']);
    }
}
