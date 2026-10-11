{{--
    Labelled form field with optional hint and the validation error for that field.

    Keeps label, hint, error message, aria-invalid and aria-describedby consistent on every form, so pages do not
    repeat this wiring. The error id is only referenced while an error exists.

    Props:
      name      string       input name; also used to read old() and $errors
      label     string       visible label
      type      string       input type, default "text"
      id        string|null  element id, default "f-<name>"
      hint      string|null  short help text below the field
      optional  bool         adds "(optional)" to the label
      value     mixed        value when there is no old input (never used for passwords)
      prefix    string|null  text shown inside the field before the input, e.g. "@"
      bag       string       validation error bag to read, default "default" (Fortify uses e.g. "updatePassword")
    Further attributes (autocomplete, required, minlength, …) go to the <input>.
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'id' => null,
    'hint' => null,
    'optional' => false,
    'value' => null,
    'prefix' => null,
    'bag' => 'default',
])

@php
    $id ??= 'f-'.$name;
    $fieldErrors = $errors->getBag($bag);
    $hasError = $fieldErrors->has($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($hasError ? $id.'-error' : ''));
    $current = $type === 'password' ? null : old($name, $value);
@endphp

<div class="flex flex-col gap-1.5">
    <label for="{{ $id }}" class="text-[15px] font-bold">
        {{ $label }}
        @if ($optional)
            <span class="font-medium text-muted">(optional)</span>
        @endif
    </label>

    @if ($prefix)
        <div @class([
            'flex min-h-[52px] items-center overflow-hidden rounded-[14px] border-[1.5px] bg-field focus-within:outline-3 focus-within:outline-offset-3 focus-within:outline-spring',
            'border-sun-text' => $hasError,
            'border-field-line' => ! $hasError,
        ])>
            <span aria-hidden="true" class="pr-1 pl-4 text-[17px] text-muted">{{ $prefix }}</span>
            <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}"
                   aria-invalid="{{ $hasError ? 'true' : 'false' }}"
                   @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
                   {{ $attributes->merge(['class' => 'min-h-[49px] min-w-0 flex-1 border-none bg-transparent pr-4 text-[17px] text-ink focus-visible:outline-none']) }}>
        </div>
    @else
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}"
               aria-invalid="{{ $hasError ? 'true' : 'false' }}"
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               {{ $attributes->merge(['class' => 'o-input']) }}>
    @endif

    @if ($hint)
        <span id="{{ $id }}-hint" class="text-sm leading-[1.45] text-muted">{{ $hint }}</span>
    @endif
    @if ($hasError)
        <p id="{{ $id }}-error" class="o-error m-0">{{ $fieldErrors->first($name) }}</p>
    @endif
</div>
