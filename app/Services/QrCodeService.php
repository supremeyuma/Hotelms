<?php

namespace App\Services;

use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeService
{
    public function generateSvg(string $content, int $size = 400): string
    {
        return QrCode::format('svg')
            ->size($size)
            ->margin(2)
            ->errorCorrection('H')
            ->style('square')
            ->eye('square')
            ->encoding('UTF-8')
            ->generate($content);
    }
}
