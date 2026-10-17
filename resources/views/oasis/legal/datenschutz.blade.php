{{--
    Datenschutzerklärung (draft): route oasis.datenschutz, frame from legal/_frame.

    Describes exactly the processing the app performs today: server logs, session and cookies, the realtime
    connection, accounts, profiles, Tropfen, oases, privacy settings, hints and mails, membership and payment
    (Mollie), the encrypted chat, the five public forms, automated content checks and moderation, the service
    providers and the end of an account. No analytics, no ads, no external fonts or scripts. A new kind of
    processing must be added here before it goes live. The review notes for the lawyer are in docs/oasis-legal-review.md.

    Retention periods come from config('oasis.privacy') and are enforced by the daily `model:prune` run
    (App\Models\Concerns\PrunedAfterRecordRetention, WaitlistEntry::prunable, ContentCheck). An open (null) value
    renders "[noch festzulegen]" and nothing is deleted automatically until it is set. Draft pending legal review.
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
            <p class="m-0">Wir verarbeiten nur die Daten, die für die Website, dein Konto, deine Mitgliedschaft und deine Anfragen nötig sind. Wir setzen keine Werbung, keine Analyse- oder Tracking-Werkzeuge und keine Inhalte von Drittanbietern ein. Schriften und Skripte liefern wir selbst aus. Wir bilden keine Werbeprofile und verkaufen keine Daten. Was andere von dir sehen, bestimmst du in deinen Privatsphäre-Einstellungen; ab Werk ist alles nur für Mitglieder sichtbar.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">3. Aufruf der Website</h2>
            <p class="m-0">Wenn du eine Seite aufrufst, verarbeitet unser Server technisch notwendige Daten: IP-Adresse, Datum und Uhrzeit, aufgerufene Adresse, übertragene Datenmenge und die Angaben deines Browsers. Das ist nötig, um die Website auszuliefern und vor Angriffen zu schützen (Art. 6 Abs. 1 lit. f DSGVO). Diese Server-Logdateien löschen wir nach {{ $open($privacy['server_log_days'], ' Tagen') }}. Gibt ein Logeintrag Hinweise auf einen Angriff, bewahren wir ihn auf, bis der Vorfall geklärt ist.</p>
            <p class="m-0">Zum Schutz vor Missbrauch begrenzen wir, wie oft Anmeldung, Registrierung, Passwort-Zurücksetzen und unsere Formulare in kurzer Zeit genutzt werden können. Dafür merken wir uns deine IP-Adresse – bei der Anmeldung zusammen mit der eingegebenen E-Mail-Adresse – für wenige Minuten (Art. 6 Abs. 1 lit. f DSGVO).</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">4. Sitzung, Cookies und Echtzeit-Verbindung</h2>
            <p class="m-0">Damit Formulare, Anmeldung und Bestätigungsseiten funktionieren, führen wir für deinen Besuch eine Sitzung auf unserem Server. Dazu speichern wir eine zufällige Sitzungskennung, deine IP-Adresse, die Angaben deines Browsers, den Zeitpunkt der letzten Aktivität und vorübergehend Inhalte, die eine Folgeseite braucht: zum Beispiel deine Eingaben nach einem Fehler oder deine Kündigung für die Bestätigungsseite.</p>
            <p class="m-0">In deinem Browser setzen wir dafür nur zwei technisch notwendige Cookies: die Sitzungskennung und ein Sicherheits-Cookie gegen gefälschte Formularanfragen (CSRF). Beide werden nach {{ config('session.lifetime') }} Minuten ohne Aktivität ungültig; abgelaufene Sitzungen löscht unser Server bei seiner regelmäßigen Bereinigung. Wenn du „Angemeldet bleiben“ wählst, setzen wir zusätzlich ein Cookie, das dich bis zur Abmeldung wiedererkennt. Diese Cookies sind für den von dir gewünschten Dienst unbedingt erforderlich (§ 25 Abs. 2 Nr. 2 TDDDG, Art. 6 Abs. 1 lit. b und f DSGVO); eine Einwilligung ist dafür nicht nötig. Tracking- oder Werbe-Cookies setzen wir nicht.</p>
            <p class="m-0">Bist du angemeldet, hält dein Browser eine verschlüsselte Verbindung zu unserem eigenen Echtzeit-Server. Darüber kommen neue Chat-Nachrichten sofort an. Dabei verarbeiten wir deine IP-Adresse und deine Konto-Kennung nur für die Dauer der Verbindung (Art. 6 Abs. 1 lit. b DSGVO).</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">5. Dein Konto</h2>
            <p class="m-0">Für dein Konto speichern wir Anzeigename, Benutzername, E-Mail-Adresse, Geburtsdatum, dein Passwort nur als sicheren Hash, den Zeitpunkt deiner Zustimmung zu AGB und Community-Regeln, das Ende deines Tests, ob deine E-Mail-Adresse bestätigt ist und, wenn du sie einschaltest, die Daten der Zwei-Faktor-Anmeldung (verschlüsselt). Wir brauchen diese Daten, um den Vertrag mit dir zu erfüllen (Art. 6 Abs. 1 lit. b DSGVO); das Geburtsdatum brauchen wir, um das Mindestalter von 16 Jahren zu prüfen.</p>
            <p class="m-0">Wir schicken dir die E-Mails, die zum Konto gehören: die Bestätigung deiner Adresse, einen Link zum Zurücksetzen des Passworts (60 Minuten gültig) und eine Erinnerung kurz vor dem Ende deines Tests.</p>
            <p class="m-0">Zum Schutz deines Kontos merken wir uns, mit welchem Browser und System du dich anmeldest (zum Beispiel „Firefox auf Windows“) und wann zuletzt – ohne IP-Adresse. Meldet sich jemand mit einem Gerät an, das wir bei dir noch nicht kennen, schicken wir dir eine E-Mail, solange du das nicht abschaltest. Ein Gerät, mit dem du dich ein Jahr lang nicht angemeldet hast, vergessen wir automatisch. Unter „Geräte und Anmeldungen“ siehst du außerdem deine laufenden Sitzungen mit Browser, System und gekürzter IP-Adresse und kannst sie beenden. Rechtsgrundlage ist unser berechtigtes Interesse, Konten vor fremdem Zugriff zu schützen (Art. 6 Abs. 1 lit. f, Art. 32 DSGVO).</p>
            <p class="{{ $required }}">Pflichtangaben sind alle Felder der Registrierung. Ohne sie können wir kein Konto anlegen. Du kannst alle Daten in den Einstellungen herunterladen und dein Konto dort jederzeit selbst löschen (siehe Abschnitt 18).</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">6. Profil, Tropfen und Oasen</h2>
            <p class="m-0">Oasis ist ein soziales Netzwerk. Damit es funktioniert, speichern wir, was du dort tust:</p>
            <ul class="m-0 flex list-disc flex-col gap-1 pl-5">
                <li>Profil: deine freiwillige Beschreibung, deinen Ort und deine Profilfarbe.</li>
                <li>Tropfen: Text, Zeitpunkt, die gewählte Sichtbarkeit und gegebenenfalls die Oase, die Antwort oder das Weitertragen, zu dem der Tropfen gehört. Hochgeladene Bilder speichern wir mit ihrer Bildbeschreibung und Größe; Standort- und Kameradaten in der Bilddatei entfernen wir beim Hochladen.</li>
                <li>Was du mit anderen teilst: wem und welchen Oasen du folgst, Reaktionen auf Tropfen, deine Mitgliedschaften und Rollen in Oasen und von dir verschickte Einladungen.</li>
                <li>Was nur du siehst: gemerkte Tropfen, stummgeschaltete, eingeschränkte und blockierte Personen und deine verborgenen Wörter. Niemand erfährt, dass du jemanden eingeschränkt hast oder welche Wörter du verbirgst.</li>
                <li>Abstimmungen der Gemeinschaft: nur dass du teilgenommen hast und an welchem Tag – nie, wofür du gestimmt hast.</li>
            </ul>
            <p class="m-0">Rechtsgrundlage ist der Vertrag mit dir (Art. 6 Abs. 1 lit. b DSGVO). Deine Statistik berechnen wir nur aus diesen Daten (zum Beispiel Antworten und Reaktionen); wir erfassen nicht, wer welche Tropfen liest. Einen Tropfen kannst du jederzeit löschen; dann löschen wir ihn mit seinen Bildern sofort. Antworten anderer auf ihn bleiben als deren Inhalte bestehen und zeigen „Tropfen nicht mehr verfügbar“.</p>
            <p class="{{ $required }}">Alle Angaben in diesem Abschnitt sind freiwillig. Bilder brauchen eine Beschreibung, damit Menschen mit Screenreader sie verstehen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">7. Wer was sieht: deine Privatsphäre-Einstellungen</h2>
            <p class="m-0">In den Privatsphäre-Einstellungen bestimmst du, wer dein Profil sieht, wer deine Tropfen standardmäßig liest, wer die Größe deiner Karawane, deinen Ort und deine Oasen sieht, wer dich erwähnen, dir antworten, deine Tropfen weitertragen und dir schreiben darf. Ab Werk ist das jeweils „Alle Mitglieder“ (Datenschutz durch Voreinstellung, Art. 25 Abs. 2 DSGVO). Einzelne Tropfen kannst du beim Schreiben öffentlich machen; öffentliche Tropfen kann jede Person ohne Konto über ihren Link lesen. Mit „Frühere Tropfen begrenzen“ machst du alle öffentlichen Tropfen auf einmal wieder nur für Mitglieder sichtbar. Bist du unter 18, ist bis zu deinem 18. Geburtstag nichts öffentlich: Profil, Tropfen, Karawane, Ort und Oasen sehen höchstens Mitglieder, Suchmaschinen nichts, und schreiben dürfen dir nur Menschen, denen du folgst.</p>
            <p class="m-0">Andere Mitglieder finden dich in der Oasis-Suche über deinen Namen oder Benutzernamen, solange du das nicht ausschaltest. Suchmaschinen dürfen dein Profil nur aufnehmen, wenn du das ausdrücklich einschaltest und dein Profil für alle sichtbar ist; ab Werk ist das aus. Was einmal in einer Suchmaschine steht, verschwindet dort erst, wenn die Suchmaschine ihren Index aktualisiert.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">8. Hinweise und E-Mails</h2>
            <p class="m-0">Wenn dir jemand folgt, dich erwähnt, dir antwortet, deinen Tropfen weiterträgt, deine Einladung annimmt oder dich in einer Oase zur Gastgeber:in macht, bekommst du einen Hinweis in der App. Dafür speichern wir, wer was wann getan hat. Hinweise löschen wir nach 90 Tagen automatisch. In den Einstellungen wählst du, welche Hinweise du bekommst (Art. 6 Abs. 1 lit. b DSGVO).</p>
            <p class="m-0">Eine tägliche E-Mail-Zusammenfassung schicken wir nur, wenn du sie selbst einschaltest. Sie enthält die Zahl neuer Hinweise und einen Link, aber keine Texte von Tropfen. Du kannst sie jederzeit wieder ausschalten. Newsletter oder Werbe-E-Mails verschicken wir nicht.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">9. Mitgliedschaft und Zahlung</h2>
            <p class="m-0">Für deine Mitgliedschaft speichern wir Stufe, Zahlweise (monatlich oder jährlich), Beitrag, Status, das Ende des bezahlten Zeitraums, ob du Gründungsmitglied mit Preisgarantie bist, die Art deines Zahlungsmittels mit den letzten vier Ziffern, die Kennungen der Zahlungen bei unserem Zahlungsdienstleister und für jede Zahlung die Angaben für den Beleg (dein Name, deine E-Mail-Adresse, Betrag, Umsatzsteuersatz und Leistung). Bei einer Geschenk-Mitgliedschaft speichern wir zusätzlich, wer sie gekauft und wer sie eingelöst hat. Rechtsgrundlage ist der Vertrag mit dir (Art. 6 Abs. 1 lit. b DSGVO).</p>
            <p class="m-0">Die Zahlung selbst wickelt Mollie B.V. ab (siehe Abschnitt 17). Deine vollständigen Karten- oder Kontodaten gibst du direkt bei Mollie ein; sie erreichen uns nicht.</p>
            <p class="m-0">Zahlungsbelege müssen wir nach Steuer- und Handelsrecht aufbewahren (§ 147 AO, § 257 HGB): {{ $open($privacy['payment_records_years'], ' Jahre') }} lang, gerechnet ab dem Ende des Kalenderjahres der Zahlung; danach löschen wir sie automatisch. Diese Pflicht gilt auch, wenn du dein Konto löschst. Die Belege verlieren dann die Verbindung zu deinem Konto und sind für jede andere Nutzung gesperrt: Nur unsere Administration kann sie für Buchhaltung und Rückfragen zu Zahlungen aufrufen (Art. 6 Abs. 1 lit. c, Art. 17 Abs. 3 lit. b DSGVO). Bestellvorgänge, die nie bezahlt wurden, löschen wir nach 90 Tagen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">10. Verschlüsselte Nachrichten</h2>
            <p class="m-0">Nachrichten werden in deinem Browser Ende-zu-Ende verschlüsselt. Den Inhalt können nur die Menschen im Chat lesen – nicht wir und nicht unser Hosting. Auf unserem Server liegen nur verschlüsselte Nachrichten, und auch die Zustellung in Echtzeit läuft auf unserem eigenen Server; auf deinem Gerät speichern wir nichts davon.</p>
            <p class="m-0">Damit der Chat funktioniert, verarbeiten wir: deine öffentlichen Chat-Schlüssel, deine privaten Chat-Schlüssel nur verschlüsselt mit deiner Chat-Passphrase (die Passphrase selbst erhalten wir nie), den Zeitpunkt eines Schlüsselwechsels, wer mit wem in welchem Chat ist, Gruppennamen, die eingestellte Löschfrist sowie zu jeder Nachricht Absender, Zeitpunkt, Größe und Ablaufzeit. Diese Begleitdaten können wir sehen, den Inhalt nicht. Rechtsgrundlage ist der Vertrag mit dir (Art. 6 Abs. 1 lit. b DSGVO).</p>
            <p class="m-0">Jede Nachricht löscht sich nach der im Chat eingestellten Frist (1, 7 oder 30 Tage) automatisch. Deine Chat-Schlüssel löschen wir mit deinem Konto. Verlässt du einen Chat oder wirst entfernt, kannst du neue Nachrichten darin nicht mehr lesen.</p>
            <p class="m-0">Meldest du eine Nachricht, schickst du uns genau diese eine Nachricht unverschlüsselt, zusammen mit deiner Begründung, dem Absender und den Zeitpunkten. Wir prüfen damit die Meldung (Art. 6 Abs. 1 lit. c DSGVO in Verbindung mit Art. 16 Digital Services Act) und bewahren sie {{ $open($privacy['records_years'], ' Jahre') }} lang als Nachweis auf; danach löschen wir sie automatisch. Andere Nachrichten aus dem Chat erhalten wir dadurch nicht.</p>
            <p class="{{ $required }}">Der Chat ist freiwillig. Ohne Chat-Schlüssel kannst du ihn nicht nutzen; alles andere bei Oasis funktioniert auch ohne.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">11. Startliste für Gründungsmitglieder</h2>
            <p class="m-0">Wenn du dich auf die Startliste setzt, speichern wir deine E-Mail-Adresse, optional deine Gruppe, den Zeitpunkt deiner Einwilligung, einen zufälligen Schlüssel für die Links in unseren E-Mails und den Zeitpunkt deiner Bestätigung. Wir schicken dir zuerst eine E-Mail mit einem Bestätigungslink (Double-Opt-in); erst danach stehst du auf der Liste. Rechtsgrundlage ist deine Einwilligung (Art. 6 Abs. 1 lit. a DSGVO). Du kannst sie jederzeit über den Abmeldelink in jeder E-Mail widerrufen; dann löschen wir deinen Eintrag. Der Widerruf gilt für die Zukunft; die bis dahin erfolgte Verarbeitung bleibt rechtmäßig.</p>
            <p class="m-0">Nicht bestätigte Einträge löschen wir automatisch nach {{ $open($privacy['unconfirmed_waitlist_days'], ' Tagen') }}. Bestätigte Einträge brauchen wir nur für die Nachrichten rund um den Start; wir löschen sie spätestens {{ $open($privacy['confirmed_waitlist_months'], ' Monate') }} nach dem Start.</p>
            <p class="{{ $required }}">Pflichtangaben: E-Mail-Adresse und Einwilligung. Die Gruppe ist freiwillig. Ohne Pflichtangaben können wir dich nicht auf die Liste setzen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">12. Kündigen und Widerrufen</h2>
            <p class="m-0">Wenn du über „Verträge hier kündigen“ kündigst oder einen Vertrag widerrufst, verarbeiten wir deinen Namen, deine E-Mail-Adresse, optional deinen Benutzernamen sowie die Angaben zu deiner Erklärung (Vertrag, Art und Zeitpunkt der Kündigung, gegebenenfalls Grund oder Abschlussdatum) und den Eingangszeitpunkt. Wir brauchen sie, um deine Erklärung umzusetzen und dir den Eingang sofort zu bestätigen (Art. 6 Abs. 1 lit. b und c DSGVO, §§ 312k, 356a BGB). Als Nachweis bewahren wir die Erklärung {{ $open($privacy['records_years'], ' Jahre') }} lang auf und löschen sie danach automatisch (Art. 6 Abs. 1 lit. f DSGVO).</p>
            <p class="{{ $required }}">Pflichtangaben im Formular: Name, E-Mail-Adresse, Vertrag, Art und Zeitpunkt; bei einer außerordentlichen Kündigung der Grund. Ohne sie können wir die Erklärung über dieses Formular nicht zuordnen. Du kannst auch jederzeit per E-Mail oder Brief kündigen oder widerrufen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">13. Inhalte melden</h2>
            <p class="m-0">Wenn du Inhalte meldest, verarbeiten wir den Link zum Inhalt, deine Begründung, deinen Namen, deine E-Mail-Adresse, deine Bestätigung, dass die Angaben nach bestem Wissen stimmen, und den Eingangszeitpunkt. Wir prüfen damit die Meldung und informieren dich über das Ergebnis (Art. 6 Abs. 1 lit. c DSGVO in Verbindung mit Art. 16 Digital Services Act). Die Meldung bewahren wir {{ $open($privacy['records_years'], ' Jahre') }} lang als Nachweis auf und löschen sie danach automatisch. Deine Identität geben wir nicht an die gemeldete Person weiter, außer das Gesetz verlangt es.</p>
            <p class="{{ $required }}">Pflichtangaben: Link, Begründung, Name, E-Mail-Adresse und die Bestätigung. Sie entsprechen den Angaben, die eine Meldung nach Art. 16 DSA enthalten soll; ohne sie können wir die Meldung über dieses Formular nicht bearbeiten.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">14. Automatische Prüfung, Moderation und Beschwerden</h2>
            <p class="m-0">Tropfen, Antworten, Profiltexte und Texte von Oasen prüfen wir automatisch mit einem Sprachmodell auf Beleidigungen, Hass, Drohungen und sexuelle Inhalte. Dafür geht nur der Text an unseren Dienstleister in der EU (siehe Abschnitt 17); gespeichert werden bei uns nur die Einschätzung (Werte je Kategorie und eine kurze Begründung) und der Zeitpunkt. Löschst du den Text, löschen wir auch die Einschätzung – außer sie hat zu einem Moderationsfall geführt. Rechtsgrundlage ist unser berechtigtes Interesse, die Community-Regeln durchzusetzen und rechtswidrige Inhalte zu erkennen (Art. 6 Abs. 1 lit. f DSGVO), sowie die Pflichten aus dem Digital Services Act (Art. 6 Abs. 1 lit. c DSGVO).</p>
            <p class="m-0">Klingt ein Tropfen verletzend, zeigen wir dir vor dem Absenden einen Hinweis; du entscheidest selbst, ob du ihn änderst. Fällt ein Text auf oder wird er gemeldet, landet er als Moderationsfall beim Moderationsteam. Eindeutig sexuelle Tropfen blenden wir bis zur Prüfung vorläufig aus und sagen dir das. Entscheidungen über Konsequenzen trifft immer ein Mensch – wir treffen keine Entscheidungen, die ausschließlich automatisiert sind (Art. 22 DSGVO), und bilden keine Profile über dich. Verschlüsselte Chat-Nachrichten werden nicht geprüft; wir können sie nicht lesen.</p>
            <p class="m-0">Deutet ein Inhalt auf eine Gefahr für Leib und Leben oder auf sexuellen Missbrauch von Kindern oder Jugendlichen hin, sichern wir ihn als Beweis: den Text, die Angaben zum Konto (Name, Benutzername, E-Mail-Adresse, Registrierung), den Zeitpunkt und eine Kopie der Bilder mit Prüfsumme. Diese Beweise bleiben auch erhalten, wenn der Inhalt oder das Konto gelöscht wird, sind nur dem Moderationsteam zugänglich und werden nach {{ config('oasis.moderation.evidence_days') }} Tagen automatisch gelöscht, wenn keine Behörde sie länger benötigt. Wir geben sie nur an Strafverfolgungsbehörden, wenn wir eine Straftat anzeigen oder gesetzlich dazu verpflichtet sind (Art. 6 Abs. 1 lit. c und f, Art. 17 Abs. 3 lit. e DSGVO, Art. 18 Digital Services Act). Bilder mit dem Verdacht auf Missbrauchsdarstellungen sperren wir sofort für alle.</p>
            <p class="m-0">Zu einem Moderationsfall speichern wir den Anlass (Meldung oder automatische Einschätzung), den betroffenen Inhalt und das betroffene Konto, die Entscheidung mit Begründung, wer sie wann getroffen hat, und deine Beschwerde, wenn du eine einlegst. Über eine Entscheidung informieren wir dich mit Begründung per E-Mail (Art. 17 DSA). Moderationsunterlagen bewahren wir {{ $open($privacy['records_years'], ' Jahre') }} lang auf, damit Entscheidungen und Beschwerden nachvollziehbar bleiben, und löschen sie danach automatisch (Art. 6 Abs. 1 lit. c und f DSGVO).</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">15. Anfragen von Organisationen</h2>
            <p class="m-0">Wenn eine Organisation gemeinsam starten möchte, verarbeiten wir den Namen der Organisation, die ungefähre Personenzahl und die angegebene E-Mail-Adresse, um ein Angebot zu machen. Fragst du im eigenen Namen an, ist die Rechtsgrundlage die Vorbereitung eines Vertrags mit dir (Art. 6 Abs. 1 lit. b DSGVO). Fragst du für eine Organisation an, verarbeiten wir deine Kontaktdaten aufgrund unseres berechtigten Interesses, geschäftliche Anfragen zu beantworten (Art. 6 Abs. 1 lit. f DSGVO). Kommt keine Zusammenarbeit zustande, löschen wir die Anfrage automatisch nach {{ $open($privacy['records_years'], ' Jahren') }}.</p>
            <p class="{{ $required }}">Pflichtangaben: Organisation, Personenzahl und E-Mail-Adresse. Ohne sie können wir kein Angebot machen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">16. Wenn du uns schreibst</h2>
            <p class="m-0">Schreibst du uns direkt eine E-Mail, verarbeiten wir deine Adresse, deinen Namen, den Inhalt und eventuelle Anhänge, um dein Anliegen zu bearbeiten (Art. 6 Abs. 1 lit. b DSGVO bei Fragen zu deinem Vertrag, sonst lit. f; bei Anfragen zu deinen Datenschutzrechten lit. c). Wir löschen die Nachricht, wenn dein Anliegen erledigt ist und keine Aufbewahrungspflicht besteht.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">17. Wer deine Daten außerdem erhält</h2>
            <p class="m-0">Wir geben deine Daten nur an Dienstleister weiter, die sie in unserem Auftrag und nach unseren Weisungen verarbeiten (Art. 28 DSGVO):</p>
            <ul class="m-0 flex list-disc flex-col gap-1 pl-5">
                <li>Hosting: {{ $privacy['hosting'] }}</li>
                <li>E-Mail-Versand und -Empfang: {{ $open($privacy['mail_provider']) }}</li>
                <li>Speicherung hochgeladener Bilder: {{ $privacy['media_storage'] }} – die Bilder liegen verschlüsselt in Frankfurt und werden nur über unsere Server ausgeliefert. Für einen möglichen Zugriff der US-Muttergesellschaft gelten der Angemessenheitsbeschluss zum EU-US Data Privacy Framework (Art. 45 DSGVO) und EU-Standardvertragsklauseln (Art. 46 Abs. 2 lit. c DSGVO).</li>
                <li>Automatische Prüfung öffentlicher Texte auf Regelverstöße: {{ $privacy['ai_provider'] }} – erhält nur den jeweiligen Text, keine Namen, Benutzernamen oder anderen Angaben zu deinem Konto</li>
            </ul>
            <p class="m-0">Zahlungen wickelt Mollie B.V., Keizersgracht 126, 1015 CW Amsterdam, Niederlande, ab. Mollie erhält deinen Namen, deine E-Mail-Adresse, den Betrag und die Zahlungsdaten, die du bei Mollie eingibst (z. B. Kartendaten oder IBAN), und verarbeitet sie zur Abwicklung deiner Beiträge (Art. 6 Abs. 1 lit. b DSGVO). Als beaufsichtigtes Zahlungsinstitut verarbeitet Mollie diese Daten zum Teil als eigener Verantwortlicher, etwa zur Betrugsvorbeugung und für gesetzliche Pflichten; dafür gilt die Datenschutzerklärung von Mollie.</p>
            <p class="m-0">Die Zustellung von Chat-Nachrichten in Echtzeit läuft auf unserem eigenen Server; ein externer Echtzeit-Dienstleister wird nicht eingesetzt. Wenn ein Dienstleister Daten außerhalb der EU verarbeitet, nennen wir hier die Garantie, auf der das beruht. Kopien deiner Kündigung, deines Widerrufs oder deiner Meldung erhält nur unser eigenes Team. Auch diese Kopien im Postfach und Sicherungskopien löschen wir nach denselben Fristen. Behörden geben wir Daten nur, wenn das Gesetz uns dazu verpflichtet.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">18. Ende deines Kontos und Löschung</h2>
            <p class="m-0">Löschst du dein Konto in den Einstellungen, löschen wir sofort dein Konto mit Profil, Tropfen, Bildern, Reaktionen, Verbindungen, Hinweisen, Einstellungen, bekannten Geräten und Chat-Schlüsseln sowie die automatischen Einschätzungen deiner Texte. Oasen, in denen du die einzige Gastgeber:in bist, übernimmt das am längsten dabei gewesene Mitglied; eine Oase ohne andere Mitglieder schließen wir. Läuft noch eine bezahlte Mitgliedschaft, kündigst du sie zuerst – sonst würden weiter Beiträge eingezogen.</p>
            <p class="m-0">Endet dein Test oder deine Mitgliedschaft, ohne dass du weitermachst, kannst du deine Daten noch {{ config('oasis.retention_days') }} Tage herunterladen. Danach löschen wir dein Konto genauso automatisch.</p>
            <p class="m-0">Nicht sofort gelöscht wird nur, was wir aus einem der oben genannten Gründe aufbewahren müssen: Zahlungsbelege (Abschnitt 9), Erklärungen und Meldungen (Abschnitte 10, 12 und 13) und Moderationsunterlagen (Abschnitt 14) – jeweils nur für die genannte Frist. Sicherungskopien überschreiben wir regelmäßig; bis dahin nutzen wir sie nur, um nach einem Ausfall Daten wiederherzustellen.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">19. Deine Rechte</h2>
            <p class="m-0">Du hast das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch gegen Verarbeitungen, die auf berechtigten Interessen beruhen (Art. 21). Eine Einwilligung kannst du jederzeit mit Wirkung für die Zukunft widerrufen (Art. 7 Abs. 3); die bis dahin erfolgte Verarbeitung bleibt rechtmäßig. Vieles kannst du direkt in den Einstellungen erledigen: Daten herunterladen, Angaben ändern und das Konto löschen. Für alles andere schreib uns an {{ $privacyMail }}.</p>
            <p class="m-0">Du kannst dich außerdem bei einer Datenschutz-Aufsichtsbehörde beschweren (Art. 77 DSGVO), zum Beispiel bei der für uns zuständigen: {{ $privacy['authority'] }}.</p>
        </section>

        <section class="flex flex-col gap-[6px]">
            <h2 class="{{ $h2 }}">20. Änderungen</h2>
            <p class="m-0">Diese Erklärung beschreibt Website, App, Konto, Mitgliedschaft und Chat so, wie sie heute funktionieren. Bevor wir Daten für einen neuen Zweck verarbeiten oder einen neuen Dienstleister einsetzen, passen wir sie an. Die jeweils aktuelle Fassung findest du immer hier.</p>
        </section>
    </div>
@endsection
