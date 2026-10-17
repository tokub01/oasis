{{--
    "Weitertragen" (route network.posts.carry): carry a Tropfen further with own words – sharing as a conversation,
    not a reflex. Variables: $post (the Tropfen being carried, with author.profile and images).
--}}
<div class="flex flex-col gap-6">
<p role="status" aria-live="polite">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <a wire:navigate href="{{ route('network.posts.show', $post) }}" class="o-link self-start">Zurück zum Tropfen</a>
            <h1 class="o-h2 m-0 text-night">Weitertragen</h1>
            <p class="o-lead m-0 text-ink-2">Schreib ein paar eigene Worte dazu: Warum ist das wichtig?</p>
        </div>

        <blockquote class="m-0 flex flex-col gap-1 rounded-2xl border border-dune-line bg-paper p-4">
            <span class="text-sm font-bold text-night">{{ $post->author->display_name }} <span class="font-medium text-muted">{{ '@'.$post->author->username }}</span></span>
            <x-network.rich-text :text="$post->body" :author="$post->author" />
        </blockquote>

        <x-network.compose id="carry-form" :carried="$post" />
    </div>
</div>
