@extends('layouts.app', ['title' => 'Mitgliedschaft widerrufen', 'active' => 'mitgliedschaft'])

@section('content')
    <div class="mx-auto flex max-w-[600px] flex-col gap-6">
        <div class="flex flex-col gap-1">
            <h1 class="o-h2 m-0 text-night">Mitgliedschaft widerrufen</h1>
            <p class="m-0 text-base text-muted">Gesetzliches Widerrufsrecht innerhalb von 14 Tagen (§ 355 BGB).</p>
        </div>

        <div class="flex flex-col gap-5 rounded-[24px] border border-paper-line bg-paper p-[clamp(20px,3vw,32px)]">
            <div class="rounded-xl bg-dune p-4">
                <dl class="m-0 flex flex-col gap-2.5 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="font-bold text-muted">Vertrag:</dt>
                        <dd class="m-0 font-extrabold text-night">Oasis Mitgliedschaft (Stufe: {{ $tierName }})</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="font-bold text-muted">Name:</dt>
                        <dd class="m-0 font-extrabold text-night">{{ $user->display_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="font-bold text-muted">E-Mail:</dt>
                        <dd class="m-0 font-extrabold text-night">{{ $user->email }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="font-bold text-muted">Rechtsgrundlage:</dt>
                        <dd class="m-0 font-extrabold text-night">14-tägiges gesetzliches Widerrufsrecht</dd>
                    </div>
                </dl>
            </div>

            <div class="flex flex-col gap-2 text-sm leading-relaxed text-ink">
                <p class="m-0">
                    <strong>Folgen des Widerrufs:</strong>
                </p>
                <ul class="m-0 flex flex-col gap-1.5 pl-4 text-muted">
                    <li>Deine Mitgliedschaft wird <strong>mit sofortiger Wirkung</strong> beendet.</li>
                    <li>Etwaige bereits gezahlte Beträge werden dir unverzüglich über dein ursprüngliches Zahlungsmittel erstattet.</li>
                    <li>Dein Konto wechselt in den Lesemodus. Du kannst deine Daten innerhalb von 30 Tagen herunterladen.</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('app.membership.withdraw') }}" class="flex flex-col gap-3 pt-2">
                @csrf
                <button type="submit" class="o-btn o-btn-md o-btn-dark w-full justify-center">
                    Jetzt widerrufen
                </button>
                <a href="{{ route('app.membership') }}" class="text-center text-sm font-bold text-muted underline">
                    Abbrechen und zurück zur Mitgliedschaft
                </a>
            </form>
        </div>
    </div>
@endsection
