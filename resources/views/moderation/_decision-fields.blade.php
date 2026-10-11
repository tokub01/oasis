{{--
    Decision fields shared by the case decision form and the "overturn" branch of a complaint review.
    Together they form the statement of reasons under DSA Art. 17: outcome, ground with rule or legal provision,
    the facts, and the explanation that the affected person reads.

    Variables:
      $outcomes  list<App\Enums\Moderation\Outcome>
      $grounds   list<App\Enums\Moderation\Ground>
      $prefix    string  id prefix so the fields can appear twice on one page (default "d")
--}}
@php($prefix ??= 'd')

<fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
    <legend class="mb-2 p-0 text-[16px] font-extrabold">Maßnahme</legend>
    @foreach ($outcomes as $outcome)
        <label class="flex min-h-11 items-center gap-3 text-[16px]">
            <input type="radio" name="outcome" value="{{ $outcome->value }}" @checked(old('outcome') === $outcome->value) required class="size-5 accent-night">
            {{ $outcome->label() }}
        </label>
    @endforeach
    @error('outcome')<p class="o-error m-0">{{ $message }}</p>@enderror
</fieldset>

<fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
    <legend class="mb-2 p-0 text-[16px] font-extrabold">Grundlage</legend>
    @foreach ($grounds as $ground)
        <label class="flex min-h-11 items-center gap-3 text-[16px]">
            <input type="radio" name="ground" value="{{ $ground->value }}" @checked(old('ground') === $ground->value) required class="size-5 accent-night">
            {{ $ground->label() }}
        </label>
    @endforeach
    @error('ground')<p class="o-error m-0">{{ $message }}</p>@enderror
</fieldset>

<x-oasis.field :id="$prefix.'-rule'" name="rule_reference" label="Regel oder Rechtsvorschrift" maxlength="255" required
               hint="Zum Beispiel „Community-Regel 2: Kein Hass“ oder „§ 185 StGB“." />

<div class="flex flex-col gap-1.5">
    <label for="{{ $prefix }}-facts" class="text-[15px] font-bold">Festgestellte Tatsachen</label>
    <textarea id="{{ $prefix }}-facts" name="facts" rows="4" maxlength="10000" required class="o-input resize-y"
              aria-invalid="{{ $errors->has('facts') ? 'true' : 'false' }}">{{ old('facts') }}</textarea>
    @error('facts')<p class="o-error m-0">{{ $message }}</p>@enderror
</div>

<div class="flex flex-col gap-1.5">
    <label for="{{ $prefix }}-explanation" class="text-[15px] font-bold">Begründung für die Betroffenen</label>
    <textarea id="{{ $prefix }}-explanation" name="explanation" rows="5" maxlength="10000" required class="o-input resize-y"
              aria-describedby="{{ $prefix }}-explanation-hint"
              aria-invalid="{{ $errors->has('explanation') ? 'true' : 'false' }}">{{ old('explanation') }}</textarea>
    <span id="{{ $prefix }}-explanation-hint" class="text-sm text-muted">Klar und respektvoll. Dieser Text geht per E-Mail an die betroffene Person und ist Teil der Begründung nach Art. 17 DSA.</span>
    @error('explanation')<p class="o-error m-0">{{ $message }}</p>@enderror
</div>
