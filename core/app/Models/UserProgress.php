<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProgress extends Model
{
    public function course(){
        return $this->belongsTo(Course::class);
    }
}
