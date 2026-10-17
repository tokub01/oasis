{{-- Installable web app (docs/oasis-pwa.md): manifest, icons and the theme colour for Android, iOS and desktop.
     $themeColor: colour of the top bar, so the system status bar blends in (default: sand). --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<meta name="theme-color" content="{{ $themeColor ?? '#F4EDE1' }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Oasis">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
