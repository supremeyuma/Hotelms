<?php

namespace App\Jobs;

use App\Models\Image;
use App\Services\ImageService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\ImageOptimizer\OptimizerChainFactory;

/**
 * OptimizeImageJob
 *
 * Runs an optimizer chain (jpegoptim, pngquant, etc.) to reduce image size.
 * Idempotent: runs safely multiple times.
 */
class OptimizeImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Image $image;
    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(Image $image)
    {
        $this->image = $image->withoutRelations();
    }

    public function tags(): array
    {
        return ['image','optimize','image:'.$this->image->id];
    }

    public function handle(AuditLoggerService $audit)
    {
        $image = Image::find($this->image->id);
        if (! $image) return;

        $disk = storage_path('app/public/');
        $filePath = $disk . $image->path;

        if (! file_exists($filePath)) return;

        try {
            $optimizerChain = OptimizerChainFactory::create();
            $optimizerChain->optimize($filePath);

            $audit->log('image_optimized', $image, $image->id);
        } catch (\Throwable $e) {
            \Log::error('OptimizeImageJob failed', ['id' => $image->id, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
