<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <title>Kündigungsbestätigung</title>
    </head>
    <body style="font-family: sans-serif; line-height: 1.5; color: #232220; max-width: 600px; margin: 0 auto; padding: 24px;">
        <h1 style="font-size: 22px; margin-bottom: 16px;">
            {{ $operatorCopy ? 'Kopie: Kündigung von '.$user->display_name : 'Bestätigung deiner Kündigung' }}
        </h1>

        <p>Eingegangen am {{ $cancelledAt->timezone('Europe/Berlin')->format('d.m.Y') }} um {{ $cancelledAt->timezone('Europe/Berlin')->format('H:i:s T') }}.</p>

        <dl style="margin: 20px 0; border: 1px solid #e5e5e5; border-radius: 12px; padding: 16px;">
            <dt style="font-weight: bold; color: #666; font-size: 14px;">Vertrag</dt>
            <dd style="margin: 0 0 12px 0;">Oasis Mitgliedschaft (Stufe: {{ $tierName }})</dd>

            <dt style="font-weight: bold; color: #666; font-size: 14px;">Name</dt>
            <dd style="margin: 0 0 12px 0;">{{ $user->display_name }}</dd>

            <dt style="font-weight: bold; color: #666; font-size: 14px;">E-Mail</dt>
            <dd style="margin: 0 0 12px 0;">{{ $user->email }}</dd>

            <dt style="font-weight: bold; color: #666; font-size: 14px;">Art der Kündigung</dt>
            <dd style="margin: 0 0 12px 0;">Ordentliche Kündigung zum Ende des Abrechnungszeitraums</dd>

            <dt style="font-weight: bold; color: #666; font-size: 14px;">Wirksam zum</dt>
            <dd style="margin: 0 0 12px 0;">{{ $effectiveDate ? $effectiveDate->timezone('Europe/Berlin')->format('d.m.Y') : 'Ende der aktuellen Periode' }}</dd>
        </dl>

        @if (! $operatorCopy)
            <p>Deine Mitgliedschaft bleibt bis zum {{ $effectiveDate ? $effectiveDate->timezone('Europe/Berlin')->format('d.m.Y') : 'Periodenende' }} vollständig aktiv. Danach wechselt dein Konto in den Lesemodus.</p>
            <p>Du hast anschließend 30 Tage Zeit, um deine Daten herunterzuladen.</p>
        @else
            <p>Diese Nachricht ist die interne Betreiberkopie der Kündigung gemäß § 312k BGB.</p>
        @endif

        <p style="margin-top: 32px; border-top: 1px solid #e5e5e5; padding-top: 16px; font-size: 14px; color: #666;">
            Dein Oasis-Team<br>
            {{ config('oasis.operator.legal_name') }}
        </p>
    </body>
</html>
