{{--
    Create or edit an oasis (routes network.oases.create / network.oases.edit). On edit, hosts also manage roles.
    Variables: $oasis (Oasis; unsaved on create), $members (Collection<User>, edit only)
--}}
@php($editing = $oasis->exists)
@extends('layouts.app', ['title' => $editing ? $oasis->name.' bearbeiten' : 'Oase eröffnen', 'active' => 'oasen', 'wide' => true])

@section('content')
    <x-network.columns label="Hinweise">
        <div class="flex flex-col gap-2">
            <a href="{{ $editing ? route('network.oases.show', $oasis) : route('network.oases.index') }}" class="o-link self-start">Zurück</a>
            <h1 class="o-h2 m-0 text-night">{{ $editing ? $oasis->name.' bearbeiten' : 'Oase eröffnen' }}</h1>
            @unless ($editing)
                <p class="o-lead m-0 text-ink-2">Du wirst Gastgeber:in: Du achtest auf die Hausregeln und kannst Tropfen entfernen, die dagegen verstoßen.</p>
            @endunless
        </div>

        <form method="POST" action="{{ $editing ? route('network.oases.update', $oasis) : route('network.oases.store') }}" class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
            @csrf
            @if ($editing) @method('PUT') @endif
            <x-oasis.field name="name" label="Name" :value="$oasis->name" required maxlength="60" />
            <x-oasis.field name="description" label="Worum geht es?" :value="$oasis->description" required maxlength="300" hint="Ein Satz, der neugierig macht." />
            <div class="flex flex-col gap-1.5">
                <label for="oasis-rules" class="text-[15px] font-bold">Hausregeln</label>
                <textarea id="oasis-rules" name="house_rules" rows="6" maxlength="3000" required class="o-input"
                          aria-describedby="oasis-rules-hint">{{ old('house_rules', $oasis->house_rules) }}</textarea>
                <p id="oasis-rules-hint" class="m-0 text-sm text-muted">Gelten zusätzlich zu den Community-Regeln von Oasis.</p>
                @error('house_rules') <p class="m-0 text-sm font-bold text-sun-text">{{ $message }}</p> @enderror
            </div>
            <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
                <legend class="mb-2 text-[15px] font-bold">Wer darf die Oase lesen?</legend>
                @foreach (\App\Enums\Network\Visibility::cases() as $option)
                    <label class="flex items-center gap-2">
                        <input type="radio" name="visibility" value="{{ $option->value }}" @checked(old('visibility', $oasis->visibility?->value) === $option->value)>
                        {{ $option->label() }}
                    </label>
                @endforeach
            </fieldset>
            <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
                <legend class="mb-2 text-[15px] font-bold">Farbe</legend>
                <div class="flex flex-wrap gap-4">
                    @foreach (\App\Enums\Network\AvatarColor::cases() as $color)
                        <label class="flex items-center gap-2">
                            <input type="radio" name="color" value="{{ $color->value }}" @checked(old('color', $oasis->color) === $color->value)>
                            <span aria-hidden="true" class="inline-block size-6 rounded-full {{ $color->classes() }}"></span>
                            {{ $color->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">{{ $editing ? 'Speichern' : 'Oase eröffnen' }}</button>
        </form>

        @if ($editing)
            <section aria-labelledby="oase-schliessen" class="flex flex-col gap-3 rounded-[24px] border border-sun-text bg-paper p-6">
                <h2 id="oase-schliessen" class="m-0 text-[21px] font-extrabold text-night">Oase schließen</h2>
                <p class="m-0 text-ink-2">Die Oase verschwindet für alle. Die Tropfen bleiben bei den Menschen, die sie geschrieben haben.</p>
                <a href="{{ route('network.oases.close', $oasis) }}" class="o-btn o-btn-md o-btn-outline self-start">Oase schließen …</a>
            </section>

            <section aria-labelledby="oase-rollen" class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
                <h2 id="oase-rollen" class="m-0 text-[21px] font-extrabold text-night">Gastgeber:innen</h2>
                @error('role') <p role="alert" class="m-0 font-bold text-sun-text">{{ $message }}</p> @enderror
                <ul class="m-0 flex list-none flex-col gap-3 p-0">
                    @foreach ($members as $member)
                        <li class="flex flex-wrap items-center justify-between gap-3">
                            <span>{{ $member->display_name }} <span class="text-muted">{{ '@'.$member->username }}</span> · {{ \App\Enums\Network\OasisRole::from($member->pivot->role)->label() }}</span>
                            <form method="POST" action="{{ route('network.oases.role', [$oasis, $member]) }}">
                                @csrf @method('PUT')
                                @if ($member->pivot->role === 'host')
                                    <input type="hidden" name="role" value="member">
                                    <button type="submit" class="o-link cursor-pointer border-none bg-transparent text-sm">Zu Mitglied machen</button>
                                @else
                                    <input type="hidden" name="role" value="host">
                                    <button type="submit" class="o-link cursor-pointer border-none bg-transparent text-sm">Zu Gastgeber:in machen</button>
                                @endif
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        <x-slot:aside>
            <x-network.side-card title="Gute Hausregeln" tone="dune">
                <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-[15px] text-ink-2">
                    <li>Kurz und konkret: drei bis fünf Punkte reichen.</li>
                    <li>Sag, was erwünscht ist – nicht nur, was verboten ist.</li>
                    <li>Die Community-Regeln von Oasis gelten immer zusätzlich.</li>
                </ul>
            </x-network.side-card>
            <x-network.side-card title="Als Gastgeber:in" tone="night">
                <p class="m-0 text-[15px] text-on-night-2">Du kannst Tropfen aus der Oase entfernen, die gegen die Hausregeln verstoßen, und weitere Gastgeber:innen ernennen. Rechtswidriges meldet ihr dem Moderationsteam.</p>
            </x-network.side-card>
        </x-slot:aside>
    </x-network.columns>
@endsection
