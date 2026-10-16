{{--
    Datenschutzerklärung (draft): route oasis.datenschutz, frame from legal/_frame.

    Describes exactly the processing the site performs today: server logs, the server-side session and its two
    cookies, accounts (stage 0), the encrypted chat (stage 3, section 6), the five forms (waitlist with double opt-in, cancellation, withdrawal, content report,
    organisation request), direct emails and the confirmation mails. No analytics, no ads, no external fonts or
    scripts. Posts and payments of later stages must be added here before they go live.

    Retention periods come from config('oasis.privacy') and are enforced by the daily `model:prune` run
    (App\Models\Concerns\PrunedAfterRecordRetention, WaitlistEntry::prunable). An open (null) value renders
    "[noch festzulegen]" and nothing is deleted automatically until it is set. Draft pending legal review.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Datenschutzerklärung', 'legalKey' => 'datenschutz'])

@php
    $operator = config('oasis.operator');
    $privacy = config('oasis.privacy');
    $privacyMail = config('oasis.mail.privacy');
    $open = fn ($value, string $suffix = '') => $value === null ? '[noch festzulegen]' : $value.$suffix;
    $h2 = 'm-0 text-[20px] font-extrabold text-night';
    $required = 'm-0 text-[15px] text-ink-2';
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
            <p class="m-0">Wir verarbeiten nur die Daten, die für die Website, dein Konto und deine Anfragen nötig sind. Wir setzen keine Werbung, keine Analyse- oder Tracking-Werkzeuge und keine Inhalte von Drittanbietern ein. Schriften und Skripte liefern wir selbst aus. Wir verkaufen keine Daten.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">3. Aufruf der Website</h2>
            <p class="m-0">Wenn du eine Seite aufrufst, verarbeitet unser Server technisch notwendige Daten: IP-Adresse, Datum und Uhrzeit, aufgerufene Adresse, übertragene Datenmenge und die Angaben deines Browsers. Das ist nötig, um die Website auszuliefern und vor Angriffen zu schützen (Art. 6 Abs. 1 lit. f DSGVO). Diese Server-Logdateien löschen wir nach {{ $open($privacy['server_log_days'], ' Tagen') }}.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">4. Sitzung und Cookies</h2>
            <p class="m-0">Damit Formulare, Anmeldung und Bestätigungsseiten funktionieren, führen wir für deinen Besuch eine Sitzung auf unserem Server. Dazu speichern wir eine zufällige Sitzungskennung, deine IP-Adresse, die Angaben deines Browsers, den Zeitpunkt der letzten Aktivität und vorübergehend Inhalte, die eine Folgeseite braucht: zum Beispiel deine Eingaben nach einem Fehler oder deine Kündigung für die Bestätigungsseite.</p>
            <p class="m-0">In deinem Browser setzen wir dafür nur zwei technisch notwendige Cookies: die Sitzungskennung und ein Sicherheits-Cookie gegen gefälschte Formularanfragen (CSRF). Beide werden nach {{ config('session.lifetime') }} Minuten ohne Aktivität ungültig; abgelaufene Sitzungen löscht unser Server bei seiner regelmäßigen Bereinigung. Wenn du „Angemeldet bleiben“ wählst, setzen wir zusätzlich ein Cookie, das dich bis zur Abmeldung wiedererkennt. Diese Cookies sind für den von dir gewünschten Dienst unbedingt erforderlich (§ 25 Abs. 2 Nr. 2 TDDDG, Art. 6 Abs. 1 lit. b und f DSGVO); eine Einwilligung ist dafür nicht nötig. Tracking- oder Werbe-Cookies setzen wir nicht.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">5. Dein Konto</h2>
            <p class="m-0">Für dein Konto speichern wir Anzeigename, Benutzername, E-Mail-Adresse, Geburtsdatum, dein Passwort nur als sicheren Hash, den Zeitpunkt deiner Zustimmung zu AGB und Community-Regeln, das Ende deines Tests, ob deine E-Mail-Adresse bestätigt ist und, wenn du sie einschaltest, die Daten der Zwei-Faktor-Anmeldung (verschlüsselt). Wir brauchen diese Daten, um den Vertrag mit dir zu erfüllen (Art. 6 Abs. 1 lit. b DSGVO); das Geburtsdatum brauchen wir, um das Mindestalter von 16 Jahren zu prüfen.</p>
            <p class="{{ $required }}">Pflichtangaben sind alle Felder der Registrierung. Ohne sie können wir kein Konto anlegen. Du kannst alle Daten in den Einstellungen herunterladen und dein Konto dort jederzeit selbst löschen; dann löschen wir die Kontodaten sofort.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">6. Verschlüsselte Nachrichten</h2>
            <p class="m-0">Nachrichten werden in deinem Browser Ende-zu-Ende verschlüsselt. Den Inhalt können nur die Menschen im Chat lesen – nicht wir, nicht unser Hosting und nicht der Dienst für die Zustellung in Echtzeit. Auf unserem Server liegen nur verschlüsselte Nachrichten; auf deinem Gerät speichern wir nichts davon.</p>
            <p class="m-0">Damit der Chat funktioniert, verarbeiten wir: deine öffentlichen Chat-Schlüssel, deine privaten Chat-Schlüssel nur verschlüsselt mit deiner Chat-Passphrase (die Passphrase selbst erhalten wir nie), den Zeitpunkt eines Schlüsselwechsels, wer mit wem in welchem Chat ist, Gruppennamen, die eingestellte Löschfrist sowie zu jeder Nachricht Absender, Zeitpunkt, Größe und Ablaufzeit. Diese Begleitdaten können wir sehen, den Inhalt nicht. Rechtsgrundlage ist der Vertrag mit dir (Art. 6 Abs. 1 lit. b DSGVO).</p>
            <p class="m-0">Jede Nachricht löscht sich nach der im Chat eingestellten Frist (1, 7 oder 30 Tage) automatisch. Deine Chat-Schlüssel löschen wir mit deinem Konto. Verlässt du einen Chat oder wirst entfernt, kannst du neue Nachrichten darin nicht mehr lesen.</p>
            <p class="m-0">Meldest du eine Nachricht, schickst du uns genau diese eine Nachricht unverschlüsselt, zusammen mit deiner Begründung, dem Absender und den Zeitpunkten. Wir prüfen damit die Meldung (Art. 6 Abs. 1 lit. c DSGVO in Verbindung mit Art. 16 Digital Services Act) und bewahren sie {{ $open($privacy['records_years'], ' Jahre') }} lang als Nachweis auf; danach löschen wir sie automatisch. Andere Nachrichten aus dem Chat erhalten wir dadurch nicht.</p>
            <p class="{{ $required }}">Der Chat ist freiwillig. Ohne Chat-Schlüssel kannst du ihn nicht nutzen; alles andere bei Oasis funktioniert auch ohne.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">7. Startliste für Gründungsmitglieder</h2>
            <p class="m-0">Wenn du dich auf die Startliste setzt, speichern wir deine E-Mail-Adresse, optional deine Gruppe, den Zeitpunkt deiner Einwilligung, einen zufälligen Schlüssel für die Links in unseren E-Mails und den Zeitpunkt deiner Bestätigung. Wir schicken dir zuerst eine E-Mail mit einem Bestätigungslink (Double-Opt-in); erst danach stehst du auf der Liste. Rechtsgrundlage ist deine Einwilligung (Art. 6 Abs. 1 lit. a DSGVO). Du kannst sie jederzeit über den Abmeldelink in jeder E-Mail widerrufen; dann löschen wir deinen Eintrag. Der Widerruf gilt für die Zukunft; die bis dahin erfolgte Verarbeitung bleibt rechtmäßig.</p>
            <p class="m-0">Nicht bestätigte Einträge löschen wir automatisch nach {{ $open($privacy['unconfirmed_waitlist_days'], ' Tagen') }}. Bestätigte Einträge brauchen wir nur für die Nachrichten rund um den Start; wir löschen sie spätestens {{ $open($privacy['confirmed_waitlist_months'], ' Monate') }} nach dem Start.</p>
            <p class="{{ $required }}">Pflichtangaben: E-Mail-Adresse und Einwilligung. Die Gruppe ist freiwillig. Ohne Pflichtangaben können wir dich nicht auf die Liste setzen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">8. Kündigen und Widerrufen</h2>
            <p class="m-0">Wenn du über „Verträge hier kündigen“ kündigst oder einen Vertrag widerrufst, verarbeiten wir deinen Namen, deine E-Mail-Adresse, optional deinen Benutzernamen sowie die Angaben zu deiner Erklärung (Vertrag, Art und Zeitpunkt der Kündigung, gegebenenfalls Grund oder Abschlussdatum) und den Eingangszeitpunkt. Wir brauchen sie, um deine Erklärung umzusetzen und dir den Eingang sofort zu bestätigen (Art. 6 Abs. 1 lit. b und c DSGVO, §§ 312k, 356a BGB). Als Nachweis bewahren wir die Erklärung {{ $open($privacy['records_years'], ' Jahre') }} lang auf und löschen sie danach automatisch (Art. 6 Abs. 1 lit. f DSGVO).</p>
            <p class="{{ $required }}">Pflichtangaben im Formular: Name, E-Mail-Adresse, Vertrag, Art und Zeitpunkt; bei einer außerordentlichen Kündigung der Grund. Ohne sie können wir die Erklärung über dieses Formular nicht zuordnen. Du kannst auch jederzeit per E-Mail oder Brief kündigen oder widerrufen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">9. Inhalte melden</h2>
            <p class="m-0">Wenn du Inhalte meldest, verarbeiten wir den Link zum Inhalt, deine Begründung, deinen Namen, deine E-Mail-Adresse, deine Bestätigung, dass die Angaben nach bestem Wissen stimmen, und den Eingangszeitpunkt. Wir prüfen damit die Meldung und informieren dich über das Ergebnis (Art. 6 Abs. 1 lit. c DSGVO in Verbindung mit Art. 16 Digital Services Act). Die Meldung bewahren wir {{ $open($privacy['records_years'], ' Jahre') }} lang als Nachweis auf und löschen sie danach automatisch. Deine Identität geben wir nicht an die gemeldete Person weiter, außer das Gesetz verlangt es.</p>
            <p class="{{ $required }}">Pflichtangaben: Link, Begründung, Name, E-Mail-Adresse und die Bestätigung. Sie entsprechen den Angaben, die eine Meldung nach Art. 16 DSA enthalten soll; ohne sie können wir die Meldung über dieses Formular nicht bearbeiten.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">10. Anfragen von Organisationen</h2>
            <p class="m-0">Wenn eine Organisation gemeinsam starten möchte, verarbeiten wir den Namen der Organisation, die ungefähre Personenzahl und die angegebene E-Mail-Adresse, um ein Angebot zu machen. Fragst du im eigenen Namen an, ist die Rechtsgrundlage die Vorbereitung eines Vertrags mit dir (Art. 6 Abs. 1 lit. b DSGVO). Fragst du für eine Organisation an, verarbeiten wir deine Kontaktdaten aufgrund unseres berechtigten Interesses, geschäftliche Anfragen zu beantworten (Art. 6 Abs. 1 lit. f DSGVO). Kommt keine Zusammenarbeit zustande, löschen wir die Anfrage automatisch nach {{ $open($privacy['records_years'], ' Jahren') }}.</p>
            <p class="{{ $required }}">Pflichtangaben: Organisation, Personenzahl und E-Mail-Adresse. Ohne sie können wir kein Angebot machen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">11. Wenn du uns schreibst</h2>
            <p class="m-0">Schreibst du uns direkt eine E-Mail, verarbeiten wir deine Adresse, deinen Namen, den Inhalt und eventuelle Anhänge, um dein Anliegen zu bearbeiten (Art. 6 Abs. 1 lit. b DSGVO bei Fragen zu deinem Vertrag, sonst lit. f; bei Anfragen zu deinen Datenschutzrechten lit. c). Wir löschen die Nachricht, wenn dein Anliegen erledigt ist und keine Aufbewahrungspflicht besteht.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">12. Wer deine Daten außerdem erhält</h2>
            <p class="m-0">Wir geben deine Daten nur an Dienstleister weiter, die sie in unserem Auftrag und nach unseren Weisungen verarbeiten (Art. 28 DSGVO):</p>
            <ul class="m-0 flex list-disc flex-col gap-1 pl-5">
                <li>Hosting: {{ $privacy['hosting'] }}</li>
                <li>E-Mail-Versand und -Empfang: {{ $open($privacy['mail_provider']) }}</li>
                <li>Automatische Prüfung öffentlicher Texte auf Regelverstöße: {{ $privacy['ai_provider'] }} – erhält nur den jeweiligen Text, keine Namen, Benutzernamen oder anderen Angaben zu deinem Konto</li>
                <li>Zustellung von Chat-Nachrichten in Echtzeit: {{ $privacy['realtime_provider'] }} – erhält nur verschlüsselte Nachrichten, Chat-Nummern und technische Verbindungsdaten wie deine IP-Adresse</li>
                <li>Zahlungsabwicklung und Abrechnung: Stripe Payments Europe, Ltd., 1 Grand Canal Street Lower, Grand Canal Dock, Dublin, D02 H210, Irland (Muttergesellschaft: Stripe, Inc., USA) – verarbeitet Zahlungs- und Abrechnungsdaten (z. B. Kartendaten, IBAN, Rechnungsadresse) zur Abwicklung der Mitgliedsbeiträge (Art. 6 Abs. 1 lit. b DSGVO) sowie zur Erfüllung gesetzlicher steuer- und handelsrechtlicher Aufbewahrungspflichten (Art. 6 Abs. 1 lit. c DSGVO). Für eventuelle Datenübermittlungen an die US-Muttergesellschaft gelten EU-Standardvertragsklauseln (Art. 46 Abs. 2 lit. c DSGVO) und der Angemessenheitsbeschluss zum EU-US Data Privacy Framework (Art. 45 DSGVO).</li>
            </ul>
            <p class="m-0">Wenn ein Dienstleister Daten außerhalb der EU verarbeitet, nennen wir hier die Garantie, auf der das beruht. Kopien deiner Kündigung, deines Widerrufs oder deiner Meldung erhält nur unser eigenes Team. Auch diese Kopien im Postfach und Sicherungskopien löschen wir nach denselben Fristen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">13. Deine Rechte</h2>
            <p class="m-0">Du hast das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch gegen Verarbeitungen, die auf berechtigten Interessen beruhen (Art. 21). Eine Einwilligung kannst du jederzeit mit Wirkung für die Zukunft widerrufen (Art. 7 Abs. 3); die bis dahin erfolgte Verarbeitung bleibt rechtmäßig. Schreib uns dafür an {{ $privacyMail }}.</p>
            <p class="m-0">Du kannst dich außerdem bei einer Datenschutz-Aufsichtsbehörde beschweren (Art. 77 DSGVO), zum Beispiel bei der für uns zuständigen: {{ $privacy['authority'] }}.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">14. Automatische Prüfung und Entscheidungen</h2>
            <p class="m-0">Tropfen, Antworten, Profiltexte und Texte von Oasen prüfen wir automatisch mit einem Sprachmodell auf Beleidigungen, Hass, Drohungen und sexuelle Inhalte. Dafür geht nur der Text an unseren Dienstleister in der EU (siehe Abschnitt 12); gespeichert werden bei uns nur die Einschätzung (Werte je Kategorie und eine kurze Begründung) und der Zeitpunkt – {{ $open($privacy['records_years'], ' Jahre') }} lang, wie andere Moderationsunterlagen. Rechtsgrundlage ist unser berechtigtes Interesse, die Community-Regeln durchzusetzen und rechtswidrige Inhalte zu erkennen (Art. 6 Abs. 1 lit. f DSGVO), sowie die Pflichten aus dem Digital Services Act (Art. 6 Abs. 1 lit. c DSGVO).</p>
            <p class="m-0">Klingt ein Tropfen verletzend, zeigen wir dir vor dem Absenden einen Hinweis; du entscheidest selbst, ob du ihn änderst. Fällt ein Text auf, landet er beim Moderationsteam. Eindeutig sexuelle Tropfen blenden wir bis zur Prüfung vorläufig aus und sagen dir das. Entscheidungen über Konsequenzen trifft immer ein Mensch – wir treffen keine Entscheidungen, die ausschließlich automatisiert sind (Art. 22 DSGVO), und bilden keine Profile über dich. Verschlüsselte Chat-Nachrichten werden nicht geprüft; wir können sie nicht lesen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">15. Änderungen</h2>
            <p class="m-0">Diese Erklärung beschreibt die Website und die Konten. Bevor Tropfen, Nachrichten und Mitgliedschaften starten, ergänzen wir sie um diese Verarbeitungen. Die jeweils aktuelle Fassung findest du immer hier.</p>
        </section>
    </div>
@endsection
