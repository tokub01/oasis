<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TransparencyReport;
use App\Queries\Membership\MembershipStats;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Publishing the monthly figures of the public Transparenz page. The form is prefilled with the live number of
 * paying members and the cost items of the previous report (or the config labels); publishing a month again
 * corrects it.
 */
class TransparencyReportController extends Controller
{
    public function index(MembershipStats $membership): View
    {
        $latest = TransparencyReport::orderByDesc('month')->first();
        $labels = array_keys($latest?->cost_items ?? config('oasis.transparency.cost_items'));

        return view('admin.transparency', [
            'reports' => TransparencyReport::orderByDesc('month')->limit(24)->get(),
            'suggestion' => [
                'month' => CarbonImmutable::now()->subMonth()->format('Y-m'),
                'members' => $membership->activeMembersCount(),
                'sponsored_seats' => $latest?->sponsored_seats ?? 0,
                'income' => round($membership->monthlyRecurringRevenue(), 2),
                'cost_items' => array_map(fn ($label) => ['label' => $label, 'amount' => $latest?->cost_items[$label] ?? null], $labels),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $request->validate([
            'month' => ['required', 'date_format:Y-m', 'before_or_equal:'.now()->format('Y-m')],
            'members' => ['required', 'integer', 'min:0'],
            'sponsored_seats' => ['required', 'integer', 'min:0'],
            'income' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.label' => ['nullable', 'string', 'max:80', 'required_with:items.*.amount'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'required_with:items.*.label'],
        ]);
        $items = collect($input['items'])->filter(fn ($item) => filled($item['label'] ?? null))
            ->mapWithKeys(fn ($item) => [trim($item['label']) => round((float) $item['amount'], 2)])->all();
        abort_if($items === [], 422);

        TransparencyReport::updateOrCreate(['month' => $input['month'].'-01'], [
            'members' => $input['members'], 'sponsored_seats' => $input['sponsored_seats'], 'income' => $input['income'],
            'cost_items' => $items, 'published_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.transparency.index')->with('status', 'Die Zahlen sind auf der Transparenz-Seite veröffentlicht.');
    }
}
