{{--
    Reactive post composer. Locked context IDs, validated text/files/alts, and a bounded live tone preview.
    Inline (quick reply inside a Tropfen card) it draws no card of its own and puts audience and button in one row.
    The audience options are shortened here; the enum label stays complete for the Privatsphäre-Center.
--}}
@php
    $id = $this->getId();
    $bodyLabel = $reply ? 'Deine Antwort' : ($carried ? 'Deine Worte dazu' : ($oasis ? 'Dein Tropfen in '.$oasis->name : 'Dein Tropfen'));
    $submitLabel = $reply ? 'Antworten' : ($carried ? 'Weitertragen' : 'Tropfen teilen');
@endphp
<form wire:submit="publish" @class(['flex flex-col gap-4', 'border-t border-paper-line pt-4' => $inline, 'rounded-[24px] bg-paper p-5' => ! $inline])>
    <p role="status" aria-live="polite" class="m-0 font-bold text-spring-text empty:sr-only">{{ $status }}</p>
    <div class="flex flex-col gap-1.5">
        <label for="body-{{ $id }}" @class(['o-label', 'sr-only' => $inline])>{{ $bodyLabel }}</label>
        <textarea id="body-{{ $id }}" wire:model.live.debounce.300ms="body" placeholder="{{ $reply ? 'Antworten …' : '' }}" rows="3" maxlength="{{ \App\Models\Post::MAX_LENGTH }}"
            class="o-input" aria-describedby="body-error-{{ $id }}" aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}"></textarea>
        <p id="body-error-{{ $id }}" class="o-error m-0">@error('body'){{ $message }}@enderror</p>
    </div>
    @if ($toneNudge)
        <div role="status" class="flex flex-col gap-2 rounded-2xl bg-dune p-4">
            <p class="m-0">Nochmal lesen? Dein Tropfen klingt verletzend. Vielleicht findest du andere Worte.</p>
            <label class="flex min-h-11 items-center gap-3"><input type="checkbox" wire:model="tone_confirmed"> So senden – ich meine es genau so.</label>
        </div>
    @endif
    @if ($links !== [])
        <section aria-labelledby="link-hint-{{ $id }}" class="flex flex-col gap-2">
            <h2 id="link-hint-{{ $id }}" class="m-0 text-base font-bold">Hast du den Link gelesen? Weitertragen heißt: Du stehst dafür ein.</h2>
            @foreach ($links as $link)
                <p wire:key="read-link-{{ $loop->index }}" class="m-0"><a class="o-link" href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer nofollow" title="{{ $link['url'] }}">{{ $link['host'] }}</a></p>
            @endforeach
            <label class="flex min-h-11 items-center gap-3"><input type="checkbox" wire:model="link_read" aria-describedby="link-read-error-{{ $id }}"> Ich habe den Inhalt gelesen</label>
            <p id="link-read-error-{{ $id }}" class="o-error m-0">@error('link_read'){{ $message }}@enderror</p>
        </section>
    @endif
    @if ($showImages)
        {{-- Open while images are chosen or have errors: otherwise a Livewire update closes it and hides the hint. --}}
        <details class="rounded-2xl border border-dune-line" @if (count($images) > 0 || $errors->has('images') || $errors->has('images.*') || $errors->has('alts.*')) open @endif>
            <summary class="flex min-h-11 cursor-pointer flex-wrap items-center gap-x-2 px-4 py-2 font-bold text-night">
                <x-oasis.icon name="plus" :size="18" />
                <span class="whitespace-nowrap">Bilder hinzufügen</span>
                <span class="text-sm font-normal text-muted">bis zu {{ \App\Models\Post::MAX_IMAGES }}, JPG, PNG, WebP oder GIF</span>
            </summary>
            <div class="flex flex-col gap-3 px-4 pb-4">
                <input type="file" wire:model="images" multiple accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Bilder auswählen" aria-describedby="upload-errors-{{ $id }}">
                <p wire:loading wire:target="images" class="m-0 text-sm text-muted">Bilder werden hochgeladen …</p>
                <div id="upload-errors-{{ $id }}">@error('images')<p class="o-error m-0">{{ $message }}</p>@enderror</div>
                @foreach ($images as $index => $image)
                    <div wire:key="upload-{{ $image->getFilename() }}" class="flex flex-col gap-1.5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="alt-{{ $id }}-{{ $index }}" class="text-[15px] font-bold">Bildbeschreibung {{ $index + 1 }}</label>
                            <button type="button" wire:click="removeImage({{ $index }})" wire:loading.attr="disabled" class="o-link cursor-pointer border-none bg-transparent text-[15px]">Bild {{ $index + 1 }} entfernen</button>
                        </div>
                        <input id="alt-{{ $id }}-{{ $index }}" wire:model="alts.{{ $index }}" maxlength="300" class="o-input" aria-describedby="alt-error-{{ $id }}-{{ $index }}">
                        <p id="alt-error-{{ $id }}-{{ $index }}" class="o-error m-0">@error('alts.'.$index){{ $message }}@enderror @error('images.'.$index){{ $message }}@enderror</p>
                    </div>
                @endforeach
                <p class="m-0 text-sm text-muted">Wir entfernen beim Hochladen Standort und Kameradaten aus den Bildern.</p>
            </div>
        </details>
    @endif
    <div @class(['flex flex-col gap-4', 'sm:flex-row sm:items-end' => $reply, 'sm:grid sm:grid-cols-2' => ! $reply])>
        <div @class(['flex flex-col gap-1.5', 'sm:flex-1' => $reply])>
            <label for="visibility-{{ $id }}" class="o-label">Wer darf lesen?</label>
            <select id="visibility-{{ $id }}" wire:model="visibility" class="o-input font-normal" aria-describedby="visibility-hint-{{ $id }}">
                @foreach (\App\Enums\Network\Visibility::cases() as $option)
                    @continue(auth()->user()?->isMinor() && $option === \App\Enums\Network\Visibility::Public)
                    <option wire:key="visibility-{{ $option->value }}" value="{{ $option->value }}">{{ $option === \App\Enums\Network\Visibility::Public ? 'Öffentlich' : $option->label() }}</option>
                @endforeach
            </select>
        </div>
        @if (! $reply && ! $carried)
            <div class="flex flex-col gap-1.5">
                <label for="kind-{{ $id }}" class="o-label">Art des Tropfens</label>
                <select id="kind-{{ $id }}" wire:model="kind" class="o-input font-normal">
                    @foreach (\App\Enums\Network\PostKind::cases() as $option)
                        <option wire:key="kind-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        @if ($reply)
            <button wire:loading.attr="disabled" type="submit" class="o-btn o-btn-md o-btn-dark">{{ $submitLabel }}</button>
        @endif
    </div>
    <p id="visibility-hint-{{ $id }}" class="m-0 -mt-2 text-sm text-muted">Öffentlich heißt: auch ohne Konto lesbar.</p>
    @error('visibility')<p class="o-error m-0">{{ $message }}</p>@enderror
    @error('kind')<p class="o-error m-0">{{ $message }}</p>@enderror
    @unless ($reply)
        <button wire:loading.attr="disabled" type="submit" class="o-btn o-btn-md o-btn-dark self-start">{{ $submitLabel }}</button>
    @endunless
</form>
