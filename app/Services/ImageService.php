<?php

namespace App\Services;

use App\Models\Image;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * ImageService
 *
 * Handles image uploads, deletion, primary toggling and basic processing.
 */
class ImageService
{
    protected string $disk = 'public';
    protected AuditLoggerService $audit;

    public function __construct(AuditLoggerService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Upload an image file and attach to a model (polymorphic).
     *
     * @param Model $model
     * @param UploadedFile $file
     * @param array $attrs ['caption'=>string, 'is_primary'=>bool, 'meta'=>array]
     * @return Image
     * @throws Exception
     */
    public function upload(Model $model, UploadedFile $file, array $attrs = []): Image
    {
        return DB::transaction(function () use ($model, $file, $attrs) {
            // Create a safe path: images/{model}/{model_id}/{timestamp}_{uuid}.{ext}
            $folder = 'images/' . Str::snake(class_basename($model)) . '/' . $model->getKey();
            $filename = now()->format('YmdHis') . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs($folder, $filename, $this->disk);

            if (! $path) {
                throw new Exception('Failed to store uploaded image.');
            }

            $image = Image::create([
                'imageable_id' => $model->getKey(),
                'imageable_type' => get_class($model),
                'path' => $path,
                'caption' => $attrs['caption'] ?? null,
                'is_primary' => boolval($attrs['is_primary'] ?? false),
                'meta' => $attrs['meta'] ?? null,
            ]);

            // If is_primary then unset others
            if ($image->is_primary) {
                $this->ensureSinglePrimary($model, $image->id);
            }

            $this->audit->log('image_uploaded', $image, $image->id, ['model' => get_class($model), 'model_id' => $model->getKey()]);

            return $image;
        });
    }

    /**
     * Delete image (file + DB record).
     *
     * @param Image $image
     * @return bool
     * @throws Exception
     */
    public function delete(Image $image): bool
    {
        return DB::transaction(function () use ($image) {
            // Delete file(s)
            if ($image->path && Storage::disk($this->disk)->exists($image->path)) {
                Storage::disk($this->disk)->delete($image->path);
            }

            // Also try thumbnail variant
            $thumbPath = preg_replace('/(\.\w+)$/', '_thumb$1', $image->path);
            if ($thumbPath && Storage::disk($this->disk)->exists($thumbPath)) {
                Storage::disk($this->disk)->delete($thumbPath);
            }

            $this->audit->log('image_deleted', $image, $image->id, ['path' => $image->path]);

            return $image->delete();
        });
    }

    /**
     * Update image meta or caption (not file).
     *
     * @param Image $image
     * @param array $attrs
     * @return Image
     */
    public function update(Image $image, array $attrs): Image
    {
        $before = $image->toArray();
        $image->update([
            'caption' => $attrs['caption'] ?? $image->caption,
            'meta' => $attrs['meta'] ?? $image->meta,
        ]);
        $this->audit->logChange('image_updated', $image, $before, $image->toArray());
        return $image->refresh();
    }

    /**
     * Set a single primary image for the imageable model.
     *
     * @param Image $image
     * @return Image
     */
    public function setPrimary(Image $image): Image
    {
        return DB::transaction(function () use ($image) {
            $model = $image->imageable()->getModel();
            // unset others
            Image::where('imageable_type', get_class($model))
                ->where('imageable_id', $model->getKey())
                ->where('id', '<>', $image->id)
                ->update(['is_primary' => false]);

            $before = $image->toArray();
            $image->update(['is_primary' => true]);
            $this->audit->logChange('image_set_primary', $image, $before, $image->toArray());
            return $image->refresh();
        });
    }

    /**
     * Ensure only one primary exists - unset others.
     *
     * @param Model $model
     * @param int|null $exceptId
     * @return void
     */
    protected function ensureSinglePrimary(Model $model, ?int $exceptId = null): void
    {
        Image::where('imageable_type', get_class($model))
            ->where('imageable_id', $model->getKey())
            ->when($exceptId, fn($q) => $q->where('id', '<>', $exceptId))
            ->update(['is_primary' => false]);
    }
}
