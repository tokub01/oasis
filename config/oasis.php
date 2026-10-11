<?php

/*
 * Oasis website: placeholders, numbers and switches.
 *
 * Views never hard-code these values. A null value means "not published yet"
 * and is rendered as "Veröffentlichen wir ab dem Start" (or hidden).
 * Open values are listed in docs/oasis-design/PLATZHALTER.md.
 */

return [

    'domain' => env('OASIS_DOMAIN', 'get-oasis.com'),

    'operator' => [
        'name' => env('OASIS_OPERATOR_NAME', '[BETREIBER]'),
        'legal_name' => env('OASIS_OPERATOR_LEGAL_NAME', '[NAME ODER FIRMA]'),
        'street' => env('OASIS_OPERATOR_STREET', '[STRASSE HAUSNUMMER]'),
        'city' => env('OASIS_OPERATOR_CITY', '[PLZ ORT]'),
        'phone' => env('OASIS_OPERATOR_PHONE', '[TELEFON]'),
        'vat_id' => env('OASIS_OPERATOR_VAT_ID', '[USt-IdNr.]'),
        'vsbg_notice' => '[Hinweis nach § 36 VSBG]',
    ],

    'mail' => [
        'contact' => env('OASIS_MAIL_CONTACT', '[KONTAKT-E-MAIL]'),
        'press' => env('OASIS_MAIL_PRESS', '[PRESSE-E-MAIL]'),
        'privacy' => env('OASIS_MAIL_PRIVACY', '[DATENSCHUTZ-E-MAIL]'),
        'dsa' => env('OASIS_MAIL_DSA', '[DSA-KONTAKT-E-MAIL]'),
        // Receives copies of cancellations, withdrawals and organisation requests.
        'operator' => env('OASIS_MAIL_OPERATOR', 'betreiber@example.tld'),
        // Receives content reports.
        'moderation' => env('OASIS_MAIL_MODERATION', 'moderation@example.tld'),
    ],

    'founder' => [
        'name' => '[DEIN NAME]',
        'photo' => null,
        'text' => '[Gründer-Text]',
    ],

    'launch_date' => env('OASIS_LAUNCH_DATE'),

    'founding' => [
        'seats' => 1000,
        // null = live count from waitlist_entries is not shown yet.
        'show_counter' => false,
    ],

    'trial_days' => 30,
    'retention_days' => 30,
    'invites_per_month' => 3,
    'group_start_days' => 7,
    'support_reply_days' => 2,
    'apps' => '[iOS und Android]',
    'statutes' => '[AGB / Satzung]',
    // Set to true only once the promises (no ads, no ad profile, no data sale, changes only with member
    // consent) are actually written into the AGB/statutes. Until then the site says "Wird festgeschrieben".
    'promises_codified' => (bool) env('OASIS_PROMISES_CODIFIED', false),
    'server_location' => '[SERVERSTANDORT UND DIENSTLEISTER]',

    // Prices in euro per month, including 19 % VAT. A year costs ten months.
    'vat_rate' => 19,
    'year_months' => 10,
    'tiers' => [
        'solidar' => ['name' => 'Solidar', 'monthly' => 3, 'min' => false],
        'oase' => ['name' => 'Oase', 'monthly' => 5, 'min' => false, 'default' => true],
        'maezen' => ['name' => 'Mäzen', 'monthly' => 12, 'min' => true],
    ],

    'payment' => [
        'provider' => '[ZAHLUNGSANBIETER]',
        'sepa_creditor_id' => '[GLÄUBIGER-ID]',
    ],

    // /mitglied-werden returns 404 until a payment provider is connected.
    'checkout_enabled' => (bool) env('OASIS_CHECKOUT_ENABLED', false),

    // Sample pages (Entdecken, Profil) with demo content. Marked as sample and noindex.
    'demo_pages' => (bool) env('OASIS_DEMO_PAGES', true),

    'transparency' => [
        // Month the numbers refer to, as display text, e.g. 'Oktober 2026'.
        'as_of' => null,
        // Paying members (integer).
        'members' => null,
        // Running costs and income per month in euro. If 'costs' is null, the sum of
        // 'cost_items' is used once every item has an amount. Coverage (income / costs)
        // is computed in the view and hidden while either value is missing.
        'costs' => null,
        'income' => null,
        // Sponsored Solidar and Paten seats (integer).
        'sponsored_seats' => null,
        // Cost items: label => amount in euro per month (null = not published yet).
        'cost_items' => [
            'Server und Speicher' => null,
            'Entwicklung und Werkzeuge' => null,
            'Moderation' => null,
            'Zahlungsgebühren' => null,
            'Rücklage' => null,
        ],
        // Member votes. The section stays hidden while this is empty. Each entry:
        // ['title' => '…', 'open' => true|false, 'ends' => '31.10.2026',
        //  'options' => ['…', '…'] (open votes), 'result' => '…' (closed votes)]
        'votes' => [],
    ],

    // Oasis forms: draft legal text, pending legal review.
    'legal' => [
        'text' => '[Text]',
        'privacy_rights' => '[Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch, Beschwerde bei einer Aufsichtsbehörde]',
        'privacy_data' => '[Kontodaten, Inhalte, Zahlungsdaten, technische Daten]',
        'contract_term' => '[Monatlich kündbar; Jahresbeitrag zum Ende des Jahres, danach monatlich kündbar]',
        'promises' => '[Keine Werbung, kein Werbeprofil, kein Datenverkauf; Änderung nur mit Zustimmung der Mitglieder]',
        'prices' => '[Stufen, Abrechnung, Preisgarantie für Gründungsmitglieder]',
        'withdrawal' => '[Widerrufsbelehrung nach dem gesetzlichen Muster, angepasst auf digitale Dienstleistungen]',
    ],
];
