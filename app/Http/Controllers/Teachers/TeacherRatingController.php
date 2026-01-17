<?php

namespace App\Http\Controllers\Teachers;

use App\Http\Controllers\Controller;
use App\Http\Traits\HttpResponses;
use App\Models\Teacher;
use App\Models\TeacherRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TeacherRatingController extends Controller
{
    use HttpResponses;


    public function rate(Request $request, $teacherId)
    {
        $user = auth()->user();
        $student = $user?->student;
        if (!$student) {
            return $this->error(null, __('auth.unauthorized') ?? 'Unauthorized', 401);
        }

        $teacher = Teacher::findOrFail($teacherId);

        $validator = Validator::make($request->all(), [
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
        ], [
            'rating.required' => __('message.rating_required'),
            'rating.integer'  => __('message.rating_integer'),
            'rating.min'      => __('message.rating_min'),
            'rating.max'      => __('message.rating_max'),
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors(), __('message.invalid_data'), 422);
        }


        $isMember = DB::table('group_memberships')
            ->join('groups', 'groups.id', '=', 'group_memberships.group_id')
            ->where('groups.teacher_id', $teacher->id)
            ->where('group_memberships.student_id', $student->id)
            ->where('group_memberships.status', 'approved')
            ->exists();

        if (!$isMember) {
            return $this->error(null, __('rating.not_allowed'), 403);
        }

        $ratingRow = TeacherRating::updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'student_id' => $student->id,
            ],
            [
                'rating'  => (int)$request->rating,
                'comment' => $request->comment,
            ]
        );

        return $this->success($ratingRow, __('rating.saved'));
    }

    public function list($teacherId)
    {
        $teacher = Teacher::findOrFail($teacherId);

        $items = TeacherRating::where('teacher_id', $teacher->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn($r) => [
                'student_id' => $r->student_id,
                'rating'     => $r->rating,
                'comment'    => $r->comment,
                'created_at' => $r->created_at,
            ]);

        return $this->success($items, __('rating.list'));
    }

    public function summary($teacherId)
    {
        $teacher = Teacher::findOrFail($teacherId);

        $count = TeacherRating::where('teacher_id', $teacher->id)->count();
        $avg   = TeacherRating::where('teacher_id', $teacher->id)->avg('rating');

        return $this->success([
            'teacher_id' => $teacher->id,
            'ratings_count' => $count,
            'average_rating' => $avg ? round((float)$avg, 2) : 0,
        ], __('rating.summary'));
    }
}
