{{--
    Landing page of an invitation link (route network.invitations.show). The token was stored in the session; after
    registration inviter and new member follow each other. Invalid/used/expired links get a neutral text.
    Variables: $invitation (Invitation|null, with inviter)
--}}
<div class="flex flex-col gap-6">
<p role="status" aria-live="polite">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <div class="o-container py-[clamp(48px,6vw,96px)]">
        <div class="mx-auto flex max-w-[620px] flex-col gap-5 rounded-[28px] bg-paper p-[clamp(24px,4vw,48px)]">
            @if ($invitation)
                <p class="o-eyebrow m-0">Einladung</p>
                <h1 class="o-h2 m-0 text-night">{{ $invitation->inviter->display_name }} lädt dich zu Oasis ein.</h1>
                <p class="o-lead m-0 text-ink-2">Oasis ist ein werbefreies soziales Netzwerk mit einem Feed, der aufhört. Wenn du dich jetzt anmeldest, folgt ihr euch automatisch.</p>
                @auth
                    <p class="m-0">Du bist schon angemeldet. <a wire:navigate href="{{ route('oasis.profil', $invitation->inviter->username) }}" class="text-night underline underline-offset-4">Zum Profil von {{ $invitation->inviter->display_name }}</a></p>
                @else
                    <a wire:navigate href="{{ route('register') }}" class="o-btn o-btn-md o-btn-primary self-start">{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
                @endauth
            @else
                <h1 class="o-h2 m-0 text-night">Dieser Einladungslink gilt nicht mehr.</h1>
                <p class="m-0 text-ink-2">Er wurde schon benutzt oder ist abgelaufen. Du kannst Oasis trotzdem testen.</p>
                <a wire:navigate href="{{ route('register') }}" class="o-btn o-btn-md o-btn-primary self-start">{{ config('oasis.trial_days') }} Tage kostenlos testen</a>
            @endif
        </div>
    </div>
</div>
