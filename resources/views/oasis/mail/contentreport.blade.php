<!doctype html><html lang="de"><body>
<h1>{{ $moderationCopy ? 'Neue Inhaltsmeldung von '.$submission->name : 'Eingangsbestätigung deiner Meldung' }}</h1>
<p>Eingegangen am {{ $submission->created_at->timezone('Europe/Berlin')->format('d.m.Y') }} um {{ $submission->created_at->timezone('Europe/Berlin')->format('H:i:s T') }}.</p>
<dl>
<dt>Link zum Inhalt</dt><dd>{{ $submission->url }}</dd>
<dt>Begründung</dt><dd>{{ $submission->reason }}</dd>
<dt>Name</dt><dd>{{ $submission->name }}</dd>
<dt>E-Mail</dt><dd>{{ $submission->email }}</dd>
<dt>Bestätigung</dt><dd>Ich bestätige, dass meine Angaben nach bestem Wissen richtig und vollständig sind.</dd>
</dl>
<p>{{ $moderationCopy ? 'Bitte prüft den gemeldeten Inhalt und die Begründung.' : 'Diese Nachricht bestätigt den Eingang deiner Angaben.' }}</p>
</body></html>
