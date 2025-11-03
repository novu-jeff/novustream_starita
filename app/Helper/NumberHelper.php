<?php

namespace App\Helper;

class NumberHelper
{
<<<<<<< HEAD
    public static function convertToWords($number)
    {
        $f = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
        $words = ucfirst($f->format($number));
        return $words . ' pesos only';
    }
=======
    public static function convertToWords($amount)
{
    $f = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);

    // Split amount into whole pesos and centavos
    $pesos = floor($amount);
    $centavos = round(($amount - $pesos) * 100);

    // Convert pesos to words and capitalize each word
    $words = ucwords($f->format($pesos));

    // Format centavos as 2-digit string (e.g., 5 → 05)
    $centavosFormatted = str_pad($centavos, 2, '0', STR_PAD_LEFT);

    return $words . ' Pesos & ' . $centavosFormatted . '/100';
}


>>>>>>> 883d53890d1a80e799e7560ece1ae4ad62407c7b
}
