{{--
    Decision fields bound to an App\Livewire\Moderation\Forms\DecisionForm, shared by the case decision and the
    "overturn" branch of a complaint review. Together they form the statement of reasons under DSA Art. 17: outcome,
    ground with rule or legal provision, the facts, and the explanation that the affected person reads.

    Variables:
      $outcomes  list<App\Enums\Moderation\Outcome>
      $grounds   list<App\Enums\Moderation\Ground>
      $prefix    string  id prefix so the fields can appear twice on one page
    The form is the component property `decision`, so errors are keyed decision.<field>.
--}}
@php($error = fn (string $field) => $errors->first('decision.'.$field))

<fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
    <legend class="mb-2 p-0 text-[16px] font-extrabold">Maßnahme</legend>
    @foreach ($outcomes as $outcome)
        <label wire:key="{{ $prefix }}-outcome-{{ $outcome->value }}" class="flex min-h-11 items-center gap-3 text-[16px]">
            <input type="radio" name="outcome" wire:model="decision.outcome" value="{{ $outcome->value }}" required class="size-5 accent-night">
            {{ $outcome->label() }}
        </label>
    @endforeach
    @if ($error('outcome'))<p role="alert" class="o-error m-0">{{ $error('outcome') }}</p>@endif
</fieldset>

<fieldset class="m-0 flex flex-col gap-3 border-0 p-0">
    <legend class="mb-2 p-0 text-[16px] font-extrabold">Grundlage</legend>
    @foreach ($grounds as $ground)
        <label wire:key="{{ $prefix }}-ground-{{ $ground->value }}" class="flex min-h-11 items-center gap-3 text-[16px]">
            <input type="radio" name="ground" wire:model="decision.ground" value="{{ $ground->value }}" required class="size-5 accent-night">
            {{ $ground->label() }}
        </label>
    @endforeach
    @if ($error('ground'))<p role="alert" class="o-error m-0">{{ $error('ground') }}</p>@endif
</fieldset>

<div class="flex flex-col gap-1.5">
    <label for="{{ $prefix }}-rule" class="text-[15px] font-bold">Regel oder Rechtsvorschrift</label>
    <input id="{{ $prefix }}-rule" type="text" wire:model="decision.rule_reference" maxlength="255" required class="o-input"
           aria-describedby="{{ $prefix }}-rule-hint{{ $error('rule_reference') ? ' '.$prefix.'-rule-error' : '' }}"
           aria-invalid="{{ $error('rule_reference') ? 'true' : 'false' }}">
    <span id="{{ $prefix }}-rule-hint" class="text-sm text-muted">Zum Beispiel „Community-Regel 2: Kein Hass“ oder „§ 185 StGB“.</span>
    @if ($error('rule_reference'))<p id="{{ $prefix }}-rule-error" class="o-error m-0">{{ $error('rule_reference') }}</p>@endif
</div>

<div class="flex flex-col gap-1.5">
    <label for="{{ $prefix }}-facts" class="text-[15px] font-bold">Festgestellte Tatsachen</label>
    <textarea id="{{ $prefix }}-facts" wire:model="decision.facts" rows="4" maxlength="10000" required class="o-input resize-y"
              @if ($error('facts')) aria-describedby="{{ $prefix }}-facts-error" @endif
              aria-invalid="{{ $error('facts') ? 'true' : 'false' }}"></textarea>
    @if ($error('facts'))<p id="{{ $prefix }}-facts-error" class="o-error m-0">{{ $error('facts') }}</p>@endif
</div>

<div class="flex flex-col gap-1.5">
    <label for="{{ $prefix }}-explanation" class="text-[15px] font-bold">Begründung für die Betroffenen</label>
    <textarea id="{{ $prefix }}-explanation" wire:model="decision.explanation" rows="5" maxlength="10000" required class="o-input resize-y"
              aria-describedby="{{ $prefix }}-explanation-hint{{ $error('explanation') ? ' '.$prefix.'-explanation-error' : '' }}"
              aria-invalid="{{ $error('explanation') ? 'true' : 'false' }}"></textarea>
    <span id="{{ $prefix }}-explanation-hint" class="text-sm text-muted">Klar und respektvoll. Dieser Text geht per E-Mail an die betroffene Person und ist Teil der Begründung nach Art. 17 DSA.</span>
    @if ($error('explanation'))<p id="{{ $prefix }}-explanation-error" class="o-error m-0">{{ $error('explanation') }}</p>@endif
</div>
