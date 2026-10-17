{{--
    Offline page (route offline): the service worker (public/sw.js) caches it at install and shows it when a page
    cannot be loaded. Must not depend on the signed-in account: it is cached once for everyone.
--}}
@extends('layouts.oasis', ['title' => 'Keine Verbindung', 'description' => 'Oasis ist gerade nicht erreichbar.', 'minimal' => true, 'noindex' => true])

@section('content')
    <x-oasis.auth-card title="Keine Verbindung" subtitle="Gerade erreichen wir das Internet nicht. Deine Daten sind sicher – es fehlt nur die Verbindung.">
        <p class="m-0 text-ink-2">Sobald du wieder online bist, geht es weiter. Ein Tropfen, den du gerade schreiben wolltest, ist noch nicht gesendet.</p>
        <a href="{{ url('/app') }}" class="o-btn o-btn-dark">Noch einmal versuchen</a>
    </x-oasis.auth-card>
@endsection
