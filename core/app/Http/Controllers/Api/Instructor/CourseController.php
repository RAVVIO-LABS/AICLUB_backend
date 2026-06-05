<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseLecture;
use App\Models\CourseLiveTeacherAssignment;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\Curriculum;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Lib\LiveClassScheduler;
use App\Services\TeacherNotificationService;
use App\Services\Zoom\LmsZoomService;
use App\Rules\FileTypeValidate;
use App\Traits\CourseDeleteManage;
use App\Traits\CourseManage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class CourseController extends Controller
{

    use CourseDeleteManage, CourseManage;


    public function list()
    {

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
            ->where('instructor_id', auth()->id())
            ->withSum(['purchases' => function ($q) {
                $q->where('payment_status', Status::PAYMENT_SUCCESS);
            }], 'amount')
            ->paginate(getPaginate());
      
        $notify[] = 'Courses page data';
        return responseSuccess('courses', $notify, [
            'courses' => $courses
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
            'video_file' => ['nullable', new FileTypeValidate(['mp4', 'mkv', 'webm', 'mov', 'vob', 'avi', 'mpeg']), 'required_without:video_uploaded_filename'],
            'video_uploaded_filename' => 'nullable|string|max:255|required_without:video_file',
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
        } elseif ($request->filled('video_uploaded_filename')) {
            $uploadedName = basename((string) $request->video_uploaded_filename);
            $ext = strtolower((string) pathinfo($uploadedName, PATHINFO_EXTENSION));

            if (!in_array($ext, ['mp4', 'mkv', 'webm', 'mov', 'vob', 'avi', 'mpeg'], true)) {
                return responseError('validation_error', ['video_uploaded_filename' => ['Invalid video extension']]);
            }

            $lecture->file = $uploadedName;
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
        $allowedExt = ['mp4', 'mkv', 'webm', 'mov', 'vob', 'avi', 'mpeg', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'pdf', 'ppt', 'pptx', 'zip', 'rar'];

        $validator = Validator::make($request->all(), [
            'section_id' => 'required|integer',
            'lecture_id' => 'required|integer',
            'resources' => ['nullable', new FileTypeValidate($allowedExt), 'required_without_all:resource_uploaded_filename,links'],
            'resource_uploaded_filename' => 'nullable|string|max:255|required_without_all:resources,links',
            'resource_original_name' => 'nullable|string|max:255',
            'links' => 'nullable|array|required_without_all:resources,resource_uploaded_filename',
            'links.*.title' => 'required_with:links|string|max:255',
            'links.*.url' => 'required_with:links|url|max:2000',
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

        $createdResources = collect();

        if ($request->hasFile('resources')) {
            $resource = new CourseResource();
            $resource->course_id = $course->id;
            $resource->course_section_id = $courseSection->id;
            $resource->course_lecture_id = $courseLecture->id;

            $originalName = trim((string) $request->resources->getClientOriginalName());
            $extension = strtolower((string) $request->resources->getClientOriginalExtension());
            $nameWithoutExt = pathinfo($originalName, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^A-Za-z0-9\-_]/', '_', $nameWithoutExt ?: 'resource');
            $fileName = $safeBase . '_' . uniqid() . ($extension ? '.' . $extension : '');

            try {
                $resource->file = fileUploader($request->resources, getFilePath('resources'), old: $resource->file, filename: $fileName);
            } catch (\Throwable $e) {
                $notify[] = 'Resource upload failed. Please check S3 bucket permissions and CORS settings.';
                return responseError('upload_failed', $notify);
            }

            if (Schema::hasColumn('course_resources', 'original_name')) {
                $resource->original_name = $originalName ?: $fileName;
            }

            $resource->save();
            $createdResources->push($resource);
        } elseif ($request->filled('resource_uploaded_filename')) {
            $uploadedName = basename((string) $request->resource_uploaded_filename);
            $ext = strtolower((string) pathinfo($uploadedName, PATHINFO_EXTENSION));

            if (!in_array($ext, $allowedExt, true)) {
                return responseError('validation_error', ['resource_uploaded_filename' => ['Invalid resource extension']]);
            }

            $resource = new CourseResource();
            $resource->course_id = $course->id;
            $resource->course_section_id = $courseSection->id;
            $resource->course_lecture_id = $courseLecture->id;
            $resource->file = $uploadedName;

            if (Schema::hasColumn('course_resources', 'original_name')) {
                $resource->original_name = (string) ($request->resource_original_name ?: $uploadedName);
            }

            $resource->save();
            $createdResources->push($resource);
        }

        foreach (($request->links ?? []) as $link) {
            $title = trim((string) ($link['title'] ?? ''));
            $url = trim((string) ($link['url'] ?? ''));
            if ($title === '' || $url === '') continue;

            $resource = new CourseResource();
            $resource->course_id = $course->id;
            $resource->course_section_id = $courseSection->id;
            $resource->course_lecture_id = $courseLecture->id;

            if (Schema::hasColumn('course_resources', 'link_title')) {
                $resource->link_title = $title;
            }
            if (Schema::hasColumn('course_resources', 'link_url')) {
                $resource->link_url = $url;
            }

            $resource->save();
            $createdResources->push($resource);
        }

        $notify[] = 'Resource added successfully';
        return responseSuccess('resource_added', $notify, [
            'resource' => $createdResources->first(),
            'resources' => $createdResources->values(),
        ]);
    }
    public function zoomBatches($slug)
    {
        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();
        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $batches = \App\Models\CourseZoomBatch::where('course_id', $course->id)
            ->with(['teacher:id,firstname,lastname,email'])
            ->orderByDesc('id')
            ->get();

        $teacherIds = \App\Models\CourseLiveTeacherAssignment::where('course_id', $course->id)
            ->where('is_active', 1)
            ->pluck('teacher_instructor_id')
            ->unique()
            ->values()
            ->all();

        $teachers = empty($teacherIds)
            ? collect()
            : \App\Models\Instructor::whereIn('id', $teacherIds)
                ->active()
                ->get(['id', 'firstname', 'lastname', 'email', 'username']);

        $notify[] = 'Zoom batches data';
        return responseSuccess('zoom_batches', $notify, [
            'batches' => $batches,
            'teachers' => $teachers,
        ]);
    }

    public function zoomMeetings($slug)
    {
        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();
        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $meetings = \App\Models\CourseZoomMeeting::where('course_id', $course->id)
            ->with([
                'batch:id,title,teacher_instructor_id',
                'batch.teacher:id,firstname,lastname,email',
                'occurrences.teacher:id,firstname,lastname,email',
            ])
            ->orderBy('start_time')
            ->get();

        $notify[] = 'Zoom meetings data';
        return responseSuccess('zoom_meetings', $notify, [
            'meetings' => $meetings,
        ]);
    }






    public function createZoomBatch(Request $request, $slug, TeacherNotificationService $teacherNotificationService)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'teacher_instructor_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();
        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $teacherId = $request->filled('teacher_instructor_id') ? (int) $request->teacher_instructor_id : null;
        $teacher = null;
        if ($teacherId) {
            $teacher = \App\Models\Instructor::active()->find($teacherId);
            if (!$teacher) {
                $notify[] = 'Invalid teacher';
                return responseError('teacher_not_found', $notify);
            }
        }

        $batch = \App\Models\CourseZoomBatch::create([
            'course_id' => (int) $course->id,
            'instructor_id' => (int) auth()->id(),
            'teacher_instructor_id' => $teacherId,
            'title' => (string) $request->title,
            'status' => 'active',
        ]);

        if ($teacher && !empty($teacher->email)) {
            $teacherNotificationService->notifyBatchCreated($teacher, $batch, $course);
        }

        $notify[] = 'Zoom batch created successfully';
        return responseSuccess('zoom_batch_created', $notify, [
            'batch' => $batch,
        ]);
    }

    public function deleteZoomBatch($id, TeacherNotificationService $teacherNotificationService)
    {
        $batch = \App\Models\CourseZoomBatch::whereHas('course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->with(['course', 'teacher'])->find($id);

        if (!$batch) {
            $notify[] = 'Batch not found';
            return responseError('batch_not_found', $notify);
        }

        $teacher = $batch->teacher;
        $cancellationReason = 'No student enrollments were received for this batch before the start date.';

        $meetingIds = \App\Models\CourseZoomMeeting::where('batch_id', $batch->id)->pluck('id')->all();
        if (!empty($meetingIds)) {
            \App\Models\CourseZoomMeetingOccurrence::whereIn('course_zoom_meeting_id', $meetingIds)->delete();
            \App\Models\CourseZoomMeeting::whereIn('id', $meetingIds)->delete();
        }

        \App\Models\TeacherUnavailabilitySlot::where('source_type', \App\Models\CourseZoomBatch::class)
            ->where('source_id', $batch->id)
            ->delete();

        $batch->delete();

        if ($teacher && !empty($teacher->email)) {
            $teacherNotificationService->notifyBatchCancelled($teacher, $batch, $batch->course, $cancellationReason);
        }

        $notify[] = 'Zoom batch deleted successfully';
        return responseSuccess('zoom_batch_deleted', $notify);
    }

    public function createZoomMeeting(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|integer',
            'teacher_instructor_id' => 'required|integer',
            'topic' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'duration' => 'required|integer|min:1|max:600',
            'recurrence' => 'nullable|array',
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $course = Course::where('instructor_id', auth()->id())->where('slug', $slug)->first();
        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $batch = \App\Models\CourseZoomBatch::where('course_id', $course->id)->find((int) $request->batch_id);
        if (!$batch) {
            $notify[] = 'Invalid batch';
            return responseError('batch_not_found', $notify);
        }
        $teacherId = (int) $request->teacher_instructor_id;
        $teacherExists = \App\Models\Instructor::active()->where('id', $teacherId)->exists();
        if (!$teacherExists) {
            $notify[] = 'Invalid teacher';
            return responseError('teacher_not_found', $notify);
        }
        $this->ensureTeacherAllocatedToCourse((int) $course->id, $teacherId);
        $this->ensureTeacherAllocatedToBatch((int) $course->id, (int) $batch->id, $teacherId);

        $meeting = new \App\Models\CourseZoomMeeting();
        $meeting->course_id = (int) $course->id;
        $meeting->instructor_id = (int) auth()->id();
        $meeting->batch_id = (int) $batch->id;
        $meeting->topic = trim((string) ($request->topic ?: $batch->title ?: 'Course Meeting'));
        $meeting->description = $request->description;
        $meeting->start_time = \Carbon\Carbon::parse($request->start_time)->toDateTimeString();
        $meeting->duration_minutes = (int) $request->duration;
        $meeting->timezone = (string) config('zoom.meeting.timezone', config('app.timezone', 'UTC'));
        $meeting->recurrence = $this->normalizeZoomRecurrence($request->recurrence, $meeting->start_time);
        $meeting->status = 'scheduled';
        $meeting->save();

        try {
            $zoom = app(LmsZoomService::class);
            $startAt = \Carbon\Carbon::parse($meeting->start_time, config('zoom.meeting.timezone', config('app.timezone', 'UTC')));
            $endAt = $startAt->copy()->addMinutes((int) $meeting->duration_minutes);
            $host = $zoom->selectHost($startAt, $endAt);

            if (!$host || empty($host['email'])) {
                \App\Models\CourseZoomMeetingOccurrence::where('course_zoom_meeting_id', $meeting->id)->delete();
                $meeting->delete();
                $notify[] = 'No zoom available for this time.';
                return responseError('zoom_host_not_available', $notify);
            }

            $zoomMeeting = $zoom->createMeeting([
                'host_email' => $host['email'],
                'meeting_name' => (string) ($meeting->topic ?: $batch->title ?: 'Course Meeting'),
                'start_time' => $startAt,
                'duration_minutes' => (int) $meeting->duration_minutes,
                'agenda' => (string) ($meeting->description ?: $course->title),
            ]);

            $meeting->zoom_meeting_id = (string) ($zoomMeeting['id'] ?? '');
            $meeting->zoom_join_url = $zoomMeeting['join_url'] ?? null;
            $meeting->zoom_host_email = $host['email'];
            $meeting->zoom_host_user_id = $zoomMeeting['host_id'] ?? $host['email'];
            $meeting->save();
        } catch (\Throwable $throwable) {
            \App\Models\CourseZoomMeetingOccurrence::where('course_zoom_meeting_id', $meeting->id)->delete();
            $meeting->delete();
            $notify[] = $throwable->getMessage();
            return responseError('zoom_meeting_create_failed', $notify);
        }

        $this->rebuildZoomMeetingOccurrences($meeting, $teacherId);

        $notify[] = 'Zoom meeting created successfully';
        return responseSuccess('zoom_meeting_created', $notify, [
            'meeting' => $meeting->load(['batch.teacher', 'occurrences']),
        ]);
    }

    public function updateZoomMeeting(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|integer',
            'teacher_instructor_id' => 'required|integer',
            'topic' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'start_time' => 'required|date',
            'duration' => 'required|integer|min:1|max:600',
            'recurrence' => 'nullable|array',
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $meeting = \App\Models\CourseZoomMeeting::whereHas('course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->find($id);

        if (!$meeting) {
            $notify[] = 'Meeting not found';
            return responseError('meeting_not_found', $notify);
        }

        $batch = \App\Models\CourseZoomBatch::where('course_id', (int) $meeting->course_id)->find((int) $request->batch_id);
        if (!$batch) {
            $notify[] = 'Invalid batch';
            return responseError('batch_not_found', $notify);
        }
        $teacherId = (int) $request->teacher_instructor_id;
        $teacherExists = \App\Models\Instructor::active()->where('id', $teacherId)->exists();
        if (!$teacherExists) {
            $notify[] = 'Invalid teacher';
            return responseError('teacher_not_found', $notify);
        }
        $this->ensureTeacherAllocatedToCourse((int) $meeting->course_id, $teacherId);
        $this->ensureTeacherAllocatedToBatch((int) $meeting->course_id, (int) $batch->id, $teacherId);

        $meeting->batch_id = (int) $batch->id;
        $meeting->topic = trim((string) ($request->topic ?: $batch->title ?: 'Course Meeting'));
        $meeting->description = $request->description;
        $meeting->start_time = \Carbon\Carbon::parse($request->start_time)->toDateTimeString();
        $meeting->duration_minutes = (int) $request->duration;
        $meeting->timezone = (string) config('zoom.meeting.timezone', config('app.timezone', 'UTC'));
        $meeting->recurrence = $this->normalizeZoomRecurrence($request->recurrence, $meeting->start_time);
        $meeting->status = 'scheduled';
        $meeting->save();

        $zoomUpdateSkipped = false;
        try {
            $zoom = app(LmsZoomService::class);
            $startAt = \Carbon\Carbon::parse($meeting->start_time, config('zoom.meeting.timezone', config('app.timezone', 'UTC')));
            $endAt = $startAt->copy()->addMinutes((int) $meeting->duration_minutes);

            if (!empty($meeting->zoom_meeting_id)) {
                $zoom->updateMeeting($meeting->zoom_meeting_id, [
                    'meeting_name' => (string) ($meeting->topic ?: $batch->title ?: 'Course Meeting'),
                    'start_time' => $startAt,
                    'duration_minutes' => (int) $meeting->duration_minutes,
                    'agenda' => (string) ($meeting->description ?: optional($meeting->course)->title),
                ]);

                try {
                    $details = $zoom->meetingDetails($meeting->zoom_meeting_id);
                    $meeting->zoom_join_url = $details['join_url'] ?? $meeting->zoom_join_url;
                    $meeting->zoom_host_email = $details['host_email'] ?? $meeting->zoom_host_email;
                    $meeting->zoom_host_user_id = $details['host_id'] ?? $meeting->zoom_host_user_id;
                    $meeting->save();
                } catch (\Throwable $ignored) {
                    // Keep update successful even if Zoom read scope is missing.
                }
            } else {
                $host = $zoom->selectHost($startAt, $endAt);
                if (!$host || empty($host['email'])) {
                    $notify[] = 'No zoom available for this time.';
                    return responseError('zoom_host_not_available', $notify);
                }

                $zoomMeeting = $zoom->createMeeting([
                    'host_email' => $host['email'],
                    'meeting_name' => (string) ($meeting->topic ?: $batch->title ?: 'Course Meeting'),
                    'start_time' => $startAt,
                    'duration_minutes' => (int) $meeting->duration_minutes,
                    'agenda' => (string) ($meeting->description ?: optional($meeting->course)->title),
                ]);

                $meeting->zoom_meeting_id = (string) ($zoomMeeting['id'] ?? '');
                $meeting->zoom_join_url = $zoomMeeting['join_url'] ?? null;
                $meeting->zoom_host_email = $host['email'];
                $meeting->zoom_host_user_id = $zoomMeeting['host_id'] ?? $host['email'];
                $meeting->save();
            }
        } catch (\Throwable $throwable) {
            if (!empty($meeting->zoom_meeting_id) && str_contains(strtolower($throwable->getMessage()), 'does not contain scope')) {
                $zoomUpdateSkipped = true;
            } else {
                $notify[] = $throwable->getMessage();
                return responseError('zoom_meeting_update_failed', $notify);
            }
        }

        $this->rebuildZoomMeetingOccurrences($meeting, $teacherId);

        if ($zoomUpdateSkipped) {
            $notify[] = 'Zoom app is missing meeting update scope. Meeting was updated in system, but Zoom meeting time/details could not be updated via API.';
        }
        $notify[] = 'Zoom meeting updated successfully';
        return responseSuccess('zoom_meeting_updated', $notify, [
            'meeting' => $meeting->load(['batch.teacher', 'occurrences']),
        ]);
    }

    public function deleteZoomMeeting($id)
    {
        $meeting = \App\Models\CourseZoomMeeting::whereHas('course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->find($id);

        if (!$meeting) {
            $notify[] = 'Meeting not found';
            return responseError('meeting_not_found', $notify);
        }

        \App\Models\CourseZoomMeetingOccurrence::where('course_zoom_meeting_id', $meeting->id)->delete();
        $meeting->delete();

        $notify[] = 'Zoom meeting deleted successfully';
        return responseSuccess('zoom_meeting_deleted', $notify);
    }

    public function updateZoomMeetingOccurrence(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'teacher_instructor_id' => 'nullable|integer',
            'start_time' => 'required|date',
            'duration' => 'required|integer|min:1|max:600',
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $occurrence = \App\Models\CourseZoomMeetingOccurrence::whereHas('meeting.course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->find($id);

        if (!$occurrence) {
            $notify[] = 'Meeting occurrence not found';
            return responseError('occurrence_not_found', $notify);
        }

        $teacherId = $request->teacher_instructor_id ? (int) $request->teacher_instructor_id : null;
        if ($teacherId) {
            $teacherExists = \App\Models\Instructor::active()->where('id', $teacherId)->exists();
            if (!$teacherExists) {
                $notify[] = 'Invalid teacher';
                return responseError('teacher_not_found', $notify);
            }
            $this->ensureTeacherAllocatedToCourse((int) optional($occurrence->meeting)->course_id, $teacherId);
            $meetingBatchId = (int) (optional($occurrence->meeting)->batch_id ?: 0);
            $meetingCourseId = (int) (optional($occurrence->meeting)->course_id ?: 0);
            if ($meetingBatchId > 0 && $meetingCourseId > 0) {
                $this->ensureTeacherAllocatedToBatch($meetingCourseId, $meetingBatchId, $teacherId);
            }
        }

        $occurrence->title = trim((string) ($request->title ?: $occurrence->title ?: optional($occurrence->meeting)->topic ?: 'Meeting'));
        $occurrence->teacher_instructor_id = $teacherId;
        $occurrence->start_time = \Carbon\Carbon::parse($request->start_time)->toDateTimeString();
        $occurrence->duration_minutes = (int) $request->duration;
        $occurrence->status = 'scheduled';
        $occurrence->save();

        $notify[] = 'Meeting occurrence updated successfully';
        return responseSuccess('zoom_meeting_occurrence_updated', $notify, [
            'occurrence' => $occurrence,
        ]);
    }

    public function cancelZoomMeetingOccurrence($id)
    {
        $occurrence = \App\Models\CourseZoomMeetingOccurrence::whereHas('meeting.course', function ($query) {
            $query->where('instructor_id', auth()->id());
        })->find($id);

        if (!$occurrence) {
            $notify[] = 'Meeting occurrence not found';
            return responseError('occurrence_not_found', $notify);
        }

        $occurrence->status = 'cancelled';
        $occurrence->save();

        $notify[] = 'Meeting occurrence cancelled successfully';
        return responseSuccess('zoom_meeting_occurrence_cancelled', $notify);
    }

    private function ensureTeacherAllocatedToCourse(int $courseId, int $teacherId): void
    {
        if ($courseId < 1 || $teacherId < 1) {
            return;
        }

        $assignment = CourseLiveTeacherAssignment::where('course_id', $courseId)
            ->whereNull('batch_id')
            ->where('teacher_instructor_id', $teacherId)
            ->first();

        if ($assignment) {
            if ((int) $assignment->is_active !== 1) {
                $assignment->is_active = 1;
                $assignment->save();
            }
            return;
        }

        CourseLiveTeacherAssignment::create([
            'course_id' => $courseId,
            'batch_id' => null,
            'teacher_instructor_id' => $teacherId,
            'assigned_by_admin_id' => null,
            'is_active' => 1,
        ]);
    }

    private function ensureTeacherAllocatedToBatch(int $courseId, int $batchId, int $teacherId): void
    {
        if ($courseId < 1 || $batchId < 1 || $teacherId < 1) {
            return;
        }

        $batch = \App\Models\CourseZoomBatch::with('course')->where('id', $batchId)
            ->where('course_id', $courseId)
            ->first();

        if (!$batch) {
            return;
        }

        $batch->teacher_instructor_id = $teacherId;
        $batch->save();

        $teacher = \App\Models\Instructor::find($teacherId);
        if ($teacher) {
            app(TeacherNotificationService::class)->notifyBatchAssigned($teacher, $batch, $batch->course);
        }

        CourseLiveTeacherAssignment::where('course_id', $courseId)
            ->where('batch_id', $batchId)
            ->where('teacher_instructor_id', '!=', $teacherId)
            ->where('is_active', 1)
            ->update(['is_active' => 0]);

        CourseLiveTeacherAssignment::updateOrCreate(
            [
                'course_id' => $courseId,
                'batch_id' => $batchId,
                'teacher_instructor_id' => $teacherId,
            ],
            [
                'assigned_by_admin_id' => null,
                'is_active' => 1,
            ]
        );
    }

    public function availableTeachers(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'start_time' => ['required', 'date_format:H:i', 'after_or_equal:09:00', 'before_or_equal:21:00'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time', 'after_or_equal:09:00', 'before_or_equal:23:59'],
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

    private function normalizeZoomRecurrence($recurrenceInput, $startTime): array
    {
        $input = is_array($recurrenceInput) ? $recurrenceInput : [];
        $type = strtolower((string) ($input['type'] ?? 'none'));
        if (!in_array($type, ['none', 'daily', 'weekly', 'monthly'], true)) {
            $type = 'none';
        }

        $interval = max(1, (int) ($input['repeat_interval'] ?? 1));
        $weeklyDays = collect($input['weekly_days'] ?? [])->map(fn ($v) => (int) $v)->filter(fn ($d) => $d >= 1 && $d <= 7)->unique()->values()->all();
        if ($type === 'weekly' && empty($weeklyDays)) {
            $weeklyDays = [((int) \Carbon\Carbon::parse($startTime)->dayOfWeek) + 1];
        }

        $endMode = strtolower((string) ($input['end_mode'] ?? 'none'));
        if (!in_array($endMode, ['none', 'date', 'occurrences'], true)) {
            $endMode = 'none';
        }

        $endTimes = max(1, (int) ($input['occurrences'] ?? 1));
        $endDateTime = null;
        if ($endMode === 'date' && !empty($input['end_date'])) {
            $endDateTime = \Carbon\Carbon::parse($input['end_date'])->endOfDay()->toDateTimeString();
        }

        return [
            'label_type' => $type,
            'repeat_interval' => $interval,
            'weekly_days' => implode(',', $weeklyDays),
            'end_mode' => $endMode,
            'end_times' => $endMode === 'occurrences' ? $endTimes : null,
            'end_date_time' => $endDateTime,
        ];
    }

    private function rebuildZoomMeetingOccurrences(\App\Models\CourseZoomMeeting $meeting, int $defaultTeacherId = 0): void
    {
        \App\Models\CourseZoomMeetingOccurrence::where('course_zoom_meeting_id', $meeting->id)->delete();

        $start = \Carbon\Carbon::parse($meeting->start_time);
        $duration = max(1, (int) $meeting->duration_minutes);
        $recurrence = is_array($meeting->recurrence) ? $meeting->recurrence : [];

        $type = strtolower((string) ($recurrence['label_type'] ?? 'none'));
        $interval = max(1, (int) ($recurrence['repeat_interval'] ?? 1));
        $endMode = strtolower((string) ($recurrence['end_mode'] ?? 'none'));
        $endTimes = max(1, (int) ($recurrence['end_times'] ?? 1));
        $endDate = !empty($recurrence['end_date_time']) ? \Carbon\Carbon::parse($recurrence['end_date_time']) : null;

        $weeklyDays = collect(explode(',', (string) ($recurrence['weekly_days'] ?? '')))
            ->map(fn ($v) => (int) trim($v))
            ->filter(fn ($d) => $d >= 1 && $d <= 7)
            ->unique()
            ->values()
            ->all();

        $occurrences = [];

        if ($type === 'none') {
            $occurrences[] = $start->copy();
        } elseif ($type === 'daily') {
            $cursor = $start->copy();
            $limit = $endMode === 'occurrences' ? $endTimes : 60;
            while (count($occurrences) < $limit) {
                if ($endDate && $cursor->gt($endDate)) break;
                $occurrences[] = $cursor->copy();
                $cursor->addDays($interval);
            }
        } elseif ($type === 'weekly') {
            if (empty($weeklyDays)) {
                $weeklyDays = [((int) $start->dayOfWeek) + 1];
            }
            $cursor = $start->copy();
            $limit = $endMode === 'occurrences' ? $endTimes : 120;
            while (count($occurrences) < $limit) {
                if ($endDate && $cursor->gt($endDate)) break;

                $weekDiff = (int) floor($start->copy()->startOfWeek()->diffInDays($cursor->copy()->startOfWeek()) / 7);
                $inIntervalWeek = $weekDiff % $interval === 0;
                $dayCode = ((int) $cursor->dayOfWeek) + 1;

                if ($inIntervalWeek && in_array($dayCode, $weeklyDays, true) && $cursor->gte($start)) {
                    $occurrences[] = $cursor->copy();
                }
                $cursor->addDay();
            }
        } else {
            $cursor = $start->copy();
            $limit = $endMode === 'occurrences' ? $endTimes : 24;
            while (count($occurrences) < $limit) {
                if ($endDate && $cursor->gt($endDate)) break;
                $occurrences[] = $cursor->copy();
                $cursor->addMonthsNoOverflow($interval);
            }
        }

        if (empty($occurrences)) {
            $occurrences[] = $start->copy();
        }

        foreach ($occurrences as $occStart) {
            \App\Models\CourseZoomMeetingOccurrence::create([
                'course_zoom_meeting_id' => (int) $meeting->id,
                'teacher_instructor_id' => $defaultTeacherId > 0 ? $defaultTeacherId : null,
                'title' => $meeting->topic,
                'start_time' => $occStart->toDateTimeString(),
                'duration_minutes' => $duration,
                'zoom_join_url' => $meeting->zoom_join_url,
                'status' => 'scheduled',
            ]);
        }
    }
    public function checkSlug(Request $request)
    {

        $exist = Course::where('slug', $request->slug)->exists();
        return response()->json([
            'exists' => $exist
        ]);
    }


    public function courseDetails($slug)
    {
        $course = Course::with('sections', 'sections.curriculums.lectures', 'sections.curriculums.quizzes', 'sections.curriculums.quizzes.questions.options', 'objects', 'requirements', 'contents', 'sections.curriculums.lectures.resources')->where('instructor_id', auth()->id())->where('slug', $slug)->first();


        if (!$course) {
            $notify[] = 'Invalid course';
            return responseError('course_not_found', $notify);
        }


        $notify[] = 'Course details fetched successfully';
        return responseSuccess('course_details', $notify, [
            'course' => $course,

        ]);
    }
    
    
       public function downloadResource($resourceId)
    {
        $resource = CourseResource::whereHas('course', function($query){
            $query->where('instructor_id', auth()->id());
            })->find($resourceId);

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

        if (!file_exists($fullPath)) {

            $notify[] = 'resources not found';
            return responseError('resources_not_found', $notify);
        }


        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimetype = mime_content_type($fullPath);

        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET,');
            header('Access-Control-Allow-Headers: Content-Type');
        }

        header("Content-Type: " . $mimetype);
        return readfile($fullPath);
    }
    
    
    
    
}
