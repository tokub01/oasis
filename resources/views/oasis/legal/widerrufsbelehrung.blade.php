{{--
    Widerrufsbelehrung (draft): route oasis.widerrufsbelehrung, frame from legal/_frame.

    Statutory model text for service contracts (Anlage 1 zu Art. 246a § 1 Abs. 2 Satz 2 EGBGB), including the
    paragraph for the online withdrawal function (Gestaltungshinweis 3, § 356a BGB): Oasis offers that function at
    oasis.widerrufen and confirms receipt with content, date and time (WithdrawalReceipt mail).
    The wording of the model must stay verbatim (formal "Sie") to keep its statutory protection; only the
    operator details are filled in from config('oasis.operator') and config('oasis.mail.contact').
    Draft pending legal review. No JavaScript required.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Widerrufsbelehrung', 'legalKey' => 'widerrufsbelehrung'])

@php
    $operator = config('oasis.operator');
    $contact = config('oasis.mail.contact');
    $address = $operator['legal_name'].', '.$operator['street'].', '.$operator['city'].', '.$operator['country'];
    $h2 = 'm-0 text-[20px] font-extrabold text-night';
@endphp

@section('legal-content')
    <div class="flex max-w-[44em] flex-col gap-[18px]">
        <p class="m-0 rounded-[14px] bg-dune px-[18px] py-[14px] text-[15px]">
            Entwurf – vor dem Start anwaltlich prüfen lassen. Der Text folgt dem gesetzlichen Muster.
        </p>

        <section class="flex flex-col gap-[10px]">
            <h2 class="{{ $h2 }}">Widerrufsrecht</h2>
            <p class="m-0">Sie haben das Recht, binnen vierzehn Tagen ohne Angabe von Gründen diesen Vertrag zu widerrufen.</p>
            <p class="m-0">Die Widerrufsfrist beträgt vierzehn Tage ab dem Tag des Vertragsabschlusses.</p>
            <p class="m-0">Um Ihr Widerrufsrecht auszuüben, müssen Sie uns ({{ $address }}, Telefon: {{ $operator['phone'] }}, E-Mail: {{ $contact }}) mittels einer eindeutigen Erklärung (z. B. ein mit der Post versandter Brief oder eine E-Mail) über Ihren Entschluss, diesen Vertrag zu widerrufen, informieren. Sie können dafür das beigefügte Muster-Widerrufsformular verwenden, das jedoch nicht vorgeschrieben ist.</p>
            <p class="m-0">Sie können Ihr Widerrufsrecht auch online unter <a href="{{ route('oasis.widerrufen') }}" class="font-bold text-night underline underline-offset-4">{{ route('oasis.widerrufen') }}</a> ausüben. Wenn Sie diese Online-Funktion nutzen, übermitteln wir Ihnen auf einem dauerhaften Datenträger (z. B. durch eine E-Mail) unverzüglich eine Eingangsbestätigung mit Informationen zum Inhalt der Widerrufserklärung sowie dem Datum und der Uhrzeit ihres Eingangs.</p>
            <p class="m-0">Zur Wahrung der Widerrufsfrist reicht es aus, dass Sie die Mitteilung über die Ausübung des Widerrufsrechts vor Ablauf der Widerrufsfrist absenden.</p>
        </section>

        <section class="flex flex-col gap-[10px]">
            <h2 class="{{ $h2 }}">Folgen des Widerrufs</h2>
            <p class="m-0">Wenn Sie diesen Vertrag widerrufen, haben wir Ihnen alle Zahlungen, die wir von Ihnen erhalten haben, einschließlich der Lieferkosten (mit Ausnahme der zusätzlichen Kosten, die sich daraus ergeben, dass Sie eine andere Art der Lieferung als die von uns angebotene, günstigste Standardlieferung gewählt haben), unverzüglich und spätestens binnen vierzehn Tagen ab dem Tag zurückzuzahlen, an dem die Mitteilung über Ihren Widerruf dieses Vertrags bei uns eingegangen ist. Für diese Rückzahlung verwenden wir dasselbe Zahlungsmittel, das Sie bei der ursprünglichen Transaktion eingesetzt haben, es sei denn, mit Ihnen wurde ausdrücklich etwas anderes vereinbart; in keinem Fall werden Ihnen wegen dieser Rückzahlung Entgelte berechnet.</p>
            <p class="m-0">Haben Sie verlangt, dass die Dienstleistungen während der Widerrufsfrist beginnen soll, so haben Sie uns einen angemessenen Betrag zu zahlen, der dem Anteil der bis zu dem Zeitpunkt, zu dem Sie uns von der Ausübung des Widerrufsrechts hinsichtlich dieses Vertrags unterrichten, bereits erbrachten Dienstleistungen im Vergleich zum Gesamtumfang der im Vertrag vorgesehenen Dienstleistungen entspricht.</p>
        </section>

        <a href="{{ route('oasis.widerrufen') }}" class="o-btn o-btn-md o-btn-outline self-start text-base font-extrabold">Vertrag widerrufen</a>

        <section class="flex flex-col gap-[10px] rounded-[20px] border border-paper-line p-6">
            <h2 class="{{ $h2 }}">Muster-Widerrufsformular</h2>
            <p class="m-0">(Wenn Sie den Vertrag widerrufen wollen, dann füllen Sie bitte dieses Formular aus und senden Sie es zurück.)</p>
            <p class="m-0">– An {{ $address }}, E-Mail: {{ $contact }}:</p>
            <p class="m-0">– Hiermit widerrufe(n) ich/wir (*) den von mir/uns (*) abgeschlossenen Vertrag über den Kauf der folgenden Waren (*)/die Erbringung der folgenden Dienstleistung (*)</p>
            <p class="m-0">– Bestellt am (*)/erhalten am (*)</p>
            <p class="m-0">– Name des/der Verbraucher(s)</p>
            <p class="m-0">– Anschrift des/der Verbraucher(s)</p>
            <p class="m-0">– Unterschrift des/der Verbraucher(s) (nur bei Mitteilung auf Papier)</p>
            <p class="m-0">– Datum</p>
            <p class="m-0 text-[15px] text-muted">(*) Unzutreffendes streichen.</p>
        </section>
    </div>
@endsection
