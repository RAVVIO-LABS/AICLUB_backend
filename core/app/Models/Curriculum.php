<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Curriculum extends Model
{

    protected $appends = ['is_completed'];


    public function lectures()
    {
        return $this->hasMany(CourseLecture::class);
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function completes()
    {
        return $this->hasMany(UserProgress::class, 'curriculum_id');
    }

    public function getIsCompletedAttribute()
    {
        $user_id = auth()->id();
        $completed = $this->completes()->where('user_id', $user_id)->exists();
        return $completed;
    }
}
