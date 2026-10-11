<?php

/*
 * German messages for the password reset flow (Fortify uses these as the session `status` or as errors).
 * "sent" and "user" read the same on purpose, and NeutralPasswordResetLinkResponse answers unknown addresses with
 * "sent", so the page never reveals whether an address has an account.
 */

return [
    'reset' => 'Dein Passwort ist geändert. Du kannst dich jetzt anmelden.',
    'sent' => 'Wenn es zu dieser Adresse ein Konto gibt, haben wir dir einen Link geschickt.',
    'throttled' => 'Bitte warte kurz, bevor du es erneut versuchst.',
    'token' => 'Dieser Link ist ungültig oder abgelaufen. Bitte fordere einen neuen an.',
    'user' => 'Wenn es zu dieser Adresse ein Konto gibt, haben wir dir einen Link geschickt.',
];
