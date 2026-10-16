{{-- app.membership.invoice: authorized paid payment and stored billing details. Print using the browser menu. --}}
@extends('layouts.app', ['title' => 'Rechnung', 'active' => 'mitgliedschaft'])
@section('content')
<article class="rounded-2xl bg-paper p-6" aria-labelledby="rechnung-titel">
    <h1 class="o-h2" id="rechnung-titel">Rechnung OAS-{{ $payment->id }}</h1>
    <p>{{ $payment->paid_at?->timezone('Europe/Berlin')->format('d.m.Y') }} · Bezahlt über Mollie · {{ $payment->mollie_payment_id }}</p>
    @if ($payment->refunded)<p role="status">Der Betrag wurde erstattet.</p>@endif
    <h2 class="o-h2-s">Rechnungssteller</h2>
    <address>{{ $details['seller']['legal_name'] ?? config('oasis.operator.legal_name') }}<br>
        {{ $details['seller']['street'] ?? config('oasis.operator.street') }}<br>
        {{ $details['seller']['city'] ?? config('oasis.operator.city') }}<br>
        USt-IdNr.: {{ $details['seller']['vat_id'] ?? config('oasis.operator.vat_id') }}
    </address>
    <h2 class="o-h2-s">Rechnungsempfänger</h2>
    <p>{{ $details['customer_name'] ?? '' }}<br>{{ $details['customer_email'] ?? '' }}<br>
        {{ $details['billing_address']['streetAndNumber'] ?? '' }}<br>
        {{ $details['billing_address']['postalCode'] ?? '' }} {{ $details['billing_address']['city'] ?? '' }}
    </p>
    <p>{{ $details['description'] ?? 'Oasis Mitgliedschaft' }} · {{ $payment->kind === 'gift' || $payment->rhythm === 'jahr' ? '12 Monate' : '1 Monat' }}</p>
    <dl>
        <dt>Gesamtbetrag (EUR)</dt><dd>{{ \App\Support\OasisFormat::euro($payment->amount / 100) }}</dd>
        <dt>Enthaltene Umsatzsteuer ({{ $details['vat_rate'] ?? config('oasis.vat_rate') }} %)</dt>
        <dd>{{ \App\Support\OasisFormat::euro(($payment->amount / 100) * ($details['vat_rate'] ?? config('oasis.vat_rate')) / (100 + ($details['vat_rate'] ?? config('oasis.vat_rate')))) }}</dd>
    </dl>
    <p>Du kannst diese Rechnung über die Druckfunktion deines Browsers speichern.</p>
    <a class="o-link" href="{{ route('app.membership.portal') }}">Zur Zahlungsverwaltung</a>
</article>
@endsection
