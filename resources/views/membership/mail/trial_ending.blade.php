<!doctype html>
<html lang="de">
    <head>
        <meta charset="utf-8">
        <title>Dein Oasis-Test endet bald</title>
    </head>
    <body style="font-family: sans-serif; line-height: 1.5; color: #232220; max-width: 600px; margin: 0 auto; padding: 24px;">
        <h1 style="font-size: 22px; margin-bottom: 16px;">Hallo {{ $user->display_name }},</h1>

        <p>dein kostenloser 30-tägiger Test bei Oasis endet in zwei Tagen am {{ $user->trial_ends_at?->timezone('Europe/Berlin')->format('d.m.Y') }}.</p>

        <p><strong>Dein Test endet von selbst. Es entstehen dir keine Kosten.</strong></p>

        <p>Wenn du weiterhin bei Oasis mitmachen, Beiträge teilen und verschlüsselte Nachrichten senden möchtest, kannst du jetzt deinen fairen Mitgliedsbeitrag wählen:</p>

        <p style="margin: 24px 0;">
            <a href="{{ route('oasis.mitglied') }}" style="display: inline-block; background-color: #232220; color: #ffffff; padding: 12px 24px; border-radius: 9999px; text-decoration: none; font-weight: bold;">
                Mitgliedschaft wählen
            </a>
        </p>

        <p>Solltest du dich gegen eine Mitgliedschaft entscheiden, geht dein Konto nach Ablauf der Testphase in den Lesemodus über. Du hast anschließend 30 Tage Zeit, um deine Daten jederzeit herunterzuladen.</p>

        <p style="margin-top: 32px; border-top: 1px solid #e5e5e5; padding-top: 16px; font-size: 14px; color: #666;">
            Dein Oasis-Team<br>
            {{ config('oasis.operator.legal_name') }}
        </p>
    </body>
</html>
