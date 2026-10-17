<?php

namespace App\Support;

/**
 * German money formatting for every Oasis view, so a price looks the same on every page.
 *
 * Used by the price card, Preise, Startseite, Hilfe, Transparenz, Gründung, Gemeinsam and the checkout.
 * Prices come from config('oasis.tiers') and may contain cents; never round them in a view.
 */
class OasisFormat
{
    /**
     * Euro sign behind a no-break space, so "5 €" never wraps between amount and sign.
     */
    public const CURRENCY = "\u{00A0}€";

    /**
     * German euro amount: whole amounts without cents ("5 €"), others with two decimals ("7,25 €").
     */
    public static function euro(float|int $amount): string
    {
        $decimals = floor($amount) == $amount ? 0 : 2;

        return number_format($amount, $decimals, ',', '.').self::CURRENCY;
    }

    /**
     * German euro amount, always with two decimals ("5,00 €").
     */
    public static function money(float|int $amount): string
    {
        return number_format($amount, 2, ',', '.').self::CURRENCY;
    }
}
