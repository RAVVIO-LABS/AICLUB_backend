<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherLeave extends Model
{
    protected $fillable = [
        'teacher_instructor_id',
        'from_date',
        'to_date',
        'reason',
        'status',
        'admin_note',
    ];

    public function teacher()
    {
        return $this->belongsTo(Instructor::class, 'teacher_instructor_id');
    }
}
