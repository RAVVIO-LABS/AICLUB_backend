<?php

namespace App\Traits;

use App\Models\CourseLecture;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\Quiz;
use App\Models\QuizQuestion;

trait CourseDeleteManage
{

    public function deleteSection($id)
    {
        $section = CourseSection::whereHas('course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->find($id);

        
        
        if (!$section) {
            $notify[] = 'Invalid request';
            return responseError('section_not_found', $notify);
        }
        
        $course = $section->course;

        $section->curriculums()->delete();
        $section->lectures()->delete();
        $quizzes = $section->quizzes();
        
        foreach ($quizzes as $quiz) {
            $questions = $quiz->questions();
            foreach ($questions as $question) {
                $question->options()->delete();
                $question->delete();
            }
           
            $quiz->delete();
        }

        $section->delete();

        $this->rearrangeLectureSerialNumbers($course->id);
        $this->rearrangeCourseQuizSerialNumbers($course->id);

        $notify[] = 'Section deleted successfully';
        return responseSuccess('section_deleted', $notify);
    }

    public function deleteLecture($id){
        $lecture = CourseLecture::whereHas('course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->find($id);
        
        if (!$lecture) {
            $notify[] = 'Invalid request';
            return responseError('lecture_not_found', $notify);
        }

        $course = $lecture->course;
        $lecture->curriculum()->delete();
        $lecture->delete();

        $this->rearrangeLectureSerialNumbers($course->id);
    
        $notify[] = 'Lecture deleted successfully';
        return responseSuccess('lecture_deleted', $notify);

    }


    public function deleteQuiz($id){
        $quiz = Quiz::whereHas('course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->find($id);
        
        if (!$quiz) {
            $notify[] = 'Invalid request';
            return responseError('quiz_not_found', $notify);
        }
        
        $course = $quiz->course;

        foreach ($quiz->questions as $question) {
            $question->options()->delete();
            $question->delete();
        }
        $quiz->curriculum()->delete();
        $quiz->delete();
        
        $this->rearrangeCourseQuizSerialNumbers($course->id);
        
        $notify[] = 'Quiz deleted successfully';
        return responseSuccess('quiz_deleted', $notify);
    }


    public function deleteQuestion($id){
        $question = QuizQuestion::with(['quiz'=>function ($query) {
            $query->whereHas('course', function ($query){
                $query->where('instructor_id', auth()->id());
            });
        }])->find($id);


        
        if (!$question) {
            $notify[] = 'Invalid request';
            return responseError('question_not_found', $notify);
        }

    
        $question->options()->delete();
        $question->delete();    

        $notify[] = 'Question deleted successfully';
        return responseSuccess('question_deleted', $notify);
    }


    public function deleteResource($id){
        $resource = CourseResource::whereHas('course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->find($id);
        
        if (!$resource) {
            $notify[] = 'Invalid request';
            return responseError('resource_not_found', $notify);
        }
        $resource->delete();

        $notify[] = 'Resource deleted successfully';
        return responseSuccess('resource_deleted', $notify);
    }




}
