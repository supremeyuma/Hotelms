<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class ActionCodeService
{
    public static function generate(int $length = 6): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $code;
    }

    public static function encrypt(string $code): string
    {
        return Crypt::encryptString($code);
    }

    public static function decrypt(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }
}
