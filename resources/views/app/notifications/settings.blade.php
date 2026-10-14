{{-- Settings page, route notifications.settings. Variables: $kinds (NoticeKind[]), $preference. No JS. --}}
@extends('layouts.app', ['title' => 'Hinweise einstellen', 'active' => 'hinweise', 'wide' => true])
@section('content')
<x-network.columns label="Deine Wahl">
    <h1 class="o-h2 m-0 text-night">Hinweise einstellen</h1>
    @if (session('status')) <p role="status" class="m-0 text-spring-text">{{ session('status') }}</p> @endif
    @if ($errors->any()) <p role="alert" class="o-error">{{ $errors->first() }}</p> @endif
    <form method="POST" action="{{ route('notifications.settings.update') }}" class="flex flex-col gap-6 rounded-[24px] bg-paper p-6">
        @csrf @method('PUT')
        <fieldset class="flex flex-col gap-2 border-0 p-0">
            <legend class="mb-3 text-xl font-extrabold text-night">In deiner Hinweis-Liste</legend>
            @foreach ($kinds as $kind)
                <label class="flex min-h-11 items-center gap-3" for="kind-{{ $kind->value }}">
                    <input type="hidden" name="kinds[{{ $kind->value }}]" value="0">
                    <input id="kind-{{ $kind->value }}" type="checkbox" name="kinds[{{ $kind->value }}]" value="1" @checked(old('kinds.'.$kind->value, $preference->enabled($kind)))>
                    {{ $kind->label() }}
                </label>
            @endforeach
        </fieldset>
        <fieldset class="border-0 p-0">
            <legend class="text-xl font-extrabold text-night">E-Mail</legend>
            <input type="hidden" name="digest_enabled" value="0">
            <label for="digest" class="flex min-h-11 items-center gap-3">
                <input id="digest" type="checkbox" name="digest_enabled" value="1" @checked(old('digest_enabled', $preference->digest_enabled))>
                Eine tägliche Zusammenfassung um 18 Uhr
            </label>
            <p class="m-0 text-sm text-muted">Nur wenn es neue ungelesene Hinweise gibt. Die E-Mail enthält eine Summe und einen Link, keine Tropfen-Texte.</p>
        </fieldset>
        <button class="o-btn o-btn-md o-btn-dark self-start" type="submit">Einstellungen speichern</button>
    </form>
    <x-slot:aside><x-network.side-card title="In deinem Tempo" tone="dune">
        <p class="m-0 text-ink-2">Die E-Mail-Zusammenfassung ist standardmäßig aus. Gelesene Hinweise bleiben bis zu 90 Tage in deiner Liste.</p>
        <a href="{{ route('notifications.index') }}" class="o-link min-h-11 py-2 text-night">Zurück zu deinen Hinweisen</a>
    </x-network.side-card></x-slot:aside>
</x-network.columns>
@endsection
