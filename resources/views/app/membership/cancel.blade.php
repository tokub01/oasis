@extends('layouts.app', ['title' => 'Mitgliedschaft kündigen', 'active' => 'mitgliedschaft'])

@section('content')
    <div class="mx-auto flex max-w-[600px] flex-col gap-6">
        <div class="flex flex-col gap-1">
            <h1 class="o-h2 m-0 text-night">Mitgliedschaft kündigen</h1>
            <p class="m-0 text-base text-muted">Bestätigung deiner Kündigung gemäß § 312k BGB.</p>
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
                        <dt class="font-bold text-muted">Art der Kündigung:</dt>
                        <dd class="m-0 font-extrabold text-night">Ordentlich zum Ende der Abrechnungsperiode</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="font-bold text-muted">Wirksam zum:</dt>
                        <dd class="m-0 font-extrabold text-night">{{ $effectiveDate ? $effectiveDate->timezone('Europe/Berlin')->format('d.m.Y') : 'Periodenende' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="flex flex-col gap-2 text-sm leading-relaxed text-ink">
                <p class="m-0">
                    <strong>Was passiert nach der Kündigung?</strong>
                </p>
                <ul class="m-0 flex flex-col gap-1.5 pl-4 text-muted">
                    <li>Deine Mitgliedschaft bleibt bis zum {{ $effectiveDate ? $effectiveDate->timezone('Europe/Berlin')->format('d.m.Y') : 'Periodenende' }} vollständig aktiv.</li>
                    <li>Danach wird dein Konto in den <strong>Lesemodus</strong> versetzt. Du kannst weiterhin alle Inhalte lesen.</li>
                    <li>Du hast anschließend <strong>30 Tage Zeit</strong>, um deine Daten jederzeit herunterzuladen.</li>
                    <li>Wir senden dir unmittelbar eine Eingangsbestätigung in Textform per E-Mail mit Datum und Uhrzeit.</li>
                </ul>
            </div>

            <form method="POST" action="{{ route('app.membership.cancel') }}" class="flex flex-col gap-3 pt-2">
                @csrf
                <button type="submit" class="o-btn o-btn-md o-btn-dark w-full justify-center">
                    Jetzt kündigen
                </button>
                <a href="{{ route('app.membership') }}" class="text-center text-sm font-bold text-muted underline">
                    Abbrechen und zurück zur Mitgliedschaft
                </a>
            </form>
        </div>
    </div>
@endsection
