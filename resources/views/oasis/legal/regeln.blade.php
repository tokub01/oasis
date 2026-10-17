{{--
    Community-rule draft from Rechtliches.dc.html at oasis.regeln.
    Marked for legal review; records moderation commitments without implying completed case processing.
    No JavaScript required.
--}}
@extends('oasis.legal._frame', ['legalTitle' => 'Community-Regeln', 'legalKey' => 'regeln'])
@section('legal-content')
    <div class="flex flex-col gap-[20px] [max-width:44em]">
        <p class="m-0 [padding:14px_18px] rounded-[14px] bg-dune text-[15px]">Platzhalter: Diese Seite braucht einen anwaltlich geprüften Text.</p>
        <p class="m-0 text-[18px] [line-height:1.6]">Oasis soll ein Ort sein, an dem man gern ist. Dafür gelten für alle diese Regeln:</p>
        <ol class="m-0 p-0 list-none flex flex-col gap-[16px]">
            <li class="flex gap-[16px] items-start">
                <span aria-hidden="true" class="flex-none [width:32px] o-display text-[22px] font-semibold text-sun-text">1</span>
                <span>
                    <b>Sei respektvoll.</b> Keine Beleidigungen, keine Belästigung, kein Mobbing, keine Drohungen.</span>
            </li>
            <li class="flex gap-[16px] items-start">
                <span aria-hidden="true" class="flex-none [width:32px] o-display text-[22px] font-semibold text-sun-text">2</span>
                <span>
                    <b>Kein Hass.</b> Keine Hetze gegen Menschen wegen Herkunft, Religion, Geschlecht, sexueller Orientierung, Behinderung oder anderer Merkmale. Keine Volksverhetzung, keine Verherrlichung des Nationalsozialismus, keine extremistischen Parolen oder Kennzeichen. Politische Meinungen sind willkommen – solange sie Menschen nicht abwerten.</span>
            </li>
            <li class="flex gap-[16px] items-start">
                <span aria-hidden="true" class="flex-none [width:32px] o-display text-[22px] font-semibold text-sun-text">3</span>
                <span>
                    <b>Nichts Rechtswidriges.</b> Keine illegalen Inhalte und keine Aufrufe zu Gewalt.</span>
            </li>
            <li class="flex gap-[16px] items-start">
                <span aria-hidden="true" class="flex-none [width:32px] o-display text-[22px] font-semibold text-sun-text">4</span>
                <span>
                    <b>Sei echt.</b> Keine falschen Profile, keine Täuschung, kein Spam. Kein Anbieten oder Suchen sexueller oder bezahlter Dienste. Profilbilder zeigen dich oder etwas Unverfängliches – keine Nacktheit, keine Gewalt, keine fremden Personen ohne deren Zustimmung.</span>
            </li>
            <li class="flex gap-[16px] items-start">
                <span aria-hidden="true" class="flex-none [width:32px] o-display text-[22px] font-semibold text-sun-text">5</span>
                <span>
                    <b>Schütze andere.</b> Keine privaten Daten anderer ohne deren Zustimmung.</span>
            </li>
            <li class="flex gap-[16px] items-start">
                <span aria-hidden="true" class="flex-none [width:32px] o-display text-[22px] font-semibold text-sun-text">6</span>
                <span>
                    <b>Oasen dürfen strenger sein.</b> Gastgeber:innen können eigene Hausregeln ergänzen, aber diese Regeln nicht lockern.</span>
            </li>
        </ol>

        {{-- Rules for tone and chat; every new behaviour rule of Oasis is added here too (docs/oasis-moderation.md). --}}
        <section id="umgangston" aria-labelledby="umgangston-titel" class="flex flex-col gap-3 scroll-mt-24">
            <h2 id="umgangston-titel" class="m-0 text-[22px] font-extrabold text-night">Umgangston</h2>
            <ul class="m-0 flex flex-col gap-2 pl-5 text-[17px] [line-height:1.6]">
                <li>Sprich andere so an, wie du selbst angesprochen werden möchtest. Anreden wie „Alter“, „Digga“ oder „Brudi“ wirken schnell herablassend – wir weisen dich dann kurz darauf hin. Das ist keine Strafe, nur eine Erinnerung.</li>
                <li>Kein Anschreien in Großbuchstaben, keine Kettennachrichten, kein Spam.</li>
            </ul>
        </section>

        <section id="chat" aria-labelledby="chat-titel" class="flex flex-col gap-3 scroll-mt-24">
            <h2 id="chat-titel" class="m-0 text-[22px] font-extrabold text-night">Im Chat</h2>
            <p class="m-0 text-[17px] [line-height:1.6]">Chats sind Ende-zu-Ende-verschlüsselt: Wir können sie nicht lesen. Hinweise auf Belästigung oder Betrug erscheinen deshalb nur auf deinem Gerät, und gemeldet wird eine Nachricht nur, wenn du das willst. Es gelten alle Regeln oben und zusätzlich:</p>
            <ul class="m-0 flex flex-col gap-2 pl-5 text-[17px] [line-height:1.6]">
                <li>Keine ungefragten sexuellen Nachrichten oder Bilder.</li>
                <li>Kein Anbieten oder Suchen sexueller oder bezahlter Dienste. Das ist ein Grund für eine dauerhafte Sperre.</li>
                <li>Frag niemanden nach Passwörtern, Codes, Kartennummern, Bankdaten oder der Wohnadresse – und gib deine eigenen nicht heraus. Oasis fragt dich nie im Chat danach.</li>
                <li>Ein „Nein“ oder keine Antwort heißt: nicht weiter anschreiben.</li>
                <li>Wer dir fremd ist, landet zuerst als Anfrage – ohne Benachrichtigung, bis du sie annimmst. Bis dahin sind höchstens drei Nachrichten möglich.</li>
                <li>Keine Drohungen, kein Mobbing, keine Hetze – auch nicht „nur privat“.</li>
                <li>Wer belästigt wird, kann die Person mit einem Tipp blockieren, den Chat löschen und die Nachricht melden.</li>
                <li>Melden ist ausdrücklich erwünscht, auch im Zweifel. Die gemeldete Person erfährt nicht, wer gemeldet hat. Wer absichtlich falsch meldet, um jemandem zu schaden, verstößt selbst gegen die Regeln.</li>
            </ul>
        </section>

        <p class="m-0 [padding:16px_18px] rounded-[14px] bg-dune text-[16px] [line-height:1.55]">Bei Verstößen entfernen wir Inhalte oder schränken Konten ein. Hält unsere automatische Prüfung einen Tropfen als eindeutigen Verstoß zurück, sperren wir das Konto vorläufig, bis ein Mensch aus dem Moderationsteam entschieden hat – in der Regel innerhalb von 48 Stunden. Du kannst dazu Stellung nehmen. Nach drei bestätigten Verstößen sperren wir ein Konto dauerhaft. Jede Entscheidung begründen wir, und du kannst widersprechen.</p>
    </div>

@endsection
