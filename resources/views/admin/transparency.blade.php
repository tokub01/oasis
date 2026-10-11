{{--
    Publish the monthly figures of the public Transparenz page (route admin.transparency.index).
    Variables: $reports (published, newest first), $suggestion (prefill: month, live members and MRR, cost items)
--}}
@extends('layouts.staff', ['title' => 'Transparenz', 'active' => 'transparency'])

@php($euro = fn ($value) => \App\Support\OasisFormat::euro((float) $value))

@section('content')
    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Admin</p>
            <h1 class="o-h2 m-0 text-night">Transparenz-Zahlen veröffentlichen</h1>
            <p class="m-0 max-w-[48em] text-ink-2">
                Die neuesten Zahlen erscheinen sofort auf der <a href="{{ route('oasis.transparenz') }}" class="text-night underline underline-offset-4">Transparenz-Seite</a>.
                Mitglieder und Einnahmen sind mit den aktuellen Werten aus den Mitgliedschaften vorausgefüllt – bitte mit der Buchhaltung abgleichen.
                Einen Monat erneut zu veröffentlichen, korrigiert ihn.
            </p>
        </div>

        <form method="POST" action="{{ route('admin.transparency.store') }}" class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
            @csrf
            <div class="grid gap-4 md:grid-cols-4">
                <x-oasis.field name="month" type="month" label="Monat" :value="$suggestion['month']" required />
                <x-oasis.field name="members" type="number" label="zahlende Mitglieder" :value="$suggestion['members']" min="0" required />
                <x-oasis.field name="sponsored_seats" type="number" label="Solidar- und Patenplätze" :value="$suggestion['sponsored_seats']" min="0" required />
                <x-oasis.field name="income" type="number" label="Einnahmen (€)" :value="$suggestion['income']" min="0" step="0.01" required />
            </div>
            <fieldset class="m-0 flex flex-col gap-3 border-none p-0">
                <legend class="mb-2 text-[15px] font-bold">Kosten im Monat (leere Zeilen werden ignoriert)</legend>
                @foreach (array_pad($suggestion['cost_items'], max(count($suggestion['cost_items']), 0) + 2, ['label' => null, 'amount' => null]) as $index => $item)
                    <div class="grid gap-3 md:grid-cols-[1fr_200px]">
                        <label class="sr-only" for="item-label-{{ $index }}">Posten {{ $index + 1 }}</label>
                        <input id="item-label-{{ $index }}" name="items[{{ $index }}][label]" class="o-input" maxlength="80" value="{{ old("items.$index.label", $item['label']) }}" placeholder="Posten">
                        <label class="sr-only" for="item-amount-{{ $index }}">Betrag Posten {{ $index + 1 }} in Euro</label>
                        <input id="item-amount-{{ $index }}" name="items[{{ $index }}][amount]" type="number" min="0" step="0.01" class="o-input" value="{{ old("items.$index.amount", $item['amount']) }}" placeholder="€">
                    </div>
                @endforeach
                @if ($errors->has('items*'))
                    <p role="alert" class="m-0 font-bold text-sun-text">Jeder Posten braucht einen Namen und einen Betrag.</p>
                @endif
            </fieldset>
            <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">Veröffentlichen</button>
        </form>

        <section aria-labelledby="admin-berichte" class="flex flex-col gap-3">
            <h2 id="admin-berichte" class="m-0 text-[21px] font-extrabold text-night">Veröffentlicht</h2>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse rounded-[20px] bg-paper text-left">
                    <thead>
                        <tr class="text-sm text-muted">
                            <th scope="col" class="p-3">Monat</th><th scope="col" class="p-3">Mitglieder</th><th scope="col" class="p-3">Einnahmen</th>
                            <th scope="col" class="p-3">Kosten</th><th scope="col" class="p-3">Deckung</th><th scope="col" class="p-3">Stand</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reports as $report)
                            <tr class="border-t border-paper-line">
                                <th scope="row" class="p-3">{{ $report->month->locale('de')->isoFormat('MMMM YYYY') }}</th>
                                <td class="p-3">{{ $report->members }}</td>
                                <td class="p-3">{{ $euro($report->income) }}</td>
                                <td class="p-3">{{ $euro($report->costs()) }}</td>
                                <td class="p-3">{{ $report->coverage() === null ? '–' : $report->coverage().' %' }}</td>
                                <td class="p-3 text-sm text-muted">{{ $report->updated_at->locale('de')->isoFormat('D. MMM YYYY') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-3 text-ink-2">Noch nichts veröffentlicht. Bis dahin zeigt die Seite „Veröffentlichen wir ab dem Start“.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
