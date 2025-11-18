<?php

namespace App\Helper;

class NumberHelper
{
    public static function convertToWords($amount)
    {
        // Use Intl's NumberFormatter when available; otherwise fall back to a PHP implementation
        $pesos = intval(floor($amount));
        $centavos = intval(round(($amount - $pesos) * 100));

        if (class_exists('\\NumberFormatter')) {
            $f = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
            $words = $f->format($pesos);
            $words = is_string($words) ? $words : (string) $words;
            $words = ucfirst($words);
        } else {
            $words = ucfirst(self::intToWords($pesos));
        }

        $centavosFormatted = str_pad($centavos, 2, '0', STR_PAD_LEFT);

        return $words . ' Pesos & ' . $centavosFormatted . '/100';
    }

    // Basic integer to English words converter for fallback (supports up to billions)
protected static function intToWords($num)
{
    if ($num === 0) return 'zero';

    $units = [
        '', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
        'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'
    ];
    $tens = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];

    $chunks = [
        1000000000 => 'billion',
        1000000 => 'million',
        1000 => 'thousand',
        100 => 'hundred'
    ];

    $result = '';

    foreach ($chunks as $div => $name) {
        if ($num >= $div) {
            $count = intval(floor($num / $div));
            $num = $num % $div;
            $result .= self::intToWords($count) . ' ' . $name . ' ';
        }
    }

    if ($num >= 20) {
        $t = intval(floor($num / 10));
        $u = $num % 10;
        $result .= $tens[$t];
        if ($u) $result .= '-' . $units[$u];
    } elseif ($num > 0) {
        $result .= $units[$num];
    }

    return trim(preg_replace('/\\s+/', ' ', $result));
}


}
