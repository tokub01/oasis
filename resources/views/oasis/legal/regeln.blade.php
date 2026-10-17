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
                    <b>Sei echt.</b> Keine falschen Profile, keine Täuschung, kein Spam. Kein Anbieten oder Suchen sexueller oder bezahlter Dienste.</span>
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
        <p class="m-0 [padding:16px_18px] rounded-[14px] bg-dune text-[16px] [line-height:1.55]">Bei Verstößen entfernen wir Inhalte oder schränken Konten ein. Hält unsere automatische Prüfung einen Tropfen als eindeutigen Verstoß zurück, sperren wir das Konto vorläufig, bis ein Mensch aus dem Moderationsteam entschieden hat – in der Regel innerhalb von 48 Stunden. Du kannst dazu Stellung nehmen. Nach drei bestätigten Verstößen sperren wir ein Konto dauerhaft. Jede Entscheidung begründen wir, und du kannst widersprechen.</p>
    </div>

@endsection
