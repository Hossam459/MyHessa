<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
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
}
