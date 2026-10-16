{{-- Reactive post composer. Locked context IDs, validated text/files/alts, and a bounded live tone preview. --}}
<form wire:submit="publish" class="flex flex-col gap-4 rounded-[24px] bg-paper p-5">
    <p role="status" aria-live="polite">{{ $status }}</p>
    <label for="body-{{ $this->getId() }}" class="o-label">{{ $reply ? 'Deine Antwort' : ($carried ? 'Deine Worte dazu' : 'Dein Tropfen') }}</label>
    <textarea id="body-{{ $this->getId() }}" wire:model.live.debounce.300ms="body" placeholder="{{ $reply ? 'Antworten …' : '' }}" rows="3" maxlength="{{ \App\Models\Post::MAX_LENGTH }}"
        class="o-input" aria-describedby="body-error-{{ $this->getId() }}" aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}"></textarea>
    <p id="body-error-{{ $this->getId() }}" class="o-error">@error('body'){{ $message }}@enderror</p>
    @if ($toneNudge)
        <div role="status" class="rounded-2xl bg-dune p-4">
            <p>Nochmal lesen? Dein Tropfen klingt verletzend. Vielleicht findest du andere Worte.</p>
            <label><input type="checkbox" wire:model="tone_confirmed"> So senden – ich meine es genau so.</label>
        </div>
    @endif
    @if ($links !== [])
        <section aria-labelledby="link-hint-{{ $this->getId() }}">
            <h2 id="link-hint-{{ $this->getId() }}" class="text-base font-bold">Hast du den Link gelesen? Weitertragen heißt: Du stehst dafür ein.</h2>
            @foreach ($links as $link)
                <p wire:key="read-link-{{ $loop->index }}"><a class="o-link" href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer nofollow" title="{{ $link['url'] }}">{{ $link['host'] }}</a></p>
            @endforeach
            <label><input type="checkbox" wire:model="link_read" aria-describedby="link-read-error-{{ $this->getId() }}"> Ich habe den Inhalt gelesen</label>
            <p id="link-read-error-{{ $this->getId() }}" class="o-error">@error('link_read'){{ $message }}@enderror</p>
        </section>
    @endif
    <div class="flex flex-wrap gap-4">
        <label class="o-label">Wer darf lesen?
            <select wire:model="visibility" class="o-input">
                @foreach (\App\Enums\Network\Visibility::cases() as $option)
                    <option wire:key="visibility-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        @if (! $reply && ! $carried)
            <label class="o-label">Art des Tropfens
                <select wire:model="kind" class="o-input">
                    @foreach (\App\Enums\Network\PostKind::cases() as $option)
                        <option wire:key="kind-{{ $option->value }}" value="{{ $option->value }}">{{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>
        @endif
    </div>
    @if ($showImages)
    <details class="rounded-2xl border border-dune-line p-4">
        <summary>Bilder hinzufügen (bis zu {{ \App\Models\Post::MAX_IMAGES }})</summary>
        <input type="file" wire:model="images" multiple accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Bilder auswählen" aria-describedby="upload-errors-{{ $this->getId() }}">
        <p wire:loading wire:target="images">Bilder werden hochgeladen …</p>
        <div id="upload-errors-{{ $this->getId() }}">@error('images')<p class="o-error">{{ $message }}</p>@enderror</div>
        @foreach ($images as $index => $image)
            <div wire:key="upload-{{ $image->getFilename() }}">
                <button type="button" wire:click="removeImage({{ $index }})" wire:loading.attr="disabled" class="o-link">Bild {{ $index + 1 }} entfernen</button>
                <label for="alt-{{ $this->getId() }}-{{ $index }}">Bildbeschreibung {{ $index + 1 }}</label>
                <input id="alt-{{ $this->getId() }}-{{ $index }}" wire:model="alts.{{ $index }}" maxlength="300" class="o-input" aria-describedby="alt-error-{{ $this->getId() }}-{{ $index }}">
                <p id="alt-error-{{ $this->getId() }}-{{ $index }}" class="o-error">@error('alts.'.$index){{ $message }}@enderror @error('images.'.$index){{ $message }}@enderror</p>
            </div>
        @endforeach
        <p>Wir entfernen beim Hochladen Standort und Kameradaten aus den Bildern.</p>
    </details>
    @endif
    @error('visibility')<p class="o-error">{{ $message }}</p>@enderror
    @error('kind')<p class="o-error">{{ $message }}</p>@enderror
    <button wire:loading.attr="disabled" type="submit" class="o-btn o-btn-md o-btn-dark">{{ $reply ? 'Antworten' : ($carried ? 'Weitertragen' : 'Tropfen teilen') }}</button>
</form>
