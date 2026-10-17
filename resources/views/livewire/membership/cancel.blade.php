{{--
    Member subscription cancellation confirmation (route app.membership.cancel.confirm, App\Livewire\Membership\Cancel).
--}}
<div>
<x-network.columns label="Vor der Kündigung">
    <x-network.page-header eyebrow="Mitgliedschaft" title="Mitgliedschaft kündigen">
        Bestätigung deiner Kündigung gemäß § 312k BGB.
    </x-network.page-header>

    <div class="flex flex-col gap-5 rounded-[24px] border border-paper-line bg-paper p-[clamp(20px,3vw,32px)]">
        <div class="rounded-xl bg-dune p-4">
            <dl class="m-0 flex flex-col gap-2.5 text-sm">
                <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="shrink-0 font-bold text-muted">Vertrag:</dt>
                    <dd class="m-0 min-w-0 font-extrabold break-words text-night sm:text-right">Oasis Mitgliedschaft (Stufe: {{ $tierName }})</dd>
                </div>
                <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="shrink-0 font-bold text-muted">Name:</dt>
                    <dd class="m-0 min-w-0 font-extrabold break-words text-night sm:text-right">{{ $user->display_name }}</dd>
                </div>
                <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="shrink-0 font-bold text-muted">E-Mail:</dt>
                    <dd class="m-0 min-w-0 font-extrabold break-words text-night sm:text-right">{{ $user->email }}</dd>
                </div>
                <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="shrink-0 font-bold text-muted">Art der Kündigung:</dt>
                    <dd class="m-0 min-w-0 font-extrabold break-words text-night sm:text-right">Ordentlich zum Ende der Abrechnungsperiode</dd>
                </div>
                <div class="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-4">
                    <dt class="shrink-0 font-bold text-muted">Wirksam zum:</dt>
                    <dd class="m-0 min-w-0 font-extrabold break-words text-night sm:text-right">{{ $effectiveDate ? $effectiveDate->timezone('Europe/Berlin')->format('d.m.Y') : 'Periodenende' }}</dd>
                </div>
            </dl>
        </div>

        <div class="flex flex-col gap-2 text-[15px] leading-relaxed text-ink">
            <p class="m-0">
                <strong>Was passiert nach der Kündigung?</strong>
            </p>
            <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-ink-2">
                <li>Deine Mitgliedschaft bleibt bis zum {{ $effectiveDate ? $effectiveDate->timezone('Europe/Berlin')->format('d.m.Y') : 'Periodenende' }} vollständig aktiv.</li>
                <li>Danach wird dein Konto in den <strong>Lesemodus</strong> versetzt. Du kannst weiterhin alle Inhalte lesen.</li>
                <li>Du hast anschließend <strong>30 Tage Zeit</strong>, um deine Daten jederzeit herunterzuladen.</li>
                <li>Wir senden dir unmittelbar eine Eingangsbestätigung in Textform per E-Mail mit Datum und Uhrzeit.</li>
            </ul>
        </div>

        <form wire:submit="cancel" class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:gap-6">
            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark w-full justify-center sm:w-auto">
                Jetzt kündigen
            </button>
            <a wire:navigate href="{{ route('app.membership') }}" class="inline-flex min-h-11 items-center justify-center text-center text-sm font-bold text-muted underline underline-offset-4">
                Abbrechen und zurück zur Mitgliedschaft
            </a>
        </form>
    </div>

    <x-slot:aside>
        <x-network.side-card title="Deine Daten mitnehmen" tone="dune">
            <p class="m-0 text-[15px] text-ink-2">Lade dir jederzeit eine Kopie deiner Daten herunter – vor oder nach der Kündigung.</p>
            <a href="{{ route('account.export') }}" class="o-link self-start text-[15px] text-night">Daten herunterladen</a>
        </x-network.side-card>
        <x-network.side-card title="Auch ohne Anmeldung" tone="night">
            <p class="m-0 text-[15px] text-on-night-2">Kündigen kannst du ebenso über das öffentliche Formular „Verträge hier kündigen“.</p>
            <a href="{{ route('oasis.kuendigen') }}" wire:navigate class="o-link self-start text-[15px] text-on-night">Verträge hier kündigen</a>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
