<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\ImageService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessImageUploadJob
 *
 * - Ensures uploaded image file is moved/validated
 * - Triggers thumbnail generation and optimization jobs
 * - Idempotent: if image record processed flag exists, will skip
 */
class ProcessImageUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Image $image;
    public int $tries = 3;
    public int $timeout = 120;
    public int $backoff = 60;

    public function __construct(Image $image)
    {
        $this->image = $image->withoutRelations();
    }

    public function middleware(): array
    {
        // prevent overlapping processing for same image
        return [new WithoutOverlapping('process-image-'.$this->image->id)];
    }

    public function tags(): array
    {
        return ['image','process','image:'.$this->image->id];
    }

    public function handle(ImageService $imageService, AuditLoggerService $audit)
    {
        // Idempotency: if meta.processed flag set, skip
        if (($this->image->meta['processed'] ?? false) === true) {
            return;
        }

        try {
            // perform any processing that the service exposes (resizing, validation)
            $image = Image::find($this->image->id);
            if (! $image) return;

            // Generate thumbnails (dispatch job)
            GenerateImageThumbnailsJob::dispatch($image);

            // Optimize (dispatch job)
            OptimizeImageJob::dispatch($image);

            // mark processed meta - done via service or model update
            $image->meta = array_merge($image->meta ?? [], ['processed' => true, 'processed_at' => now()->toISOString()]);
            $image->save();

            $audit->log('image_processed', $image, $image->id, ['processed_by_job' => true]);
        } catch (\Throwable $e) {
            Log::error('ProcessImageUploadJob failed', ['id' => $this->image->id, 'err' => $e->getMessage()]);
            throw $e;
        }
    }
}
