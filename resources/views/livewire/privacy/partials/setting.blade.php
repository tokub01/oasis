{{--
    One privacy setting, shared by the privacy center and the guided check. $key is a key of App\Support\Privacy\PrivacyOptions;
    the value is bound to settings.<key> and saved by the component as soon as it changes.
--}}
@use('App\Support\Privacy\PrivacyOptions')
@php
    $text = PrivacyOptions::text($key);
    $id = 'privacy-'.str_replace('_', '-', $key);
@endphp
<div wire:key="setting-{{ $key }}" class="flex flex-col gap-3 border-t border-paper-line pt-5 first:border-t-0 first:pt-0">
    @if (PrivacyOptions::isSwitch($key))
        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <label for="{{ $id }}" class="cursor-pointer text-[17px] font-bold text-night">{{ $text['title'] }}</label>
                <p id="{{ $id }}-hilfe" class="m-0 text-[15px] leading-[1.55] text-ink-2">{{ $text['help'] }}</p>
            </div>
            <input id="{{ $id }}" type="checkbox" role="switch" wire:model.live="settings.{{ $key }}" aria-describedby="{{ $id }}-hilfe"
                   class="mt-1 size-6 flex-none cursor-pointer accent-night">
        </div>
    @else
        <fieldset class="m-0 flex flex-col gap-2 border-0 p-0" aria-describedby="{{ $id }}-hilfe">
            <legend class="mb-1 p-0 text-[17px] font-bold text-night">{{ $text['title'] }}</legend>
            <p id="{{ $id }}-hilfe" class="m-0 mb-1 text-[15px] leading-[1.55] text-ink-2">{{ $text['help'] }}</p>
            <div class="flex flex-wrap gap-2">
                @foreach (PrivacyOptions::choices($key) as $value => $label)
                    <label wire:key="{{ $key }}-{{ $value }}" class="flex min-h-11 cursor-pointer items-center gap-2.5 rounded-full border border-dune-line bg-field px-4 text-[15px] has-checked:border-night has-checked:bg-night has-checked:text-on-night">
                        <input type="radio" name="{{ $key }}" value="{{ $value }}" wire:model.live="settings.{{ $key }}" class="size-4 accent-sun">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </fieldset>
    @endif
    @error('settings.'.$key) <p role="alert" class="m-0 text-sm font-bold text-sun-text">{{ $message }}</p> @enderror
</div>
