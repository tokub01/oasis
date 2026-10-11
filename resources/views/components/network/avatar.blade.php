{{--
    Initials avatar of a member in the colour they chose (profile.avatar_color). Decorative: the name is always
    written next to it, so it is hidden from screen readers.

    Props: user (App\Models\User with profile loaded or lazy), size (px, default 44)
--}}
@props(['user', 'size' => 44])

<span aria-hidden="true" style="width: {{ $size }}px; height: {{ $size }}px; font-size: {{ round($size / 3) }}px"
      {{ $attributes->class(['flex flex-none items-center justify-center rounded-full font-extrabold', $user->profileOrDefault()->avatar_color->classes()]) }}>{{ $user->initials() }}</span>
