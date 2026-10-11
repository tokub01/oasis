{{--
    Form for a new Tropfen, a reply or a carry ("weitertragen"). Posts to network.posts.store; works without JS.

    Props:
      label      string           visible label of the text field
      submit     string           button text
      oasis      Oasis|null       post into this oasis (hidden field)
      replyTo    Post|null        reply to this Tropfen (hidden field)
      carried    Post|null        carry this Tropfen further (hidden field)
      images     bool             offer image upload (default true; false for replies)

    Images: up to four slots, each with its own required description (alt text). Uploaded files are re-encoded on the
    server without metadata.
--}}
@props(['label' => 'Dein Tropfen', 'submit' => 'Tropfen teilen', 'oasis' => null, 'replyTo' => null, 'carried' => null, 'images' => true])

@php
    $profileVisibility = auth()->user()->profileOrDefault()->visibility->value;
    $forced = $oasis?->visibility->value === 'members' || $replyTo?->visibility->value === 'members' || $carried?->visibility->value === 'members';
    $id = 'compose-'.($replyTo?->id ?? $carried?->id ?? $oasis?->slug ?? 'quelle');
@endphp

<form method="POST" action="{{ route('network.posts.store') }}" enctype="multipart/form-data"
      {{ $attributes->class('flex flex-col gap-4 rounded-[24px] bg-paper p-5') }}>
    @csrf
    @if ($oasis) <input type="hidden" name="oasis" value="{{ $oasis->slug }}"> @endif
    @if ($replyTo) <input type="hidden" name="reply_to" value="{{ $replyTo->id }}"> @endif
    @if ($carried) <input type="hidden" name="carried" value="{{ $carried->id }}"> @endif

    <div class="flex flex-col gap-1.5">
        <label for="{{ $id }}-body" class="text-[15px] font-bold">{{ $label }}</label>
        <textarea id="{{ $id }}-body" name="body" rows="3" maxlength="{{ \App\Models\Post::MAX_LENGTH }}" required
                  class="o-input min-h-[96px] resize-y" aria-invalid="{{ $errors->has('body') ? 'true' : 'false' }}"
                  aria-describedby="{{ $id }}-hint{{ $errors->has('body') ? ' '.$id.'-error' : '' }}">{{ old('body') }}</textarea>
        <p id="{{ $id }}-hint" class="m-0 text-sm text-muted">Höchstens {{ \App\Models\Post::MAX_LENGTH }} Zeichen.</p>
        @error('body') <p id="{{ $id }}-error" class="m-0 text-sm font-bold text-sun-text">{{ $message }}</p> @enderror
    </div>

    @if ($images)
        <details class="rounded-2xl border border-dune-line px-4 py-3" @if ($errors->has('images*') || $errors->has('alts*')) open @endif>
            <summary class="cursor-pointer font-bold text-night">Bilder hinzufügen (bis zu {{ \App\Models\Post::MAX_IMAGES }})</summary>
            <div class="mt-3 flex flex-col gap-4">
                @for ($slot = 0; $slot < \App\Models\Post::MAX_IMAGES; $slot++)
                    <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
                        <legend class="text-sm font-bold">Bild {{ $slot + 1 }}</legend>
                        <input type="file" name="images[{{ $slot }}]" accept="image/jpeg,image/png,image/webp,image/gif" aria-label="Bild {{ $slot + 1 }} auswählen">
                        <label for="{{ $id }}-alt-{{ $slot }}" class="text-sm">Bildbeschreibung</label>
                        <input id="{{ $id }}-alt-{{ $slot }}" type="text" name="alts[{{ $slot }}]" maxlength="300" class="o-input" value="{{ old('alts.'.$slot) }}">
                        @error('images.'.$slot) <p class="m-0 text-sm font-bold text-sun-text">{{ $message }}</p> @enderror
                        @error('alts.'.$slot) <p class="m-0 text-sm font-bold text-sun-text">{{ $message }}</p> @enderror
                    </fieldset>
                @endfor
                <p class="m-0 text-sm text-muted">Wir entfernen beim Hochladen Standort und Kameradaten aus den Bildern.</p>
            </div>
        </details>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        @if ($forced)
            <input type="hidden" name="visibility" value="members">
            <p class="m-0 text-sm text-muted">Sichtbar nur für Mitglieder, wie der Ort, an dem du schreibst.</p>
        @else
            <div class="flex flex-col gap-1.5">
                <label for="{{ $id }}-visibility" class="text-sm font-bold">Wer darf lesen?</label>
                <select id="{{ $id }}-visibility" name="visibility" class="o-input">
                    @foreach (\App\Enums\Network\Visibility::cases() as $option)
                        <option value="{{ $option->value }}" @selected(old('visibility', $profileVisibility) === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <button type="submit" class="o-btn o-btn-md o-btn-dark">{{ $submit }}</button>
    </div>
</form>
