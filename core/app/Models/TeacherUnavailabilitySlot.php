<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherUnavailabilitySlot extends Model
{
    protected $fillable = [
        'teacher_instructor_id',
        'start_at',
        'end_at',
        'reason',
        'source_type',
        'source_id',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
    ];

    public function teacher()
    {
        return $this->belongsTo(Instructor::class, 'teacher_instructor_id');
    }
}
