<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\LiveClassScheduler;
use App\Models\ConsultationRequest;
use App\Models\Course;
use App\Models\CourseLecture;
use App\Models\CourseLectureBatchRelease;
use App\Models\CourseLiveBatch;
use App\Models\CourseLiveBooking;
use App\Models\CourseLiveSession;
use App\Models\CourseLiveTeacherAssignment;
use App\Models\CourseZoomBatch;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\Curriculum;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Rules\FileTypeValidate;
use App\Services\TeacherNotificationService;
use App\Services\Zoom\LmsZoomService;
use App\Services\Reminder\FreeClassReminderService;
use App\Traits\CourseDeleteManage;
use App\Traits\CourseManage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{

    use CourseDeleteManage, CourseManage;


    public function list()
    {

        $teacherId = (int) auth()->id();
        $courses = Course::with([
            'sections',
            'sections.curriculums.lectures',
            'sections.curriculums.quizzes',
            'sections.curriculums.quizzes.questions.options',
            'objects',
            'requirements',
            'contents',
            'sections.curriculums.lectures.resources',
            'purchases'
        ])
            ->where(function ($query) use ($teacherId) {
                $query->whereHas('liveTeacherAssignments', function ($assignmentQuery) use ($teacherId) {
                    $assignmentQuery->where('teacher_instructor_id', $teacherId)->where('is_active', 1);
                })->orWhereHas('zoomBatches', function ($zoomBatchQuery) use ($teacherId) {
                    $zoomBatchQuery->where('teacher_instructor_id', $teacherId);
                });
            })
            ->withSum(['purchases' => function ($q) {
                $q->where('payment_status', Status::PAYMENT_SUCCESS);
            }], 'amount')
            ->paginate(getPaginate());
      
        $notify[] = 'Courses page data';
        return responseSuccess('courses', $notify, [
            'courses' => $courses
        ]);
    }

    public function demoRequests()
    {
        $teacherId = (int) auth()->id();
        $batchEnrollmentCounts = CourseLiveBooking::where('course_id', $course->id)
            ->whereHas('purchase', function ($query) {
                $query->where('payment_status', Status::PAYMENT_SUCCESS);
            })
            ->whereNotNull('batch_id')
            ->selectRaw('batch_id, COUNT(DISTINCT user_id) as students_count')
            ->groupBy('batch_id')
            ->pluck('students_count', 'batch_id');

        $requests = ConsultationRequest::with(['course', 'assignedTeacher', 'scheduledByTeacher'])
            ->where('assigned_teacher_id', $teacherId)
            ->orderByRaw("CASE WHEN status = 'assigned_to_teacher' THEN 0 WHEN status = 'scheduled' THEN 1 ELSE 2 END")
            ->orderBy('preferred_at')
            ->orderByDesc('id')
            ->get();

        $courses = Course::whereHas('liveTeacherAssignments', function ($query) use ($teacherId) {
            $query->where('teacher_instructor_id', $teacherId)
                ->where('is_active', 1);
        })->orderBy('title')->get(['id', 'title', 'slug']);

        $notify[] = 'Demo requests data';
        return responseSuccess('demo_requests', $notify, [
            'requests' => $requests,
            'courses' => $courses,
        ]);
    }

    public function scheduleDemoRequest(Request $request, $id, LmsZoomService $zoom, FreeClassReminderService $freeClassReminderService)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|integer',
            'scheduled_at' => 'required|date|after:now',
            'meeting_duration' => 'nullable|integer|min:15|max:240',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $teacherId = (int) auth()->id();
        $demoRequest = ConsultationRequest::where('assigned_teacher_id', $teacherId)->find($id);

        if (!$demoRequest) {
            $notify[] = 'Demo request not found';
            return responseError('demo_request_not_found', $notify);
        }

        $course = Course::where('id', $request->course_id)
            ->whereHas('liveTeacherAssignments', function ($query) use ($teacherId) {
                $query->where('teacher_instructor_id', $teacherId)
                    ->where('is_active', 1);
            })
            ->first();

        if (!$course) {
            $notify[] = 'Selected course is not allocated to you';
            return responseError('invalid_course', $notify);
        }

        try {
            $scheduledAt = Carbon::parse($request->scheduled_at, config('zoom.meeting.timezone', config('app.timezone', 'UTC')));
        } catch (\Throwable $throwable) {
            $notify[] = 'Please select a valid schedule date and time';
            return responseError('invalid_schedule_time', $notify);
        }

        $duration = (int) ($request->meeting_duration ?: 30);
        $endAt = $scheduledAt->copy()->addMinutes($duration);
        $host = $zoom->selectHost($scheduledAt, $endAt);

        if (!$host) {
            $notify[] = 'No Zoom host is available for the selected time';
            return responseError('zoom_unavailable', $notify);
        }

        $isReschedule = !empty($demoRequest->zoom_meeting_id);
        $zoomUpdateSkipped = false;
        try {
            if ($isReschedule) {
                $meeting = $zoom->updateMeeting($demoRequest->zoom_meeting_id, [
                    'meeting_name' => $zoom->buildMeetingName($course->title, $scheduledAt, 1),
                    'start_time' => $scheduledAt,
                    'duration_minutes' => $duration,
                    'agenda' => $course->title,
                ]);
            } else {
                $meeting = $zoom->createMeeting([
                    'host_email' => $host['email'],
                    'meeting_name' => $zoom->buildMeetingName($course->title, $scheduledAt, 1),
                    'start_time' => $scheduledAt,
                    'duration_minutes' => $duration,
                    'agenda' => $course->title,
                ]);
            }
        } catch (\Throwable $throwable) {
            if ($isReschedule && str_contains(strtolower($throwable->getMessage()), 'does not contain scope')) {
                $zoomUpdateSkipped = true;
                $meeting = [
                    'id' => $demoRequest->zoom_meeting_id,
                    'join_url' => $demoRequest->zoom_join_url,
                    'host_email' => $demoRequest->zoom_host_email,
                ];
            } else {
                $notify[] = $throwable->getMessage();
                return responseError('zoom_create_failed', $notify);
            }
        }

        $demoRequest->course_id = $course->id;
        $demoRequest->scheduled_at = $scheduledAt;
        $demoRequest->meeting_duration = $duration;
        $demoRequest->scheduled_by_teacher_id = $teacherId;
        $demoRequest->status = 'scheduled';
        $demoRequest->zoom_meeting_id = $meeting['id'] ?? $demoRequest->zoom_meeting_id;
        $demoRequest->zoom_join_url = $meeting['join_url'] ?? $demoRequest->zoom_join_url;
        $demoRequest->zoom_host_email = $meeting['host_email'] ?? $demoRequest->zoom_host_email ?? $host['email'];
        $demoRequest->notes = $request->notes;
        $demoRequest->reminder_24h_sent_at = null;
        $demoRequest->reminder_1h_sent_at = null;
        $demoRequest->save();
        if (!empty($isReschedule)) {
            $freeClassReminderService->sendRescheduleUpdate($demoRequest);
        } else {
            $freeClassReminderService->sendConfirmation($demoRequest);
        }

        if ($zoomUpdateSkipped) {
            $notify[] = 'Zoom app is missing meeting update scope. Schedule was updated in system and student notified, but Zoom meeting time could not be updated via API.';
        }
        $notify[] = !empty($isReschedule) ? 'Demo class rescheduled successfully' : 'Demo class scheduled successfully';
        return responseSuccess('demo_class_scheduled', $notify, [
            'request' => $demoRequest->load(['course', 'assignedTeacher', 'scheduledByTeacher']),
        ]);
    }

    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|unique:courses,slug',
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $course = new Course();
        $course->title = $request->title;
        $course->slug = $request->slug;
        $course->instructor_id = auth()->user()->id;

        $course->save();

        $notify[] = 'Course created successfully';
        return responseSuccess('course_created', $notify, [
            'course' => $course
        ]);
    }

    public function addSection(Request $request, $slug)
    {

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'learning_object' => 'nullable|string',
            'section_id' => 'nullable|integer'
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();

        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('Invalid course', $notify);
        }

        if ($request->section_id) {
            $courseSection = CourseSection::where('course_id', $course->id)->find($request->section_id);
            if (!$courseSection) {
                $notify[] = 'Invalid section';
                return responseError('Invalid section', $notify);
            }
            $notify[] = 'Section updated successfully';
        } else {
            $courseSection = new CourseSection();
            $notify[] = 'Section added successfully';
        }

        $courseSection->course_id = $course->id;
        $courseSection->title = $request->title;
        $courseSection->learning_object = $request->learning_object;
        $courseSection->save();


        return responseSuccess('section_added', $notify, [
            'course' => $course,
            'course_section' => $courseSection
        ]);
    }


    public function addLecture(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'section_id' => 'required|integer',
            'lecture_id' => 'nullable|integer',
            'is_live_class' => 'nullable|boolean',

        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();

        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('Invalid course', $notify);
        }

        $courseSection = CourseSection::where('course_id', $course->id)->find($request->section_id);

        if (!$courseSection) {
            $notify[] = 'Invalid section';
            return responseError('Invalid section', $notify);
        }

        if ($request->lecture_id) {
            $lecture = CourseLecture::where('course_section_id', $courseSection->id)->find($request->lecture_id);
            if (!$lecture) {
                $notify[] = ['error', 'Lecture already exists'];
                return responseError('Lecture already exists', $notify);
            }
        } else {
            $curriculum = new Curriculum();
            $curriculum->course_id = $course->id;
            $curriculum->course_section_id = $courseSection->id;
            $curriculum->type = Status::LECTURE;
            $curriculum->save();

            $lecture = new CourseLecture();
            $lecture->content_type = 0;
            $lecture->curriculum_id = $curriculum->id;
            $lecture->serial_number = CourseLecture::where('course_id', $course->id)->max('serial_number') + 1;
        }

        $lecture->course_id = $course->id;
        $lecture->course_section_id = $courseSection->id;
        $lecture->title = $request->title;
        $lecture->is_preview = $request->is_preview ? Status::YES : Status::NO;
        $lecture->is_live_class = $request->is_live_class ? Status::YES : Status::NO;
        $lecture->save();



        $this->rearrangeLectureSerialNumbers($course->id);

        $notify[] = 'Lecture added successfully';
        return responseSuccess('lecture_added', $notify, [
            'course' => $course,
            'lecture' => $lecture,

        ]);
    }

    private function rearrangeLectureSerialNumbers($courseId)
    {


        $sections = CourseSection::where('course_id', $courseId)->orderBy('id')->get();


        $serial = 1;
        foreach ($sections as $section) {
            $lectures = CourseLecture::where('course_section_id', $section->id)
                ->orderBy('serial_number')
                ->get();

            foreach ($lectures as $lecture) {
                $lecture->serial_number = $serial;
                $lecture->save();
                $serial++;
            }
        }
    }

    public function addLectureVideo(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'lecture_id' => 'required|integer',
            'section_id' => 'required|integer',
            'video_file' => ['required', new FileTypeValidate(['mp4', 'mkv', 'webm', 'mov', 'vob', 'avi', 'mpeg'])],
            'video_duration' => 'required'
        ]);


        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();
        if (!$course) {
            $notify[] =  'Invalid course';
            return responseError('Invalid course', $notify);
        }
        $courseSection = CourseSection::where('course_id', $course->id)->find($request->section_id);
        if (!$courseSection) {
            $notify[] = 'Invalid section';
            return responseError('Invalid section', $notify);
        }
        $lecture = CourseLecture::where('course_section_id', $courseSection->id)
            ->whereIn('content_type', [Status::NO, Status::VIDEO])
            ->find($request->lecture_id);
        if (!$lecture) {
            $notify[] = 'Invalid lecture';
            return responseError('Invalid lecture', $notify);
        }
        if ($lecture->file) {
            $course->status = Status::PENDING;
            $course->save();
        }


        if ($request->hasFile('video_file')) {
            $fileName = preg_replace('/[^a-zA-Z0-9-_\.]/', '', titleToKey($lecture->title)) . '_' . uniqid() . '.' . $request->video_file->getClientOriginalExtension();
            $lecture->file = fileUploader($request->video_file, getFilePath('video'), old: $lecture->file, filename: $fileName);
        }


        $lecture->content_type = Status::VIDEO;
        $lecture->video_duration = $request->video_duration;
        $lecture->save();

        $notify[] = 'Lecture Video added successfully';
        return responseSuccess('video_added', $notify, [
            'lecture' => $lecture
        ]);
    }

    public function addLectureArticle(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'lecture_id' => 'required|integer',
            'section_id' => 'required|integer',
            'article' => 'required|string'
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());


        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();
        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('Invalid course', $notify);
        }
        $courseSection = CourseSection::where('course_id', $course->id)->find($request->section_id);
        if (!$courseSection) {
            $notify[] = 'Invalid section';
            return responseError('Invalid section', $notify);
        }
        $lecture = CourseLecture::where('course_section_id', $courseSection->id)
            ->whereIn('content_type', [Status::NO, Status::ARTICLE])
            ->find($request->lecture_id);
        if (!$lecture) {
            $notify[] = 'Invalid lecture';
            return responseError('Invalid lecture', $notify);
        }

        $lecture->article = $request->article;
        $lecture->content_type = Status::ARTICLE;
        $lecture->save();

        $notify[] = 'Lecture article added successfully';
        return responseSuccess('video_added', $notify, [
            'lecture' => $lecture
        ]);
    }

    public function addQuiz(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'section_id' => 'required|integer',
            'quiz_id' => 'nullable|integer',
            'passing_percentage' => 'required|between:0,100',

        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();

        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('Invalid course', $notify);
        }

        $courseSection = CourseSection::where('course_id', $course->id)->find($request->section_id);

        if (!$courseSection) {
            $notify[] = 'Invalid section';
            return responseError('Invalid section', $notify);
        }


        if ($request->quiz_id) {
            $quiz = Quiz::find($request->quiz_id);
        } else {
            $curriculum = new Curriculum();
            $curriculum->course_id = $course->id;
            $curriculum->course_section_id = $courseSection->id;
            $curriculum->type = Status::QUIZ;
            $curriculum->save();

            $quiz = new Quiz();
            $quiz->curriculum_id = $curriculum->id;
            $quiz->serial_number = Quiz::where('course_id', $course->id)->max('serial_number') + 1;
        }
        $quiz->course_id = $course->id;
        $quiz->course_section_id = $courseSection->id;
        $quiz->title = $request->title;
        $quiz->description = $request->description;
        $quiz->passing_percentage = $request->passing_percentage;
        $quiz->save();

        if (!$request->quiz_id) {
            $this->rearrangeCourseQuizSerialNumbers($course->id);
        }


        $notify[] = 'Quiz created successfully';
        return responseSuccess('quiz_added', $notify, [
            'quiz' => $quiz
        ]);
    }


    private function rearrangeCourseQuizSerialNumbers($courseId)
    {
        $sections = CourseSection::where('course_id', $courseId)->orderBy('id')->get();

        $serial = 1;
        foreach ($sections as $section) {
            $quizzes = Quiz::where('course_section_id', $section->id)
                ->orderBy('serial_number')
                ->get();

            foreach ($quizzes as $quiz) {
                $quiz->serial_number = $serial;
                $quiz->save();
                $serial++;
            }
        }
    }


    public function addQuizOption(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'required|integer',
            'quiz_id' => 'required|integer',
            'question' => 'required|string',
            'answers' => 'required|array|min:2',
            'explain' => 'nullable|string',
            'question_id' => 'nullable|integer',
        ], [
            'answers.required' => 'Answers are required.',
            'answers.array' => 'Answers must be an array.',
            'answers.min' => 'Answers must have at least 2 options.',
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();

        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('Invalid course', $notify);
        }

        $courseSection = CourseSection::where('course_id', $course->id)->find($request->section_id);

        if (!$courseSection) {
            $notify[] = 'Invalid section';
            return responseError('Invalid section', $notify);
        }

        $quiz = Quiz::where('course_section_id', $courseSection->id)->find($request->quiz_id);

        if (!$quiz) {
            $notify[] = 'Invalid quiz';
            return responseError('Invalid quiz', $notify);
        }


        if ($request->question_id) {
            $question = QuizQuestion::where('quiz_id', $quiz->id)->find($request->question_id);
            if (!$question) {
                $notify[] = 'Invalid question';
                return responseError('Invalid question', $notify);
            }
            $question->options()->delete();
        } else {
            $question = new QuizQuestion();
        }

        $question->quiz_id = $quiz->id;
        $question->question = $request->question;

        $question->save();

        foreach ($request->answers as  $ans) {
            if ($ans['answer']) {
                try {
                    $options = new QuizOption();
                    $options->quiz_id = $quiz->id;
                    $options->quiz_question_id = $question->id;
                    $options->option = $ans['answer'];
                    $options->explanation = $ans['explanation'];
                    $options->answer = $ans['correct'] ? 1 : 0;
                    $options->save();
                } catch (\Throwable $th) {
                    $notify[] = 'Error while saving option: ' . $ans['answer'];
                    return responseError('options_added_error', $notify);
                   
                }
            }
        }

        $notify[] = 'Quiz options added successfully';
        return responseSuccess('options_added', $notify, [
            'question' => $question->load('options'),
            'quiz' => $quiz

        ]);
    }






    public function saveResources(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'section_id' => 'required|integer',
            'lecture_id' => 'required|integer',
            'resources' => ['required', new FileTypeValidate(['mp4', 'mkv', 'webm', 'mov', 'vob', 'avi', 'mpeg', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'pdf', 'ppt', 'pptx','zip','rar'])]
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();

        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('Invalid course', $notify);
        }

        $courseSection = CourseSection::where('course_id', $course->id)->find($request->section_id);

        if (!$courseSection) {
            $notify[] = 'Invalid section';
            return responseError('Invalid section', $notify);
        }

        $courseLecture = CourseLecture::where('course_id', $course->id)->where('course_section_id', $courseSection->id)->find($request->lecture_id);
        if (!$courseLecture) {
            $notify[] = 'Invalid lecture';
            return responseError('Invalid lecture', $notify);
        }


        $resources = new CourseResource();
        $resources->course_id = $course->id;
        $resources->course_section_id = $courseSection->id;
        $resources->course_lecture_id = $courseLecture->id;
        if ($request->resources) {
            $originalName = trim((string) $request->resources->getClientOriginalName());
            $extension = strtolower((string) $request->resources->getClientOriginalExtension());
            $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^A-Za-z0-9\-_]/', '_', $nameWithoutExt ?: 'resource');
            $fileName = $safeBase . '_' . uniqid() . ($extension ? '.' . $extension : '');
            try {
                $resources->file = fileUploader($request->resources, getFilePath('resources'), old: $resources->file, filename: $fileName);
            } catch (\Throwable $e) {
                $notify[] = 'Resource upload failed. Please check S3 bucket permissions and CORS settings.';
                return responseError('upload_failed', $notify);
            }
            if (Schema::hasColumn('course_resources', 'original_name')) {
                $resources->original_name = $originalName ?: $fileName;
            }
        }
        $resources->save();

        $notify[] = 'Resource added successfully';
        return responseSuccess('resource_added', $notify, [
            'resource' => $resources
        ]);
    }




    public function checkSlug(Request $request)
    {

        $exist = Course::where('slug', $request->slug)->exists();
        return response()->json([
            'exists' => $exist
        ]);
    }

    public function storeLiveBatch(Request $request, $slug, TeacherNotificationService $teacherNotificationService)
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'nullable|integer',
            'class_type' => 'required|in:group',
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'meeting_start_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'meeting_end_time' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'class_duration' => 'nullable|integer|min:1|max:480',
            'capacity' => 'nullable|integer|min:1',
            'lecture_ids' => 'nullable|array',
            'lecture_ids.*' => 'integer',
            'lecture_schedule' => 'nullable|array',
            'lecture_schedule.*' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $normalizedMeetingStartTime = date('H:i', strtotime((string) $request->meeting_start_time));
        $normalizedMeetingEndTime = date('H:i', strtotime((string) $request->meeting_end_time));
        if ($normalizedMeetingStartTime < '09:00' || $normalizedMeetingEndTime > '21:00') {
            $notify[] = 'Meeting time must be between 09:00 and 21:00.';
            return responseError('validation_error', $notify);
        }
        if (!preg_match('/^\d{2}:(00|30)$/', $normalizedMeetingStartTime) || !preg_match('/^\d{2}:(00|30)$/', $normalizedMeetingEndTime)) {
            $notify[] = 'Meeting time must be in 30-minute intervals.';
            return responseError('validation_error', $notify);
        }
        if (strtotime($normalizedMeetingEndTime) <= strtotime($normalizedMeetingStartTime)) {
            $notify[] = 'Meeting end time must be after start time.';
            return responseError('validation_error', $notify);
        }

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();
        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $isNewBatch = !$request->batch_id;
        if ($request->batch_id) {
            $batch = CourseLiveBatch::where('course_id', $course->id)->find($request->batch_id);
            if (!$batch) {
                $notify[] = 'Batch not found';
                return responseError('batch_not_found', $notify);
            }
        } else {
            $batch = new CourseLiveBatch();
            $batch->course_id = $course->id;
            $batch->created_by_instructor_id = auth()->id();
        }

        $start = strtotime($normalizedMeetingStartTime);
        $end = strtotime($normalizedMeetingEndTime);
        $duration = (int) (($end - $start) / 60);
        $lectureIds = collect($request->lecture_ids ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $lectureSchedule = collect($request->lecture_schedule ?? [])
            ->mapWithKeys(function ($date, $lectureId) {
                if (!$date) {
                    return [];
                }

                return [(int) $lectureId => Carbon::parse($date)->toDateString()];
            })
            ->all();

        $sessionCount = $lectureIds ? count($lectureIds) : (int) $course->liveLectures()->count();

        if ($sessionCount < 1) {
            $notify[] = 'Please add lectures and mark at least one lecture as live class before creating Zoom batch.';
            return responseError('live_lecture_required', $notify);
        }

        if ($lectureIds) {
            $validLectureCount = $course->liveLectures()->whereIn('id', $lectureIds)->count();
            if ($validLectureCount !== count($lectureIds)) {
                $notify[] = 'Selected lectures are not valid live lectures for this course.';
                return responseError('invalid_live_lectures', $notify);
            }
        }

        if (!empty($lectureSchedule)) {
            $targetLectureIds = $lectureIds ?: $course->liveLectures()->pluck('id')->map(fn ($id) => (int) $id)->all();
            $missingLectureSchedule = collect($targetLectureIds)
                ->filter(fn ($lectureId) => empty($lectureSchedule[(int) $lectureId]))
                ->values();

            if ($missingLectureSchedule->isNotEmpty()) {
                $notify[] = 'Please select schedule date for each selected live lecture.';
                return responseError('lecture_schedule_required', $notify);
            }
        }

        $batch->class_type = $request->class_type;
        $batch->title = $request->title;
        $batch->zoom_meeting_link = null;
        $batch->batch_number = null;
        $batch->start_date = $request->start_date;
        $dynamicEndDate = !empty($lectureSchedule) ? collect($lectureSchedule)->max() : null;
        $batch->end_date = $request->end_date ?: ($dynamicEndDate ?: Carbon::parse($request->start_date)->addDays($sessionCount - 1)->toDateString());
        $batch->meeting_start_time = $normalizedMeetingStartTime;
        $batch->meeting_end_time = $normalizedMeetingEndTime;
        $batch->class_duration = $request->class_duration ?: max($duration, 0);
        $batch->capacity = $request->capacity;
        $batch->lecture_ids = $lectureIds ?: null;
        $batch->status = Status::ENABLE;
        $batch->save();

        try {
            $zoom = app(LmsZoomService::class);
            if (!$isNewBatch) {
                $zoom->deleteSessionsForBatch($batch, auth()->id(), 'teacher');
            }
            $zoom->generateBatchSessions($course, $batch, $lectureSchedule);
        } catch (\Throwable $throwable) {
            if ($isNewBatch) {
                $batch->delete();
            }

            $notify[] = $throwable->getMessage();
            return responseError('zoom_schedule_conflict', $notify);
        }

        if ($isNewBatch) {
            $teacher = auth()->user();
            if ($teacher && !empty($teacher->email)) {
                $teacherNotificationService->notifyBatchCreated(
                    $teacher,
                    $batch->loadMissing('sessions'),
                    $course
                );
            }
        }

        $notify[] = 'Live batch saved successfully';
        return responseSuccess('live_batch_saved', $notify, [
            'batch' => $batch->load('sessions'),
        ]);
    }

    public function liveBatches($slug)
    {
        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();
        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $batches = CourseLiveBatch::where('course_id', $course->id)
            ->with(['teachers.teacher'])
            ->orderBy('id', 'desc')
            ->get();

        $notify[] = 'Live batches data';
        return responseSuccess('live_batches', $notify, [
            'batches' => $batches,
        ]);
    }

    public function deleteLiveBatch($id, TeacherNotificationService $teacherNotificationService)
    {
        $batch = CourseLiveBatch::whereHas('course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->with(['course', 'teachers.teacher', 'bookings'])->find($id);

        if (!$batch) {
            $notify[] = 'Batch not found';
            return responseError('batch_not_found', $notify);
        }

        $teachers = $batch->teachers
            ->pluck('teacher')
            ->filter(fn ($teacher) => $teacher && !empty($teacher->email))
            ->unique('id')
            ->values();
        $cancellationReason = $this->resolveBatchCancellationReason($batch->bookings->count(), $batch->start_date, $batch->end_date);

        app(LmsZoomService::class)->deleteSessionsForBatch($batch, auth()->id(), 'teacher');
        \App\Models\TeacherUnavailabilitySlot::where('source_type', CourseLiveBatch::class)
            ->where('source_id', $batch->id)
            ->delete();

        $batch->delete();

        foreach ($teachers as $teacher) {
            $teacherNotificationService->notifyBatchCancelled($teacher, $batch, $batch->course, $cancellationReason);
        }

        $notify[] = 'Batch deleted successfully';
        return responseSuccess('batch_deleted', $notify);
    }

    private function resolveBatchCancellationReason(int $bookingCount, ?string $startDate, ?string $endDate): string
    {
        if ($bookingCount < 1) {
            return 'No student enrollments were received for this batch before the start date.';
        }

        $endDateValue = $endDate ?: $startDate;
        if ($endDateValue && Carbon::parse($endDateValue)->lt(now()->startOfDay())) {
            return 'The batch schedule has already passed and has been removed from the calendar.';
        }

        return 'The batch was cancelled and removed from the schedule.';
    }

    public function availableTeachers(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'start_time' => ['required', 'date_format:H:i', 'after_or_equal:09:00', 'before_or_equal:21:00', 'regex:/^\d{2}:(00|30)$/'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time', 'after_or_equal:09:00', 'before_or_equal:21:00', 'regex:/^\d{2}:(00|30)$/'],
            'batch_id' => 'nullable|integer',
            'exclude_meeting_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();
        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $teachers = LiveClassScheduler::availableCourseTeachers(
            $course->id,
            $request->start_date,
            $request->start_time,
            $request->end_time,
            $request->exclude_meeting_id ? (int) $request->exclude_meeting_id : null
        );

        if ($request->batch_id && !$request->exclude_meeting_id) {
            $teachers = collect($teachers)->filter(function ($teacher) use ($request) {
                return !LiveClassScheduler::hasBatchTeacherClash((int) $teacher->id, (int) $request->batch_id);
            })->values();
        } else {
            $teachers = collect($teachers)->values();
        }

        $notify[] = 'Available teachers';
        return responseSuccess('available_teachers', $notify, [
            'teachers' => $teachers,
        ]);
    }

    public function assignedLiveSessions()
    {
        $sessions = CourseLiveSession::with(['course', 'batch', 'user'])
            ->where('assigned_teacher_id', auth()->id())
            ->whereIn('status', ['scheduled', 'assigned', 'started'])
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        $notify[] = 'Assigned live sessions';
        return responseSuccess('assigned_live_sessions', $notify, [
            'sessions' => $sessions,
        ]);
    }

    public function startLiveSession($sessionId, LmsZoomService $zoom)
    {
        $session = CourseLiveSession::find($sessionId);

        if (!$session) {
            $notify[] = 'Live session not found.';
            return responseError('live_session_not_found', $notify);
        }

        $teacherId = (int) auth()->id();

        $isDirectlyAssigned = (int) $session->assigned_teacher_id === $teacherId;
        $hasCourseAllocation = CourseLiveTeacherAssignment::where('course_id', $session->course_id)
            ->where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->exists();
        $hasBatchAllocation = $session->batch_id
            ? CourseLiveTeacherAssignment::where('course_id', $session->course_id)
                ->where('batch_id', $session->batch_id)
                ->where('teacher_instructor_id', $teacherId)
                ->where('is_active', 1)
                ->exists()
            : false;

        if (!$isDirectlyAssigned && !$hasCourseAllocation && !$hasBatchAllocation) {
            $notify[] = 'You are not assigned to this live session.';
            return responseError('unauthorized_live_session', $notify);
        }

        if (!$isDirectlyAssigned) {
            $session->assigned_teacher_id = $teacherId;
            $session->status = in_array($session->status, ['scheduled', 'assigned', 'started']) ? $session->status : 'assigned';
            $session->save();
        }

        if (!$session->zoom_meeting_id) {
            $notify[] = 'Zoom meeting is not available for this session.';
            return responseError('zoom_meeting_missing', $notify);
        }

        $token = $zoom->createStartRedirectToken($session, $teacherId);

        $notify[] = 'Start class redirect created';
        return responseSuccess('zoom_start_redirect', $notify, [
            'redirect_url' => url('/api/teacher/course/live-session/' . $session->id . '/start-redirect/' . $token),
        ]);
    }

    public function startLiveSessionRedirect($sessionId, $token, LmsZoomService $zoom)
    {
        $payload = $zoom->consumeStartRedirectToken($token);

        if (!$payload || (int) ($payload['session_id'] ?? 0) !== (int) $sessionId) {
            abort(403);
        }

        $session = CourseLiveSession::findOrFail($sessionId);
        $teacherId = (int) ($payload['teacher_id'] ?? 0);

        if ((int) $session->assigned_teacher_id !== $teacherId || !$session->zoom_meeting_id) {
            abort(403);
        }

        $startUrl = $zoom->freshStartUrl($session->zoom_meeting_id);
        if (!$startUrl) {
            abort(409);
        }

        $zoom->markStarted($session, $teacherId);

        return redirect()->away($startUrl);
    }


    public function courseDetails($slug)
    {
        $teacherId = (int) auth()->id();

        $course = Course::with(
            'sections',
            'sections.curriculums',
            'sections.curriculums.lectures',
            'sections.curriculums.quizzes',
            'sections.curriculums.quizzes.questions.options',
            'objects',
            'requirements',
            'contents',
            'sections.curriculums.lectures.resources',
            'liveBatches.teachers.teacher',
            'liveBatches.sessions',
            'liveBatches.sessions.lecture',
            'zoomBatches.meetings.occurrences'
        )
            ->where('slug', $slug)
            ->where(function ($query) use ($teacherId) {
                $query->whereHas('liveTeacherAssignments', function ($assignmentQuery) use ($teacherId) {
                    $assignmentQuery->where('teacher_instructor_id', $teacherId)->where('is_active', 1);
                })->orWhereHas('zoomBatches', function ($zoomBatchQuery) use ($teacherId) {
                    $zoomBatchQuery->where('teacher_instructor_id', $teacherId);
                });
            })
            ->first();

        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $batchEnrollmentCounts = CourseLiveBooking::where('course_id', $course->id)
            ->whereHas('purchase', function ($query) {
                $query->where('payment_status', Status::PAYMENT_SUCCESS);
            })
            ->whereNotNull('batch_id')
            ->selectRaw('batch_id, COUNT(DISTINCT user_id) as students_count')
            ->groupBy('batch_id')
            ->pluck('students_count', 'batch_id');

        $zoomAllocatedBatches = collect($course->zoomBatches ?? [])
            ->filter(fn ($batch) => (int) ($batch->teacher_instructor_id ?? 0) === $teacherId)
            ->values()
            ->map(function ($batch) use ($batchEnrollmentCounts) {
                $occurrences = collect($batch->meetings ?? [])
                    ->flatMap(function ($meeting) {
                        return collect($meeting->occurrences ?? [])
                            ->filter(fn ($occurrence) => ($occurrence->status ?? 'scheduled') !== 'cancelled')
                            ->map(function ($occurrence) use ($meeting) {
                                return (object) [
                                    'scheduled_at' => $occurrence->start_time,
                                    'duration_minutes' => (int) ($occurrence->duration_minutes ?? $meeting->duration_minutes ?? 0),
                                ];
                            });
                    })
                    ->sortBy('scheduled_at')
                    ->values();

                $firstOccurrence = $occurrences->first();
                $lastOccurrence = $occurrences->last();
                $startDate = $firstOccurrence?->scheduled_at ? Carbon::parse($firstOccurrence->scheduled_at)->toDateString() : null;
                $endDate = $lastOccurrence?->scheduled_at ? Carbon::parse($lastOccurrence->scheduled_at)->toDateString() : null;
                $meetingStartTime = $firstOccurrence?->scheduled_at ? Carbon::parse($firstOccurrence->scheduled_at)->format('H:i:s') : null;
                $meetingEndTime = null;
                if ($firstOccurrence?->scheduled_at) {
                    $meetingEndTime = Carbon::parse($firstOccurrence->scheduled_at)
                        ->addMinutes((int) ($firstOccurrence->duration_minutes ?? 0))
                        ->format('H:i:s');
                }

                return (object) [
                    'id' => (int) $batch->id,
                    'title' => (string) $batch->title,
                    'source' => 'zoom',
                    'students_count' => (int) ($batchEnrollmentCounts[$batch->id] ?? 0),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'meeting_start_time' => $meetingStartTime,
                    'meeting_end_time' => $meetingEndTime,
                    'class_type' => 'group',
                    'sessions' => $occurrences,
                ];
            });

        $hasCourseLevelAllocation = CourseLiveTeacherAssignment::where('course_id', $course->id)
            ->whereNull('batch_id')
            ->where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->exists();

        $allocatedBatchesQuery = CourseLiveBatch::where('course_id', $course->id)
            ->whereHas('teachers', function ($query) use ($teacherId) {
                $query->where('teacher_instructor_id', $teacherId)->where('is_active', 1);
            });

        if ($hasCourseLevelAllocation) {
            $allocatedBatchesQuery = CourseLiveBatch::where('course_id', $course->id);
        }

        $liveAllocatedBatches = collect(
            $allocatedBatchesQuery
                ->select('id', 'title', 'start_date', 'end_date', 'meeting_start_time', 'meeting_end_time', 'class_type')
                ->orderBy('id')
                ->get()
                ->map(function ($batch) use ($batchEnrollmentCounts) {
                    $batch->source = 'live';
                    $batch->students_count = (int) ($batchEnrollmentCounts[$batch->id] ?? 0);
                    return $batch;
                })
                ->all()
        );

        $allocatedBatches = $liveAllocatedBatches
            ->merge($zoomAllocatedBatches)
            ->unique(fn ($batch) => ($batch->source ?? 'live') . ':' . (int) $batch->id)
            ->values();

        $course->setAttribute('live_batches', $allocatedBatches->values());

        $lectureReleaseMap = CourseLectureBatchRelease::where('course_id', $course->id)
            ->whereIn('batch_id', $allocatedBatches->pluck('id')->all())
            ->get(['course_lecture_id', 'batch_id', 'is_released'])
            ->mapWithKeys(function ($item) {
                return [((int) $item->course_lecture_id) . ':' . ((int) $item->batch_id) => (bool) $item->is_released];
            });

        $notify[] = 'Course details fetched successfully';
        return responseSuccess('course_details', $notify, [
            'course' => $course,
            'allocated_batches' => $allocatedBatches,
            'lecture_release_map' => $lectureReleaseMap,

        ]);
    }


    public function toggleLectureRelease(Request $request, $lectureId)
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|integer',
            'batch_source' => 'nullable|in:live,zoom',
            'is_released' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $teacherId = (int) auth()->id();
        $lecture = CourseLecture::find($lectureId);
        if (!$lecture) {
            $notify[] = 'Invalid lecture';
            return responseError('lecture_not_found', $notify);
        }

        $batchSource = (string) ($request->batch_source ?? '');
        $isZoomBatch = false;
        $batch = null;
        if ($batchSource === 'zoom') {
            $batch = CourseZoomBatch::where('id', (int) $request->batch_id)
                ->where('course_id', $lecture->course_id)
                ->first();
            $isZoomBatch = (bool) $batch;
        } elseif ($batchSource === 'live') {
            $batch = CourseLiveBatch::where('id', (int) $request->batch_id)
                ->where('course_id', $lecture->course_id)
                ->first();
        } else {
            $batch = CourseLiveBatch::where('id', (int) $request->batch_id)
                ->where('course_id', $lecture->course_id)
                ->first();
            if (!$batch) {
                $batch = CourseZoomBatch::where('id', (int) $request->batch_id)
                    ->where('course_id', $lecture->course_id)
                    ->first();
                $isZoomBatch = (bool) $batch;
            }
        }

        if (!$batch) {
            $notify[] = 'Invalid batch';
            return responseError('batch_not_found', $notify);
        }

        $hasBatchAllocation = CourseLiveTeacherAssignment::where('course_id', $lecture->course_id)
            ->where('batch_id', $batch->id)
            ->where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->exists();
        $hasCourseAllocation = CourseLiveTeacherAssignment::where('course_id', $lecture->course_id)
            ->whereNull('batch_id')
            ->where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->exists();

        $hasZoomBatchOwnership = $isZoomBatch
            && (int) $batch->teacher_instructor_id === $teacherId;

        if (!$hasBatchAllocation && !$hasCourseAllocation && !$hasZoomBatchOwnership) {
            $notify[] = 'Batch not allocated to you';
            return responseError('batch_not_allocated', $notify);
        }

        $release = CourseLectureBatchRelease::updateOrCreate(
            [
                'course_lecture_id' => (int) $lecture->id,
                'batch_id' => (int) $batch->id,
            ],
            [
                'course_id' => (int) $lecture->course_id,
                'course_section_id' => (int) $lecture->course_section_id,
                'is_released' => (bool) $request->is_released,
                'released_by_type' => 'teacher',
                'released_by_id' => $teacherId,
            ]
        );

        $notify[] = $release->is_released
            ? 'Lecture released to selected batch successfully'
            : 'Lecture hidden from selected batch successfully';

        return responseSuccess('lecture_release_updated', $notify, [
            'lecture_id' => (int) $lecture->id,
            'batch_id' => (int) $batch->id,
            'is_released' => (bool) $release->is_released,
        ]);
    }

    public function toggleSectionRelease(Request $request, $sectionId)
    {
        $validator = Validator::make($request->all(), [
            'is_released_to_students' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $section = CourseSection::where('id', $sectionId)
            ->whereHas('course.liveTeacherAssignments', function ($query) {
                $query->where('teacher_instructor_id', auth()->id())
                    ->where('is_active', 1);
            })
            ->first();

        if (!$section) {
            $notify[] = 'Invalid section';
            return responseError('section_not_found', $notify);
        }

        $section->is_released_to_students = (bool) $request->is_released_to_students;
        $section->save();

        $notify[] = $section->is_released_to_students
            ? 'Section released to students successfully'
            : 'Section hidden from students successfully';

        return responseSuccess('section_release_updated', $notify, [
            'section_id' => $section->id,
            'is_released_to_students' => (bool) $section->is_released_to_students,
        ]);
    }
    
    
       public function downloadResource(Request $request, $resourceId)
    {
        $teacherId = (int) auth()->id();

        $resource = CourseResource::whereHas('course.liveTeacherAssignments', function ($query) use ($teacherId) {
                $query->where('teacher_instructor_id', $teacherId)
                    ->where('is_active', 1);
            })
            ->find($resourceId);

        if (!$resource) {
            $notify[] = 'Invalid Resource';
            return responseError('resource_not_found', $notify);
        }

        $course = $resource->course;

        if (!$course) {
            $notify[] = 'Invalid Course';
            return responseError('course_not_found', $notify);
        }

  

        $file = $resource->file;
        $path = getFilePath('resources');
        $fullPath = $path . '/' . $file;
        $cloudDisk = env('UPLOAD_DISK', 's3');
        $isCloud = $cloudDisk === 's3';
        $cloudKey = ltrim($fullPath, '/');

        if ($isCloud && !Storage::disk($cloudDisk)->exists($cloudKey)) {
            $notify[] = 'resources not found';
            return responseError('resources_not_found', $notify);
        }

        if (!$isCloud && !file_exists($fullPath)) {

            $notify[] = 'resources not found';
            return responseError('resources_not_found', $notify);
        }


        $mimetype = $isCloud
            ? (Storage::disk($cloudDisk)->mimeType($cloudKey) ?: 'application/octet-stream')
            : mime_content_type($fullPath);
        $originalName = (string) ($resource->original_name ?? '');
        $downloadName = trim($originalName) !== '' ? $originalName : basename($file);

        $downloadExt = strtolower((string) pathinfo($downloadName, PATHINFO_EXTENSION));
        if ($downloadExt === '') {
            $storedExt = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
            $mimeToExt = [
                'application/pdf' => 'pdf',
                'video/mp4' => 'mp4',
                'video/webm' => 'webm',
                'video/quicktime' => 'mov',
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'application/zip' => 'zip',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
                'application/vnd.ms-powerpoint' => 'ppt',
            ];
            $resolvedExt = $storedExt ?: ($mimeToExt[$mimetype] ?? '');
            if ($resolvedExt !== '') {
                $downloadName .= '.' . $resolvedExt;
            }
        }

        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET,');
            header('Access-Control-Allow-Headers: Content-Type');
        }

        $isPreview = (int) $request->query('preview', 0) === 1;
        $contentDisposition = $isPreview ? 'inline' : 'attachment';

        if ($isCloud) {
            return response()->stream(function () use ($cloudDisk, $cloudKey) {
                echo Storage::disk($cloudDisk)->get($cloudKey);
            }, 200, [
                'Content-Type' => $mimetype ?: 'application/octet-stream',
                'Content-Disposition' => $contentDisposition . '; filename="' . addslashes($downloadName) . '"',
            ]);
        }

        if ($isPreview) {
            return response()->file($fullPath, [
                'Content-Type' => $mimetype ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . addslashes($downloadName) . '"',
            ]);
        }

        return response()->download($fullPath, $downloadName, [
            'Content-Type' => $mimetype ?: 'application/octet-stream',
        ]);
    }
    
    
    
    
}
