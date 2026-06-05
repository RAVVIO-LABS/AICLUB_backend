<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseLiveBooking extends Model
{
    protected $fillable = [
        'course_id',
        'course_purchased_id',
        'batch_id',
        'user_id',
        'class_type',
        'start_date',
        'start_time',
        'end_time',
        'class_duration',
        'status',
        'assigned_teacher_id',
        'assigned_by_admin_id',
        'notes',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
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

    public function assignedTeacher()
    {
        return $this->belongsTo(Instructor::class, 'assigned_teacher_id');
    }

    public function sessions()
    {
        return $this->hasMany(CourseLiveSession::class, 'course_purchased_id', 'course_purchased_id');
    }
}
