<?php

/*
|--------------------------------------------------------------------------
| Oasis website configuration
|--------------------------------------------------------------------------
|
| Every number, contact, price, placeholder and switch the public website shows.
| Views never hard-code these values; change them here (or via .env where an env()
| call exists) and every page follows.
|
| Conventions:
| - Values in [BRACKETS] are open placeholders from docs/oasis-design/PLATZHALTER.md
|   and must be filled before launch.
| - A null figure means "not published yet": views render "Veröffentlichen wir ab
|   dem Start" or hide the element. Never put invented numbers here.
| - Amounts are in euro and include VAT unless a comment says otherwise.
|
*/

return [

    // Public domain, shown in texts such as "get-oasis.com/@name".
    'domain' => env('OASIS_DOMAIN', 'get-oasis.com'),

    /*
     * Operator details for the Impressum (§ 5 DDG), the footer © line and the SEPA mandate.
     */
    'operator' => [
        // Short name in the footer and the SEPA mandate.
        'name' => env('OASIS_OPERATOR_NAME', 'Tobias Kubina – VelWebSolutions'),
        // Full legal name, legal form, address and phone for the Impressum.
        'legal_name' => env('OASIS_OPERATOR_LEGAL_NAME', 'Tobias Kubina, B.Sc. – VelWebSolutions'),
        'legal_form' => 'Einzelunternehmen',
        'street' => env('OASIS_OPERATOR_STREET', 'Gubener Str. 14'),
        'city' => env('OASIS_OPERATOR_CITY', '47829 Krefeld'),
        'country' => 'Deutschland',
        'phone' => env('OASIS_OPERATOR_PHONE', '0176 32544620'),
        // VAT identification number (§ 27a UStG).
        'vat_id' => env('OASIS_OPERATOR_VAT_ID', 'DE459259380'),
        // Person responsible for editorial content (§ 18 Abs. 2 MStV).
        'responsible' => 'Tobias Kubina, B.Sc.',
        // Consumer dispute resolution notice (§ 36 VSBG), full sentence as shown in the Impressum.
        'vsbg_notice' => 'Wir sind zur Teilnahme an Streitbeilegungsverfahren vor einer Verbraucherschlichtungsstelle weder verpflichtet noch bereit (§ 36 VSBG).',
    ],

    /*
     * Mail addresses. The first four are shown on pages; the last two receive form notifications.
     * For now all of them go to one inbox; split them per purpose via .env when needed.
     */
    'mail' => [
        // General contact: footer, Hilfe, Manifest, Impressum.
        'contact' => env('OASIS_MAIL_CONTACT', 'info@velwebsolutions.de'),
        // Press contact on the Manifest page.
        'press' => env('OASIS_MAIL_PRESS', 'info@velwebsolutions.de'),
        // Data protection contact on the Hilfe page and in the privacy policy.
        'privacy' => env('OASIS_MAIL_PRIVACY', 'info@velwebsolutions.de'),
        // Single point of contact under DSA Art. 11 and 12 (Impressum).
        'dsa' => env('OASIS_MAIL_DSA', 'info@velwebsolutions.de'),
        // Receives copies of cancellations, withdrawals and organisation requests.
        'operator' => env('OASIS_MAIL_OPERATOR', 'info@velwebsolutions.de'),
        // Receives content reports (DSA Art. 16).
        'moderation' => env('OASIS_MAIL_MODERATION', 'info@velwebsolutions.de'),
    ],

    /*
     * "Wer dahinter steht" on the Manifest page.
     */
    'founder' => [
        'name' => '[DEIN NAME]',
        // Public URL or asset path of a photo; null shows a dashed photo placeholder.
        'photo' => null,
        'text' => '[Gründer-Text]',
    ],

    // Launch date as Y-m-d (e.g. "2027-03-01"); null = not announced yet, pages say "bald".
    'launch_date' => env('OASIS_LAUNCH_DATE'),

    'founding' => [
        // Number of founding seats with a price guarantee (banner, Preise, Gründung).
        'seats' => 1000,
    ],

    // Disk for uploaded media (Tropfen images): 'local' (storage/app/private) or 's3' (private EU bucket, see .env AWS_*).
    // Files are always served through the app after a visibility check, never via public bucket URLs.
    'media_disk' => env('OASIS_MEDIA_DISK', 'local'),

    /*
     * Automated content check (stage 10, docs/oasis-auto-moderation.md). Language model via the Laravel AI SDK on
     * Mistral (EU); only the text is sent. Scores are 0..1. A human always decides; held = hidden until reviewed.
     */
    'moderation' => [
        'enabled' => (bool) env('OASIS_AI_MODERATION', true),
        'provider' => env('OASIS_MODERATION_PROVIDER', 'mistral'),
        'model' => env('OASIS_MODERATION_MODEL', 'mistral-small-latest'),
        // "Nochmal lesen?" before sending (insult, hate, threat).
        'nudge_threshold' => 0.6,
        // Opens a moderation case.
        'report_threshold' => 0.7,
        // Clearly sexual Tropfen are held until reviewed.
        'hold_threshold' => 0.85,
    ],

    // Free trial length in days. Used in every "30 Tage testen" text.
    'trial_days' => 30,
    // Days in which posts can still be downloaded after a trial ends or a membership is cancelled.
    'retention_days' => 30,
    // Invitations a member can send per month (Gemeinsam starten).
    'invites_per_month' => 3,
    // Days a group has to get everyone on board before a group trial starts (Gemeinsam starten).
    'group_start_days' => 7,
    // Promised support reply time in working days (Hilfe).
    'support_reply_days' => 2,
    // Native apps as display text (Funktionen).
    'apps' => '[iOS und Android]',
    // Name of the document that contains the promises, e.g. "AGB" or "Satzung" (Manifest, Transparenz).
    'statutes' => '[AGB / Satzung]',
    // Set to true only once the promises (no ads, no ad profile, no data sale, changes only with member
    // consent) are actually written into that document. Until then the site says "Wird festgeschrieben" (UWG).
    'promises_codified' => (bool) env('OASIS_PROMISES_CODIFIED', false),
    // Hosting and service providers incl. region, as one sentence (Hilfe, Datenschutz).
    'server_location' => '[SERVERSTANDORT UND DIENSTLEISTER]',

    /*
     * Facts for the privacy policy (Datenschutzerklärung). A null value renders "[noch festzulegen]" so an open
     * point is visible instead of an invented claim. Check every value against the real setup before launch.
     */
    'privacy' => [
        // Hosting provider incl. address and server location (taken over from velwebsolutions.de; verify for Oasis).
        'hosting' => 'IONOS SE, Elgendorfer Str. 57, 56410 Montabaur, Server in Deutschland',
        // Provider that sends the confirmation mails, incl. region. Planned: Amazon SES via SMTP (see PROMPT.md).
        'mail_provider' => null,
        // Automated content check (stage 10): only the text is sent. Verify DPA and retention terms before launch.
        'ai_provider' => 'Mistral AI SAS, 15 rue des Halles, 75001 Paris, Frankreich – Verarbeitung in der EU',
        // Realtime delivery of encrypted chat messages runs on our own server via Laravel Reverb (null = no third-party provider).
        'realtime_provider' => null,
        // Days after which web server log files (IP address, time, URL, browser) are deleted.
        'server_log_days' => null,
        // Days after which unconfirmed waitlist entries are deleted.
        'unconfirmed_waitlist_days' => null,
        // Months after launch after which confirmed waitlist entries are deleted (purpose: launch news).
        'confirmed_waitlist_months' => null,
        // Years for which cancellations, withdrawals, content reports and organisation requests are kept as proof.
        // Enforced automatically: models using PrunedAfterRecordRetention are deleted by the daily model:prune.
        'records_years' => null,
        // Competent data protection supervisory authority for the operator (Krefeld, NRW).
        'authority' => 'Landesbeauftragte für Datenschutz und Informationsfreiheit Nordrhein-Westfalen, Kavalleriestraße 2–4, 40213 Düsseldorf',
        // Date of the current version, shown as "Stand".
        'as_of' => 'Oktober 2026',
    ],

    /*
     * Membership prices. Monthly amounts in euro incl. VAT; format them with App\Support\OasisFormat.
     */
    // VAT rate in percent, used to show the VAT included in a price (amount × rate / (100 + rate)).
    'vat_rate' => 19,
    // A yearly membership costs this many monthly amounts ("2 Monate geschenkt" = 12 - year_months).
    'year_months' => 10,
    // Tiers in display order. `min` = the amount is a minimum ("ab 12 €"); exactly one tier has `default`.
    'tiers' => [
        'solidar' => ['name' => 'Solidar', 'monthly' => 3, 'min' => false],
        'oase' => ['name' => 'Oase', 'monthly' => 5, 'min' => false, 'default' => true],
        'maezen' => ['name' => 'Mäzen', 'monthly' => 12, 'min' => true],
    ],

    /*
     * Payment details shown on the checkout page (only reachable when checkout_enabled is true).
     */
    'payment' => [
        // Card payment provider named in the checkout hint.
        'provider' => 'Mollie',
        // SEPA creditor identifier for the mandate text.
        'sepa_creditor_id' => '[GLÄUBIGER-ID]',
    ],

    /*
     * Account retention policies after trial or cancellation.
     */
    'retention' => [
        // Automatically prune and delete content of expired accounts past retention_days.
        'auto_delete_expired' => (bool) env('OASIS_RETENTION_AUTO_DELETE', false),
    ],

    // /mitglied-werden returns 404 until a payment provider is connected: no order button without a real checkout.
    'checkout_enabled' => (bool) env('OASIS_CHECKOUT_ENABLED', false),

    // Guest preview pages (Entdecken, Profil) with sample content, labelled "Beispielansicht" and noindex.
    // Set to false in production if the samples should not be public.
    'demo_pages' => (bool) env('OASIS_DEMO_PAGES', true),

    /*
     * Transparenz page. Every null value renders "Veröffentlichen wir ab dem Start".
     */
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
        // Member votes are real records now (admin area, App\Models\Vote); see docs/oasis-admin.md.
    ],

    /*
     * Draft building blocks of the legal pages (Datenschutz, AGB, Widerrufsbelehrung).
     * Placeholders only: the final texts must be written or reviewed by a lawyer before launch.
     */
    'legal' => [
        // Generic placeholder for paragraphs that still need a text.
        'text' => '[Text]',
        // Data subject rights listed in the privacy policy (Art. 15–21 and 77 GDPR).
        'privacy_rights' => '[Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit, Widerspruch, Beschwerde bei einer Aufsichtsbehörde]',
        // Categories of personal data named in the privacy policy.
        'privacy_data' => '[Kontodaten, Inhalte, Zahlungsdaten, technische Daten]',
        // Contract term and notice periods for the AGB.
        'contract_term' => '[Monatlich kündbar; Jahresbeitrag zum Ende des Jahres, danach monatlich kündbar]',
        // The promises the AGB must contain (see promises_codified).
        'promises' => '[Keine Werbung, kein Werbeprofil, kein Datenverkauf; Änderung nur mit Zustimmung der Mitglieder]',
        // Price and billing section of the AGB.
        'prices' => '[Stufen, Abrechnung, Preisgarantie für Gründungsmitglieder]',
        // Withdrawal instructions based on the statutory model.
        'withdrawal' => '[Widerrufsbelehrung nach dem gesetzlichen Muster, angepasst auf digitale Dienstleistungen]',
    ],
];
