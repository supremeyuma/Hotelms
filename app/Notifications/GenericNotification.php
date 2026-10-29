<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A plain operational notification: a title and a bag of context lines.
 *
 * `NotificationService` sends this in four places (booking confirmations,
 * maintenance alerts, order-status changes and housekeeping reminders), but the
 * class did not exist — so every guest maintenance report died with
 * "Class App\Notifications\GenericNotification not found" and no alert was ever
 * delivered.
 *
 * Mail only, deliberately: the app has no `notifications` table, so a database
 * channel would fail on every send.
 */
class GenericNotification extends Notification
{
    use Queueable;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $title,
        public array $payload = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject($this->title)
            ->line($this->title);

        foreach ($this->flatten($this->payload) as $label => $value) {
            $message->line("{$label}: {$value}");
        }

        return $message;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function flatten(array $payload, string $prefix = ''): array
    {
        $lines = [];

        foreach ($payload as $key => $value) {
            $label = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $lines += $this->flatten($value, $label);
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 'yes' : 'no';
            }

            $lines[$label] = is_scalar($value) || $value === null ? (string) $value : json_encode($value);
        }

        return $lines;
    }
}
