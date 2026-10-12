<?php

/*
|--------------------------------------------------------------------------
| Image Processing
|--------------------------------------------------------------------------
|
| The desktop runtime bundles the gd extension, which ships inside the PHP
| build and needs no host libraries. imagick is available as a fallback but
| requires ImageMagick installed alongside the binary, so gd is the default.
|
| The driver lives here so switching is a one-line change rather than a hunt
| through the image pipeline.
|
*/

return [
    'driver' => env('IMAGE_DRIVER', 'gd'),
];
