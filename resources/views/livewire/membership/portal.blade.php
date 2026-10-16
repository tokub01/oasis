{{-- Billing management for app.membership.portal (App\Livewire\Membership\Portal); payments belong to the authenticated account. --}}
<section class="rounded-2xl bg-paper p-6" aria-labelledby="zahlung-titel">
    <h1 id="zahlung-titel" class="o-h2">Zahlungsart & Rechnungen</h1>
    @if ($error !== '')
        <p role="alert" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-[18px] py-3.5 text-sm font-bold text-rose-800">{{ $error }}</p>
    @endif
    <p>Für ein neues Zahlungsmittel wird 1 € belastet und anschließend auf dasselbe Zahlungsmittel erstattet. Dein Mitgliedsbeitrag bleibt gleich.</p>
    <form wire:submit="updatePaymentMethod">
        <label for="zahlart" class="o-label">Neues Zahlungsmittel</label>
        <select name="zahlart" id="zahlart" wire:model="zahlart" class="o-input">
            <option value="sepa">Verfügbare Zahlungsmittel bei Mollie</option>
            <option value="karte">Kreditkarte</option>
            <option value="paypal">PayPal</option>
        </select>
        @error('zahlart')<p role="alert" class="o-error">{{ $message }}</p>@enderror
        <button class="o-btn o-btn-dark o-btn-md" type="submit" wire:loading.attr="disabled">Zahlungsmittel ändern</button>
    </form>
    <h2 class="o-h2-s">Bezahlte Beiträge</h2>
    <ul>
        @forelse ($payments as $payment)
            <li wire:key="payment-{{ $payment->id }}">{{ $payment->paid_at?->timezone('Europe/Berlin')->format('d.m.Y') }} · {{ \App\Support\OasisFormat::euro($payment->amount / 100) }} · <a class="o-link" href="{{ route('app.membership.invoice', $payment->id) }}">Rechnung OAS-{{ $payment->id }}</a> {{ $payment->refunded ? '(erstattet)' : '' }}</li>
        @empty
            <li>Noch keine bezahlten Beiträge vorhanden.</li>
        @endforelse
    </ul>
    <a wire:navigate class="o-link" href="{{ route('app.membership') }}">Zur Mitgliedschaft</a>
</section>
