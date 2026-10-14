{{--
    "Menschen einladen" (route network.invitations.index): personal links with a monthly budget.
    Variables: $invitations (Collection<Invitation>, newest first), $remaining (int, links left this month)
--}}
@extends('layouts.app', ['title' => 'Einladen', 'active' => 'einladen', 'wide' => true])

@section('content')
    <x-network.columns label="Hinweise">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Gemeinsam starten</p>
            <h1 class="o-h2 m-0 text-night">Menschen einladen</h1>
            <p class="m-0 text-ink-2">
                Mit deinem Link folgt ihr euch nach der Anmeldung automatisch. Du kannst {{ config('oasis.invites_per_month') }} Links pro Monat erstellen;
                diesen Monat noch {{ $remaining }}. Ein Link gilt {{ \App\Models\Invitation::VALID_DAYS }} Tage.
            </p>
        </div>

        @error('invitation') <p role="alert" class="m-0 font-bold text-sun-text">{{ $message }}</p> @enderror
        @if ($remaining > 0 && auth()->user()->can('participate'))
            <form method="POST" action="{{ route('network.invitations.store') }}">
                @csrf
                <button type="submit" class="o-btn o-btn-md o-btn-dark">Neuen Einladungslink erstellen</button>
            </form>
        @endif

        <ul class="m-0 flex list-none flex-col gap-3 p-0">
            @forelse ($invitations as $invitation)
                <li class="flex flex-col gap-1 rounded-[20px] bg-paper px-5 py-4">
                    @if ($invitation->accepted_at)
                        <span class="font-bold text-spring-text">Angenommen am {{ $invitation->accepted_at->locale('de')->isoFormat('D. MMMM YYYY') }}</span>
                    @elseif ($invitation->expires_at->isPast())
                        <span class="text-muted">Abgelaufen</span>
                    @else
                        <label for="invite-{{ $invitation->id }}" class="text-sm font-bold">Gültig bis {{ $invitation->expires_at->locale('de')->isoFormat('D. MMMM') }} – Link zum Weitergeben:</label>
                        <div class="flex flex-wrap sm:flex-nowrap gap-2 items-center">
                            <input id="invite-{{ $invitation->id }}" type="text" readonly class="o-input font-mono text-sm flex-1" value="{{ route('network.invitations.show', $invitation->token) }}">
                            <button type="button" data-oasis="share" data-url="{{ route('network.invitations.show', $invitation->token) }}" aria-label="Link kopieren" hidden
                                    class="o-btn o-btn-sm o-btn-dark whitespace-nowrap">
                                <span data-share-label>Link kopieren</span>
                            </button>
                        </div>
                    @endif
                </li>
            @empty
                <li class="text-ink-2">Du hast noch keine Einladungslinks erstellt.</li>
            @endforelse
        </ul>
        <x-slot:aside>
            <x-network.side-card title="Diesen Monat" tone="night">
                <p class="m-0 text-[40px] leading-none font-extrabold">{{ $remaining }}</p>
                <p class="m-0 text-[15px] text-on-night-2">von {{ config('oasis.invites_per_month') }} Einladungslinks übrig</p>
            </x-network.side-card>
            <x-network.side-card title="Was Eingeladene bekommen" tone="dune">
                <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[15px] text-ink-2">
                    <li>{{ config('oasis.trial_days') }} Tage kostenlos testen, ohne Zahlungsdaten.</li>
                    <li>Ihr folgt euch nach der Anmeldung automatisch.</li>
                    <li>Keine Werbung, kein Feed, der festhält.</li>
                </ul>
            </x-network.side-card>
        </x-slot:aside>
    </x-network.columns>
@endsection
