<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseLectureOneToOneRelease extends Model
{
    protected $fillable = [
        'course_id',
        'course_section_id',
        'course_lecture_id',
        'live_booking_id',
        'user_id',
        'is_released',
        'released_by_type',
        'released_by_id',
    ];

    protected $casts = [
        'is_released' => 'boolean',
    ];
}
