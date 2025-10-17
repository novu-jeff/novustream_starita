<?php

namespace App\Helper;

class NumberHelper
{
    public static function convertToWords($number)
    {
        $f = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
        $words = ucfirst($f->format($number));
        return $words . ' pesos only';
    }
}
