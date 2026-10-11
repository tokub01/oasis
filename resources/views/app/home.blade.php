{{--
    "Quelle": the member's home (route app.home, /app). Until stage 2 (posts and feed) it shows the welcome and
    the honest end-of-feed state from the design ("Du bist auf dem neuesten Stand").
--}}
@extends('layouts.app', ['title' => 'Quelle', 'active' => 'quelle'])

@section('content')
    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Quelle</p>
            <h1 class="o-h2 m-0 text-night">Hallo {{ auth()->user()->display_name }}.</h1>
            <p class="o-lead m-0 text-ink-2">Hier siehst du bald der Reihe nach, was Menschen und Oasen teilen, denen du folgst.</p>
        </div>

        <div class="flex flex-col gap-1 rounded-[20px] border-2 border-dashed border-spring p-6 text-center">
            <span class="font-bold text-spring-text">Du bist auf dem neuesten Stand.</span>
            <span class="text-ink-2">Zeit zum Durchatmen.</span>
        </div>

        <div class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
            <h2 class="m-0 text-[21px] font-extrabold text-night">Nächste Schritte</h2>
            <ul class="m-0 flex list-disc flex-col gap-2 pl-5 text-ink-2">
                <li><a href="{{ route('app.settings') }}" class="text-night underline underline-offset-4">Profil vervollständigen</a></li>
                <li><a href="{{ route('oasis.entdecken') }}" class="text-night underline underline-offset-4">Als Gast reinlesen, wer schon da ist</a></li>
            </ul>
        </div>
    </div>
@endsection
