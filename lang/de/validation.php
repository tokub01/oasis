<?php

/* German messages for the rules used by Fortify sign-up, sign-in and account settings. */
return [
    'accepted' => ':attribute muss akzeptiert werden.',
    'required' => ':attribute ist erforderlich.',
    'string' => ':attribute muss eine Zeichenkette sein.',
    'email' => 'Bitte gib eine gültige E-Mail-Adresse ein.',
    'unique' => ':attribute wird bereits verwendet.',
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'date_format' => ':attribute muss dem Format :format entsprechen.',
    'before_or_equal' => ':attribute muss am oder vor dem :date liegen.',
    'regex' => ':attribute hat ein ungültiges Format.',
    'not_in' => ':attribute ist nicht verfügbar.',
    'current_password' => 'Das aktuelle Passwort ist nicht korrekt.',
    'min' => ['string' => ':attribute muss mindestens :min Zeichen lang sein.'],
    'max' => ['string' => ':attribute darf höchstens :max Zeichen lang sein.'],
    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss Groß- und Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Zahl enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => 'Dieses Passwort wurde in einem Datenleck gefunden. Bitte wähle ein anderes.',
    ],
    'attributes' => [
        'display_name' => 'Der Anzeigename',
        'username' => 'Der Benutzername',
        'email' => 'Die E-Mail-Adresse',
        'login' => 'Die E-Mail-Adresse oder der Benutzername',
        'birth_date' => 'Das Geburtsdatum',
        'password' => 'Das Passwort',
        'current_password' => 'Das aktuelle Passwort',
        'code' => 'Der Bestätigungscode',
        'recovery_code' => 'Der Wiederherstellungscode',
    ],
];
