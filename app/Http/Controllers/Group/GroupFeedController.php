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
        $p->teacher_name = $p->teacher?->user?->user_name;
        $p->teacher_image_profile_url = $p->teacher?->user?->image_profile
            ? asset('storage/users/' . $p->teacher->user->image_profile)
            : null;

        $p->attachments->transform(function ($a) {
            $a->url = asset('storage/' . ltrim($a->file_path, '/'));
            return $a;
        });

        return $p;
    });

    return $this->success($posts, __('feed.list'));
}

    // ✅ POST /groups/{groupId}/feed
    public function create(Request $request, $groupId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;
        if (!$teacher) return $this->error(null, 'Unauthorized', 401);

        $group = Group::findOrFail($groupId);
        if ($group->teacher_id != $teacher->id) {
            return $this->error(null, 'Forbidden', 403);
        }

        $validator = Validator::make($request->all(), [
            'content' => 'required|string|max:5000',
            'is_pinned' => 'nullable|boolean',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240', // 10MB each
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), 'Invalid data', 422);
        }

        $post = null;

        DB::transaction(function () use ($request, $group, $teacher, &$post) {

            $post = GroupPost::create([
                'group_id' => $group->id,
                'teacher_id' => $teacher->id,
                'content' => $request->content,
                'is_pinned' => (bool)$request->get('is_pinned', false),
            ]);

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('public/group_posts/' . $group->id);

                    GroupPostAttachment::create([
                        'post_id' => $post->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => str_replace('public/', '', $path), // storage path
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }
        });

        $post = GroupPost::with(['attachments', 'teacher.user'])->find($post->id);

        return $this->success($post, 'Post created');
    }

    // ✅ DELETE /groups/{groupId}/feed/{postId}
    public function delete($groupId, $postId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;
        if (!$teacher) return $this->error(null, 'Unauthorized', 401);

        $group = Group::findOrFail($groupId);
        if ($group->teacher_id != $teacher->id) return $this->error(null, 'Forbidden', 403);

        $post = GroupPost::where('group_id', $group->id)->findOrFail($postId);

        // delete files
        foreach ($post->attachments as $a) {
            Storage::delete('public/' . $a->file_path);
        }

        $post->delete();

        return $this->success(null, 'Post deleted');
    }

    // ✅ POST /groups/{groupId}/feed/{postId}/pin
    public function togglePin($groupId, $postId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;
        if (!$teacher) return $this->error(null, 'Unauthorized', 401);

        $group = Group::findOrFail($groupId);
        if ($group->teacher_id != $teacher->id) return $this->error(null, 'Forbidden', 403);

        $post = GroupPost::where('group_id', $group->id)->findOrFail($postId);
        $post->is_pinned = !$post->is_pinned;
        $post->save();

        return $this->success($post, 'Pin updated');
    }
}
