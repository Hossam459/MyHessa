<?php
namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupPost;
use App\Models\GroupPostAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Notifications\AppDatabaseNotification;

class GroupFeedController extends Controller
{
    use HttpResponses;

   public function list(Request $request, $groupId)
{
    $user = auth()->user();
    if (!$user) return $this->error(null, __('messages.unauthorized'), 401);

    $group = \App\Models\Group::findOrFail($groupId);

    $teacher = $user->teacher;
    $student = $user->student;

    // ✅ 1) Teacher of group
    if ($teacher && $group->teacher_id == $teacher->id) {
        return $this->listFeed($request, $group);
    }

    // ✅ 2) Approved student in group فقط
    if ($student) {
        $isApproved = \App\Models\GroupMembership::where('group_id', $group->id)
            ->where('student_id', $student->id)
            ->where('status', 'approved')
            ->exists();

        if (!$isApproved) {
            return $this->error(null, __('group.forbidden_group_feed'), 403);
        }

        return $this->listFeed($request, $group);
    }

    return $this->error(null, __('messages.unauthorized'), 401);
}

private function listFeed(Request $request, \App\Models\Group $group)
{
    $perPage = min((int)$request->get('per_page', 10), 30);

    $posts = \App\Models\GroupPost::with(['attachments', 'teacher.user'])
        ->where('group_id', $group->id)
        ->orderByDesc('is_pinned')
        ->orderByDesc('id')
        ->paginate($perPage);

    $posts->getCollection()->transform(function ($p) {
        return $this->formatPost($p);
    });

    return $this->success($posts, __('feed.list'));
}

private function formatPost(GroupPost $post): array
{
    return [
        'id' => $post->id,
        'group_id' => $post->group_id,
        'teacher_id' => $post->teacher_id,
        'content' => $post->content,
        'is_pinned' => (bool) $post->is_pinned,
        'teacher_name' => $post->teacher?->user?->user_name,
        'teacher_image_profile_url' => $post->teacher?->user?->image_profile_url,
        'attachments' => $post->attachments->map(function ($attachment) use ($post) {
            return [
                'id' => $attachment->id,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
                'url' => asset('storage/' . ltrim($attachment->file_path, '/')),
                'download_url' => url("/api/groups/{$post->group_id}/feed/attachments/{$attachment->id}/download"),
            ];
        })->values(),
        'created_at' => $post->created_at,
        'updated_at' => $post->updated_at,
    ];
}

    // ✅ POST /groups/{groupId}/feed
private function ensureGroupFeedAccess(Group $group): array
{
    $user = auth()->user();
    if (!$user) return [false, $this->error(null, __('messages.unauthorized'), 401)];

    if ($user->teacher && $group->teacher_id == $user->teacher->id) {
        return [true, null];
    }

    if ($user->student) {
        $isApproved = GroupMembership::where('group_id', $group->id)
            ->where('student_id', $user->student->id)
            ->where('status', 'approved')
            ->exists();

        if ($isApproved) return [true, null];

        return [false, $this->error(null, __('group.forbidden_group_feed'), 403)];
    }

    return [false, $this->error(null, __('messages.unauthorized'), 401)];
}

    public function create(Request $request, $groupId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;
        if (!$teacher) return $this->error(null, __('messages.unauthorized'), 401);

        $group = Group::findOrFail($groupId);
        if ($group->teacher_id != $teacher->id) {
            return $this->error(null, __('group.forbidden_teacher_group'), 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
            'is_pinned' => 'nullable|boolean',
            'attachment' => 'nullable|file|max:10240',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // 10MB each
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('messages.invalid_data'), 422);
        }

        $post = null;

        DB::transaction(function () use ($request, $group, $teacher, &$post) {

            $isPinned = (bool)$request->get('is_pinned', false);
            if ($isPinned) {
                $this->unpinOtherGroupPosts($group->id);
            }

            $post = GroupPost::create([
                'group_id' => $group->id,
                'teacher_id' => $teacher->id,
                'content' => $request->content,
                'is_pinned' => $isPinned,
            ]);

            $files = [];
            if ($request->hasFile('attachment')) {
                $files[] = $request->file('attachment');
            }

            if ($request->hasFile('attachments')) {
                $files = array_merge($files, $request->file('attachments'));
            }

            foreach ($files as $file) {
                    $path = $file->store('group_posts/' . $group->id, 'public');

                    GroupPostAttachment::create([
                        'post_id' => $post->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
            }
        });

        $post = GroupPost::with(['attachments', 'teacher.user'])->find($post->id);
        $this->notifyApprovedStudents($group, [
            'type' => 'group_feed_post_created',
            'title' => __('notifications.group_feed_post_created_title'),
            'body' => __('notifications.group_feed_post_created_body', ['group' => $group->name]),
            'data' => [
                'group_id' => $group->id,
                'post_id' => $post->id,
                'teacher_id' => $teacher->id,
            ],
        ]);

        return $this->success($this->formatPost($post), __('feed.created'));
    }

    // ✅ PUT /groups/{groupId}/feed/{postId}
    public function update(Request $request, $groupId, $postId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;
        if (!$teacher) return $this->error(null, __('messages.unauthorized'), 401);

        $group = Group::findOrFail($groupId);
        if ($group->teacher_id != $teacher->id) {
            return $this->error(null, __('group.forbidden_teacher_group'), 403);
        }

        $post = GroupPost::with('attachments')
            ->where('group_id', $group->id)
            ->findOrFail($postId);

        $validator = Validator::make($request->all(), [
            'content' => 'sometimes|required|string|max:5000',
            'is_pinned' => 'sometimes|boolean',
            'attachment' => 'nullable|file|max:10240',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240',
            'delete_attachment_ids' => 'nullable|array',
            'delete_attachment_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('messages.invalid_data'), 422);
        }

        DB::transaction(function () use ($request, $group, $post) {
            $updates = [];

            if ($request->has('content')) {
                $updates['content'] = $request->input('content');
            }

            if ($request->has('is_pinned')) {
                $updates['is_pinned'] = $request->boolean('is_pinned');
            }

            if (($updates['is_pinned'] ?? false) === true) {
                $this->unpinOtherGroupPosts($group->id, $post->id);
            }

            if (!empty($updates)) {
                $post->update($updates);
            }

            $deleteAttachmentIds = $request->input('delete_attachment_ids', []);
            if (!empty($deleteAttachmentIds)) {
                $attachments = $post->attachments()
                    ->whereIn('id', $deleteAttachmentIds)
                    ->get();

                foreach ($attachments as $attachment) {
                    Storage::disk('public')->delete($attachment->file_path);
                    Storage::delete('public/' . $attachment->file_path);
                    $attachment->delete();
                }
            }

            $files = [];
            if ($request->hasFile('attachment')) {
                $files[] = $request->file('attachment');
            }

            if ($request->hasFile('attachments')) {
                $files = array_merge($files, $request->file('attachments'));
            }

            foreach ($files as $file) {
                $path = $file->store('group_posts/' . $group->id, 'public');

                GroupPostAttachment::create([
                    'post_id' => $post->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        });

        $post = GroupPost::with(['attachments', 'teacher.user'])->find($post->id);

        return $this->success($this->formatPost($post), __('feed.updated'));
    }

    public function downloadAttachment($groupId, $attachmentId)
    {
        $group = Group::findOrFail($groupId);

        [$allowed, $resp] = $this->ensureGroupFeedAccess($group);
        if (!$allowed) return $resp;

        $attachment = GroupPostAttachment::whereHas('post', function ($query) use ($group) {
                $query->where('group_id', $group->id);
            })
            ->findOrFail($attachmentId);

        $path = ltrim($attachment->file_path, '/');

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download(
                $path,
                $attachment->file_name,
                ['Content-Type' => $attachment->mime_type]
            );
        }

        $localPath = 'public/' . $path;
        if (Storage::exists($localPath)) {
            return Storage::download(
                $localPath,
                $attachment->file_name,
                ['Content-Type' => $attachment->mime_type]
            );
        }

        return $this->error(null, __('messages.not_found'), 404);
    }

    public function delete($groupId, $postId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;
        if (!$teacher) return $this->error(null, __('messages.unauthorized'), 401);

        $group = Group::findOrFail($groupId);
        if ($group->teacher_id != $teacher->id) return $this->error(null, __('group.forbidden_teacher_group'), 403);

        $post = GroupPost::where('group_id', $group->id)->findOrFail($postId);

        // delete files
        foreach ($post->attachments as $a) {
            Storage::disk('public')->delete($a->file_path);
            Storage::delete('public/' . $a->file_path);
        }

        $post->delete();

        return $this->success(null, __('feed.deleted'));
    }

    // ✅ POST /groups/{groupId}/feed/{postId}/pin
    public function togglePin($groupId, $postId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;
        if (!$teacher) return $this->error(null, __('messages.unauthorized'), 401);

        $group = Group::findOrFail($groupId);
        if ($group->teacher_id != $teacher->id) return $this->error(null, __('group.forbidden_teacher_group'), 403);

        DB::transaction(function () use ($group, $postId, &$post) {
            $post = GroupPost::where('group_id', $group->id)->findOrFail($postId);
            $post->is_pinned = !$post->is_pinned;

            if ($post->is_pinned) {
                $this->unpinOtherGroupPosts($group->id, $post->id);
            }

            $post->save();
        });

        return $this->success($post, __('feed.pin_updated'));
    }

    private function unpinOtherGroupPosts(int $groupId, ?int $exceptPostId = null): void
    {
        GroupPost::where('group_id', $groupId)
            ->when($exceptPostId, fn ($query) => $query->where('id', '!=', $exceptPostId))
            ->where('is_pinned', true)
            ->update(['is_pinned' => false]);
    }

    private function notifyApprovedStudents(Group $group, array $payload): void
    {
        GroupMembership::with('student.user')
            ->where('group_id', $group->id)
            ->where('status', GroupMembership::STATUS_APPROVED)
            ->get()
            ->pluck('student.user')
            ->filter()
            ->each(fn ($user) => $user->notify(new AppDatabaseNotification($payload)));
    }
}
