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
use Illuminate\Support\Carbon;

/**
 * CleanupOldImagesJob
 *
 * Deletes images soft-deleted or older than retention period.
 */
class CleanupOldImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function tags(): array
    {
        return ['cleanup','images'];
    }

    public function handle(ImageService $imageService, AuditLoggerService $audit)
    {
        $retainDays = config('hms.retention.images', 90);
        $threshold = now()->subDays($retainDays);

        // delete soft-deleted permanently and old unlinked images
        $images = Image::onlyTrashed()->where('deleted_at','<=', $threshold)->get();

        foreach ($images as $img) {
            try {
                $imageService->delete($img);
                $img->forceDelete();
                $audit->log('image_cleanup_deleted', $img, $img->id);
            } catch (\Throwable $e) {
                \Log::error('CleanupOldImagesJob failed for image '.$img->id, ['error' => $e->getMessage()]);
            }
        }
    }
}
