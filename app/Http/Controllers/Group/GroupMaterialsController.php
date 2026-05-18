<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\Group;
use App\Models\GroupAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GroupMaterialsController extends Controller
{
    use HttpResponses;

    private function ensureGroupAccess(Group $group): array
    {
        $user = auth()->user();
        if (!$user) return [false, $this->error(null, __('messages.unauthorized'), 401)];

        // teacher of group
        if ($user->teacher && $group->teacher_id == $user->teacher->id) {
            return [true, null];
        }

        // approved student in group
        if ($user->student) {
            $ok = GroupMembership::where('group_id', $group->id)
                ->where('student_id', $user->student->id)
                ->where('status', 'approved')
                ->exists();

            if ($ok) return [true, null];

            return [false, $this->error(null, __('group.forbidden_group_materials'), 403)];
        }

        return [false, $this->error(null, __('messages.unauthorized'), 401)];
    }

    // ✅ GET /groups/{groupId}/materials
    public function list($groupId)
    {
        $group = Group::findOrFail($groupId);

        [$allowed, $resp] = $this->ensureGroupAccess($group);
        if (!$allowed) return $resp;

        $items = GroupAttachment::where('group_id', $group->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($a) {
                return [
                    'id' => $a->id,
                    'title' => $a->title,
                    'file_name' => $a->file_name,
                    'mime_type' => $a->mime_type,
                    'file_size' => $a->file_size,
                    'url' => asset('storage/' . ltrim($a->file_path, '/')),
                    'created_at' => $a->created_at,
                ];
            });

        return $this->success($items, __('materials.list'));
    }

    // ✅ POST /groups/{groupId}/materials  (Teacher only)
    public function upload(Request $request, $groupId)
    {
        $user = auth()->user();
        if (!$user || !$user->teacher) return $this->error(null, __('messages.unauthorized'), 401);

        $group = Group::findOrFail($groupId);
        if ($group->teacher_id != $user->teacher->id) {
            return $this->error(null, __('group.forbidden_teacher_group'), 403);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'file'  => 'required|file|max:20480', // 20MB
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('validation.invalid_data'), 422);
        }

        $file = $request->file('file');
        $path = $file->store('public/group_materials/' . $group->id);

        $attachment = GroupAttachment::create([
            'group_id'   => $group->id,
            'teacher_id' => $user->teacher->id,
            'title'      => $request->title,
            'file_name'  => $file->getClientOriginalName(),
            'file_path'  => str_replace('public/', '', $path),
            'mime_type'  => $file->getMimeType(),
            'file_size'  => $file->getSize(),
        ]);

        return $this->success([
            'id' => $attachment->id,
            'title' => $attachment->title,
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'file_size' => $attachment->file_size,
            'url' => asset('storage/' . $attachment->file_path),
        ], __('materials.uploaded'));
    }

    // ✅ DELETE /groups/{groupId}/materials/{attachmentId} (Teacher only)
    public function delete($groupId, $attachmentId)
    {
        $user = auth()->user();
        if (!$user || !$user->teacher) return $this->error(null, __('messages.unauthorized'), 401);

        $group = Group::findOrFail($groupId);
        if ($group->teacher_id != $user->teacher->id) {
            return $this->error(null, __('group.forbidden_teacher_group'), 403);
        }

        $attachment = GroupAttachment::where('group_id', $group->id)->findOrFail($attachmentId);

        // delete file
        Storage::delete('public/' . $attachment->file_path);

        $attachment->delete();

        return $this->success(null, __('materials.deleted'));
    }

}
