<?php

namespace App\Enums\Moderation;

/**
 * What a member reports (quick report for Tropfen, profiles and oases, and the chat report dialog). Plain words
 * instead of legal terms, so reporting is easy, also for young people who are being bullied.
 */
enum ReportCategory: string
{
    case Bullying = 'mobbing';
    case Harassment = 'belaestigung';
    case Sexual = 'sexuell';
    case Hate = 'hass';
    case Threat = 'drohung';
    case Scam = 'betrug';
    case Spam = 'spam';
    case Other = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Bullying => 'Mobbing – jemand wird fertiggemacht, bloßgestellt oder ausgegrenzt',
            self::Harassment => 'Belästigung oder Beleidigung',
            self::Sexual => 'Sexuelle Belästigung oder Anfragen',
            self::Hate => 'Hass, Hetze oder Extremismus',
            self::Threat => 'Drohung oder Gewalt',
            self::Scam => 'Betrug oder Fragen nach Passwort, Bank- oder Kartendaten',
            self::Spam => 'Spam oder Fake-Profil',
            self::Other => 'Etwas anderes stimmt nicht',
        };
    }
}
