@extends('layouts.app', ['title' => 'Mitgliedschaft', 'active' => 'mitgliedschaft'])

@php
    $card = 'flex flex-col gap-4 rounded-[24px] border border-paper-line bg-paper p-[clamp(20px,3vw,32px)]';
    $h2 = 'm-0 text-[21px] font-extrabold text-night';
@endphp

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
            <h1 class="o-h2 m-0 text-night">Mitgliedschaft</h1>
            <p class="m-0 text-base text-muted">Verwalte deinen Beitrag, deine Rechnungen und deinen Status bei Oasis.</p>
        </div>

        {{-- Status card --}}
        <section aria-labelledby="status-titel" class="{{ $card }}">
            <h2 id="status-titel" class="{{ $h2 }}">Status</h2>

            @if ($subscription && $subscription->valid())
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-spring/15 px-3 py-1 text-sm font-bold text-spring-text">
                            <x-oasis.icon name="check" :size="16" />
                            Aktive Mitgliedschaft
                        </span>
                        @if ($isFoundingMember)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-sun/30 px-3 py-1 text-sm font-extrabold text-sun-ink">
                                <x-oasis.icon name="shield" :size="16" />
                                Gründungsmitglied mit Preisgarantie
                            </span>
                        @endif
                    </div>

                    <dl class="m-0 grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="rounded-xl bg-dune p-3">
                            <dt class="text-xs font-bold text-muted">Stufe</dt>
                            <dd class="m-0 text-lg font-extrabold text-night">
                                {{ $tiers[$subscription->type]['name'] ?? ucfirst($subscription->type) }}
                            </dd>
                        </div>
                        <div class="rounded-xl bg-dune p-3">
                            <dt class="text-xs font-bold text-muted">Abrechnungsstatus</dt>
                            <dd class="m-0 text-lg font-extrabold text-night">
                                @if ($subscription->onGracePeriod())
                                    Gekündigt zum {{ $subscription->ends_at?->timezone('Europe/Berlin')->format('d.m.Y') }}
                                @else
                                    Aktiv
                                @endif
                            </dd>
                        </div>
                    </dl>

                    @if ($subscription->onGracePeriod())
                        <div class="rounded-xl bg-sun/20 p-4 text-[15px] text-ink">
                            <strong>Kündigung vorgemerkt:</strong> Dein Vertrag endet am {{ $subscription->ends_at?->timezone('Europe/Berlin')->format('d.m.Y') }}. Bis dahin kannst du Oasis ohne Einschränkungen weiternutzen. Danach wechselt dein Konto in den Lesemodus.
                        </div>
                    @endif

                    <div class="mt-2 flex flex-wrap items-center gap-4">
                        <a href="{{ route('app.membership.portal') }}" class="o-btn o-btn-md o-btn-dark">
                            Zahlungsart & Rechnungen verwalten
                        </a>

                        @if (! $subscription->onGracePeriod())
                            <a href="{{ route('app.membership.cancel.confirm') }}" class="o-link text-sm font-bold text-night">
                                Vertrag kündigen
                            </a>
                        @endif

                        @if ($isEligibleForWithdrawal && ! $subscription->onGracePeriod())
                            <a href="{{ route('app.membership.withdraw.confirm') }}" class="text-xs text-muted underline">
                                Mitgliedschaft widerrufen (14 Tage)
                            </a>
                        @endif
                    </div>
                </div>

            @elseif ($activeGift)
                <div class="flex flex-col gap-3">
                    <span class="inline-flex self-start items-center gap-1.5 rounded-full bg-spring/15 px-3 py-1 text-sm font-bold text-spring-text">
                        <x-oasis.icon name="gift" :size="16" />
                        Geschenkte Mitgliedschaft
                    </span>

                    <p class="m-0 text-base text-ink">
                        Deine durch einen Gutschein aktivierte Mitgliedschaft (Stufe: <strong>{{ $tiers[$activeGift->tier]['name'] ?? 'Oase' }}</strong>) ist bis zum <strong>{{ $activeGift->expires_at?->timezone('Europe/Berlin')->format('d.m.Y') }}</strong> aktiv.
                    </p>

                    @if ($isFoundingMember)
                        <span class="inline-flex self-start items-center gap-1.5 rounded-full bg-sun/30 px-3 py-1 text-sm font-extrabold text-sun-ink">
                            <x-oasis.icon name="shield" :size="16" />
                            Gründungsmitglied mit Preisgarantie
                        </span>
                    @endif
                </div>

            @elseif ($trialDaysLeft !== null)
                <div class="flex flex-col gap-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <span class="inline-flex self-start items-center gap-1.5 rounded-full bg-dune px-3 py-1 text-sm font-bold text-night">
                            <x-oasis.icon name="clock" :size="16" />
                            Kostenloser Test
                        </span>
                        @if ($isFoundingMember)
                            <span class="inline-flex self-start items-center gap-1.5 rounded-full bg-sun/30 px-3 py-1 text-sm font-extrabold text-sun-ink">
                                <x-oasis.icon name="shield" :size="16" />
                                Gründungsmitglied mit Preisgarantie
                            </span>
                        @endif
                    </div>

                    <p class="m-0 text-base text-ink">
                        Du hast noch <strong>{{ $trialDaysLeft }} {{ $trialDaysLeft === 1 ? 'Tag' : 'Tage' }}</strong> in deinem kostenlosen 30-Tage-Test (endet am {{ $user->trial_ends_at?->timezone('Europe/Berlin')->format('d.m.Y') }}).
                    </p>
                    <p class="m-0 text-sm text-muted">
                        Der Test endet von selbst. Es entstehen dir keine Kosten.
                        @if ($remainingFoundingSeats > 0)
                            Noch {{ number_format($remainingFoundingSeats, 0, ',', '.') }} Plätze mit dauerhafter Preisgarantie verfügbar.
                        @endif
                    </p>

                    <div class="mt-2">
                        <a href="{{ route('oasis.mitglied') }}" class="o-btn o-btn-md o-btn-dark">
                            Mitgliedschaft wählen
                        </a>
                    </div>
                </div>

            @else
                <div class="flex flex-col gap-3">
                    <span class="inline-flex self-start items-center gap-1.5 rounded-full bg-sun/30 px-3 py-1 text-sm font-bold text-sun-ink">
                        <x-oasis.icon name="lock" :size="16" />
                        Test abgelaufen · Lesemodus
                    </span>

                    <p class="m-0 text-base text-ink">
                        Dein kostenloser Test ist beendet. Dein Konto befindet sich im Lesemodus: Du kannst alle Inhalte lesen, dein Konto exportieren und löschen. Um wieder Beiträge zu verfassen und Nachrichten zu senden, wähle einen Mitgliedsbeitrag.
                    </p>

                    <div class="mt-2">
                        <a href="{{ route('oasis.mitglied') }}" class="o-btn o-btn-md o-btn-dark">
                            Mitglied werden
                        </a>
                    </div>
                </div>
            @endif
        </section>

        {{-- Voucher redemption card --}}
        <section aria-labelledby="gutschein-titel" class="{{ $card }}">
            <h2 id="gutschein-titel" class="{{ $h2 }}">Gutscheincode einlösen</h2>
            <p class="m-0 text-sm text-muted">
                Hast du einen Gutschein für eine Oasis-Mitgliedschaft erhalten? Löse ihn hier ein, um deine Mitgliedschaft sofort zu aktivieren.
            </p>

            <form method="POST" action="{{ route('app.membership.redeem') }}" class="flex flex-wrap items-start gap-3">
                @csrf
                <div class="flex flex-1 flex-col gap-1.5 min-w-[240px]">
                    <label for="code" class="text-sm font-bold sr-only">Gutscheincode</label>
                    <input id="code" name="code" type="text" placeholder="OASIS-XXXX-XXXX" required
                           class="min-h-12 rounded-xl border border-field-line bg-field px-4 text-base font-mono uppercase text-ink">
                    @error('code')
                        <span class="text-sm font-bold text-sun-text">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="o-btn o-btn-md o-btn-dark min-h-12">
                    Code einlösen
                </button>
            </form>
        </section>

        {{-- Only the purchaser sees their paid gift codes; no JavaScript required. --}}
        @if ($purchasedGifts->isNotEmpty())
            <section aria-labelledby="geschenke-titel" class="{{ $card }}">
                <h2 id="geschenke-titel" class="{{ $h2 }}">Deine gekauften Gutscheine</h2>
                <ul>
                    @foreach ($purchasedGifts as $gift)
                        <li><code>{{ $gift->code }}</code> · {{ $tiers[$gift->tier]['name'] ?? $gift->tier }} · {{ $gift->isRedeemed() ? 'Bereits eingelöst' : 'Zum Weitergeben' }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        {{-- Gift purchase card --}}
        <section aria-labelledby="verschenken-titel" class="{{ $card }}">
            <h2 id="verschenken-titel" class="{{ $h2 }}">Mitgliedschaft verschenken</h2>
            <p class="m-0 text-sm text-muted">
                Verschenke 12 Monate werbefreie Oase an Freunde oder Familie (2 Monate geschenkt). Du erhältst einen Gutscheincode zum Weitergeben.
            </p>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach ($tiers as $tierKey => $tier)
                    <div class="flex flex-col justify-between gap-3 rounded-2xl border border-dune-line bg-dune p-4">
                        <div>
                            <span class="text-base font-extrabold text-night">{{ $tier['name'] }}</span>
                            <p class="m-0 text-sm text-ink-2 mt-1">12 Monate für {{ \App\Support\OasisFormat::euro($tier['monthly'] * config('oasis.year_months', 10)) }}</p>
                        </div>
                        <form method="POST" action="{{ route('app.membership.gift.checkout') }}">
                            @csrf
                            <input type="hidden" name="tier" value="{{ $tierKey }}">
                            <button type="submit" class="o-btn o-btn-sm o-btn-dark w-full">
                                Verschenken
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    </div>
@endsection
