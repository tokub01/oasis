{{--
    Locked account, component App\Livewire\Account\Suspended (route app.suspended).
    Variables: $suspension (AccountSuspension|null), $strikes (int), $limit (int).
--}}
<div class="relative px-[clamp(16px,4vw,48px)] py-[clamp(40px,6vw,88px)]">
    @if ($suspension)
        @php($ban = $suspension->type === \App\Models\AccountSuspension::BAN)
        <div class="mx-auto flex max-w-[640px] flex-col gap-6 rounded-[28px] border border-paper-line bg-paper p-[clamp(22px,4vw,44px)]">
            <div class="flex flex-col gap-2">
                <p class="o-eyebrow m-0 text-sun-text">{{ $suspension->label() }}</p>
                <h1 class="o-display m-0 text-[clamp(30px,6vw,40px)] leading-[1.1] text-night">{{ $ban ? 'Dein Konto ist dauerhaft gesperrt' : 'Dein Konto ist vorübergehend gesperrt' }}</h1>
                <p class="m-0 text-ink-2">Seit {{ $suspension->started_at->timezone('Europe/Berlin')->format('d.m.Y, H:i') }} Uhr.
                    @if ($suspension->type === \App\Models\AccountSuspension::PROVISIONAL)
                        Ein Mensch aus dem Moderationsteam prüft das, in der Regel innerhalb von {{ config('oasis.moderation.review_hours') }} Stunden. Bis dahin kannst du nichts schreiben und Oasis nicht lesen.
                    @elseif ($ban)
                        Du kannst Oasis nicht mehr nutzen.
                    @else
                        Das Moderationsteam hebt die Sperre auf, wenn es dein Konto wieder freigibt.
                    @endif
                </p>
            </div>

            <section aria-labelledby="vorwurf-titel" class="flex flex-col gap-2 rounded-2xl bg-dune p-5">
                <h2 id="vorwurf-titel" class="m-0 text-lg font-extrabold text-night">Der Vorwurf</h2>
                <p class="m-0">{{ $suspension->accusation }}</p>
                @if ($suspension->excerpt)
                    <blockquote class="m-0 border-l-4 border-sun pl-3 text-ink-2 whitespace-pre-line break-words">„{{ $suspension->excerpt }}“</blockquote>
                @endif
                @if ($suspension->type === \App\Models\AccountSuspension::PROVISIONAL)
                    <p class="m-0 text-sm text-ink-2">Erkannt hat das ein automatisiertes Verfahren (Sprachmodell). Ob wirklich ein Verstoß vorliegt, entscheidet ein Mensch.</p>
                @endif
                <p class="m-0 text-sm text-ink-2">Bestätigte Verstöße bisher: <strong>{{ $strikes }} von {{ $limit }}</strong>. Nach {{ $limit }} bestätigten Verstößen sperren wir ein Konto dauerhaft. <a href="{{ route('oasis.regeln') }}" class="o-link">Community-Regeln</a></p>
            </section>

            <section aria-labelledby="stellung-titel" class="flex flex-col gap-3">
                <h2 id="stellung-titel" class="m-0 text-lg font-extrabold text-night">Deine Stellungnahme</h2>
                <p class="m-0 text-ink-2">{{ $ban ? 'Hältst du die Sperre für falsch? Schreib uns, warum – das ist deine Beschwerde gegen die Entscheidung.' : 'Du kannst dich entschuldigen, den Zusammenhang erklären oder widersprechen. Das Moderationsteam liest es, bevor es entscheidet.' }}</p>
                <p role="status" class="m-0 font-bold text-spring-text empty:sr-only">{{ $status }}</p>
                <form wire:submit="save" class="flex flex-col gap-3" novalidate>
                    <label for="statement" class="o-label">Stellungnahme</label>
                    <textarea id="statement" wire:model="statement" rows="6" maxlength="{{ \App\Livewire\Account\Suspended::STATEMENT_MAX }}" class="o-input"
                              aria-describedby="statement-hint statement-error" @error('statement') aria-invalid="true" @enderror></textarea>
                    <p id="statement-hint" class="m-0 text-sm text-muted">
                        @if ($suspension->statement_at)
                            Zuletzt gesendet am {{ $suspension->statement_at->timezone('Europe/Berlin')->format('d.m.Y, H:i') }} Uhr. Du kannst sie bis zur Entscheidung ändern.
                        @else
                            Höchstens {{ \App\Livewire\Account\Suspended::STATEMENT_MAX }} Zeichen.
                        @endif
                    </p>
                    <p id="statement-error" class="o-error m-0">@error('statement'){{ $message }}@enderror</p>
                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Stellungnahme senden</button>
                </form>
            </section>

            <section aria-labelledby="weiter-titel" class="flex flex-col gap-2">
                <h2 id="weiter-titel" class="m-0 text-lg font-extrabold text-night">Was du weiterhin kannst</h2>
                <ul class="m-0 flex flex-col gap-1.5 pl-5">
                    <li><a href="{{ route('account.export') }}" class="o-link">Deine Daten herunterladen</a></li>
                    <li><a href="{{ route('app.membership') }}" class="o-link">Mitgliedschaft ansehen oder kündigen</a></li>
                    <li><a href="{{ route('app.settings') }}" class="o-link">Einstellungen und Konto löschen</a></li>
                </ul>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="o-link cursor-pointer border-none bg-transparent p-0 text-base text-night">Abmelden</button>
                </form>
            </section>
        </div>
    @endif
</div>
