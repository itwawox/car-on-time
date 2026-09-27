<?php

namespace App\Support;

class Phone
{
    /** Российский номер в виде +79781234567: 8… и 10-значные номера приводятся к +7. */
    public static function e164(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (strlen($digits) === 11 && $digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        }
        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        }

        return '+'.$digits;
    }
}
