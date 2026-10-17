{{--
    AGB draft from Rechtliches.dc.html at oasis.agb; operator, prices and periods come from oasis configuration.
    Describes the planned service, pending legal review before launch. No JavaScript required.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'AGB', 'legalKey' => 'agb'])
@section('legal-content')

    <div class="flex flex-col gap-[22px] [max-width:44em]">
        <p class="m-0 [padding:14px_18px] rounded-[14px] bg-dune text-[15px]"><strong>Entwurf – vor dem Start anwaltlich prüfen lassen</strong>. Diese Bedingungen beschreiben das geplante Angebot und sind noch keine Freigabe für den Start.</p>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 1 Betreiber und Geltungsbereich</h2>
            <p class="m-0">Oasis wird von {{ config('oasis.operator.legal_name') }}, {{ config('oasis.operator.legal_form') }}, {{ config('oasis.operator.street') }}, {{ config('oasis.operator.city') }}, {{ config('oasis.operator.country') }}, betrieben. Du erreichst uns unter <a class="o-link-inline" href="mailto:{{ config('oasis.mail.contact') }}">{{ config('oasis.mail.contact') }}</a>.</p>
            <p class="m-0">Diese Bedingungen gelten für die Nutzung von Oasis, den kostenlosen Test und die Mitgliedschaft. Individuelle Vereinbarungen haben Vorrang. Zwingende gesetzliche Rechte bleiben unberührt.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 2 Das Angebot</h2>
            <p class="m-0">Oasis ist ein werbefreies soziales Netzwerk für kurze Beiträge, die wir „Tropfen“ nennen. Es wird ausschließlich durch Mitgliedsbeiträge finanziert. Alle Beitragsstufen erhalten dieselben Funktionen; ein höherer Beitrag unterstützt den gemeinsamen Betrieb.</p>
            <p class="m-0">Wir stellen die vereinbarten Funktionen und die gesetzlich erforderlichen Aktualisierungen während der Vertragslaufzeit bereit. Notwendige Wartungsarbeiten kündigen wir nach Möglichkeit vorher an und halten Unterbrechungen möglichst kurz. Deine gesetzlichen Rechte bei Ausfällen oder Mängeln bleiben bestehen.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 3 Alter und Konto</h2>
            <p class="m-0">Du kannst Oasis ab 16 Jahren testen. Eine kostenpflichtige Mitgliedschaft kannst du ab 18 Jahren selbst abschließen. Mit 16 oder 17 Jahren ist sie nur mit Zustimmung deiner gesetzlichen Vertretung oder als Geschenk-Mitgliedschaft möglich. Auch bei einem Geschenk gelten die gesetzlichen Regeln zur Zustimmung deiner gesetzlichen Vertretung; durch das Geschenk entstehen dir keine Zahlungspflichten.</p>
            <p class="m-0">Für Minderjährige gelten auch beim Test die gesetzlichen Regeln zur Geschäftsfähigkeit. Gib bei Registrierung und Bestellung richtige Angaben an, halte deine Kontaktdaten aktuell und schütze deine Zugangsdaten. Informiere uns, wenn du einen unbefugten Zugriff auf dein Konto bemerkst.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 4 Kostenloser Test und Vertragsschluss</h2>
            <p class="m-0">Der kostenlose Test dauert {{ config('oasis.trial_days') }} Tage und erfordert keine Zahlungsdaten. Er endet automatisch. Ohne deine aktive Bestellung entsteht keine kostenpflichtige Mitgliedschaft.</p>
            <p class="m-0">Vor einer Bestellung zeigen wir dir die gewählte Stufe, den Gesamtpreis, die Zahlungsweise, die Laufzeit und diese Bedingungen. Du kannst deine Angaben prüfen und korrigieren. Mit „Zahlungspflichtig bestellen“ gibst du ein verbindliches Angebot ab. Der Vertrag kommt zustande, wenn wir die Bestellung ausdrücklich annehmen und dir die Vertragsbestätigung einschließlich dieser Bedingungen per E-Mail senden. Eine reine Eingangsbestätigung ist noch keine Annahme. Die Vertragssprache ist Deutsch.</p>
            <p class="m-0">Ein Eintrag in die Startliste ist keine Bestellung und begründet keine Zahlungspflicht. Solange die Bestellfunktion noch nicht freigeschaltet ist, kannst du keine Mitgliedschaft über die Website abschließen.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 5 Beiträge und Preisgarantie</h2>
            <p class="m-0">Die folgenden Beiträge enthalten {{ config('oasis.vat_rate') }} % Umsatzsteuer. Der Jahresbeitrag entspricht {{ config('oasis.year_months') }} Monatsbeiträgen und deckt 12 Monate ab:</p>
            <ul class="m-0 list-disc pl-6">
                @foreach (config('oasis.tiers') as $tier)
                    <li>{{ $tier['name'] }}: {{ $tier['min'] ? 'ab ' : '' }}{{ \App\Support\OasisFormat::euro($tier['monthly']) }} pro Monat oder {{ $tier['min'] ? 'ab ' : '' }}{{ \App\Support\OasisFormat::euro($tier['monthly'] * config('oasis.year_months')) }} pro Jahr.</li>
                @endforeach
            </ul>
            <p class="m-0">Bei einer Stufe mit Mindestbeitrag bestimmst du deinen Beitrag ab diesem Betrag selbst. Maßgeblich ist der Gesamtpreis, dem du bei der Bestellung zustimmst. Zusätzliche kostenpflichtige Leistungen werden nicht ohne deine ausdrückliche Bestellung berechnet.</p>
            <p class="m-0">Die ersten {{ number_format(config('oasis.founding.seats'), 0, ',', '.') }} Mitglieder erhalten für ihren vereinbarten Mitgliedsbeitrag eine Preisgarantie, solange ihre Mitgliedschaft ohne Unterbrechung besteht. Eine von dir ausdrücklich gewünschte Beitragsänderung bleibt möglich; eine Erhöhung durch uns ist während der Garantie ausgeschlossen.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 6 Zahlung</h2>
            <p class="m-0">Auf der Website kannst du per SEPA-Lastschrift, PayPal oder Karte bezahlen. Monatsbeiträge werden jeweils zu Beginn des bezahlten Monats, Jahresbeiträge zu Beginn des vereinbarten Jahres fällig. Den ersten Zahlungstermin und die weiteren Abrechnungstermine teilen wir dir vor der Bestellung mit.</p>
            <p class="m-0">Eine SEPA-Lastschrift setzt dein gesondertes Mandat voraus. Betrag und Einzugstermin kündigen wir vor dem Einzug an. Gesetzliche Erstattungsrechte gegenüber deiner Bank bleiben unberührt. Bei PayPal und Kartenzahlung gelten für die Zahlungsabwicklung ergänzend die Bedingungen des jeweiligen Zahlungsdienstes.</p>
            @if (config('oasis.apps'))
                <p class="m-0">In den Apps sind auch Käufe über den App Store oder Google Play möglich. Für solche Käufe gelten ergänzend die Bedingungen des jeweiligen Stores, insbesondere zu Abrechnung und Aboverwaltung. Vor dem Kauf wird angezeigt, wer dein Vertragspartner ist. Zwingende Verbraucherrechte werden dadurch nicht eingeschränkt.</p>
            @endif
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 7 Laufzeit und Kündigung</h2>
            <p class="m-0">Eine monatliche Mitgliedschaft läuft auf unbestimmte Zeit. Du kannst sie jederzeit ohne Kündigungsfrist zum Ende des laufenden Monats kündigen.</p>
            <p class="m-0">Eine Jahresmitgliedschaft läuft zunächst 12 Monate. Du kannst jederzeit zum Ende dieser Laufzeit kündigen. Ohne Kündigung läuft sie danach auf unbestimmte Zeit mit monatlicher Abrechnung zum vereinbarten Monatsbeitrag weiter und ist jederzeit zum Ende des laufenden Monats kündbar. Ein neues festes Jahr beginnt nur, wenn du es ausdrücklich bestellst.</p>
            <p class="m-0">Du kannst ohne Anmeldung über den Button „<a class="o-link-inline" href="{{ route('oasis.kuendigen') }}">Verträge hier kündigen</a>“ unten auf jeder Seite sowie als angemeldetes Mitglied direkt in deinen Mitgliedschaftseinstellungen kündigen (§ 312k BGB). Wir bestätigen den Eingang mit Inhalt, Datum und Uhrzeit sowie dem angegebenen Beendigungszeitpunkt sofort per E-Mail. Du kannst auch per E-Mail an die Kontaktadresse aus § 1 kündigen. Eine Unterschrift oder ein Brief ist nicht erforderlich.</p>
            <p class="m-0">Beide Seiten können aus wichtigem Grund außerordentlich kündigen. Soweit gesetzlich erforderlich, muss zuvor Gelegenheit zur Abhilfe gegeben oder abgemahnt werden. Weitergehende gesetzliche Kündigungsrechte bleiben bestehen. Eine bloße Löschung der App beendet den Vertrag nicht.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 8 Geschenk-Mitgliedschaft</h2>
            <p class="m-0">Geschenk-Mitgliedschaften haben eine Laufzeit von 3 oder 12 Monaten. Preis, Beginn und Ende werden vor der Bestellung angezeigt. Der schenkenden Person wird der vereinbarte Gesamtbetrag berechnet. Die Mitgliedschaft endet automatisch ohne Kündigung und ohne weitere Belastung der schenkenden oder beschenkten Person. Eine anschließende bezahlte Mitgliedschaft erfordert eine neue aktive Bestellung.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 9 Widerruf</h2>
            <p class="m-0">Als Verbraucherin oder Verbraucher hast du bei einem online geschlossenen Vertrag das gesetzliche Widerrufsrecht. Einzelheiten, Fristen und Folgen findest du in unserer <a class="o-link-inline" href="{{ route('oasis.widerrufsbelehrung') }}">Widerrufsbelehrung</a>. Über <a class="o-link-inline" href="{{ route('oasis.widerrufen') }}">Vertrag widerrufen</a> kannst du deinen Widerruf elektronisch erklären. Die dort genannten anderen Wege bleiben ebenfalls möglich.</p>
            <p class="m-0">Die Zustimmung zu diesen AGB ist weder ein Verzicht auf dein Widerrufsrecht noch ein ausdrückliches Verlangen, die kostenpflichtige Leistung schon vor Ablauf der Widerrufsfrist zu beginnen.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 10 Unsere Versprechen</h2>
            @if (config('oasis.promises_codified'))
                <p class="m-0"><strong>Festgeschrieben:</strong> Keine Werbung, kein Werbeprofil und kein Verkauf deiner Daten sind verbindlicher Bestandteil dieser Bedingungen. Wir verwenden deine Daten nicht zur Bildung eines Werbeprofils. Diese Kernversprechen dürfen nur mit Zustimmung der Mitglieder geändert werden; ohne deine ausdrückliche Zustimmung gelten sie für deinen Vertrag weiter.</p>
            @else
                <p class="m-0"><strong>Wird festgeschrieben:</strong> Wir planen, vor dem Start die Versprechen „Keine Werbung, kein Werbeprofil, kein Datenverkauf“ verbindlich festzuschreiben. Änderungen sollen dann nur mit Zustimmung der Mitglieder möglich sein. Die vertragliche Festschreibung ist noch nicht abgeschlossen.</p>
            @endif
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 11 Deine Inhalte und die Community</h2>
            <p class="m-0">Du behältst die Rechte an deinen Tropfen und anderen Inhalten. Du räumst uns ein einfaches, unentgeltliches Nutzungsrecht ein, soweit es für den Betrieb von Oasis erforderlich ist: Wir dürfen deine Inhalte speichern, technisch vervielfältigen und entsprechend deiner gewählten Sichtbarkeit anzeigen. Das umfasst die technisch notwendige Verarbeitung durch unsere Dienstleister, aber keine eigenständige Vermarktung deiner Inhalte.</p>
            <p class="m-0">Dieses Recht endet mit der Löschung des Inhalts oder der Beendigung der Mitgliedschaft, abgesehen von der für deinen Datenexport erforderlichen Speicherung und gesetzlich vorgeschriebener Aufbewahrung. Technische Sicherungskopien dürfen bis zu ihrer regulären Überschreibung bestehen bleiben und werden nicht weiter veröffentlicht. Rechte anderer Personen an eigenen Beiträgen bleiben unberührt.</p>
            <p class="m-0">Veröffentliche nur Inhalte, zu deren Nutzung du berechtigt bist. Beachte die Rechte anderer, insbesondere Urheberrechte, Persönlichkeitsrechte und Datenschutz. Es gelten die <a class="o-link-inline" href="{{ route('oasis.regeln') }}">Community-Regeln</a>. Ihre Einbeziehung erlaubt keine einseitige Änderung der vereinbarten Vertragsbedingungen.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 12 Meldungen, Moderation und Beschwerden</h2>
            <p class="m-0">Rechtswidrige Inhalte kannst du ohne Konto über das Formular „<a class="o-link-inline" href="{{ route('oasis.melden') }}">Inhalte melden</a>“ melden (Art. 16 DSA). Wir prüfen Meldungen sorgfältig. Bei Rechtsverstößen oder Verstößen gegen die Community-Regeln können wir Inhalte entfernen, ihre Sichtbarkeit einschränken oder bei schweren oder wiederholten Verstößen das Konto beschränken. Wir berücksichtigen Schwere, Folgen und Umstände des Verstoßes sowie die Rechte der Beteiligten. Eine Sperre beendet Zahlungspflichten nicht automatisch; gesetzliche Minderungs-, Erstattungs- und Kündigungsrechte bleiben bestehen.</p>
            <p class="m-0">Betroffene erhalten eine nachvollziehbare Begründung der Entscheidung nach Art. 17 DSA, soweit keine gesetzliche Ausnahme greift, einschließlich des zugrunde liegenden Inhalts, der maßgeblichen Tatsachen und Regeln und gegebenenfalls des Einsatzes automatisierter Verfahren.</p>
            <p class="m-0">Du kannst innerhalb von mindestens sechs Monaten ab Mitteilung der Entscheidung kostenlos intern Beschwerde einlegen: Antworte auf die Entscheidungsmitteilung oder schreibe an <a class="o-link-inline" href="mailto:{{ config('oasis.mail.dsa') }}">{{ config('oasis.mail.dsa') }}</a>. Eine zweite Person im Team prüft die Entscheidung; die Beschwerdeentscheidung wird nicht ausschließlich automatisiert getroffen und dir mit Begründung mitgeteilt. Dieses Verfahren orientiert sich an Art. 20 DSA und wird auch angeboten, wenn Oasis als kleines Unternehmen von dessen gesetzlicher Pflicht ausgenommen ist. Gesetzliche außergerichtliche und gerichtliche Rechtsbehelfe bleiben offen.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 13 Vertragsende und Datenexport</h2>
            <p class="m-0">Nach dem Ende des Tests oder deiner Mitgliedschaft kannst du deine Daten und Tropfen noch {{ config('oasis.retention_days') }} Tage herunterladen. Danach werden sie gelöscht, soweit keine gesetzliche Aufbewahrungspflicht oder ein anderer rechtmäßiger Aufbewahrungsgrund besteht. Einzelheiten zur Verarbeitung stehen in der <a class="o-link-inline" href="{{ route('oasis.datenschutz') }}">Datenschutzerklärung</a>. Deine gesetzlichen Rechte auf Herausgabe von Inhalten, Datenübertragbarkeit und Löschung werden durch die Downloadfrist nicht eingeschränkt.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 14 Änderungen der Bedingungen</h2>
            <p class="m-0">Wenn wir Änderungen dieser Bedingungen vorschlagen, informieren wir dich rechtzeitig vor dem vorgesehenen Beginn per E-Mail über den Text, den Grund und die Auswirkungen. Du kannst widersprechen oder nach § 7 kündigen. Für deinen bestehenden Vertrag werden Änderungen nur mit deiner ausdrücklichen Zustimmung wirksam. Schweigen, die weitere Nutzung oder ein unterlassener Widerspruch gelten nicht als Zustimmung. Ohne Zustimmung gelten die bisherigen Bedingungen weiter.</p>
            @if (config('oasis.promises_codified'))
                <p class="m-0">Für die Kernversprechen aus § 10 ist zusätzlich die Zustimmung der Mitglieder erforderlich. Eine Mehrheitsentscheidung ersetzt deine ausdrückliche Zustimmung zur Änderung deines Vertrags nicht.</p>
            @else
                <p class="m-0">Für die geplanten Kernversprechen aus § 10 soll nach ihrer Festschreibung zusätzlich die Zustimmung der Mitglieder erforderlich sein.</p>
            @endif
            <p class="m-0">Diese Regelung begründet kein Recht, die vereinbarte digitale Leistung einseitig zu ändern. Gesetzliche Rechte bei Änderungen digitaler Produkte bleiben unberührt.</p>
        </section>
        <section class="flex flex-col gap-[6px]">
            <h2 class="m-0 text-[20px] font-extrabold text-night">§ 15 Haftung, Recht und Streitfälle</h2>
            <p class="m-0">Wir haften unbeschränkt bei Vorsatz und grober Fahrlässigkeit sowie für schuldhaft verursachte Schäden an Leben, Körper oder Gesundheit. Dasselbe gilt bei einer übernommenen Garantie und nach zwingenden gesetzlichen Haftungsvorschriften, insbesondere dem Produkthaftungsgesetz.</p>
            <p class="m-0">Bei einfacher Fahrlässigkeit haften wir für die Verletzung wesentlicher Vertragspflichten, deren Erfüllung die ordnungsgemäße Durchführung des Vertrags erst ermöglicht und auf deren Einhaltung du regelmäßig vertrauen darfst. Diese Haftung ist auf den bei Vertragsschluss vorhersehbaren, vertragstypischen Schaden begrenzt. Im Übrigen ist die Haftung für einfache Fahrlässigkeit ausgeschlossen. Die vorstehenden Ausnahmen und deine gesetzlichen Mängelrechte bleiben unberührt.</p>
            <p class="m-0">Es gilt deutsches Recht. Wenn du als Verbraucherin oder Verbraucher in einem anderen Staat deinen gewöhnlichen Aufenthalt hast, bleiben dessen zwingende Verbraucherschutzvorschriften anwendbar, soweit sie dir nach den gesetzlichen Regeln zustehen. Für gerichtliche Streitigkeiten gelten die gesetzlichen Zuständigkeitsregeln; es wird kein ausschließlicher Gerichtsstand am Sitz des Betreibers vereinbart. Eine verpflichtende Schiedsgerichtsbarkeit wird nicht vereinbart.</p>
            <p class="m-0">{{ config('oasis.operator.vsbg_notice') }} Gesetzliche Rechte auf Streitbeilegung nach dem DSA bleiben unberührt.</p>
        </section>
    </div>

@endsection
