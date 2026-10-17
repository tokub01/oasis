{{-- Login alert (App\Mail\Privacy\LoginAlert). Variables: $user, $device (e.g. "Firefox auf Windows"), $at (Carbon). --}}
<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <title>Neue Anmeldung bei Oasis</title>
    </head>
    <body style="font-family: sans-serif; line-height: 1.5; color: #232220; max-width: 600px; margin: 0 auto; padding: 24px;">
        <h1 style="font-size: 22px; margin-bottom: 16px;">Hallo {{ $user->display_name }},</h1>

        <p>gerade hat sich jemand mit einem Gerät bei deinem Oasis-Konto angemeldet, das wir bei dir noch nicht kannten:</p>

        <p style="background: #f3ede3; border-radius: 12px; padding: 12px 16px;">
            <strong>{{ $device }}</strong><br>
            {{ $at->timezone('Europe/Berlin')->locale('de')->isoFormat('dddd, D. MMMM YYYY, HH:mm') }} Uhr
        </p>

        <p><strong>Warst du das?</strong> Dann musst du nichts tun.</p>

        <p><strong>Warst du das nicht?</strong> Ändere sofort dein Passwort und melde dich auf allen anderen Geräten ab:</p>

        <p style="margin: 24px 0;">
            <a href="{{ route('app.privacy.devices') }}" style="display: inline-block; background-color: #232220; color: #ffffff; padding: 12px 24px; border-radius: 9999px; text-decoration: none; font-weight: bold;">
                Geräte ansehen
            </a>
        </p>

        <p style="font-size: 14px; color: #666;">Diese E-Mail kannst du in den Privatsphäre-Einstellungen unter „E-Mail bei Anmeldung von einem neuen Gerät“ abschalten.</p>

        <p style="margin-top: 32px; border-top: 1px solid #e5e5e5; padding-top: 16px; font-size: 14px; color: #666;">
            Dein Oasis-Team<br>
            {{ config('oasis.operator.legal_name') }}
        </p>
    </body>
</html>
