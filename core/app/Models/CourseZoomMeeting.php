<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseZoomMeeting extends Model
{
    protected $fillable = [
        'course_id',
        'instructor_id',
        'batch_id',
        'topic',
        'description',
        'start_time',
        'duration_minutes',
        'timezone',
        'recurrence',
        'zoom_meeting_id',
        'zoom_join_url',
        'zoom_host_email',
        'zoom_host_user_id',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'recurrence' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function instructor()
    {
        return $this->belongsTo(Instructor::class, 'instructor_id');
    }

    public function batch()
    {
        return $this->belongsTo(CourseZoomBatch::class, 'batch_id');
    }

    public function occurrences()
    {
        return $this->hasMany(CourseZoomMeetingOccurrence::class, 'course_zoom_meeting_id')->orderBy('start_time');
    }
}
