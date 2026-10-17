{{--
    Einstieg für neue Mitglieder, component App\Livewire\Onboarding\Welcome (route app.welcome). Variables: $steps
    (key => title), $key (current step), $oases (Collection<Oasis> with members_count, step 3), $joined (oasis ids),
    $audiences and $visibilities (value => label, limited for members under 18), $colors (AvatarColor[]), $public.
    Every step can be skipped; "Später" ends the tour.
--}}
<div>
<x-network.columns label="Dein Einstieg">
    <header class="flex flex-col gap-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="o-eyebrow m-0">Willkommen · Schritt {{ $step }} von {{ count($steps) }}</p>
            <button type="button" wire:click="finish" class="o-link inline-flex min-h-11 items-center border-none bg-transparent text-[15px] text-night">Später – direkt zur Quelle</button>
        </div>
        <h1 id="willkommen-titel" tabindex="-1" class="o-h2 m-0 text-night outline-none">{{ $steps[$key] }}</h1>
        <div aria-hidden="true" class="flex gap-2">
            @foreach (array_keys($steps) as $i => $stepKey)
                <span @class(['h-1.5 flex-1 rounded-full', 'bg-night' => $i < $step, 'bg-dune' => $i >= $step])></span>
            @endforeach
        </div>
    </header>

    <section class="flex flex-col gap-5 rounded-[24px] bg-paper p-5 sm:p-6" wire:key="schritt-{{ $key }}">
        @if ($key === 'profil')
            <p class="m-0 text-ink-2">Ein paar Worte helfen anderen, dich einzuordnen. Alles ist freiwillig und lässt sich jederzeit ändern.</p>
            <div class="flex flex-col gap-1.5">
                <label for="w-bio" class="text-[15px] font-bold">Über dich <span class="font-medium text-muted">(optional)</span></label>
                <textarea id="w-bio" wire:model="bio" rows="3" maxlength="300" class="o-input resize-y" placeholder="Zum Beispiel: Ich fotografiere analog und gärtnere auf dem Balkon."
                          @error('bio') aria-describedby="w-bio-fehler" aria-invalid="true" @enderror></textarea>
                @error('bio') <p id="w-bio-fehler" class="o-error m-0">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="w-ort" class="text-[15px] font-bold">Ort <span class="font-medium text-muted">(optional)</span></label>
                <input id="w-ort" type="text" wire:model="location" maxlength="80" class="o-input" placeholder="Zum Beispiel deine Stadt"
                       @error('location') aria-describedby="w-ort-fehler" aria-invalid="true" @enderror>
                @error('location') <p id="w-ort-fehler" class="o-error m-0">{{ $message }}</p> @enderror
            </div>
            <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
                <legend class="mb-2 p-0 text-[15px] font-bold">Farbe deines Profilbilds</legend>
                <div class="flex flex-wrap gap-x-4 gap-y-1">
                    @foreach ($colors as $color)
                        <label wire:key="farbe-{{ $color->value }}" class="flex min-h-11 items-center gap-2">
                            <input type="radio" name="farbe" value="{{ $color->value }}" wire:model="avatar_color">
                            <span aria-hidden="true" class="inline-block size-6 rounded-full {{ $color->classes() }}"></span>{{ $color->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @elseif ($key === 'sichtbarkeit')
            <p class="m-0 text-ink-2">Oasis startet zurückhaltend: Nur Mitglieder sehen dich. Du kannst das jetzt ändern oder später in Ruhe im Privatsphäre-Check.</p>
            @foreach ([['profile_audience', 'Wer darf dein Profil sehen?', $audiences], ['default_post_visibility', 'Wer liest deine Tropfen normalerweise?', $visibilities]] as [$field, $legend, $choices])
                <fieldset wire:key="feld-{{ $field }}" class="m-0 flex flex-col gap-2 border-none p-0">
                    <legend class="mb-1 p-0 text-[17px] font-bold text-night">{{ $legend }}</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($choices as $value => $label)
                            <label wire:key="{{ $field }}-{{ $value }}" class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-2xl border border-dune-line bg-field px-4 py-2 text-[15px] sm:rounded-full has-checked:border-night has-checked:bg-night has-checked:text-on-night">
                                <input type="radio" name="{{ $field }}" value="{{ $value }}" wire:model="{{ $field }}" class="size-4 accent-sun">{{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error($field) <p class="o-error m-0">{{ $message }}</p> @enderror
                </fieldset>
            @endforeach
            <a href="{{ route('app.privacy.check') }}" wire:navigate class="o-link self-start text-[15px] text-night">Alle Einstellungen im Privatsphäre-Check</a>
        @elseif ($key === 'oasen')
            <p class="m-0 text-ink-2">Oasen sind Gruppen zu einem Thema. Tritt ein paar bei, dann füllt sich deine Quelle. Ausgesucht von Menschen, nicht von einem Algorithmus.</p>
            <ul class="m-0 grid list-none gap-3 p-0 sm:grid-cols-2">
                @forelse ($oases as $oasis)
                    <li wire:key="oase-{{ $oasis->id }}" class="flex flex-col gap-2 rounded-2xl border border-paper-line p-4">
                        <span class="flex items-center gap-3">
                            <span aria-hidden="true" class="size-9 flex-none rounded-full bg-{{ $oasis->color }}"></span>
                            <span class="flex min-w-0 flex-col leading-tight"><span class="font-bold break-words text-night">{{ $oasis->name }}</span><span class="text-sm text-muted">{{ $oasis->members_count }} Mitglieder</span></span>
                        </span>
                        <p class="m-0 text-[15px] break-words text-ink-2">{{ \Illuminate\Support\Str::limit($oasis->description, 110) }}</p>
                        @php($in = in_array($oasis->id, $joined, true))
                        <button type="button" wire:click="toggleOasis({{ $oasis->id }})" wire:loading.attr="disabled" aria-pressed="{{ $in ? 'true' : 'false' }}"
                                @class(['o-btn o-btn-sm self-start', 'o-btn-dark' => $in, 'o-btn-outline' => ! $in])>{{ $in ? 'Beigetreten ✓' : 'Beitreten' }}</button>
                    </li>
                @empty
                    <li class="text-ink-2">Noch gibt es keine öffentlichen Oasen. Du kannst selbst eine gründen.</li>
                @endforelse
            </ul>
            <a href="{{ route('network.oases.index') }}" wire:navigate class="o-link self-start text-[15px] text-night">Alle Oasen ansehen</a>
        @else
            <p class="m-0 text-ink-2">Deine Quelle zeigt, was Menschen teilen, denen du folgst – der Reihe nach und mit Ende. Such nach Menschen, die du kennst, oder lade sie ein.</p>
            <form method="GET" action="{{ route('search.index') }}" class="flex flex-col gap-2 min-[400px]:flex-row" role="search">
                <label for="w-suche" class="sr-only">Menschen suchen</label>
                <input id="w-suche" type="search" name="q" maxlength="100" placeholder="Name oder @benutzername" class="o-input min-w-0 flex-1">
                <button type="submit" class="o-btn o-btn-md o-btn-outline">Suchen</button>
            </form>
            <a href="{{ route('network.invitations.index') }}" wire:navigate class="o-btn o-btn-md o-btn-outline self-start">Freund:innen einladen</a>
        @endif
    </section>

    <div class="flex flex-wrap items-center gap-3">
        @if ($step > 1)
            <button type="button" wire:click="back" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-outline">Zurück</button>
        @endif
        @if ($step < count($steps))
            <button type="button" wire:click="next" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark">Weiter</button>
            <button type="button" wire:click="skipStep" wire:loading.attr="disabled" class="o-link inline-flex min-h-11 items-center border-none bg-transparent px-2 text-[15px] text-night">Schritt überspringen</button>
        @else
            <button type="button" wire:click="finish" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark">Los geht's</button>
        @endif
    </div>

    <x-slot:aside>
        <x-network.side-card title="In deinem Tempo" tone="dune">
            <p class="m-0 text-[15px] text-ink-2">Jeder Schritt ist freiwillig. Alles findest du später auch in deinem Profil, den Privatsphäre-Einstellungen und unter Oasen.</p>
        </x-network.side-card>
    </x-slot:aside>
</x-network.columns>
</div>
