{{--
    Register of data breaches, component App\Livewire\Admin\DataBreaches (route admin.breaches). Variables: $breaches
    (Collection<DataBreach>, open first), $reports (id => drafted Art. 33 report), $risks (value => label).
--}}
@php
    $card = 'flex flex-col gap-4 rounded-[24px] bg-paper p-5 sm:p-6';
    $berlin = fn ($date) => $date?->timezone('Europe/Berlin')->format('d.m.Y, H:i').' Uhr';
    $fields = [
        ['title', 'Kurze Bezeichnung', 'input', 'z. B. „Rechnungs-E-Mails an falsche Empfänger“'],
        ['description', 'Was ist passiert?', 'textarea', 'Ablauf, Ursache, seit wann, wie entdeckt.'],
        ['data_categories', 'Welche Daten sind betroffen?', 'textarea', 'z. B. Name, E-Mail-Adresse, Zahlungsdaten.'],
        ['consequences', 'Wahrscheinliche Folgen für die Betroffenen', 'textarea', 'z. B. Spam, Identitätsmissbrauch, Bloßstellung.'],
        ['measures', 'Ergriffene oder geplante Maßnahmen', 'textarea', 'z. B. Lücke geschlossen, Passwörter zurückgesetzt.'],
    ];
@endphp
<div class="flex flex-col gap-8">
    <div class="flex flex-col gap-2">
        <p class="o-eyebrow m-0">Admin</p>
        <h1 class="o-h2 m-0 text-night">Datenpannen</h1>
        <p class="m-0 max-w-[52em] text-ink-2">
            Jede Verletzung des Schutzes personenbezogener Daten wird hier dokumentiert – auch wenn sie nicht gemeldet werden muss (Art. 33 Abs. 5 DSGVO).
            Besteht ein Risiko, muss die Aufsichtsbehörde binnen 72 Stunden ab Bekanntwerden informiert werden; bei hohem Risiko auch die Betroffenen.
            Oasis meldet nichts selbst: Die Seite bereitet die Meldung vor, du schickst sie ab. Der Notfallplan steht in docs/oasis-incident-response.md.
        </p>
    </div>

    <p role="status" aria-live="polite" class="m-0 rounded-2xl bg-dune px-[18px] py-3.5 font-bold text-night empty:sr-only">{{ $status }}</p>

    <form wire:submit="record" class="{{ $card }}" novalidate>
        <h2 class="m-0 text-[21px] font-extrabold text-night">Neue Datenpanne erfassen</h2>
        @foreach ($fields as [$name, $label, $type, $hint])
            <div wire:key="feld-{{ $name }}" class="flex flex-col gap-1.5">
                <label for="panne-{{ $name }}" class="text-[15px] font-bold">{{ $label }}</label>
                @if ($type === 'input')
                    <input id="panne-{{ $name }}" type="text" wire:model="form.{{ $name }}" maxlength="160" class="o-input"
                           aria-describedby="panne-{{ $name }}-hilfe @error('form.'.$name) panne-{{ $name }}-fehler @enderror" @error('form.'.$name) aria-invalid="true" @enderror>
                @else
                    <textarea id="panne-{{ $name }}" wire:model="form.{{ $name }}" rows="3" maxlength="5000" class="o-input resize-y"
                              aria-describedby="panne-{{ $name }}-hilfe @error('form.'.$name) panne-{{ $name }}-fehler @enderror" @error('form.'.$name) aria-invalid="true" @enderror></textarea>
                @endif
                <p id="panne-{{ $name }}-hilfe" class="m-0 text-sm text-muted">{{ $hint }}</p>
                @error('form.'.$name) <p id="panne-{{ $name }}-fehler" class="o-error m-0">{{ $message }}</p> @enderror
            </div>
        @endforeach
        <div class="grid gap-4 md:grid-cols-2">
            <div class="flex flex-col gap-1.5">
                <label for="panne-discovered_at" class="text-[15px] font-bold">Bekannt geworden am</label>
                <input id="panne-discovered_at" type="datetime-local" wire:model="form.discovered_at" class="o-input"
                       @error('form.discovered_at') aria-describedby="panne-discovered_at-fehler" aria-invalid="true" @enderror>
                @error('form.discovered_at') <p id="panne-discovered_at-fehler" class="o-error m-0">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="panne-affected_people" class="text-[15px] font-bold">Ungefähre Zahl Betroffener <span class="font-normal text-muted">(optional)</span></label>
                <input id="panne-affected_people" type="number" min="0" inputmode="numeric" wire:model="form.affected_people" class="o-input"
                       @error('form.affected_people') aria-describedby="panne-affected_people-fehler" aria-invalid="true" @enderror>
                @error('form.affected_people') <p id="panne-affected_people-fehler" class="o-error m-0">{{ $message }}</p> @enderror
            </div>
        </div>
        <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
            <legend class="mb-2 p-0 text-[15px] font-bold">Risiko für die Betroffenen</legend>
            @foreach ($risks as $value => $label)
                <label wire:key="risiko-{{ $value }}" class="flex min-h-11 items-center gap-3">
                    <input type="radio" name="risiko" value="{{ $value }}" wire:model="form.risk" class="size-5 flex-none accent-night"> {{ $label }}
                </label>
            @endforeach
            @error('form.risk') <p class="o-error m-0">{{ $message }}</p> @enderror
        </fieldset>
        <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Datenpanne erfassen</button>
    </form>

    <section aria-labelledby="register-titel" class="flex flex-col gap-4">
        <h2 id="register-titel" class="m-0 text-[21px] font-extrabold text-night">Register ({{ $breaches->count() }})</h2>
        @forelse ($breaches as $breach)
            @php
                $due = $breach->reportDueAt();
                $hoursLeft = (int) floor(now()->diffInMinutes($due, false) / 60);
            @endphp
            <article wire:key="panne-{{ $breach->id }}" @class([$card, 'border-2 border-sun' => $breach->reportOutstanding() && ! $breach->closed_at])>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex min-w-0 flex-col">
                        <h3 class="m-0 text-lg font-extrabold break-words text-night">{{ $breach->title }}</h3>
                        <p class="m-0 text-sm text-ink-2">Bekannt seit {{ $berlin($breach->discovered_at) }} · {{ $risks[$breach->risk] }}</p>
                    </div>
                    <span class="rounded-full bg-sand px-3 py-1 text-sm font-bold text-night">{{ $breach->closed_at ? 'Abgeschlossen' : 'Offen' }}</span>
                </div>

                @if ($breach->reportOutstanding() && ! $breach->closed_at)
                    <p role="alert" class="m-0 font-bold text-sun-text">
                        {{ $hoursLeft >= 0 ? 'Meldung an die Aufsichtsbehörde fällig in '.$hoursLeft.' Stunden ('.$berlin($due).').' : 'Die 72 Stunden sind abgelaufen ('.$berlin($due).'). Sofort melden und die Verzögerung begründen.' }}
                    </p>
                @endif

                @if ($breach->mustReport())
                    <details>
                        <summary class="cursor-pointer font-bold text-night">Vorbereitete Meldung an die Aufsichtsbehörde</summary>
                        <label for="meldung-{{ $breach->id }}" class="sr-only">Meldungstext zum Kopieren</label>
                        <textarea id="meldung-{{ $breach->id }}" readonly rows="14" class="o-input mt-2 w-full resize-y font-mono text-sm">{{ $reports[$breach->id] }}</textarea>
                        <p class="mt-1 mb-0 text-sm text-muted">Zuständig: {{ config('oasis.privacy.authority') }}. Bitte prüfen und dort über das offizielle Meldeformular einreichen.</p>
                    </details>
                @endif

                <ul class="m-0 flex list-none flex-col gap-1 p-0 text-sm">
                    <li>Meldung an die Behörde: {{ $breach->authority_reported_at ? $berlin($breach->authority_reported_at).' – '.$breach->authority_reference : ($breach->mustReport() ? 'noch offen' : 'nicht nötig') }}</li>
                    <li>Betroffene informiert: {{ $breach->people_notified_at ? $berlin($breach->people_notified_at) : ($breach->mustNotifyPeople() ? 'noch offen' : 'nicht nötig') }}</li>
                </ul>

                @unless ($breach->closed_at)
                    <div class="flex flex-col gap-3 border-t border-paper-line pt-4">
                        @if ($breach->reportOutstanding())
                            <form wire:submit="recordReport({{ $breach->id }})" class="flex flex-col gap-2">
                                <label for="referenz-{{ $breach->id }}" class="text-[15px] font-bold">Meldung abgeschickt? Behörde und Aktenzeichen</label>
                                <div class="flex flex-col gap-2 min-[480px]:flex-row">
                                    <input id="referenz-{{ $breach->id }}" type="text" wire:model="references.{{ $breach->id }}" maxlength="160" class="o-input min-w-0 flex-1"
                                           @error('references.'.$breach->id) aria-describedby="referenz-{{ $breach->id }}-fehler" aria-invalid="true" @enderror>
                                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-dark">Meldung vermerken</button>
                                </div>
                                @error('references.'.$breach->id) <p id="referenz-{{ $breach->id }}-fehler" class="o-error m-0">{{ $message }}</p> @enderror
                            </form>
                        @endif
                        @if ($breach->mustNotifyPeople() && ! $breach->people_notified_at)
                            <button type="button" wire:click="recordNotified({{ $breach->id }})" wire:confirm="Sind alle Betroffenen in klarer Sprache benachrichtigt (Art. 34 DSGVO)?" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline self-start">Benachrichtigung der Betroffenen vermerken</button>
                        @endif
                        <form wire:submit="addNote({{ $breach->id }})" class="flex flex-col gap-2">
                            <label for="nachtrag-{{ $breach->id }}" class="text-[15px] font-bold">Nachtrag</label>
                            <textarea id="nachtrag-{{ $breach->id }}" wire:model="notes.{{ $breach->id }}" rows="2" maxlength="2000" class="o-input resize-y"
                                      @error('notes.'.$breach->id) aria-describedby="nachtrag-{{ $breach->id }}-fehler" aria-invalid="true" @enderror></textarea>
                            @error('notes.'.$breach->id) <p id="nachtrag-{{ $breach->id }}-fehler" class="o-error m-0">{{ $message }}</p> @enderror
                            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline self-start">Nachtrag speichern</button>
                        </form>
                        <button type="button" wire:click="close({{ $breach->id }})" wire:confirm="Datenpanne abschließen?" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline self-start">Abschließen</button>
                        @error('close.'.$breach->id) <p class="o-error m-0">{{ $message }}</p> @enderror
                    </div>
                @endunless

                <details>
                    <summary class="cursor-pointer text-sm font-bold text-night">Verlauf ({{ count($breach->log) }})</summary>
                    <ol class="m-0 mt-2 flex list-none flex-col gap-2 border-l-2 border-dune-line p-0 pl-4 text-sm">
                        @foreach ($breach->log as $i => $entry)
                            <li wire:key="panne-{{ $breach->id }}-log-{{ $i }}"><span class="text-ink-2">{{ $berlin(\Carbon\CarbonImmutable::parse($entry['at'])) }} · {{ $entry['by'] }}:</span> {{ $entry['text'] }}</li>
                        @endforeach
                    </ol>
                </details>
            </article>
        @empty
            <p class="m-0 rounded-[20px] bg-paper p-5 text-ink-2">Noch keine Datenpannen erfasst.</p>
        @endforelse
    </section>
</div>
