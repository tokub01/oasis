{{--
    "Weitertragen" (route network.posts.carry): carry a Tropfen further with own words – sharing as a conversation,
    not a reflex. Variables: $post (the Tropfen being carried, with author.profile and images).
--}}
@extends('layouts.app', ['title' => 'Weitertragen', 'active' => 'quelle'])

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <a href="{{ route('network.posts.show', $post) }}" class="o-link self-start">Zurück zum Tropfen</a>
            <h1 class="o-h2 m-0 text-night">Weitertragen</h1>
            <p class="o-lead m-0 text-ink-2">Schreib ein paar eigene Worte dazu: Warum ist das wichtig?</p>
        </div>

        <blockquote class="m-0 flex flex-col gap-1 rounded-2xl border border-dune-line bg-paper p-4">
            <span class="text-sm font-bold text-night">{{ $post->author->display_name }} <span class="font-medium text-muted">{{ '@'.$post->author->username }}</span></span>
            <x-network.rich-text :text="$post->body" />
        </blockquote>

        @php($links = \App\Support\Network\TextLinks::in($post->body))
        @if ($links !== [])
            <section class="flex flex-col gap-3 rounded-2xl border border-dune-line bg-paper p-4" aria-labelledby="read-link-hint">
                <h2 id="read-link-hint" class="m-0 text-base font-bold text-night">Hast du den Link gelesen? Weitertragen heißt: Du stehst dafür ein.</h2>
                <ul class="m-0 flex flex-col gap-2 pl-5">
                    @foreach ($links as $link)
                        <li><a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer nofollow" title="{{ $link['url'] }}" class="o-link break-words">{{ $link['host'] }}</a></li>
                    @endforeach
                </ul>
                <label for="link-read" class="flex min-h-11 items-center gap-3">
                    <input id="link-read" type="checkbox" name="link_read" value="1" form="carry-form" required
                           @checked(old('link_read') === '1') aria-invalid="{{ $errors->has('link_read') ? 'true' : 'false' }}"
                           aria-describedby="read-link-hint{{ $errors->has('link_read') ? ' link-read-error' : '' }}">
                    <span>Ich habe den Inhalt gelesen</span>
                </label>
                @error('link_read') <p id="link-read-error" class="o-error m-0">{{ $message }}</p> @enderror
            </section>
        @endif

        <x-network.compose id="carry-form" label="Deine Worte dazu" submit="Weitertragen" :carried="$post" />
    </div>
@endsection
