<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseLiveTeacherAssignment extends Model
{
    protected $fillable = [
        'course_id',
        'batch_id',
        'teacher_instructor_id',
        'assigned_by_admin_id',
        'is_active',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function batch()
    {
        return $this->belongsTo(CourseLiveBatch::class, 'batch_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Instructor::class, 'teacher_instructor_id');
    }
}
