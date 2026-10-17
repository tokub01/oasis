{{--
    Datenschutz & Sicherheit: route oasis.sicherheit. Explains in plain words what happens with data and how it is
    protected. Every provider comes from config('oasis.privacy') – the same values as the privacy policy – so both
    pages always agree; a value that is not decided yet says so instead of inventing one. Only states what the code
    does today (see docs/oasis-chat-client.md, docs/oasis-auto-moderation.md, docs/oasis-security.md).
--}}
@extends('layouts.oasis', [
    'title' => 'Datenschutz & Sicherheit',
    'description' => 'Was mit deinen Daten bei Oasis passiert und wie wir sie schützen – in klaren Worten.',
    'active' => 'sicherheit',
])

@php
    $privacy = config('oasis.privacy');
    $notYet = 'legen wir vor dem Start fest';

    $promises = [
        ['title' => 'Keine Werbung, kein Tracking.', 'text' => 'Kein Werbeprofil, keine Analyse-Werkzeuge, keine fremden Schriften oder Skripte. Wir verkaufen keine Daten.'],
        ['title' => 'Chats nur für euch.', 'text' => 'Nachrichten werden in deinem Browser verschlüsselt. Lesen können sie nur die Menschen im Chat – nicht wir.'],
        ['title' => 'So wenig wie möglich.', 'text' => 'Wir speichern nur, was Oasis zum Funktionieren braucht – kein Profil über deine Interessen.'],
        ['title' => 'Du behältst die Kontrolle.', 'text' => 'Du kannst deine Daten jederzeit herunterladen und dein Konto selbst löschen.'],
    ];

    $chat = [
        'Die Schlüssel entstehen in deinem Browser und sind mit einer eigenen Chat-Passphrase gesichert. Sie verlässt deinen Browser nie und ist mit Argon2id geschützt.',
        'Unser Server sieht nur verschlüsselte Nachrichten. Auch wir können sie nicht lesen und deshalb auch nicht automatisch prüfen.',
        'Auf deinem Gerät wird nichts gespeichert: Beim Verlassen der Seite und nach 30 Minuten ohne Aktivität sperrt sich der Chat.',
        'Nachrichten löschen sich selbst – je nach Chat nach 1, 7 oder 30 Tagen, auch auf unserem Server.',
        'Sicherheitsnummern zeigen dir, dass du wirklich mit der richtigen Person schreibst.',
    ];

    $places = [
        ['what' => 'Konto, Tropfen und Oasen', 'who' => $privacy['hosting'] ?? null],
        ['what' => 'Bilder in Tropfen', 'who' => $privacy['media_storage'] ?? null],
        ['what' => 'Chat in Echtzeit', 'who' => ($privacy['realtime_provider'] ?? null) ?? 'auf unserem eigenen Server, ohne Drittanbieter'],
        ['what' => 'Automatische Prüfung öffentlicher Texte', 'who' => $privacy['ai_provider'] ?? null],
        ['what' => 'Zahlungen', 'who' => 'Mollie B.V., Amsterdam, Niederlande – deine Karten- und Kontodaten gibst du nur dort ein, wir sehen sie nie'],
        ['what' => 'Bestätigungs-E-Mails', 'who' => $privacy['mail_provider'] ?? null],
    ];

    $account = [
        'Passwörter speichern wir nur als sicheren Hash (bcrypt), nie im Klartext.',
        'Auf Wunsch schützt eine Zwei-Faktor-Anmeldung dein Konto zusätzlich.',
        'Strenge Sicherheitsregeln im Browser (Content Security Policy) verhindern, dass fremder Code auf Oasis ausgeführt wird.',
        'Schriften und alle Skripte kommen von unserem eigenen Server – beim Aufruf von Oasis fragt dein Browser keine fremden Dienste an.',
    ];
@endphp

@section('content')
    <section aria-labelledby="s-titel" class="bg-night py-[clamp(64px,7vw,112px)] text-on-night">
        <div class="o-container">
            <div class="flex max-w-[52em] flex-col gap-[22px]">
                <p class="o-eyebrow o-eyebrow-night">Vertrauen</p>
                <h1 id="s-titel" class="o-h1 text-[clamp(40px,5.4vw,76px)] leading-[1.04]">Datenschutz &amp; Sicherheit</h1>
                <p class="max-w-[34em] text-[clamp(18px,1.5vw,20px)] leading-[1.6] text-on-night-2">Was mit deinen Daten passiert und wie wir sie schützen – in klaren Worten. Die rechtlich vollständige Fassung steht in der <a href="{{ route('oasis.datenschutz') }}" wire:navigate class="text-on-night underline underline-offset-4">Datenschutzerklärung</a>.</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="kurz-titel" class="py-[clamp(64px,7vw,104px)]">
        <div class="o-container flex flex-col gap-11">
            <h2 id="kurz-titel" class="o-h2-s text-night">Kurz gesagt</h2>
            <ul class="o-auto-grid m-0 list-none gap-x-10 gap-y-9 p-0 [--min:260px]">
                @foreach ($promises as $promise)
                    <li class="flex flex-col gap-2.5 border-t-2 border-night pt-5">
                        <h3 class="text-[22px] leading-[1.3] font-extrabold text-night">{{ $promise['title'] }}</h3>
                        <p class="text-[17px] leading-[1.6] text-ink-2">{{ $promise['text'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section aria-labelledby="chat-titel" class="border-y border-paper-line bg-paper py-[clamp(64px,7vw,104px)]">
        <div class="o-container o-auto-grid items-start gap-x-[72px] gap-y-8 [--min:340px]">
            <div class="flex flex-col gap-3">
                <h2 id="chat-titel" class="o-h2-s text-night">Der Chat ist <span class="whitespace-nowrap">Ende-zu-Ende</span> verschlüsselt</h2>
                <p class="text-lg leading-[1.6] text-ink-2">Echte Ende-zu-Ende-Verschlüsselung: Nicht einmal Oasis kann mitlesen.</p>
            </div>
            <ul class="m-0 flex list-none flex-col gap-4 p-0 text-[17px] leading-[1.6] text-ink">
                @foreach ($chat as $point)
                    <li class="flex gap-3"><x-oasis.icon name="check-circle" :size="22" class="mt-0.5 flex-none text-spring-text" /><span>{{ $point }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section aria-labelledby="ort-titel" class="py-[clamp(64px,7vw,104px)]">
        <div class="o-container flex flex-col gap-8">
            <div class="flex max-w-[44em] flex-col gap-3">
                <h2 id="ort-titel" class="o-h2-s text-night">Wo deine Daten liegen</h2>
                <p class="text-lg leading-[1.6] text-ink-2">So weit wie möglich in Deutschland und der EU. Wo ein Anbieter zu einem Konzern außerhalb der EU gehört, sagen wir es dazu.</p>
            </div>
            <dl class="m-0 flex flex-col divide-y divide-paper-line rounded-[24px] border border-paper-line bg-paper">
                @foreach ($places as $place)
                    <div class="o-auto-grid gap-x-8 gap-y-1 p-5 [--min:260px]">
                        <dt class="font-bold text-night">{{ $place['what'] }}</dt>
                        <dd class="m-0 text-ink-2">{{ $place['who'] ?? $notYet }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section aria-labelledby="pruefung-titel" class="border-y border-paper-line bg-paper py-[clamp(64px,7vw,104px)]">
        <div class="o-container o-auto-grid items-start gap-x-[72px] gap-y-8 [--min:340px]">
            <h2 id="pruefung-titel" class="o-h2-s text-night">Moderation mit Augenmaß</h2>
            <div class="flex max-w-[40em] flex-col gap-4 text-[17px] leading-[1.65] text-ink">
                <p>Öffentliche Texte prüft ein Sprachmodell in der EU auf Beleidigungen, Hetze, Drohungen, Extremismus, Mobbing und sexuelle Inhalte. Eindeutige Fälle halten wir schon beim Senden zurück, bis ein Mensch sie geprüft hat. Dabei geht nur der Text hinaus – kein Name, kein Benutzername, keine E-Mail-Adresse.</p>
                <p>Das Ergebnis ist ein Hinweis, keine Entscheidung: Ob etwas gegen die Regeln verstößt, entscheidet immer ein Mensch, mit Begründung. Gegen Moderationsentscheidungen kannst du widersprechen.</p>
                <p>Verschlüsselte Chats werden nie automatisch geprüft. Wer dort etwas Verletzendes bekommt, kann die Nachricht selbst melden.</p>
            </div>
        </div>
    </section>

    <section id="hilfe-mobbing" aria-labelledby="mobbing-titel" class="py-[clamp(64px,7vw,104px)]">
        <div class="o-container o-auto-grid items-start gap-x-[72px] gap-y-8 [--min:340px]">
            <h2 id="mobbing-titel" class="o-h2-s text-night">Hilfe bei Mobbing und Belästigung</h2>
            <div class="flex max-w-[40em] flex-col gap-4 text-[17px] leading-[1.65] text-ink">
                <p>Melden ist ausdrücklich erwünscht – auch wenn du dir nicht sicher bist. Bei jedem Tropfen, jedem Profil, jeder Oase und jeder Chat-Nachricht findest du „Melden“. Ein paar Tipps genügen, Fachbegriffe brauchst du nicht.</p>
                <p>Die gemeldete Person erfährt nicht, wer gemeldet hat. Du kannst sie außerdem blockieren oder stumm schalten. Im Chat warnt dich dein Gerät, wenn eine Nachricht belästigend wirkt oder nach Passwort oder Bankdaten fragt.</p>
                <x-oasis.support-contacts />
            </div>
        </div>
    </section>

    <section aria-labelledby="konto-titel" class="py-[clamp(64px,7vw,104px)]">
        <div class="o-container o-auto-grid items-start gap-x-[72px] gap-y-8 [--min:340px]">
            <h2 id="konto-titel" class="o-h2-s text-night">Dein Konto ist geschützt</h2>
            <ul class="m-0 flex list-none flex-col gap-4 p-0 text-[17px] leading-[1.6] text-ink">
                @foreach ($account as $point)
                    <li class="flex gap-3"><x-oasis.icon name="check-circle" :size="22" class="mt-0.5 flex-none text-spring-text" /><span>{{ $point }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section aria-labelledby="luecke-titel" class="border-t border-paper-line bg-paper py-[clamp(64px,7vw,104px)]">
        <div class="o-container">
            <div class="flex max-w-[52em] flex-col gap-5">
                <h2 id="luecke-titel" class="o-h2-s text-night">Sicherheitslücke gefunden?</h2>
                <p class="text-[17px] leading-[1.65] text-ink">Danke, dass du hilfst, Oasis sicher zu machen. Schreib uns an <a href="mailto:{{ config('oasis.mail.security') }}?subject=Sicherheitsl%C3%BCcke" class="text-night underline underline-offset-4">{{ config('oasis.mail.security') }}</a> mit einer Beschreibung, wie sich die Lücke nachvollziehen lässt. Wir bestätigen den Eingang innerhalb von {{ config('oasis.support_reply_days') }} Werktagen, halten dich auf dem Laufenden und nennen dich gern, wenn die Lücke geschlossen ist.</p>
                <p class="text-[17px] leading-[1.65] text-ink">Bitte greif dabei nicht auf Daten anderer Menschen zu, verändere oder lösche nichts, beeinträchtige den Betrieb nicht (keine Last- oder Spamtests) und gib die Lücke erst bekannt, wenn wir sie schließen konnten. Wer sich daran hält, muss von uns keine rechtlichen Schritte befürchten. Die maschinenlesbare Fassung steht unter <a href="{{ route('oasis.security-txt') }}" class="text-night underline underline-offset-4">/.well-known/security.txt</a>.</p>
            </div>
        </div>
    </section>

    <section aria-labelledby="rechte-titel" class="bg-dune py-[clamp(64px,7vw,104px)]">
        <div class="o-container">
            <div class="flex max-w-[52em] flex-col gap-5">
                <h2 id="rechte-titel" class="o-h2-s text-night">Deine Rechte</h2>
                <p class="text-[17px] leading-[1.65] text-ink">Du kannst jederzeit erfahren, welche Daten wir über dich haben, sie berichtigen, herunterladen oder löschen lassen. Den Export und das Löschen deines Kontos erledigst du selbst in den <a href="{{ route('app.settings') }}" wire:navigate class="text-night underline underline-offset-4">Einstellungen</a>.</p>
                <p class="text-[17px] leading-[1.65] text-ink">Fragen zum Datenschutz: <a href="mailto:{{ config('oasis.mail.privacy') }}" class="text-night underline underline-offset-4">{{ config('oasis.mail.privacy') }}</a>. Du kannst dich außerdem bei der Aufsichtsbehörde beschweren: {{ $privacy['authority'] ?? $notYet }}.</p>
            </div>
        </div>
    </section>
@endsection
