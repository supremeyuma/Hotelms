<?php
// app/Services/RoomServiceMenuService.php

namespace App\Services;

use App\Models\Setting;
use App\Models\Order;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Cache;

/**
 * RoomServiceMenuService
 *
 * CRUD and retrieval for menu items stored in settings or DB.
 * For demo we save menu in settings->room_service_menu as JSON.
 */
class RoomServiceMenuService
{
    protected AuditLoggerService $audit;

    public function __construct(AuditLoggerService $audit)
    {
        $this->audit = $audit;
    }

    /**
     * Get entire menu (cached)
     *
     * @return array
     */
    public function getMenu(): array
    {
        return Cache::remember('room_service_menu', 3600, function () {
            $setting = Setting::where('key', 'room_service_menu')->first();
            if (!$setting) return [];
            $value = $setting->value;
            return is_string($value) ? json_decode($value, true) ?? [] : (array)$value;
        });
    }

    /**
     * Save the menu structure (array)
     *
     * @param array $menu
     * @return void
     */
    public function saveMenu(array $menu): void
    {
        Setting::updateOrCreate(['key' => 'room_service_menu'], ['value' => json_encode($menu)]);
        Cache::forget('room_service_menu');
        $this->audit->log('room_service_menu_updated', 'Setting', 0, ['menu' => $menu]);
    }

    /**
     * Find menu item by id path (category,itemId) if items have IDs
     *
     * @param string $category
     * @param mixed $itemId
     * @return array|null
     */
    public function findItem(string $category, $itemId): ?array
    {
        $menu = $this->getMenu();
        if (!isset($menu[$category])) return null;
        foreach ($menu[$category] as $item) {
            if (isset($item['id']) && $item['id'] == $itemId) return $item;
        }
        return null;
    }
}
