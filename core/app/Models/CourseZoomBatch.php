<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseZoomBatch extends Model
{
    protected $fillable = [
        'course_id',
        'instructor_id',
        'title',
        'teacher_instructor_id',
        'status',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function teacher()
    {
        return $this->belongsTo(Instructor::class, 'teacher_instructor_id');
    }

    public function meetings()
    {
        return $this->hasMany(CourseZoomMeeting::class, 'batch_id');
    }
}
