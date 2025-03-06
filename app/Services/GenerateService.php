<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class GenerateService {

    public static function qr_code(string $url, $size) {

        $qrCode = QrCode::size($size)->generate($url);
        return $qrCode;
    }

}