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
        'as_of' => null,
        'members' => null,
        'costs' => null,
        'income' => null,
        'sponsored_seats' => null,
        // Cost items: label => amount in euro per month (null = not published yet).
        'cost_items' => [],
        'votes' => [],
    ],

];
