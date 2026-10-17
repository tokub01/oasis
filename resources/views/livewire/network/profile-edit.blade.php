{{--
    Edit your own profile (route network.profile.edit). Name and username are changed in the settings.
    Variables: $profile (Profile, stored or default)
--}}
<div class="flex flex-col gap-6">
<p role="status" aria-live="polite">{{ $postStatus }}</p>
<p wire:loading class="text-muted">Wird geladen …</p>
    <x-network.columns label="Hinweise">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Profil</p>
            <h1 class="o-h2 m-0 text-night">Dein Profil</h1>
            <p class="m-0 text-ink-2">
                So sehen dich andere: <a wire:navigate href="{{ route('oasis.profil', auth()->user()->username) }}" class="text-night underline underline-offset-4">dein Profil ansehen</a>.
                Name und Benutzername änderst du in den <a wire:navigate href="{{ route('app.settings') }}" class="text-night underline underline-offset-4">Einstellungen</a>.
            </p>
        </div>

        <form wire:submit="save" class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">

            <div class="flex flex-col gap-1.5">
                <label for="profile-bio" class="text-[15px] font-bold">Über dich <span class="font-medium text-muted">(optional)</span></label>
                <textarea id="profile-bio" name="bio" wire:model="bio" rows="3" maxlength="300" class="o-input" aria-describedby="profile-bio-error"></textarea>
                @error('bio') <p id="profile-bio-error" class="m-0 text-sm font-bold text-sun-text">{{ $message }}</p> @enderror
            </div>
            <x-oasis.field name="location" wire:model="location" label="Ort" :optional="true" maxlength="80" :value="$profile->location" hint="Zum Beispiel deine Stadt. Lass es leer, wenn du das nicht zeigen möchtest." />

            <p class="m-0 text-ink-2">Wer dein Profil lesen und mit dir interagieren darf, wählst du in den
                <a wire:navigate href="{{ route('app.privacy') }}" class="o-link">Privatsphäre-Einstellungen</a>.</p>

            <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
                <legend class="mb-2 text-[15px] font-bold">Farbe deines Profilbilds</legend>
                <div class="flex flex-wrap gap-4">
                    @foreach (\App\Enums\Network\AvatarColor::cases() as $color)
                        <label wire:key="profile-edit-2-{{ $color->value }}" class="flex items-center gap-2">
                            <input type="radio" name="avatar_color" wire:model="avatar_color" aria-describedby="profile-edit-avatar_color-error" value="{{ $color->value }}" >
                            <span aria-hidden="true" class="inline-block size-6 rounded-full {{ $color->classes() }}"></span>
                            {{ $color->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <p id="profile-edit-avatar_color-error" class="o-error">@error('avatar_color'){{ $message }}@enderror</p>

            <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-md o-btn-dark self-start">Profil speichern</button>
        </form>

        <div class="flex flex-wrap gap-3">
            <a wire:navigate href="{{ route('network.stats') }}" class="o-btn o-btn-md o-btn-outline">Deine Zahlen</a>
            <a wire:navigate href="{{ route('network.invitations.index') }}" class="o-btn o-btn-md o-btn-outline">Menschen einladen</a>
        </div>
        <x-slot:aside>
            <x-network.side-card title="Vorschau">
                <div class="flex items-center gap-3">
                    <x-network.avatar :user="auth()->user()" :size="56" />
                    <span class="flex min-w-0 flex-col leading-tight">
                        <span class="truncate font-extrabold text-night">{{ auth()->user()->display_name }}</span>
                        <span class="text-sm text-muted">{{ '@'.auth()->user()->username }}</span>
                    </span>
                </div>
                <p class="m-0 text-[15px] text-ink-2">{{ $profile->bio ?: 'Noch keine Beschreibung.' }}</p>
                <a wire:navigate href="{{ route('oasis.profil', auth()->user()->username) }}" class="o-link self-start text-[15px]">Profil ansehen</a>
            </x-network.side-card>

        </x-slot:aside>
    </x-network.columns>
</div>
