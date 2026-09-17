<?php

namespace App\Support;

class Money
{
    public static function format(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';

        return $sign.number_format(abs($amount), 0, '', ' ').' ₸';
    }
}
