<?php

namespace App\Models;

use App\Constants\Status;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{

    protected $appends = ['total_grade', 'quiz_results'];
    public function questions(){
        return $this->hasMany(QuizQuestion::class);
    }

    public function options(){
        return $this->hasMany(QuizOption::class);
    }

    public function course(){
        return $this->belongsTo(Course::class);
    }

    public function curriculum(){
        return $this->belongsTo(Curriculum::class);
    }

    public function section(){
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }



    public function getTotalGradeAttribute(){
        return$this->questions()->count();
    }


    public function getQuizResultsAttribute(){
     
        $correctQuestionIds = QuizSubmit::where('user_id', auth()->id())->where('course_id', $this->course_id)->where('quiz_id', $this->id)->where('answer', Status::YES)->pluck('quiz_question_id')->toArray();
        

        $totalQuestion = $this->questions()->count();

        $correctQuestion = count($correctQuestionIds);

        $percentage = $correctQuestion ? ($correctQuestion / $totalQuestion ) * 100 : 0;


        $isParticipate = UserProgress::where('user_id', auth()->id())->where('course_id',$this->course_id)->where('curriculum_id',$this->curriculum_id)->exists(); 

        $quizGrad = [
            'percentage' => round($percentage),
            'get_point' => $correctQuestion,
            'is_participate' => $isParticipate,
            
         ];
         return $quizGrad;
        
    }

    









}
    