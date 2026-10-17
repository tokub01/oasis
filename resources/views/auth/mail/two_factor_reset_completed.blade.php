{{-- TwoFactorResetCompleted: $user. No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Zwei-Faktor-Anmeldung zurückgesetzt</title></head>
<body style="font-family: sans-serif; line-height: 1.5; color: #232220; max-width: 600px; margin: 0 auto; padding: 24px;">
<h1 style="font-size: 22px;">Hallo {{ $user->display_name }},</h1>
<p>die Zwei-Faktor-Anmeldung deines Oasis-Kontos ist jetzt ausgeschaltet, und du bist auf allen Geräten abgemeldet.</p>
<p>Melde dich mit deinem Passwort an und richte die Zwei-Faktor-Anmeldung in den Einstellungen gleich wieder ein – mit neuen Wiederherstellungscodes, die du sicher aufbewahrst.</p>
<p><strong>Warst du das nicht?</strong> Ändere sofort dein Passwort und schreib uns an {{ config('oasis.mail.contact') }}.</p>
<p style="margin-top: 32px; border-top: 1px solid #e5e5e5; padding-top: 16px; font-size: 14px; color: #666;">Dein Oasis-Team<br>{{ config('oasis.operator.legal_name') }}</p>
</body></html>
