<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoursePurchased extends Model
{
    
    public function user(){
        return $this->belongsTo(User::class);
    }

    public function course(){
        return $this->belongsTo(Course::class);
    }

    public function instructor(){
        return $this->belongsTo(Instructor::class);
    }

    public function liveBooking()
    {
        return $this->hasOne(CourseLiveBooking::class, 'course_purchased_id');
    }
    
}
