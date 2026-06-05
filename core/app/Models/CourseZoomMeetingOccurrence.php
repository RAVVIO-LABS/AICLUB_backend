<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseZoomMeetingOccurrence extends Model
{
    protected $fillable = [
        'course_zoom_meeting_id',
        'title',
        'start_time',
        'duration_minutes',
        'zoom_join_url',
        'zoom_occurrence_id',
        'status',
        'teacher_instructor_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
    ];

    public function meeting()
    {
        return $this->belongsTo(CourseZoomMeeting::class, 'course_zoom_meeting_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Instructor::class, 'teacher_instructor_id');
    }
}
