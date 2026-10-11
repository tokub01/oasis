{{--
    Text-form cancellation receipt sent by CancellationReceipt under § 312k BGB.
    $submission: App\Models\CancellationRequest with the retained declaration and receipt time.
    $operatorCopy: bool selects the internal copy instead of personal wording. No JavaScript required.
--}}
<!doctype html>
<html lang="de">
    <body>
        <h1>{{ $operatorCopy ? 'Kopie: Kündigung von '.$submission->name : 'Bestätigung deiner Kündigung' }}</h1>
        <p>Eingegangen am {{ $submission->created_at->timezone('Europe/Berlin')->format('d.m.Y') }} um {{ $submission->created_at->timezone('Europe/Berlin')->format('H:i:s T') }}.</p>
        <dl>
            <dt>Vertrag</dt>
            <dd>{{ $submission->contract }}</dd>
            <dt>Name</dt>
            <dd>{{ $submission->name }}</dd>
            <dt>E-Mail</dt>
            <dd>{{ $submission->email }}</dd>
            <dt>Benutzername</dt>
            <dd>{{ $submission->username ?? '–' }}</dd>
            <dt>Art der Kündigung</dt>
            <dd>{{ $submission->type === 'ausserordentlich' ? 'Außerordentlich' : 'Ordentlich' }}</dd>
            <dt>Grund</dt>
            <dd>{{ $submission->reason ?? '–' }}</dd>
            <dt>Zeitpunkt</dt>
            <dd>{{ $submission->timing === 'datum' ? 'Zu einem bestimmten Datum' : 'Zum nächstmöglichen Zeitpunkt' }}</dd>
            <dt>Gewünschtes Datum</dt>
            <dd>{{ $submission->effective_date?->format('d.m.Y') ?? '–' }}</dd>
        </dl>
        <p>{{ $operatorCopy ? 'Diese Nachricht ist die Betreiberkopie der eingegangenen Angaben.' : 'Diese Nachricht bestätigt den Eingang deiner Angaben.' }}</p>
    </body>
</html>
