<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\User;
use App\Notifications\AppDatabaseNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    use HttpResponses;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 20), 50);
        $unreadOnly = filter_var($request->get('unread_only', false), FILTER_VALIDATE_BOOLEAN);

        $notifications = $user->notifications()
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($perPage);

        $notifications->getCollection()->transform(fn ($notification) => $this->formatNotification($notification));

        return $this->success([
            'unread_count' => $user->unreadNotifications()->count(),
            'notifications' => $notifications,
        ], __('notifications.list'));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->success([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ], __('notifications.unread_count'));
    }

    public function markAsRead(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $notificationId)
            ->firstOrFail();

        $notification->markAsRead();

        return $this->success($this->formatNotification($notification->fresh()), __('notifications.marked_as_read'));
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->success([
            'unread_count' => 0,
        ], __('notifications.marked_all_as_read'));
    }

    public function destroy(Request $request, string $notificationId): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $notificationId)
            ->firstOrFail();

        $notification->delete();

        return $this->success(null, __('notifications.deleted'));
    }

    public function registerFcmToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => ['required', 'string', 'max:512'],
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('notifications.fcm_token_invalid'), 422);
        }

        $user = $request->user();
        $user->forceFill([
            'fcm_token' => $request->input('fcm_token'),
            'fcm_token_updated_at' => now(),
        ])->save();

        return $this->success([
            'fcm_token_updated_at' => $user->fcm_token_updated_at,
        ], __('notifications.fcm_token_registered'));
    }

    public function removeFcmToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill([
            'fcm_token' => null,
            'fcm_token_updated_at' => now(),
        ])->save();

        return $this->success(null, __('notifications.fcm_token_removed'));
    }

    public function sendToUsers(Request $request): JsonResponse
    {
        $target = $this->normalizeTarget($request->input('target', 'all'));
        $request->merge(['target' => $target]);

        $validator = Validator::make($request->all(), [
            'target' => ['required', 'in:all,teachers,students'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:1000'],
            'type' => ['sometimes', 'string', 'max:100'],
            'data' => ['sometimes', 'array'],
            'image_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('notifications.send_invalid'), 422);
        }

        $payload = [
            'type' => $request->input('type', 'manual_broadcast'),
            'title' => $request->input('title'),
            'body' => $request->input('body'),
            'data' => $request->input('data', []),
        ];

        if ($request->filled('image_url')) {
            $payload['image_url'] = $request->input('image_url');
        }

        $query = User::query()
            ->when($target === 'teachers')
            ->when($target === 'students');

        $recipientsCount = (clone $query)->count();
        $pushRecipientsCount = (clone $query)->whereNotNull('fcm_token')->count();

        $query->select(['id', 'fcm_token'])
            ->orderBy('id')
            ->chunkById(100, function ($users) use ($payload) {
                $users->each(fn (User $user) => $user->notify(new AppDatabaseNotification($payload)));
            });

        return $this->success([
            'target' => $target,
            'recipients_count' => $recipientsCount,
            'push_recipients_count' => $pushRecipientsCount,
        ], __('notifications.sent'));
    }

    private function formatNotification($notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->data['type'] ?? class_basename($notification->type),
            'title' => $notification->data['title'] ?? null,
            'body' => $notification->data['body'] ?? null,
            'data' => $notification->data['data'] ?? [],
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
        ];
    }

    private function normalizeTarget(mixed $target): string
    {
        if (! is_string($target)) {
            return '';
        }

        return match ($target) {
            'teacher' => 'teachers',
            'student' => 'students',
            default => $target ?: 'all',
        };
    }
}
