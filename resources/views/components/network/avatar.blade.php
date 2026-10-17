{{--
    Avatar of a member: their profile photo, or initials in the colour they chose (profile.avatar_color). Decorative:
    the name is always written next to it, so it is hidden from screen readers.

    The photo shows only where the profile is visible to the viewer and – for signed-in viewers – where their setting
    "Wessen Bilder siehst du sofort?" allows images of this person; otherwise the initials stay (no unwanted images).

    Props: user (App\Models\User with profile loaded or lazy), size (px, default 44)
--}}
@props(['user', 'size' => 44])
@php
    $profile = $user->profileOrDefault();
    $viewer = auth()->user();
    $photo = $profile->photo_path !== null && $profile->isVisibleTo($viewer) && ($viewer === null || $viewer->showsImagesOf($user))
        ? $profile->photoUrl() : null;
@endphp

@if ($photo)
    <img src="{{ $photo }}" alt="" aria-hidden="true" width="{{ $size }}" height="{{ $size }}" loading="lazy"
         {{ $attributes->class(['flex-none rounded-full bg-dune object-cover']) }} style="width: {{ $size }}px; height: {{ $size }}px">
@else
    <span aria-hidden="true" style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size / 3) }}px"
          {{ $attributes->class(['flex flex-none items-center justify-center rounded-full font-extrabold', $profile->avatar_color->classes()]) }}>{{ $user->initials() }}</span>
@endif
