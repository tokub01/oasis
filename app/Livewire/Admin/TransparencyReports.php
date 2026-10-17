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
 * (route admin.transparency.index), admins only. Members, carried seats and income come from the real memberships
 * and are shown read-only; the form asks only for the month and the costs, starting with the cost items of the
 * previous report (or the config labels) plus two empty rows.
 */
class TransparencyReports extends Component
{
    /** Month as Y-m. */
    public string $month = '';

    /** @var list<array{label: ?string, amount: mixed}> cost rows; rows without a name are ignored */
    public array $items = [];

    /** Confirmation after publishing, announced through role="status". */
    public ?string $status = null;

    /** Last month and the cost items of the latest report, or the configured item names. */
    public function mount(): void
    {
        Gate::authorize('administrate');
        $latest = TransparencyReport::orderByDesc('month')->first();
        $this->month = CarbonImmutable::now()->subMonth()->format('Y-m');
        $labels = $latest !== null ? array_keys($latest->cost_items) : config('oasis.transparency.cost_items');
        foreach ($labels as $label) {
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
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.label' => ['nullable', 'string', 'max:80', 'required_with:items.*.amount'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'required_with:items.*.label'],
        ]);
        if (! $action->handle(auth()->user(), $input['month'], $input['items'])) {
            $this->addError('items', 'Bitte trage mindestens einen Kostenposten ein.');

            return;
        }
        $this->status = 'Die Zahlen sind auf der Transparenz-Seite veröffentlicht.';
    }

    /** Re-checked on every Livewire request, so a revoked admin role closes the page at once. */
    public function render(MembershipStats $membership): View
    {
        Gate::authorize('administrate');

        return view('livewire.admin.transparency-reports', [
            'live' => ['members' => $membership->activeMembersCount(), 'sponsored_seats' => $membership->sponsoredSeats(),
                'income' => $membership->monthlyRecurringRevenue()],
            'reports' => TransparencyReport::orderByDesc('month')->limit(24)->get(),
        ])->layout('layouts.staff', ['title' => 'Transparenz', 'active' => 'transparency']);
    }
}
