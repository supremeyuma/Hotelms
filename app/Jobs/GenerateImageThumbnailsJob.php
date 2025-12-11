<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\ImageService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManagerStatic as InterventionImage;

/**
 * GenerateImageThumbnailsJob
 *
 * Generates thumbnails in background. Idempotent: overwrite existing thumbnail if present.
 */
class GenerateImageThumbnailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Image $image;
    public int $tries = 2;
    public int $timeout = 90;

    public function __construct(Image $image)
    {
        $this->image = $image->withoutRelations();
    }

    public function middleware(): array
    {
        // Rate limit thumbnail generation to avoid spikes
        return [new RateLimited('image-thumbnails')];
    }

    public function tags(): array
    {
        return ['image','thumbnail','image:'.$this->image->id];
    }

    public function handle(ImageService $imageService, AuditLoggerService $audit)
    {
        $image = Image::find($this->image->id);
        if (!$image) return;

        $disk = Storage::disk('public');
        $path = $image->path;
        if (! $path || ! $disk->exists($path)) {
            // nothing to do
            return;
        }

        // thumbnail path pattern
        $thumbPath = preg_replace('/(\.\w+)$/', '_thumb$1', $path);

        // create thumbnail using Intervention (assumes extension installed)
        try {
            $contents = $disk->get($path);
            $img = InterventionImage::make($contents)
                ->fit(400, 300, function ($constraint) {
                    $constraint->upsize();
                })->encode();

            $disk->put($thumbPath, (string) $img);
            $audit->log('image_thumbnail_generated', $image, $image->id, ['thumb' => $thumbPath]);
        } catch (\Throwable $e) {
            \Log::error('GenerateImageThumbnailsJob failed', ['id' => $image->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
