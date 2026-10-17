{{--
    Payment search, component App\Livewire\Admin\Payments (route admin.payments). Variables: $payments (paginator of
    MembershipPayment with user), $statuses (filter value => label), $mollieId (bool, the search term is a Mollie id),
    $retention (years records are kept, or null).
--}}
@use('App\Support\OasisFormat')
@php
    $kinds = ['membership' => 'Mitgliedschaft', 'renewal' => 'Verlängerung', 'gift' => 'Geschenk', 'mandate' => 'Zahlungsmittel'];
    $date = fn (?string $iso) => $iso ? \Carbon\CarbonImmutable::parse($iso)->timezone('Europe/Berlin')->locale('de')->isoFormat('D. MMM YYYY, HH:mm') : '–';
    $label = fn (string $status) => $statuses[$status] ?? $status;
    // Mollie sends amounts as "5.00" and method keys; shown the German way.
    $amount = fn (?string $value) => $value === null ? '–' : OasisFormat::money((float) $value);
    $methods = ['creditcard' => 'Kreditkarte', 'paypal' => 'PayPal', 'directdebit' => 'SEPA-Lastschrift', 'banktransfer' => 'Überweisung',
        'applepay' => 'Apple Pay', 'googlepay' => 'Google Pay', 'ideal' => 'iDEAL', 'bancontact' => 'Bancontact', 'klarna' => 'Klarna'];
    $method = fn (?string $key) => $key === null ? null : ($methods[$key] ?? $key);
@endphp
<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-2">
        <p class="o-eyebrow m-0">Admin</p>
        <h1 class="o-h2 m-0 text-night">Zahlungen</h1>
        <p class="m-0 max-w-[52em] text-ink-2">
            Für Buchhaltung und Rückfragen zu Zahlungen. Belege bleiben auch nach einer Kontolöschung gesperrt erhalten{{ $retention ? ' und werden nach '.$retention.' Jahren (ab Ende des Kalenderjahres) automatisch gelöscht' : '' }}.
            Jede Abfrage bei Mollie wird protokolliert.
        </p>
    </div>

    <form wire:submit="searchMollie" role="search" class="flex flex-col gap-4 rounded-[24px] bg-paper p-5 sm:p-6">
        <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_220px]">
            <div class="flex flex-col gap-1.5">
                <label for="zahlung-suche" class="text-[15px] font-bold">Suche</label>
                <input id="zahlung-suche" type="search" wire:model.live.debounce.400ms="q" maxlength="120" autocomplete="off" class="o-input"
                       aria-describedby="zahlung-suche-hilfe">
                <p id="zahlung-suche-hilfe" class="m-0 text-sm text-muted">Name, E-Mail, Benutzername, Belegnummer (#12) oder Mollie-ID (tr_… / cst_…).</p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="zahlung-status" class="text-[15px] font-bold">Status</label>
                <select id="zahlung-status" wire:model.live="status" class="o-input">
                    <option value="">Alle</option>
                    @foreach ($statuses as $value => $text)
                        <option value="{{ $value }}">{{ $text }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        @if ($mollieId)
            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Direkt bei Mollie suchen</button>
        @endif
    </form>

    @if ($mollie || $mollieError)
        <section aria-labelledby="mollie-titel" class="flex flex-col gap-4 rounded-[24px] border-2 border-night bg-paper p-5 sm:p-6" wire:key="mollie-{{ md5(json_encode($mollie).$mollieError) }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="mollie-titel" class="m-0 text-[21px] font-extrabold text-night">Live bei Mollie</h2>
                <button type="button" wire:click="closeMollie" class="o-btn o-btn-sm o-btn-outline">Schließen</button>
            </div>
            @if ($mollieError)
                <p role="alert" class="m-0 font-bold text-sun-text">{{ $mollieError }}</p>
            @elseif ($mollie['kind'] === 'payment')
                @php($p = $mollie['payment'])
                <dl class="m-0 grid gap-x-6 gap-y-2 sm:grid-cols-[max-content_minmax(0,1fr)]">
                    <dt class="text-muted">Zahlung</dt><dd class="m-0 font-bold break-all">{{ $p['id'] }}</dd>
                    <dt class="text-muted">Status</dt><dd class="m-0 font-bold">{{ $label($p['status']) }}</dd>
                    <dt class="text-muted">Betrag</dt><dd class="m-0">{{ $amount($p['amount']) }}@if ($p['refunded'] && (float) $p['refunded'] > 0) · erstattet {{ $amount($p['refunded']) }}@endif</dd>
                    <dt class="text-muted">Zahlungsmittel</dt><dd class="m-0">{{ $method($p['method']) ?? '–' }}</dd>
                    <dt class="text-muted">Beschreibung</dt><dd class="m-0">{{ $p['description'] ?? '–' }}</dd>
                    <dt class="text-muted">Erstellt</dt><dd class="m-0">{{ $date($p['created']) }}</dd>
                    <dt class="text-muted">Bezahlt</dt><dd class="m-0">{{ $date($p['paid']) }}</dd>
                    <dt class="text-muted">Kunde</dt><dd class="m-0 break-all">{{ $p['customer'] ?? '–' }}</dd>
                    <dt class="text-muted">Abo</dt><dd class="m-0 break-all">{{ $p['subscription'] ?? '–' }}</dd>
                </dl>
                @if ($p['dashboard'])
                    <a href="{{ $p['dashboard'] }}" target="_blank" rel="noopener noreferrer" class="o-btn o-btn-md o-btn-dark self-start">Im Mollie-Dashboard öffnen</a>
                @endif
            @else
                @php($c = $mollie['customer'])
                <dl class="m-0 grid gap-x-6 gap-y-2 sm:grid-cols-[max-content_minmax(0,1fr)]">
                    <dt class="text-muted">Kunde</dt><dd class="m-0 font-bold break-all">{{ $c['id'] }}</dd>
                    <dt class="text-muted">Name</dt><dd class="m-0">{{ $c['name'] ?? '–' }}</dd>
                    <dt class="text-muted">E-Mail</dt><dd class="m-0 break-all">{{ $c['email'] ?? '–' }}</dd>
                    <dt class="text-muted">Angelegt</dt><dd class="m-0">{{ $date($c['created']) }}</dd>
                </dl>
                @if ($c['dashboard'])
                    <a href="{{ $c['dashboard'] }}" target="_blank" rel="noopener noreferrer" class="o-btn o-btn-md o-btn-dark self-start">Im Mollie-Dashboard öffnen</a>
                @endif
                <h3 class="m-0 text-lg font-extrabold text-night">Zahlungen dieser Kundin / dieses Kunden bei Mollie ({{ count($mollie['payments']) }})</h3>
                @forelse ($mollie['payments'] as $p)
                    <div wire:key="mollie-payment-{{ $p['id'] }}" class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-paper-line pt-3">
                        <span class="flex min-w-0 flex-col">
                            <span class="font-bold break-all">{{ $p['id'] }} · {{ $amount($p['amount']) }}</span>
                            <span class="text-sm text-ink-2">{{ $label($p['status']) }} · {{ $method($p['method']) ?? 'Zahlungsmittel offen' }} · {{ $date($p['created']) }}{{ $p['subscription'] ? ' · Abo' : '' }}</span>
                        </span>
                        @if ($p['dashboard'])
                            <a href="{{ $p['dashboard'] }}" target="_blank" rel="noopener noreferrer" class="o-link inline-flex min-h-11 items-center text-[15px] text-night">In Mollie öffnen</a>
                        @endif
                    </div>
                @empty
                    <p class="m-0 text-ink-2">Keine Zahlungen bei Mollie.</p>
                @endforelse
            @endif
        </section>
    @endif

    <section aria-labelledby="belege-titel" class="flex flex-col gap-3">
        <h2 id="belege-titel" class="m-0 text-[21px] font-extrabold text-night">Belege in Oasis ({{ $payments->total() }})</h2>
        @forelse ($payments as $payment)
            @php($invoice = $payment->invoice_data ?? [])
            <article wire:key="beleg-{{ $payment->id }}" class="flex flex-col gap-3 rounded-[20px] bg-paper p-5">
                <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1">
                    <div class="flex min-w-0 flex-col">
                        <span class="font-extrabold text-night">#{{ $payment->id }} · {{ $kinds[$payment->kind] ?? $payment->kind }} · {{ OasisFormat::money($payment->amount / 100) }}</span>
                        <span class="text-sm break-words text-ink-2">
                            {{ $invoice['customer_name'] ?? $payment->user?->display_name ?? '–' }}
                            · {{ $invoice['customer_email'] ?? $payment->user?->email ?? '–' }}
                            @if ($payment->user === null) · <b class="text-sun-text">Konto gelöscht – Beleg gesperrt</b> @endif
                        </span>
                    </div>
                    <span class="rounded-full bg-sand px-3 py-1 text-sm font-bold text-night">{{ $label($payment->status) }}{{ $payment->refunded ? ' · erstattet' : '' }}</span>
                </div>
                <p class="m-0 text-sm break-all text-muted">
                    {{ config('oasis.tiers.'.$payment->tier.'.name', $payment->tier) }} · {{ $payment->rhythm === 'jahr' ? 'jährlich' : 'monatlich' }}
                    · angelegt {{ $payment->created_at->timezone('Europe/Berlin')->locale('de')->isoFormat('D. MMM YYYY, HH:mm') }}
                    @if ($payment->paid_at) · bezahlt {{ $payment->paid_at->timezone('Europe/Berlin')->locale('de')->isoFormat('D. MMM YYYY, HH:mm') }} @endif
                    · {{ $payment->mollie_payment_id ?? 'keine Mollie-Zahlung' }}
                </p>
                <div class="flex flex-wrap gap-2">
                    @if ($payment->mollie_payment_id)
                        <button type="button" wire:click="checkPayment({{ $payment->id }})" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">Zahlung bei Mollie prüfen</button>
                    @endif
                    @if ($payment->mollie_customer_id)
                        <button type="button" wire:click="checkCustomer({{ $payment->id }})" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">Alle Zahlungen der Person bei Mollie</button>
                    @endif
                </div>
            </article>
        @empty
            <p class="m-0 rounded-[20px] bg-paper p-5 text-ink-2">Keine Belege gefunden.@if ($mollieId) Die ID lässt sich trotzdem direkt bei Mollie suchen.@endif</p>
        @endforelse
        {{ $payments->links() }}
    </section>
</div>
