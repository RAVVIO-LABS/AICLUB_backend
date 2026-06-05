<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoomAuditLog extends Model
{
    protected $fillable = [
        'course_id',
        'batch_id',
        'live_session_id',
        'actor_type',
        'actor_id',
        'action',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
