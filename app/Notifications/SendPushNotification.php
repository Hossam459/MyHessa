<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Push-only notification. Sends via FCM without writing a database row.
 * Use AppDatabaseNotification when both an in-app entry and a push are
 * required.
 */
class SendPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $title,
        protected string $body,
        protected ?string $imageUrl = null,
        protected array $data = [],
    ) {
    }

    public function via(object $notifiable): array
    {
        return [FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->imageUrl,
            'data' => $this->data,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'image_url' => $this->imageUrl,
            'data' => $this->data,
        ];
    }
}
