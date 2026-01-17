<?php

namespace App\Http\Controllers\Group;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\Group;
use App\Models\GroupAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GroupAttachmentController extends Controller
{
    use HttpResponses;

    private function assertTeacherOwnsGroup(Group $group, int $teacherId): void
    {
        if ((int)$group->teacher_id !== (int)$teacherId) {
            abort(403, __('group.not_group_teacher'));
        }
    }

    // POST /groups/{groupId}/attachments
    public function upload(Request $request, $groupId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;

        if (!$teacher) {
            return $this->error(null, __('auth.unauthorized') ?? 'Unauthorized', 401);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'files' => 'required|array|min:1',
            'files.*' => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,pdf'
        ], [
            'files.required' => __('attachment.files_required'),
            'files.array' => __('attachment.files_array'),
            'files.*.max' => __('attachment.file_too_large'),
            'files.*.mimes' => __('attachment.file_type_not_allowed'),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('attachment.invalid_data'), 422);
        }

        $group = Group::findOrFail($groupId);
        $this->assertTeacherOwnsGroup($group, $teacher->id);

        $saved = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store("public/groups/{$group->id}");

            $attachment = GroupAttachment::create([
                'group_id'   => $group->id,
                'teacher_id' => $teacher->id,
                'title'      => $request->title,
                'file_name'  => $file->getClientOriginalName(),
                'file_path'  => $path,
                'mime_type'  => $file->getClientMimeType(),
                'file_size'  => $file->getSize(),
            ]);

            $saved[] = [
                'id' => $attachment->id,
                'title' => $attachment->title,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
                'url' => Storage::url($attachment->file_path),
                'created_at' => $attachment->created_at,
            ];
        }

        return $this->success($saved, __('attachment.uploaded'));
    }

    // GET /groups/{groupId}/attachments
    public function list($groupId)
    {
        $group = Group::findOrFail($groupId);

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
                    'url' => Storage::url($a->file_path),
                    'created_at' => $a->created_at,
                ];
            });

        return $this->success($items, __('attachment.list'));
    }

    // DELETE /groups/{groupId}/attachments/{attachmentId}
    public function delete($groupId, $attachmentId)
    {
        $user = auth()->user();
        $teacher = $user?->teacher;

        if (!$teacher) {
            return $this->error(null, __('auth.unauthorized') ?? 'Unauthorized', 401);
        }

        $group = Group::findOrFail($groupId);
        $this->assertTeacherOwnsGroup($group, $teacher->id);

        $attachment = GroupAttachment::where('group_id', $groupId)
            ->where('id', $attachmentId)
            ->firstOrFail();

        Storage::delete($attachment->file_path);
        $attachment->delete();

        return $this->success(null, __('attachment.deleted'));
    }
}
