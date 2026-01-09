<?php
// database/seeders/WebsiteContentSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Content;

class WebsiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $contents = [

            // SITE
            ['key' => 'site.name', 'value' => 'Azure Sands Beach Resort', 'type' => 'text'],

            // HOME – HERO
            ['key' => 'home.hero.title', 'value' => 'A Private Beachfront Escape', 'type' => 'text'],
            ['key' => 'home.hero.subtitle', 'value' => 'Luxury, privacy and unforgettable moments by the ocean.', 'type' => 'text'],
            ['key' => 'home.hero.image', 'value' => '/images/seed/home-hero.jpg', 'type' => 'image'],

            // HOME – EXPERIENCE
            ['key' => 'home.experience.text', 'value' => 'Nestled along pristine shores, our resort blends modern comfort with coastal serenity. From golden sunsets to vibrant nightlife, every moment is designed to elevate your stay.', 'type' => 'text'],

            // AMENITIES
            ['key' => 'amenities.content', 'value' => '
                <ul>
                    <li>Private beach access</li>
                    <li>Luxury swimming pool</li>
                    <li>Club & night lounge</li>
                    <li>Restaurant & cocktail bar</li>
                    <li>24/7 front desk service</li>
                    <li>Daily housekeeping</li>
                    <li>Laundry & dry-cleaning</li>
                    <li>High-speed Wi-Fi</li>
                    <li>Secure on-site parking</li>
                </ul>
            ', 'type' => 'html'],

            // CLUB & LOUNGE
            ['key' => 'club.description', 'value' => '
                <p>
                    Our club and lounge redefine nightlife by the sea. Featuring curated music,
                    premium cocktails and an exclusive atmosphere, it is the heartbeat of the resort
                    after sunset.
                </p>
                <p>
                    Dress code applies. Open weekends and selected weekdays.
                </p>
            ', 'type' => 'html'],

            // POLICIES
            ['key' => 'policies.content', 'value' => '
                <h3>Check-in & Check-out</h3>
                <p>Check-in from 2:00 PM. Check-out by 12:00 PM.</p>

                <h3>Cancellation Policy</h3>
                <p>Cancellations made 48 hours before arrival are fully refundable.</p>

                <h3>Payments</h3>
                <p>All bookings must be guaranteed. Outstanding bills must be cleared before checkout.</p>

                <h3>Guest Conduct</h3>
                <p>Guests are expected to respect other guests and hotel property.</p>

                <h3>Club Rules</h3>
                <p>Management reserves the right to refuse entry.</p>
            ', 'type' => 'html'],

            // FOOTER
            ['key' => 'footer.about', 'value' => 'A luxury beachfront resort offering comfort, nightlife and exceptional hospitality.', 'type' => 'text'],
            ['key' => 'footer.contact', 'value' => 'Phone: +234 XXX XXX XXXX | Email: reservations@azuresands.com', 'type' => 'text'],
            ['key' => 'footer.location', 'value' => 'Beachfront Road, Coastal City, Nigeria', 'type' => 'text'],
        ];

        foreach ($contents as $content) {
            Content::updateOrCreate(
                ['key' => $content['key']],
                $content
            );
        }
    }
}
