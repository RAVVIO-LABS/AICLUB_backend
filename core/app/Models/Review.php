<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{

    protected $casts = [
        'instructor_reply_date' => 'datetime'
    ];


    public function user(){
        return $this->belongsTo(User::class);
    }

    public function course(){
        return $this->belongsTo(Course::class);
    }


    public function instructor(){
        return $this->belongsTo(Instructor::class);
    }


}
