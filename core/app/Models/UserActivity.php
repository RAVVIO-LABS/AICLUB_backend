<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivity extends Model
{
    protected $casts = [
        'active_date' => 'datetime'
    ];
}
