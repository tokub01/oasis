<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <title>Widerrufsbestätigung</title>
    </head>
    <body style="font-family: sans-serif; line-height: 1.5; color: #232220; max-width: 600px; margin: 0 auto; padding: 24px;">
        <h1 style="font-size: 22px; margin-bottom: 16px;">
            {{ $operatorCopy ? 'Kopie: Widerruf von '.$user->display_name : 'Bestätigung deines Widerrufs' }}
        </h1>

        <p>Eingegangen am {{ $withdrawnAt->timezone('Europe/Berlin')->format('d.m.Y') }} um {{ $withdrawnAt->timezone('Europe/Berlin')->format('H:i:s T') }}.</p>

        <dl style="margin: 20px 0; border: 1px solid #e5e5e5; border-radius: 12px; padding: 16px;">
            <dt style="font-weight: bold; color: #666; font-size: 14px;">Vertrag</dt>
            <dd style="margin: 0 0 12px 0;">Oasis Mitgliedschaft (Stufe: {{ $tierName }})</dd>

            <dt style="font-weight: bold; color: #666; font-size: 14px;">Name</dt>
            <dd style="margin: 0 0 12px 0;">{{ $user->display_name }}</dd>

            <dt style="font-weight: bold; color: #666; font-size: 14px;">E-Mail</dt>
            <dd style="margin: 0 0 12px 0;">{{ $user->email }}</dd>

            <dt style="font-weight: bold; color: #666; font-size: 14px;">Art der Erklärung</dt>
            <dd style="margin: 0 0 12px 0;">Widerruf innerhalb der 14-tägigen gesetzlichen Widerrufsfrist (§ 355 BGB)</dd>

            <dt style="font-weight: bold; color: #666; font-size: 14px;">Wirksamkeit</dt>
            <dd style="margin: 0 0 12px 0;">Sofortige Beendigung des Vertrags</dd>
        </dl>

        @if (! $operatorCopy)
            <p>Dein Vertrag wurde mit sofortiger Wirkung beendet. Bereits gezahlte Beträge erstatten wir dir unverzüglich auf dein ursprüngliches Zahlungsmittel zurück (§ 357 Abs. 1 BGB).</p>
        @else
            <p>Diese Nachricht ist die interne Betreiberkopie des Widerrufs.</p>
        @endif

        <p style="margin-top: 32px; border-top: 1px solid #e5e5e5; padding-top: 16px; font-size: 14px; color: #666;">
            Dein Oasis-Team<br>
            {{ config('oasis.operator.legal_name') }}
        </p>
    </body>
</html>
