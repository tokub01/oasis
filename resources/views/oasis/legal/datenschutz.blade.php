{{--
    Datenschutzerklärung (draft): route oasis.datenschutz, frame from legal/_frame.

    Describes exactly the processing the website performs today: server logs, the session/CSRF cookie, the five
    forms (waitlist with double opt-in, cancellation, withdrawal, content report, organisation request) and the
    confirmation mails. No analytics, no ads, no external fonts or scripts. Accounts, posts and payments of the
    future app are not covered yet and must be added before that launch.

    Facts come from config('oasis.operator'), config('oasis.mail') and config('oasis.privacy'); an open (null)
    value renders "[noch festzulegen]". Draft pending legal review. No JavaScript required.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Datenschutzerklärung', 'legalKey' => 'datenschutz'])

@php
    $operator = config('oasis.operator');
    $privacy = config('oasis.privacy');
    $privacyMail = config('oasis.mail.privacy');
    $open = fn ($value, string $suffix = '') => $value === null ? '[noch festzulegen]' : $value.$suffix;
    $h2 = 'm-0 text-[20px] font-extrabold text-night';
@endphp

@section('legal-content')
    <div class="flex max-w-[44em] flex-col gap-[22px]">
        <p class="m-0 rounded-[14px] bg-dune px-[18px] py-[14px] text-[15px]">
            Entwurf – vor dem Start anwaltlich prüfen lassen. Stand: {{ $privacy['as_of'] }}.
        </p>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">1. Wer für deine Daten verantwortlich ist</h2>
            <p class="m-0">
                {{ $operator['legal_name'] }}, {{ $operator['street'] }}, {{ $operator['city'] }}, {{ $operator['country'] }}<br>
                E-Mail: {{ $privacyMail }}, Telefon: {{ $operator['phone'] }}
            </p>
            <p class="m-0">Einen Datenschutzbeauftragten haben wir nicht benannt, weil wir dazu nicht verpflichtet sind. Für alle Fragen zum Datenschutz erreichst du uns unter der Adresse oben.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">2. Kurz gesagt</h2>
            <p class="m-0">Wir verarbeiten nur die Daten, die für die Website und deine Anfragen nötig sind. Wir setzen keine Werbung, keine Analyse- oder Tracking-Werkzeuge und keine Inhalte von Drittanbietern ein. Schriften und Skripte liefern wir selbst aus. Wir verkaufen keine Daten.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">3. Aufruf der Website</h2>
            <p class="m-0">Wenn du eine Seite aufrufst, verarbeitet unser Server technisch notwendige Daten: IP-Adresse, Datum und Uhrzeit, aufgerufene Adresse, übertragene Datenmenge und die Angaben deines Browsers. Das ist nötig, um die Website auszuliefern und vor Angriffen zu schützen (Art. 6 Abs. 1 lit. f DSGVO). Diese Server-Logdateien löschen wir nach {{ $open($privacy['server_log_days'], ' Tagen') }}.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">4. Cookies</h2>
            <p class="m-0">Wir setzen nur zwei technisch notwendige Cookies: ein Sitzungs-Cookie, damit Formulare und ihre Bestätigungsseiten funktionieren, und ein Sicherheits-Cookie gegen gefälschte Formularanfragen (CSRF). Beide werden nach {{ config('session.lifetime') }} Minuten ohne Aktivität ungültig. Sie sind für den von dir gewünschten Dienst unbedingt erforderlich (§ 25 Abs. 2 Nr. 2 TDDDG, Art. 6 Abs. 1 lit. f DSGVO); eine Einwilligung ist dafür nicht nötig. Tracking- oder Werbe-Cookies setzen wir nicht.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">5. Startliste für Gründungsmitglieder</h2>
            <p class="m-0">Wenn du dich auf die Startliste setzt, speichern wir deine E-Mail-Adresse, optional deine Gruppe, den Zeitpunkt deiner Einwilligung und ob du die Adresse bestätigt hast. Wir schicken dir zuerst eine E-Mail mit einem Bestätigungslink (Double-Opt-in); erst danach stehst du auf der Liste. Rechtsgrundlage ist deine Einwilligung (Art. 6 Abs. 1 lit. a DSGVO), die du jederzeit über den Abmeldelink in jeder E-Mail widerrufen kannst. Dann löschen wir deinen Eintrag. Nicht bestätigte Einträge löschen wir nach {{ $open($privacy['unconfirmed_waitlist_days'], ' Tagen') }}.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">6. Kündigen und Widerrufen</h2>
            <p class="m-0">Wenn du über „Verträge hier kündigen“ kündigst oder einen Vertrag widerrufst, verarbeiten wir deinen Namen, deine E-Mail-Adresse, optional deinen Benutzernamen sowie die Angaben zu deiner Erklärung (Vertrag, Art und Zeitpunkt der Kündigung, gegebenenfalls Grund oder Abschlussdatum) und den Eingangszeitpunkt. Wir brauchen sie, um deine Erklärung umzusetzen und dir den Eingang sofort zu bestätigen (Art. 6 Abs. 1 lit. b und c DSGVO, § 312k BGB). Wir bewahren die Erklärung als Nachweis {{ $open($privacy['records_years'], ' Jahre') }} lang auf (Art. 6 Abs. 1 lit. f DSGVO).</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">7. Inhalte melden</h2>
            <p class="m-0">Wenn du Inhalte meldest, verarbeiten wir den Link zum Inhalt, deine Begründung, deinen Namen, deine E-Mail-Adresse und deine Bestätigung, dass die Angaben nach bestem Wissen stimmen. Wir prüfen damit die Meldung und informieren dich über das Ergebnis (Art. 6 Abs. 1 lit. c DSGVO in Verbindung mit Art. 16 Digital Services Act). Die Meldung bewahren wir {{ $open($privacy['records_years'], ' Jahre') }} lang als Nachweis auf. Deine Identität geben wir nicht an die gemeldete Person weiter, außer das Gesetz verlangt es.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">8. Anfragen von Organisationen</h2>
            <p class="m-0">Wenn deine Organisation gemeinsam starten möchte, verarbeiten wir den Namen der Organisation, die ungefähre Personenzahl und eine E-Mail-Adresse, um dir ein Angebot zu machen (Art. 6 Abs. 1 lit. b DSGVO). Kommt keine Zusammenarbeit zustande, löschen wir die Anfrage nach {{ $open($privacy['records_years'], ' Jahren') }}.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">9. Wer deine Daten außerdem erhält</h2>
            <p class="m-0">Wir geben deine Daten nur an Dienstleister weiter, die sie in unserem Auftrag und nach unseren Weisungen verarbeiten (Art. 28 DSGVO):</p>
            <ul class="m-0 flex list-disc flex-col gap-1 pl-5">
                <li>Hosting: {{ $privacy['hosting'] }}</li>
                <li>E-Mail-Versand: {{ $open($privacy['mail_provider']) }}</li>
            </ul>
            <p class="m-0">Wenn ein Dienstleister Daten außerhalb der EU verarbeitet, nennen wir hier die Garantie, auf der das beruht. Kopien deiner Kündigung, deines Widerrufs oder deiner Meldung erhält nur unser eigenes Team.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">10. Deine Rechte</h2>
            <p class="m-0">Du hast das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch gegen Verarbeitungen, die auf berechtigten Interessen beruhen (Art. 21). Eine Einwilligung kannst du jederzeit mit Wirkung für die Zukunft widerrufen (Art. 7 Abs. 3). Schreib uns dafür an {{ $privacyMail }}.</p>
            <p class="m-0">Du kannst dich außerdem bei einer Datenschutz-Aufsichtsbehörde beschweren (Art. 77 DSGVO), zum Beispiel bei der für uns zuständigen: {{ $privacy['authority'] }}.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">11. Automatisierte Entscheidungen</h2>
            <p class="m-0">Wir treffen keine Entscheidungen, die ausschließlich automatisiert sind, und bilden keine Profile über dich.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">12. Änderungen</h2>
            <p class="m-0">Diese Erklärung beschreibt die Website. Bevor Konten, Tropfen und Mitgliedschaften starten, ergänzen wir sie um diese Verarbeitungen. Die jeweils aktuelle Fassung findest du immer hier.</p>
        </section>
    </div>
@endsection
