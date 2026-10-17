{{-- Billing management for app.membership.portal (App\Livewire\Membership\Portal); payments belong to the authenticated account. --}}
@php
    $card = 'flex flex-col gap-5 rounded-[24px] border border-paper-line bg-paper p-[clamp(20px,3vw,32px)]';
    $h2 = 'm-0 text-[21px] font-extrabold text-night';
@endphp

<div>
<x-network.columns label="Zahlungen">
    <x-network.page-header eyebrow="Mitgliedschaft" title="Zahlungsart & Rechnungen">
        Ändere dein Zahlungsmittel und lade die Rechnungen zu deinen Beiträgen herunter.
    </x-network.page-header>

    @if ($error !== '')
        <p role="alert" class="m-0 rounded-2xl border border-rose-200 bg-rose-50 px-[18px] py-3.5 text-sm font-bold text-rose-800">{{ $error }}</p>
    @endif

    <section aria-labelledby="zahlart-titel" class="{{ $card }}">
        <h2 id="zahlart-titel" class="{{ $h2 }}">Zahlungsmittel ändern</h2>
        <p class="m-0 text-[15px] text-ink-2">Für ein neues Zahlungsmittel wird 1 € belastet und anschließend auf dasselbe Zahlungsmittel erstattet. Dein Mitgliedsbeitrag bleibt gleich.</p>
        <form wire:submit="updatePaymentMethod" class="flex flex-col gap-4">
            <div class="flex flex-col gap-2">
                <label for="zahlart" class="o-label">Neues Zahlungsmittel</label>
                <select name="zahlart" id="zahlart" wire:model="zahlart" class="o-input"
                        aria-invalid="{{ $errors->has('zahlart') ? 'true' : 'false' }}"
                        @error('zahlart') aria-describedby="zahlart-error" @enderror>
                    <option value="sepa">Auswahl bei Mollie</option>
                    <option value="karte">Kreditkarte</option>
                    <option value="paypal">PayPal</option>
                </select>
                @error('zahlart')<p id="zahlart-error" role="alert" class="o-error m-0">{{ $message }}</p>@enderror
            </div>
            <button class="o-btn o-btn-dark o-btn-md self-start" type="submit" wire:loading.attr="disabled">Zahlungsmittel ändern</button>
        </form>
    </section>

    <section aria-labelledby="rechnungen-titel" class="{{ $card }}">
        <h2 id="rechnungen-titel" class="{{ $h2 }}">Bezahlte Beiträge</h2>
        <ul class="m-0 flex list-none flex-col divide-y divide-paper-line p-0">
            @forelse ($payments as $payment)
                <li wire:key="payment-{{ $payment->id }}" class="flex flex-col gap-1 py-3 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                    <span class="flex flex-col">
                        <span class="font-bold text-night">{{ $payment->paid_at?->timezone('Europe/Berlin')->format('d.m.Y') }}</span>
                        <span class="text-[15px] text-ink-2">{{ \App\Support\OasisFormat::euro($payment->amount / 100) }}{{ $payment->refunded ? ' · erstattet' : '' }}</span>
                    </span>
                    <a class="o-link self-start text-[15px] text-night sm:self-auto" href="{{ route('app.membership.invoice', $payment->id) }}">
                        <x-oasis.icon name="arrow-right" :size="16" />
                        Rechnung OAS-{{ $payment->id }}
                    </a>
                </li>
            @empty
                <li class="text-[15px] text-ink-2">Noch keine bezahlten Beiträge vorhanden.</li>
            @endforelse
        </ul>
    </section>

    <a wire:navigate class="o-link self-start text-night" href="{{ route('app.membership') }}">Zur Mitgliedschaft</a>

    <x-slot:aside>
        <x-network.side-card title="So klappt der Wechsel" tone="night">
            <ol class="m-0 flex list-decimal flex-col gap-1.5 pl-5 text-[15px] text-on-night-2">
                <li>Zahlungsmittel wählen und auf „Zahlungsmittel ändern“ tippen.</li>
                <li>Bei Mollie bestätigen – dafür wird 1 € belastet.</li>
                <li>Der Euro geht auf dasselbe Zahlungsmittel zurück, dein Beitrag bleibt gleich.</li>
            </ol>
        </x-network.side-card>
        <x-network.side-card title="Fragen zur Rechnung?" tone="dune">
            <p class="m-0 text-[15px] text-ink-2">Jede Rechnung kannst du einzeln herunterladen. Bei Fragen hilft dir unsere Hilfe-Seite weiter.</p>
            <a href="{{ route('oasis.hilfe') }}" wire:navigate class="o-link self-start text-[15px] text-night">Hilfe &amp; Kontakt</a>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
