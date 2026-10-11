{{--
    Organisation-enquiry mail sent to the operator by OrganisationNotice.
    $submission: App\Models\OrganisationRequest containing organisation, estimated people count and reply email.
    No JavaScript required.
--}}
<!doctype html>
<html lang="de">
    <body>
        <h1>Neue Anfrage für Organisationen</h1>
        <p>Eingegangen am {{ $submission->created_at->timezone('Europe/Berlin')->format('d.m.Y') }} um {{ $submission->created_at->timezone('Europe/Berlin')->format('H:i:s T') }}.</p>
        <dl>
            <dt>Organisation</dt>
            <dd>{{ $submission->organisation }}</dd>
            <dt>Personen</dt>
            <dd>{{ $submission->people }}</dd>
            <dt>E-Mail für Rückfragen</dt>
            <dd>{{ $submission->email }}</dd>
        </dl>
        <p>Diese Nachricht bestätigt den Eingang deiner Angaben.</p>
    </body>
</html>
