<!doctype html><html lang="de"><body>
<h1>{{ $operatorCopy ? 'Kopie: Widerruf von '.$submission->name : 'Eingangsbestätigung deines Widerrufs' }}</h1>
<p>Eingegangen am {{ $submission->created_at->timezone('Europe/Berlin')->format('d.m.Y') }} um {{ $submission->created_at->timezone('Europe/Berlin')->format('H:i:s T') }}.</p>
<dl>
<dt>Vertrag</dt><dd>{{ $submission->contract }}</dd>
<dt>Name</dt><dd>{{ $submission->name }}</dd>
<dt>E-Mail</dt><dd>{{ $submission->email }}</dd>
<dt>Abgeschlossen am</dt><dd>{{ $submission->contracted_on?->format('d.m.Y') ?? '–' }}</dd>
</dl>
<p>{{ $operatorCopy ? 'Diese Nachricht ist die Betreiberkopie der eingegangenen Angaben.' : 'Diese Nachricht bestätigt den Eingang deiner Angaben.' }}</p>
</body></html>
