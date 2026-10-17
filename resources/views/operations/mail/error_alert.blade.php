{{-- ErrorAlertMail: unexpected error ($class, $location, $path). Deliberately without message, input or user. No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Fehler bei Oasis</title></head><body>
<h1>Unerwarteter Fehler</h1>
<p><strong>{{ $class }}</strong><br>in {{ $location }}<br>Aufruf: {{ $path }}<br>{{ now()->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr</p>
<p>Die vollständigen Angaben stehen im Server-Log (storage/logs). Derselbe Fehler meldet sich höchstens einmal pro
{{ \App\Support\Operations\ErrorAlert::THROTTLE_MINUTES }} Minuten.</p>
</body></html>
