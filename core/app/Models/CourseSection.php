<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    protected $casts = [
        'is_released_to_students' => 'boolean',
    ];

    protected $appends = ['total_duration', 'section_item', 'curriculum_complate'];
    public function lectures()
    {
        return $this->hasMany(CourseLecture::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function curriculums()
    {
        return $this->hasMany(Curriculum::class, 'course_section_id',);
    }

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }


    public function getTotalDurationAttribute()
    {
        $totalSeconds = 0;
        foreach ($this->lectures as $lecture) {

            if ($lecture->content_type == Status::VIDEO) {
                list($hours, $minutes, $seconds) = explode(':', $lecture->video_duration);
                $totalSeconds += ($hours * 3600) + ($minutes * 60) + $seconds;
            }
        }


        $hours = floor($totalSeconds / 3600);
        $minutes = floor(($totalSeconds % 3600) / 60);
        $seconds = $totalSeconds % 60;

        $totalDuration = "{$hours}h {$minutes}m {$seconds}s";

        return $totalDuration;
    }


    public function getSectionItemAttribute()
    {
        return [
            'lectures' => $this->lectures()->count(),
            'quizzes' => $this->quizzes()->count(),
        ];
    }

    public function getCurriculumComplateAttribute()
    {
        return UserProgress::where('user_id', auth()->id())->where('course_id', $this->course_id)->whereIn('curriculum_id', $this->curriculums()->pluck('id')->toArray())->count();
    }
}
