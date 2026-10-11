{{--
    Moderation queue page (route moderation.index). The controller passes no data; the Livewire component
    App\Livewire\Moderation\Queue loads and filters the cases itself.
--}}
@extends('layouts.staff', ['title' => 'Warteschlange', 'active' => 'queue'])

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
            <h1 class="o-h2 m-0 text-night">Warteschlange</h1>
            <p class="m-0 text-ink-2">Meldungen nach Art. 16 DSA. Jede Entscheidung braucht eine Begründung, die die Betroffenen verstehen.</p>
        </div>
        <livewire:moderation.queue />
    </div>
@endsection
