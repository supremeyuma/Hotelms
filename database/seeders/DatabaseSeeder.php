<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Master DatabaseSeeder that runs all sub-seeders in proper order
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Core foundational data
        /*$this->call([
            RolesSeeder::class,
            PropertiesSeeder::class,
            RoomTypesSeeder::class,
            RoomsSeeder::class,
            SettingsSeeder::class,
        ]);

        // Users & staff
        $this->call([
            UsersSeeder::class,
            StaffProfilesSeeder::class,
        ]);

        // Media and inventory
        $this->call([
            ImagesSeeder::class,
            InventoryItemsSeeder::class,
            InventoryLogsSeeder::class,
        ]);

        // Content pages
        $this->call([
            PagesSeeder::class,
        ]);

        // Operational data
        $this->call([
            BookingsSeeder::class,
            PaymentsSeeder::class,
            OrdersSeeder::class,
            OrderEventsSeeder::class,
            //OrderItemsSeeder::class ?? OrderItemsSeeder::class, // fallback: orders already populated
            MaintenanceTicketsSeeder::class,
            AuditLogsSeeder::class,
        ]);*/

        $this->call([
            WebsiteContentSeeder::class,
            GallerySeeder::class,
        ]);
    }
}
