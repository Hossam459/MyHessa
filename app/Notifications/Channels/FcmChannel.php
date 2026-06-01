<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Throwable;

class FcmChannel
{
    public function __construct(private readonly Messaging $messaging)
    {
    }

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $token = $this->resolveToken($notifiable, $notification);

        if (! $token) {
            return;
        }

        $payload = $notification->toFcm($notifiable);

        if ($payload === null) {
            return;
        }

        $message = $this->buildMessage($payload, $token);

        try {
            $this->messaging->send($message);
        } catch (NotFound $e) {
            $this->forgetStaleToken($notifiable, $token);
        } catch (Throwable $e) {
            Log::warning('FCM push failed', [
                'message' => $e->getMessage(),
                'notifiable' => method_exists($notifiable, 'getKey') ? $notifiable->getKey() : null,
            ]);
        }
    }

    private function resolveToken(object $notifiable, Notification $notification): ?string
    {
        if (method_exists($notifiable, 'routeNotificationFor')) {
            $token = $notifiable->routeNotificationFor('fcm', $notification);

            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        return null;
    }

    private function buildMessage(array $payload, string $token): CloudMessage
    {
        $title = (string) ($payload['title'] ?? '');
        $body = (string) ($payload['body'] ?? '');
        $imageUrl = $payload['image_url'] ?? null;
        $data = $this->normaliseData($payload['data'] ?? []);

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(FcmNotification::create($title, $body, $imageUrl ?: null))
            ->withData($data)
            ->withAndroidConfig([
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                ],
            ])
            ->withApnsConfig([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                        'content-available' => 1,
                    ],
                ],
            ]);

        return $message;
    }

    private function normaliseData(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $flat = [];

        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }

            $flat[(string) $key] = is_scalar($value)
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $flat;
    }

    private function forgetStaleToken(object $notifiable, string $token): void
    {
        if (! property_exists($notifiable, 'fcm_token') && ! method_exists($notifiable, 'getAttribute')) {
            return;
        }

        if ($notifiable->fcm_token === $token) {
            $notifiable->forceFill([
                'fcm_token' => null,
                'fcm_token_updated_at' => now(),
            ])->save();
        }
    }
}
