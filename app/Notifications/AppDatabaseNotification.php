<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Delivers an in-app (database) notification and, when the notifiable has an
 * FCM token, an accompanying push notification using the same payload.
 *
 * Payload shape:
 *   [
 *     'type'  => 'group_new_post',
 *     'title' => 'New post in Group X',
 *     'body'  => '...',
 *     'data'  => ['group_id' => 1, ...],          // optional
 *     'image_url' => 'https://...',                // optional
 *   ]
 */
class AppDatabaseNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly array $payload)
    {
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($this->shouldSendPush($notifiable)) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => (string) ($this->payload['title'] ?? ''),
            'body' => (string) ($this->payload['body'] ?? ''),
            'image_url' => $this->payload['image_url'] ?? null,
            'data' => array_merge(
                ['type' => (string) ($this->payload['type'] ?? '')],
                (array) ($this->payload['data'] ?? [])
            ),
        ];
    }

    private function shouldSendPush(object $notifiable): bool
    {
        if (! method_exists($notifiable, 'routeNotificationFor')) {
            return false;
        }

        $token = $notifiable->routeNotificationFor('fcm', $this);

        return is_string($token) && $token !== '';
    }
}
