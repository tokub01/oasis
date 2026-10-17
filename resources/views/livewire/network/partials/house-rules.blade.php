{{--
    House rules of an oasis, one rule per line (leading list marks from the form are dropped), plus the hint to the
    Community-Regeln. Used in the side column (desktop) and above the feed (phones and tablets). Variables: $oasis.
--}}
@php($rules = collect(preg_split('/\R/', (string) $oasis->house_rules))->map(fn (string $line) => trim(ltrim(trim($line), '-•*')))->filter()->values())
@if ($rules->count() > 1)
    <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[15px]">
        @foreach ($rules as $rule)
            <li wire:key="house-rule-{{ $loop->index }}">{{ $rule }}</li>
        @endforeach
    </ul>
@else
    <p class="m-0 text-[15px]">{{ $rules->first() }}</p>
@endif
<p class="m-0 text-sm text-muted">Zusätzlich gelten die <a wire:navigate href="{{ route('oasis.regeln') }}" class="text-night underline underline-offset-4">Community-Regeln</a> von Oasis.</p>
