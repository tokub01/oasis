{{--
    Erklärung zur Barrierefreiheit (draft): route oasis.barrierefreiheit, frame from legal/_frame. Describes the
    accessibility of the website and the app in the sense of § 14 BFSG and Anlage 3 (Barrierefreiheitsanforderungen an
    Dienstleistungen): target standard, state, known limits, feedback and the market surveillance authority.
    Facts come from config('oasis.accessibility'); open values render "[noch festzulegen]". Draft pending legal review.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Barrierefreiheit', 'legalKey' => 'barrierefreiheit'])

@php
    $a11y = config('oasis.accessibility');
    $contact = config('oasis.mail.contact');
    $open = fn ($value) => $value ?? '[noch festzulegen]';
    $h2 = 'm-0 text-[20px] font-extrabold text-night';
@endphp

@section('legal-content')
    <div class="flex max-w-[44em] flex-col gap-[22px]">
        <p class="m-0 rounded-[14px] bg-dune px-[18px] py-[14px] text-[15px]">Entwurf – vor dem Start anwaltlich prüfen lassen. Stand: {{ $a11y['as_of'] }}.</p>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">Unser Anspruch</h2>
            <p class="m-0">Oasis soll für alle Menschen nutzbar sein – mit Tastatur, Screenreader, Vergrößerung oder auf einem kleinen Handy. Wir richten uns nach den Web Content Accessibility Guidelines (WCAG) 2.1 auf Stufe AA und der europäischen Norm EN 301 549. Das gilt für die Website, die App im Browser, den Abschluss der Mitgliedschaft und alle Formulare.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">Wie barrierefrei Oasis ist</h2>
            <p class="m-0">Oasis ist <strong>{{ $a11y['status'] }}</strong>. Grundlage ist eine Selbstbewertung vom {{ $a11y['assessed_on'] }}: automatische Prüfungen bei jeder Änderung und manuelle Tests aller Seiten mit Tastatur und auf Bildschirmbreiten von 320 bis 1.440 Pixeln.</p>
            <ul class="m-0 flex list-disc flex-col gap-1 pl-5">
                <li>Alle Funktionen sind mit der Tastatur erreichbar, der Fokus ist immer sichtbar.</li>
                <li>Überschriften, Beschriftungen und Fehlermeldungen sind für Screenreader ausgezeichnet; Änderungen werden angesagt.</li>
                <li>Texte haben ausreichenden Kontrast, Schaltflächen eine Mindestgröße von 44 Pixeln.</li>
                <li>Die Seiten passen sich bis 320 Pixel Breite an und funktionieren mit 200 % Vergrößerung.</li>
                <li>Wer ein Bild teilt, muss es beschreiben; Bilder ohne Beschreibung lassen sich nicht veröffentlichen.</li>
                <li>Bewegung wird reduziert, wenn das im System eingestellt ist.</li>
            </ul>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">Was noch nicht barrierefrei ist</h2>
            <ul class="m-0 flex list-disc flex-col gap-1 pl-5">
                <li>Der verschlüsselte Chat braucht JavaScript, weil die Verschlüsselung in deinem Browser passiert. Alles andere funktioniert auch ohne.</li>
                <li>Die Bezahlseite stellt unser Zahlungsdienstleister Mollie bereit. Für ihre Barrierefreiheit ist Mollie verantwortlich; bei Problemen helfen wir dir, die Mitgliedschaft anders abzuschließen.</li>
                <li>Bilder, die Mitglieder teilen, beschreiben die Mitglieder selbst. Wir können nicht garantieren, dass jede Beschreibung hilfreich ist.</li>
                <li>Eine Prüfung durch eine unabhängige Stelle steht noch aus.</li>
            </ul>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">Barriere gefunden?</h2>
            <p class="m-0">Schreib uns an <a class="o-link-inline" href="mailto:{{ $contact }}?subject=Barriere%20bei%20Oasis">{{ $contact }}</a> oder ruf an unter {{ config('oasis.operator.phone') }}. Beschreibe kurz, auf welcher Seite und womit (z. B. Screenreader, Tastatur) du nicht weiterkommst. Wir antworten innerhalb von {{ config('oasis.support_reply_days') }} Werktagen und helfen dir in der Zwischenzeit auf einem anderen Weg.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">Wenn wir nicht weiterhelfen</h2>
            <p class="m-0">Für die Einhaltung des Barrierefreiheitsstärkungsgesetzes ist die Marktüberwachungsbehörde zuständig: {{ $open($a11y['authority']) }}. Dort kannst du dich beschweren, wenn wir eine Barriere nicht beseitigen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">So funktioniert Oasis</h2>
            <p class="m-0">Oasis ist ein werbefreies soziales Netzwerk. Du kannst {{ config('oasis.trial_days') }} Tage kostenlos testen und danach eine Mitgliedschaft abschließen; was sie kostet und wie du kündigst, steht in den <a class="o-link-inline" href="{{ route('oasis.agb') }}">AGB</a> und unter <a class="o-link-inline" href="{{ route('oasis.preise') }}">Preise</a>. Wie wir mit deinen Daten umgehen, steht in der <a class="o-link-inline" href="{{ route('oasis.datenschutz') }}">Datenschutzerklärung</a>.</p>
        </section>
    </div>
@endsection
