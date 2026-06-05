<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseLiveSession extends Model
{
    protected $fillable = [
        'course_id',
        'course_lecture_id',
        'batch_id',
        'course_purchased_id',
        'user_id',
        'assigned_teacher_id',
        'zoom_host_user_id',
        'zoom_host_email',
        'zoom_subaccount_id',
        'session_number',
        'scheduled_at',
        'reminder_sent_at',
        'duration_minutes',
        'meeting_name',
        'zoom_meeting_id',
        'zoom_join_url',
        'recording_status',
        'status',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function lecture()
    {
        return $this->belongsTo(CourseLecture::class, 'course_lecture_id');
    }

    public function batch()
    {
        return $this->belongsTo(CourseLiveBatch::class, 'batch_id');
    }

    public function purchase()
    {
        return $this->belongsTo(CoursePurchased::class, 'course_purchased_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Instructor::class, 'assigned_teacher_id');
    }
}
