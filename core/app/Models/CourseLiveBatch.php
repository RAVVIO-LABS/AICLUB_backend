<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseLiveBatch extends Model
{
    protected $fillable = [
        'course_id',
        'created_by_instructor_id',
        'class_type',
        'title',
        'zoom_meeting_link',
        'batch_number',
        'start_date',
        'end_date',
        'meeting_start_time',
        'meeting_end_time',
        'class_duration',
        'capacity',
        'lecture_ids',
        'status',
    ];

    protected $casts = [
        'lecture_ids' => 'array',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function teachers()
    {
        return $this->hasMany(CourseLiveTeacherAssignment::class, 'batch_id')->where('is_active', 1);
    }

    public function bookings()
    {
        return $this->hasMany(CourseLiveBooking::class, 'batch_id');
    }

    public function sessions()
    {
        return $this->hasMany(CourseLiveSession::class, 'batch_id');
    }
}
