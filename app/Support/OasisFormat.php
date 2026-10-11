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
     * German euro amount: whole amounts without cents ("5 €"), others with two decimals ("7,25 €").
     */
    public static function euro(float|int $amount): string
    {
        $decimals = floor($amount) == $amount ? 0 : 2;

        return number_format($amount, $decimals, ',', '.').' €';
    }

    /**
     * German euro amount, always with two decimals ("5,00 €").
     */
    public static function money(float|int $amount): string
    {
        return number_format($amount, 2, ',', '.').' €';
    }
}
