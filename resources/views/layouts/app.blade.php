<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Oasis' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    @auth
        <div class="grid grid-cols-2">
            <div>
                Test
            </div>
            <div>
                Test
                {{$slot}}
            </div>
        </div>
    @endauth
    @guest
        <div class="h-screen w-screen">
            {{ $slot }}
        </div>
    @endguest
    @livewireScripts
</body>
</html>
