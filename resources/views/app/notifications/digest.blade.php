{{-- Daily opt-in email. Variables: $count (int), $unsubscribeUrl (signed, works without signing in). No post contents, no JS. --}}
<!doctype html><html lang="de"><body>
<h1>Deine Hinweise bei Oasis</h1>
<p>Für dich gibt es {{ $count === 1 ? 'einen neuen ungelesenen Hinweis' : $count.' neue ungelesene Hinweise' }}.</p>
<p><a href="{{ route('notifications.index') }}">In Ruhe ansehen</a></p>
<p><a href="{{ $unsubscribeUrl }}">E-Mail-Zusammenfassung abbestellen</a> · <a href="{{ route('notifications.settings') }}">Hinweise einstellen</a></p>
</body></html>
