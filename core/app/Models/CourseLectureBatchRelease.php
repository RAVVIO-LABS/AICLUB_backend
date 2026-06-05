<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseLectureBatchRelease extends Model
{
    protected $fillable = [
        'course_id',
        'course_section_id',
        'course_lecture_id',
        'batch_id',
        'is_released',
        'released_by_type',
        'released_by_id',
    ];

    protected $casts = [
        'is_released' => 'boolean',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function section()
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    public function lecture()
    {
        return $this->belongsTo(CourseLecture::class, 'course_lecture_id');
    }

    public function batch()
    {
        return $this->belongsTo(CourseLiveBatch::class, 'batch_id');
    }
}
