{{--
    Statutory 14-day contract withdrawal confirmation (route app.membership.withdraw.confirm, App\Livewire\Membership\Withdraw).
--}}
<div>
<x-network.columns label="Zum Widerruf">
    <x-network.page-header eyebrow="Mitgliedschaft" title="Mitgliedschaft widerrufen">
        Gesetzliches Widerrufsrecht innerhalb von 14 Tagen (§ 355 BGB).
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
                    <dt class="shrink-0 font-bold text-muted">Rechtsgrundlage:</dt>
                    <dd class="m-0 min-w-0 font-extrabold break-words text-night sm:text-right">14-tägiges gesetzliches Widerrufsrecht</dd>
                </div>
            </dl>
        </div>

        <div class="flex flex-col gap-2 text-[15px] leading-relaxed text-ink">
            <p class="m-0">
                <strong>Folgen des Widerrufs:</strong>
            </p>
            <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-ink-2">
                <li>Deine Mitgliedschaft wird <strong>mit sofortiger Wirkung</strong> beendet.</li>
                <li>Etwaige bereits gezahlte Beträge werden dir unverzüglich über dein ursprüngliches Zahlungsmittel erstattet.</li>
                <li>Dein Konto wechselt in den Lesemodus. Du kannst deine Daten innerhalb von 30 Tagen herunterladen.</li>
            </ul>
        </div>

        <form wire:submit="withdraw" class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:gap-6">
            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark w-full justify-center sm:w-auto">
                Jetzt widerrufen
            </button>
            <a wire:navigate href="{{ route('app.membership') }}" class="inline-flex min-h-11 items-center justify-center text-center text-sm font-bold text-muted underline underline-offset-4">
                Abbrechen und zurück zur Mitgliedschaft
            </a>
        </form>
    </div>

    <x-slot:aside>
        <x-network.side-card title="Widerrufsbelehrung" tone="dune">
            <p class="m-0 text-[15px] text-ink-2">Alle Einzelheiten zu Frist, Folgen und Erstattung stehen in der Widerrufsbelehrung.</p>
            <a href="{{ route('oasis.widerrufsbelehrung') }}" wire:navigate class="o-link self-start text-[15px] text-night">Widerrufsbelehrung lesen</a>
        </x-network.side-card>
        <x-network.side-card title="Deine Daten mitnehmen" tone="night">
            <p class="m-0 text-[15px] text-on-night-2">Nach dem Widerruf hast du 30 Tage Zeit, deine Daten herunterzuladen. Das geht auch jetzt schon.</p>
            <a href="{{ route('account.export') }}" class="o-link self-start text-[15px] text-on-night">Daten herunterladen</a>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
