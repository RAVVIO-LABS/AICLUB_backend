<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultationRequest extends Model
{
    protected $guarded = ['id'];
    protected $casts = [
        'preferred_at' => 'datetime',
        'scheduled_at' => 'datetime',
        'confirmation_sent_at' => 'datetime',
        'reminder_24h_sent_at' => 'datetime',
        'reminder_1h_sent_at' => 'datetime',
    ];

    public function assignedTeacher()
    {
        return $this->belongsTo(Instructor::class, 'assigned_teacher_id');
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function scheduledByTeacher()
    {
        return $this->belongsTo(Instructor::class, 'scheduled_by_teacher_id');
    }
}
