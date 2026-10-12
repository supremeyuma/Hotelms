<?php

namespace Tests\Feature\Images;

use App\Jobs\GenerateImageThumbnailsJob;
use App\Models\Image;
use App\Models\Room;
use App\Services\AuditLoggerService;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The thumbnail job referenced Intervention Image but the package was never
 * installed, so it failed silently on every run. These tests pin the v3
 * behaviour: a thumbnail is produced, scaled down to the target box, and a
 * missing source file is a no-op rather than an error.
 */
class GenerateImageThumbnailsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_a_scaled_down_thumbnail(): void
    {
        config(['image.driver' => 'gd']);
        Storage::fake('public');
        Storage::disk('public')->put('images/demo/source.png', $this->png(800, 600));

        $image = $this->imageRecord('images/demo/source.png');

        $this->runJob($image);

        $this->assertTrue(Storage::disk('public')->exists('images/demo/source_thumb.png'));

        $info = getimagesizefromstring(Storage::disk('public')->get('images/demo/source_thumb.png'));
        $this->assertNotFalse($info);
        $this->assertSame(400, $info[0]);
        $this->assertSame(300, $info[1]);
    }

    public function test_it_does_not_upscale_a_smaller_image(): void
    {
        config(['image.driver' => 'gd']);
        Storage::fake('public');
        Storage::disk('public')->put('images/demo/small.png', $this->png(100, 80));

        $image = $this->imageRecord('images/demo/small.png');

        $this->runJob($image);

        $info = getimagesizefromstring(Storage::disk('public')->get('images/demo/small_thumb.png'));
        $this->assertNotFalse($info);
        $this->assertSame(100, $info[0]);
        $this->assertSame(80, $info[1]);
    }

    public function test_it_is_a_no_op_when_the_source_file_is_missing(): void
    {
        config(['image.driver' => 'gd']);
        Storage::fake('public');

        $image = $this->imageRecord('images/demo/missing.png');

        $this->runJob($image);

        $this->assertFalse(Storage::disk('public')->exists('images/demo/missing_thumb.png'));
    }

    private function runJob(Image $image): void
    {
        (new GenerateImageThumbnailsJob($image))->handle(
            app(ImageService::class),
            app(AuditLoggerService::class),
        );
    }

    private function imageRecord(string $path): Image
    {
        return Image::create([
            'imageable_id' => 1,
            'imageable_type' => Room::class,
            'path' => $path,
            'is_primary' => false,
        ]);
    }

    private function png(int $width, int $height): string
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefilledrectangle($canvas, 0, 0, $width, $height, imagecolorallocate($canvas, 120, 160, 200));

        ob_start();
        imagepng($canvas);
        $data = (string) ob_get_clean();
        imagedestroy($canvas);

        return $data;
    }
}
