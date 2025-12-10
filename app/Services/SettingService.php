<?php
// app/Services/SettingService.php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * SettingService
 *
 * Manage global settings and caching.
 */
class SettingService
{
    protected AuditLoggerService $audit;
    public function __construct(AuditLoggerService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Update multiple settings.
     *
     * @param array $data
     * @return void
     */
    public function update(array $data): void
    {
        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => is_array($value) ? json_encode($value) : $value]);
        }

        Cache::forget('settings_all');
        $this->audit->log('settings_updated', 'Setting', 0, ['data' => $data]);
    }

    /**
     * Upload file (logo/banner) and persist path.
     *
     * @param UploadedFile $file
     * @param string $key
     * @return string Stored path
     */
    public function uploadFile(UploadedFile $file, string $key): string
    {
        $path = $file->store('settings', 'public');
        Setting::updateOrCreate(['key' => $key], ['value' => $path]);
        Cache::forget('settings_all');
        $this->audit->log('setting_file_uploaded', 'Setting', 0, ['key' => $key, 'path' => $path]);
        return $path;
    }

    /**
     * Get cached settings
     *
     * @return array
     */
    public function all(): array
    {
        return Cache::remember('settings_all', 60 * 60, function () {
            return Setting::all()->pluck('value', 'key')->toArray();
        });
    }
}
