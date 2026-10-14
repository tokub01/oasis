{{-- Daily opt-in email. Variable: $count (int), no post contents and no JavaScript. --}}
<!doctype html><html lang="de"><body>
<h1>Deine Hinweise bei Oasis</h1>
<p>Für dich gibt es {{ $count === 1 ? 'einen neuen ungelesenen Hinweis' : $count.' neue ungelesene Hinweise' }}.</p>
<p><a href="{{ route('notifications.index') }}">In Ruhe ansehen</a></p>
<p><a href="{{ route('notifications.settings') }}">E-Mail-Zusammenfassung ausschalten</a></p>
</body></html>
