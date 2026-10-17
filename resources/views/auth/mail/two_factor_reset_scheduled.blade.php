{{-- TwoFactorResetScheduled: $reset (with user), $url (cancel/finish link; null for the operator copy). No JS. --}}
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Zwei-Faktor-Rücksetzung</title></head>
<body style="font-family: sans-serif; line-height: 1.5; color: #232220; max-width: 600px; margin: 0 auto; padding: 24px;">
@if ($url)
    <h1 style="font-size: 22px;">Hallo {{ $reset->user->display_name }},</h1>
    <p>die Zwei-Faktor-Anmeldung deines Kontos wird am <strong>{{ $reset->available_at->timezone('Europe/Berlin')->format('d.m.Y') }} um {{ $reset->available_at->timezone('Europe/Berlin')->format('H:i') }} Uhr</strong> zurückgesetzt, wenn du sie dann über diesen Link abschließt:</p>
    <p style="margin: 24px 0;"><a href="{{ $url }}" style="display: inline-block; background-color: #232220; color: #ffffff; padding: 12px 24px; border-radius: 9999px; text-decoration: none; font-weight: bold;">Rücksetzung ansehen</a></p>
    <p><strong>Warst du das nicht?</strong> Öffne den Link und brich die Rücksetzung ab – und ändere dein Passwort. Meldest du dich in der Zwischenzeit mit deinem zweiten Faktor an, wird die Rücksetzung automatisch abgebrochen.</p>
@else
    <h1 style="font-size: 22px;">Rücksetzung für ein Team-Konto</h1>
    <p>Für das Team-Konto von {{ $reset->user->display_name }} (Rolle: {{ $reset->user->role->value }}) wurde die Rücksetzung der Zwei-Faktor-Anmeldung bestätigt. Sie ist ab {{ $reset->available_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr möglich.</p>
    <p>Bitte auf einem anderen Weg mit der Person klären, ob das gewollt ist.</p>
@endif
<p style="margin-top: 32px; border-top: 1px solid #e5e5e5; padding-top: 16px; font-size: 14px; color: #666;">Dein Oasis-Team<br>{{ config('oasis.operator.legal_name') }}</p>
</body></html>
