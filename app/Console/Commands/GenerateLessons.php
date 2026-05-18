<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GroupSchedule;
use App\Models\Lesson;
use Carbon\Carbon;

class GenerateLessons extends Command
{
    protected $signature = 'lessons:generate';
    protected $description = 'Auto generate lessons based on group schedules';

    public function handle()
    {
        $now = Carbon::now();
        if ($now->month < 5 || $now->month > 9) {
            $this->info('Lesson generation is only active from May to September.');
            return 0;
        }

        $today = $now->toDateString();
        $dayOfWeek = $now->dayOfWeekIso; // 1 = Monday .. 7 = Sunday

        $schedules = GroupSchedule::with('group')
            ->where('day_of_week', $dayOfWeek)
            ->get();

        foreach ($schedules as $schedule) {

            // وقت بداية الحصة
            $start = Carbon::parse($today . ' ' . $schedule->start_time);

            // لو الوقت لسه ما جاش
            if ($now->lt($start)) {
                continue;
            }

            // منع التكرار
            $exists = Lesson::where('schedule_id', $schedule->id)
                ->where('lesson_date', $today)
                ->exists();

            if ($exists) {
                continue;
            }

            Lesson::create([
                'group_id'     => $schedule->group_id,
                'teacher_id'   => $schedule->group->teacher_id,
                'schedule_id'  => $schedule->id,
                'title'        => $schedule->group->name . ' Lesson',
                'lesson_date'  => $today,
                'start_time'   => $start,
                'end_time'     => Carbon::parse($today . ' ' . $schedule->end_time),
                'attendance_status' => 'pending',
            ]);
        }

        $this->info('Lessons generated successfully');
    }
}