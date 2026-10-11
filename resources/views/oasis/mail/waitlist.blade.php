{{--
    Double-opt-in mail sent by WaitlistConfirmation for a pending waitlist entry.
    $confirmUrl: string, signed URL expiring after seven days; $unsubscribeUrl: string, signed GET confirmation step.
    The seven-day wording must match WaitlistConfirmation::content. No JavaScript required.
--}}
<!doctype html>
<html lang="de">
    <body>
        <h1>Bitte bestätige deine E-Mail-Adresse</h1>
        <p>Du möchtest auf die Startliste von Oasis. Bestätige deine Adresse innerhalb von sieben Tagen:</p>
        <p>
            <a href="{{ $confirmUrl }}">E-Mail-Adresse bestätigen</a>
        </p>
        <p>Wenn du dich nicht angemeldet hast, musst du nichts tun.</p>
        <p>
            <a href="{{ $unsubscribeUrl }}">Von der Startliste abmelden</a>
        </p>
    </body>
</html>
