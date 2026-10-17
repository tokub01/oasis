{{-- TwoFactorResetLink: $user, $url, $hours. No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Zwei-Faktor-Anmeldung zurücksetzen</title></head>
<body style="font-family: sans-serif; line-height: 1.5; color: #232220; max-width: 600px; margin: 0 auto; padding: 24px;">
<h1 style="font-size: 22px;">Hallo {{ $user->display_name }},</h1>
<p>jemand möchte die Zwei-Faktor-Anmeldung deines Oasis-Kontos zurücksetzen – mit deinem richtigen Passwort.</p>
<p><strong>Warst du das?</strong> Dann bestätige es innerhalb von {{ $hours }} Stunden:</p>
<p style="margin: 24px 0;"><a href="{{ $url }}" style="display: inline-block; background-color: #232220; color: #ffffff; padding: 12px 24px; border-radius: 9999px; text-decoration: none; font-weight: bold;">Rücksetzung bestätigen</a></p>
<p>Zu deinem Schutz wird die Zwei-Faktor-Anmeldung erst {{ \App\Models\TwoFactorReset::WAIT_HOURS }} Stunden nach der Bestätigung zurückgesetzt. So lange kannst du jederzeit abbrechen.</p>
<p><strong>Warst du das nicht?</strong> Dann kennt jemand dein Passwort. Ignoriere diese E-Mail und ändere dein Passwort sofort in den Einstellungen.</p>
<p style="margin-top: 32px; border-top: 1px solid #e5e5e5; padding-top: 16px; font-size: 14px; color: #666;">Dein Oasis-Team<br>{{ config('oasis.operator.legal_name') }}</p>
</body></html>
