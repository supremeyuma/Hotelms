<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function generatePng(string $content, int $size = 400): string
    {
        return QrCode::format('png')
            ->size($size)
            ->margin(2)
            ->errorCorrection('H')
            ->style('square')
            ->eye('square')
            ->encoding('UTF-8')
            ->generate($content);
    }
}
