{{--
    Edit your own profile (route network.profile.edit). Name and username are changed in the settings.
    Variables: $profile (Profile, stored or default)
--}}
@extends('layouts.app', ['title' => 'Profil', 'active' => 'profil'])

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <p class="o-eyebrow m-0">Profil</p>
            <h1 class="o-h2 m-0 text-night">Dein Profil</h1>
            <p class="m-0 text-ink-2">
                So sehen dich andere: <a href="{{ route('oasis.profil', auth()->user()->username) }}" class="text-night underline underline-offset-4">dein Profil ansehen</a>.
                Name und Benutzername änderst du in den <a href="{{ route('app.settings') }}" class="text-night underline underline-offset-4">Einstellungen</a>.
            </p>
        </div>

        <form method="POST" action="{{ route('network.profile.update') }}" class="flex flex-col gap-5 rounded-[24px] bg-paper p-6">
            @csrf @method('PUT')
            <div class="flex flex-col gap-1.5">
                <label for="profile-bio" class="text-[15px] font-bold">Über dich <span class="font-medium text-muted">(optional)</span></label>
                <textarea id="profile-bio" name="bio" rows="3" maxlength="300" class="o-input">{{ old('bio', $profile->bio) }}</textarea>
                @error('bio') <p class="m-0 text-sm font-bold text-sun-text">{{ $message }}</p> @enderror
            </div>
            <x-oasis.field name="location" label="Ort" :optional="true" maxlength="80" :value="$profile->location" hint="Zum Beispiel deine Stadt. Lass es leer, wenn du das nicht zeigen möchtest." />

            <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
                <legend class="mb-2 text-[15px] font-bold">Wer darf dein Profil und neue Tropfen lesen?</legend>
                @foreach (\App\Enums\Network\Visibility::cases() as $option)
                    <label class="flex items-center gap-2">
                        <input type="radio" name="visibility" value="{{ $option->value }}" @checked(old('visibility', $profile->visibility->value) === $option->value)>
                        {{ $option->label() }}
                    </label>
                @endforeach
                <p class="m-0 text-sm text-muted">Bei jedem Tropfen kannst du das noch einmal einzeln wählen.</p>
            </fieldset>

            <fieldset class="m-0 flex flex-col gap-2 border-none p-0">
                <legend class="mb-2 text-[15px] font-bold">Farbe deines Profilbilds</legend>
                <div class="flex flex-wrap gap-4">
                    @foreach (\App\Enums\Network\AvatarColor::cases() as $color)
                        <label class="flex items-center gap-2">
                            <input type="radio" name="avatar_color" value="{{ $color->value }}" @checked(old('avatar_color', $profile->avatar_color->value) === $color->value)>
                            <span aria-hidden="true" class="inline-block size-6 rounded-full {{ $color->classes() }}"></span>
                            {{ $color->label() }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <button type="submit" class="o-btn o-btn-md o-btn-dark self-start">Profil speichern</button>
        </form>

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('network.stats') }}" class="o-btn o-btn-md o-btn-outline">Deine Zahlen</a>
            <a href="{{ route('network.invitations.index') }}" class="o-btn o-btn-md o-btn-outline">Menschen einladen</a>
        </div>
    </div>
@endsection
