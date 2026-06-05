<?php

namespace App\Http\Controllers\Api\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\GoogleAuthenticator;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseComplete;
use App\Models\CourseLiveBooking;
use App\Models\CourseLiveSession;
use App\Models\CourseLectureBatchRelease;
use App\Models\CourseLectureOneToOneRelease;
use App\Models\CoursePurchased;
use App\Models\CourseResource;
use App\Models\CourseZoomMeeting;
use App\Models\Curriculum;
use App\Models\CourseSection;
use App\Models\GetCertificateUser;
use App\Models\Quiz;
use App\Models\QuizSubmit;
use App\Models\Review;
use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserProgress;
use App\Services\Zoom\LmsZoomService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CourseController extends Controller
{
    public function enrolledCourse()
    {

        $enrolledCourses = CoursePurchased::where('user_id', auth()->id())->where('payment_status', Status::PAYMENT_SUCCESS)->with('course')->paginate(getPaginate());

        $notify[] = 'Enrolled courses';
        return responseSuccess('enrolled_courses', $notify, [
            'enrolled_courses' => $enrolledCourses
        ]);
    }



    public function watchCourse($slug)
    {

        $course = Course::with('sections', 'lectures', 'quizzes', 'curriculums', 'sections.curriculums.lectures', 'sections.curriculums.quizzes', 'sections.curriculums.quizzes.questions.options', 'objects', 'requirements', 'contents', 'sections.curriculums.lectures.resources', 'instructor', 'reviews.user', 'reviews.instructor', 'purchases')->where('slug', $slug)->first();

        if (!$course) {
            $notify[] =  'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $coursePurchase = CoursePurchased::where('user_id', auth()->id())
            ->where('course_id', $course->id)
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->with('liveBooking')
            ->first();

        $enrollCourse = (bool) $coursePurchase;

        if (!$enrollCourse) {
            $notify[] =  'You need to enroll in this course to watch the lecture';
            return responseError('enroll_course_failed', $notify);
        }

        // Enrolled students can only access sections released by teacher/admin.
        $releasedSections = $course->sections
            ->filter(function ($section) {
                return (int) ($section->is_released_to_students ?? 1) === 1;
            })
            ->values();
        $releasedSectionIds = $releasedSections->pluck('id')->all();

        $course->setRelation('sections', $releasedSections);
        $course->setRelation('curriculums', $course->curriculums
            ->whereIn('course_section_id', $releasedSectionIds)
            ->values());
        $course->setRelation('lectures', $course->lectures
            ->whereIn('course_section_id', $releasedSectionIds)
            ->values());
        $course->setRelation('quizzes', $course->quizzes
            ->whereIn('course_section_id', $releasedSectionIds)
            ->values());

        $enrolledUsers = User::whereHas('purchases', function ($query) use ($course) {
            $query->where('payment_status', Status::PAYMENT_SUCCESS)->where('course_id', $course->id);
        })->latest()->take(4)->get();

        $selectedBatchId = (int) optional($coursePurchase->liveBooking)->batch_id;
        $classType = (string) optional($coursePurchase->liveBooking)->class_type;

        if ($classType === 'group' && $selectedBatchId > 0) {
            $releasedLectureIds = CourseLectureBatchRelease::where('course_id', $course->id)
                ->where('batch_id', $selectedBatchId)
                ->where('is_released', 1)
                ->pluck('course_lecture_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $visibleLectures = $course->lectures
                ->whereIn('id', $releasedLectureIds)
                ->values();
            $visibleSectionIds = $visibleLectures
                ->pluck('course_section_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $course->setRelation('lectures', $visibleLectures);
            $course->setRelation('sections', $course->sections
                ->whereIn('id', $visibleSectionIds)
                ->values());
            $course->setRelation('curriculums', $course->curriculums
                ->whereIn('course_section_id', $visibleSectionIds)
                ->values());
            $course->setRelation('quizzes', $course->quizzes
                ->whereIn('course_section_id', $visibleSectionIds)
                ->values());
        }

        if ($classType === 'one_to_one' && optional($coursePurchase->liveBooking)->id) {
            $liveBookingId = (int) $coursePurchase->liveBooking->id;
            $releasedLectureIds = CourseLectureOneToOneRelease::where('course_id', $course->id)
                ->where('live_booking_id', $liveBookingId)
                ->where('user_id', auth()->id())
                ->where('is_released', 1)
                ->pluck('course_lecture_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $visibleLectures = $course->lectures
                ->whereIn('id', $releasedLectureIds)
                ->values();
            $visibleSectionIds = $visibleLectures
                ->pluck('course_section_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $course->setRelation('lectures', $visibleLectures);
            $course->setRelation('sections', $course->sections
                ->whereIn('id', $visibleSectionIds)
                ->values());
            $course->setRelation('curriculums', $course->curriculums
                ->whereIn('course_section_id', $visibleSectionIds)
                ->values());
            $course->setRelation('quizzes', $course->quizzes
                ->whereIn('course_section_id', $visibleSectionIds)
                ->values());
        }

        if ($classType === 'group') {
            $meetingQuery = CourseZoomMeeting::where('course_id', $course->id)
                ->orderBy('start_time');

            if ($selectedBatchId > 0) {
                $meetingQuery->where('batch_id', $selectedBatchId);
            } else {
                $meetingQuery->whereNull('batch_id');
            }

            $course->live_sessions = $meetingQuery
                ->get()
                ->map(function ($meeting) {
                    return [
                        'id' => $meeting->id,
                        'batch_id' => $meeting->batch_id,
                        'course_lecture_id' => null,
                        'session_number' => null,
                        'lecture_title' => $meeting->topic,
                        'scheduled_at' => $meeting->start_time,
                        'duration_minutes' => $meeting->duration_minutes,
                        'meeting_name' => $meeting->topic,
                        'zoom_join_url' => $meeting->zoom_join_url,
                        'recording_status' => null,
                        'status' => $meeting->status,
                    ];
                })
                ->unique(function ($session) {
                    $meetingId = (string) ($session['id'] ?? '');
                    $scheduledAt = (string) ($session['scheduled_at'] ?? '');
                    $joinUrl = (string) ($session['zoom_join_url'] ?? '');
                    $title = (string) ($session['lecture_title'] ?? '');
                    $batchId = (string) ($session['batch_id'] ?? '');

                    return $joinUrl !== ''
                        ? 'join:' . $joinUrl . '|time:' . $scheduledAt
                        : 'meeting:' . $meetingId . '|batch:' . $batchId . '|title:' . $title . '|time:' . $scheduledAt;
                })
                ->values();
        } else {
            $course->live_sessions = CourseLiveSession::where('course_id', $course->id)
                ->where('course_purchased_id', $coursePurchase->id)
                ->orderBy('scheduled_at')
                ->get()
                ->map(function ($session) {
                    return [
                        'id' => $session->id,
                        'batch_id' => $session->batch_id,
                        'course_lecture_id' => $session->course_lecture_id,
                        'session_number' => $session->session_number,
                        'lecture_title' => $session->lecture_title,
                        'scheduled_at' => $session->scheduled_at,
                        'duration_minutes' => $session->duration_minutes,
                        'meeting_name' => $session->meeting_name,
                        'zoom_join_url' => $session->zoom_join_url,
                        'recording_status' => $session->recording_status,
                        'status' => $session->status,
                    ];
                })->values();
        }

        $course->live_booking = $coursePurchase->liveBooking ? [
            'id' => $coursePurchase->liveBooking->id,
            'batch_id' => $coursePurchase->liveBooking->batch_id,
            'class_type' => $coursePurchase->liveBooking->class_type,
            'status' => $coursePurchase->liveBooking->status,
            'start_date' => $coursePurchase->liveBooking->start_date,
            'start_time' => $coursePurchase->liveBooking->start_time,
            'end_time' => $coursePurchase->liveBooking->end_time,
            'class_duration' => $coursePurchase->liveBooking->class_duration,
        ] : null;

        $course->can_schedule_one_to_one = (bool) ($coursePurchase->liveBooking && $coursePurchase->liveBooking->class_type === 'one_to_one');

        $notify[] = 'Course content';
        return responseSuccess('course_content', $notify, [
            'course' => $course,
            'enrolled_users' => $enrolledUsers

        ]);
    }

    public function scheduleOneToOne(Request $request, $slug)
    {
        $notify[] = 'One-to-one schedule is managed by admin. Please wait for your schedule to be assigned.';
        return responseError('admin_managed_schedule', $notify);
    }

    public function lectureComplete($id)
    {

        $curriculum = Curriculum::find($id);

        if (!$curriculum) {
            $notify[] =  'Invalid curriculum';
            return responseError('curriculum_not_found', $notify);
        }

        $course = $curriculum->course;

        if (!$course) {
            $notify[] =  'Invalid course';
            return responseError('course_not_found', $notify);
        }

        $coursePurchase = CoursePurchased::where('user_id', auth()->id())
            ->where('course_id', $course->id)
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->with('liveBooking')
            ->first();

        if (!$coursePurchase) {
            $notify[] =  'You are not enrolled in this course';
            return responseError('enroll_course_failed', $notify);
        }

        $isSectionReleased = CourseSection::where('id', $curriculum->course_section_id)
            ->where('course_id', $course->id)
            ->where('is_released_to_students', 1)
            ->exists();
        if (!$isSectionReleased) {
            $notify[] = 'This syllabus section is not released yet';
            return responseError('section_not_released', $notify);
        }

        $userProgress = UserProgress::where('user_id', auth()->id())->where('course_id', $course->id)->where('curriculum_id', $curriculum->id)->first();

        if ($userProgress) {
            $userProgress->delete();
        } else {
            $userProgress = new UserProgress();
            $userProgress->user_id = auth()->id();
            $userProgress->course_id = $course->id;
            $userProgress->curriculum_id = $curriculum->id;
            $userProgress->save();
        }


        $releasedSectionIds = CourseSection::where('course_id', $course->id)
            ->where('is_released_to_students', 1)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $classType = (string) optional($coursePurchase->liveBooking)->class_type;
        $selectedBatchId = (int) optional($coursePurchase->liveBooking)->batch_id;

        if ($classType === 'group' && $selectedBatchId > 0) {
            $releasedLectureSectionIds = CourseLectureBatchRelease::where('course_id', $course->id)
                ->where('batch_id', $selectedBatchId)
                ->where('is_released', 1)
                ->join('course_lectures', 'course_lectures.id', '=', 'course_lecture_batch_releases.course_lecture_id')
                ->pluck('course_lectures.course_section_id')
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all();

            $releasedSectionIds = array_values(array_intersect($releasedSectionIds, $releasedLectureSectionIds));
        }

        if ($classType === 'one_to_one' && optional($coursePurchase->liveBooking)->id) {
            $liveBookingId = (int) $coursePurchase->liveBooking->id;
            $releasedLectureSectionIds = CourseLectureOneToOneRelease::where('course_id', $course->id)
                ->where('live_booking_id', $liveBookingId)
                ->where('user_id', auth()->id())
                ->where('is_released', 1)
                ->join('course_lectures', 'course_lectures.id', '=', 'course_lecture_one_to_one_releases.course_lecture_id')
                ->pluck('course_lectures.course_section_id')
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all();

            $releasedSectionIds = array_values(array_intersect($releasedSectionIds, $releasedLectureSectionIds));
        }

        $accessibleCurriculumIds = Curriculum::where('course_id', $course->id)
            ->whereIn('course_section_id', $releasedSectionIds)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->all();

        $totalCurriculum = count($accessibleCurriculumIds);
        $completedCurriculum = UserProgress::where('user_id', auth()->id())
            ->where('course_id', $course->id)
            ->whereIn('curriculum_id', $accessibleCurriculumIds)
            ->count();
        $percentage = $totalCurriculum > 0 ? (($completedCurriculum / $totalCurriculum) * 100) : 0;


        $notify[] = 'Lecture completed successfully';
        return responseSuccess('lecture_completed', $notify, [
            'percentage' => $percentage,
            'course' => $course->load('sections')
        ]);
    }



    public function quizView($id)
    {
        $quiz = Quiz::with('questions.options')->find($id);

        if (!$quiz) {
            $notify[] =  'Invalid Quiz';
            return responseError('quiz_not_found', $notify);
        }

        $section = $quiz->section;

        if (!$section) {
            $notify[] =  'Invalid Section';
            return responseError('section_not_found', $notify);
        }

        $course = $section->course;

        if (!$course) {
            $notify[] = 'Invalid Course';
            return responseError('course_not_found', $notify);
        }

        $enrollCourse = CoursePurchased::where('user_id', auth()->id())->where('course_id', $course->id)->where('payment_status', Status::PAYMENT_SUCCESS)->exists();

        if (!$enrollCourse) {
            $notify[] =  'You need to enroll in this course to access the quiz';
            return responseError('enroll_course_failed', $notify);
        }

        $isSectionReleased = CourseSection::where('id', $quiz->course_section_id)
            ->where('course_id', $course->id)
            ->where('is_released_to_students', 1)
            ->exists();
        if (!$isSectionReleased) {
            $notify[] = 'This syllabus section is not released yet';
            return responseError('section_not_released', $notify);
        }

        $coursePurchase = CoursePurchased::where('user_id', auth()->id())
            ->where('course_id', $course->id)
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->with('liveBooking')
            ->first();
        $classType = (string) optional($coursePurchase?->liveBooking)->class_type;
        if ($classType === 'one_to_one' && optional($coursePurchase->liveBooking)->id) {
            $liveBookingId = (int) $coursePurchase->liveBooking->id;
            $releasedSectionIds = CourseLectureOneToOneRelease::where('course_id', $course->id)
                ->where('live_booking_id', $liveBookingId)
                ->where('user_id', auth()->id())
                ->where('is_released', 1)
                ->join('course_lectures', 'course_lectures.id', '=', 'course_lecture_one_to_one_releases.course_lecture_id')
                ->pluck('course_lectures.course_section_id')
                ->map(fn ($value) => (int) $value)
                ->unique()
                ->values()
                ->all();

            if (!in_array((int) $quiz->course_section_id, $releasedSectionIds, true)) {
                $notify[] = 'This content is not released for you yet';
                return responseError('not_released_for_student', $notify);
            }
        }


        $notify[] = 'Quiz content';
        return responseSuccess('quiz_content', $notify, ['quiz' => $quiz, 'course' => $course]);
    }


    public function quizSubmit(Request $request)
    {
        $quiz = Quiz::find($request->quiz_id);

        if (!$quiz) {
            $notify[] =  'Invalid Quiz';
            return responseError('quiz_not_found', $notify);
        }

        $section = $quiz->section;

        if (!$section) {
            $notify[] = 'Invalid Section';
            return responseError('section_not_found', $notify);
        }

        $course = $section->course;

        if (!$course) {
            $notify[] = 'Invalid Course';
            return responseError('course_not_found', $notify);
        }

        $enrollCourse = CoursePurchased::where('user_id', auth()->id())->where('course_id', $course->id)->where('payment_status', Status::PAYMENT_SUCCESS)->exists();
        if (!$enrollCourse) {
            $notify[] =  'You need to enroll in this course to access the quiz';
            return responseError('enroll_course_failed', $notify);
        }

        $isSectionReleased = CourseSection::where('id', $quiz->course_section_id)
            ->where('course_id', $course->id)
            ->where('is_released_to_students', 1)
            ->exists();
        if (!$isSectionReleased) {
            $notify[] = 'This syllabus section is not released yet';
            return responseError('section_not_released', $notify);
        }

        UserProgress::where('user_id', auth()->id())->where('course_id', $course->id)->where('curriculum_id', $quiz->curriculum_id)->delete();


        $validator = Validator::make($request->all(), [
            'answers' => 'required',
        ], [
            'required' => 'Answer is required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }


        $options = $quiz->options()->whereIn('id', $request->answers)->get();

        if (count($quiz->questions) != count($options)) {
            $notify[] =  'Invalid answer options';
            return responseError('validation_error', $notify);
        }

        $quizSubmitData = QuizSubmit::where('course_id', $course->id)->where('quiz_id', $quiz->id)->where('user_id', auth()->id())->get();

        if (count($quizSubmitData) > 0) {
            foreach ($quizSubmitData as $data) {
                $data->delete();
            }
        }


        foreach ($options as $option) {
            $quizSubmit = new QuizSubmit();
            $quizSubmit->user_id = auth()->id();
            $quizSubmit->course_id = $course->id;
            $quizSubmit->course_section_id = $section->id;
            $quizSubmit->quiz_id = $quiz->id;
            $quizSubmit->quiz_question_id = $option->quiz_question_id;
            $quizSubmit->quiz_option_id = $option->id;
            $quizSubmit->answer = $option->answer;
            $quizSubmit->save();
        }

        $userProgress = new UserProgress();
        $userProgress->user_id = auth()->id();
        $userProgress->course_id = $course->id;
        $userProgress->curriculum_id = $quiz->curriculum_id;
        $userProgress->save();

        $notify[] = 'Quiz submitted successfully';
        return responseSuccess('quiz_submitted', $notify, [
            'quiz' => $quiz
        ]);
    }

    public function ResourcesDownload($resourceId)
    {
        $notify[] = 'Resource download is disabled for students. Preview only.';
        return responseError('resource_download_disabled', $notify);
    }



    public function userActivity()
    {
        $activity = UserActivity::where('user_id', auth()->id())->whereDate('active_date', now())->exists();

        if (!$activity) {
            $activity = new UserActivity();
            $activity->user_id = auth()->id();
            $activity->active_date = now();
            $activity->save();
        }

        $notify[] = 'User activity recorded';
        return responseSuccess(
            'activity_list',
            $notify,
            ['activities' => $activity]
        );
    }


    public function reviewSubmit(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'rate_value' => 'required|in:1,2,3,4,5',
        ]);
        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }
        $course = Course::active()->where('slug', $slug)->first();

        if (!$course) {
            $notify[] =  'Invalid Course';
            return responseError('course_not_found', $notify);
        }

        $enrollCourse = CoursePurchased::where('user_id', auth()->id())->where('course_id', $course->id)->where('payment_status', Status::PAYMENT_SUCCESS)->exists();

        if (!$enrollCourse) {
            $notify[] =  'You need to enroll in this course to access the review';
            return responseError('enroll_course_failed', $notify);
        }
        $courseReview = Review::where('user_id', auth()->id())->where('course_id', $course->id)->first();
        if ($courseReview) {
            $notify[] =  'You have already submitted a review for this course';
            return responseError('review_submitted', $notify);
        }


        if ($request->rate_value) {
            $courseReview = new Review();
            $courseReview->user_id = auth()->id();
            $courseReview->course_id = $course->id;
            $courseReview->instructor_id = $course->instructor_id;
            $courseReview->comment = $request->user_comment;
            $courseReview->rating = $request->rate_value;
            $courseReview->save();
        }

        $course = $course->load('reviews');

        $notify[] = 'Review submitted successfully';
        return responseSuccess('review_submitted', $notify, [
            'course_review' => $course->reviews->load('user'),
            'course' => $course
        ]);
    }

    public function certificateDownload($slug)
    {
        $course = Course::active()->where('slug', $slug)->first();

        if (!$course) {
            $notify[] = 'Invalid Course';
            return responseError('course_not_found', $notify);
        }

        $enrollCourse = CoursePurchased::where('user_id', auth()->id())->where('course_id', $course->id)->where('payment_status', Status::PAYMENT_SUCCESS)->exists();


        if (!$enrollCourse) {
            $notify[] = 'You need to enroll in this course to access the certificate';
            return responseError('enroll_course_failed', $notify);
        }

        $courseComplete = $course->completes()->where('user_id', auth()->id())->first();

        if (!$courseComplete) {
            $notify[] =  'You have not completed this course yet';
            return responseError('course_not_completed', $notify);
        }

        $user = auth()->user();

        $getCertificateData = GetCertificateUser::where('user_id', $user->id)->where('course_id', $course->id)->first();

        if (!$getCertificateData) {
            $secret = getTrx();
            $getCertificateData = new GetCertificateUser();
            $getCertificateData->user_id = $user->id;
            $getCertificateData->course_id = $course->id;
            $getCertificateData->secret = $secret;
            $getCertificateData->save();
        }


        $url  = gs('verify_url') . '/' . $getCertificateData->secret;



        $qrCodeUrl = certificateQr($url);


        $general = gs();

        $logo = '<img src="data:image/png;base64,' . base64_encode(file_get_contents(getFilePath('logoIcon') . '/logo_dark.png')) . '" />';
        $qrCode = '<img  src="data:image/png;base64,' . base64_encode(file_get_contents($qrCodeUrl)) . '" />';

        $certificate = str_replace("{{student_name}}", $user->firstname . ' ' . $user->lastname, $general->certificate_template);
        $certificate = str_replace("{{site_name}}", $general->site_name, $certificate);
        $certificate = str_replace("{{qr_code}}", $qrCode, $certificate);
        $certificate = str_replace("{{course_title}}", $course->title, $certificate);
        $certificate = str_replace("{{instructor_name}}", $course->instructor->firstname . ' ' . $course->instructor->lastname, $certificate);
        $certificate = str_replace("{{site_logo}}", $logo, $certificate);
        $certificate = str_replace("{{course_completion_date}}", showDateTime($courseComplete->created_at, 'F j, Y'), $certificate);
        $certificate = str_replace("{{course_based_message}}", $course->congrats_message, $certificate);

        $data = [
            'certificate' => $certificate
        ];


        $pdf = PDF::loadView('certificate_pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('dpi', 110)
            ->setOption('defaultFont', 'sans-serif');


        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
    }

    public function courseComplete($slug)
    {
        $course = Course::active()->where('slug', $slug)->first();

        if (!$course) {
            $notify[] = 'Invalid Course';
            return responseError('course_not_found', $notify);
        }

        $enrollCourse = CoursePurchased::where('user_id', auth()->id())->where('course_id', $course->id)->where('payment_status', Status::PAYMENT_SUCCESS)->exists();

        if (!$enrollCourse) {
            $notify[] =  'You need to enroll in this course to access the course completion';
            return responseError('enroll_course_failed', $notify);
        }

        if ($course->progress != 100) {
            $notify[] =  'Please complete the all course lecture.';
            return responseError('course_completion_failed', $notify);
        }

        $user = auth()->user();
        $isCompleted = CourseComplete::where('user_id', $user->id)->where('course_id', $course->id)->exists();
        if ($isCompleted) {
            $notify[] = 'You have already completed this course';
            return responseError('course_completion_failed', $notify);
        }

        $courseComplete = new CourseComplete();
        $courseComplete->user_id = auth()->id();
        $courseComplete->course_id = $course->id;
        $courseComplete->instructor_id = $course->instructor_id;
        $courseComplete->save();


        Notify(auth()->user(),'COURSE_COMPLETE',[
            'congrats_message'=>$course->congrats_message,
        ]);

        $notify[] = 'Course completed successfully';
        return responseSuccess('course_completion', $notify);
    }


    public function checkCoupon(Request $request)
    {


        $validator = Validator::make($request->all(), [
            'coupon_code' => 'required',
        ]);
        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $coupon      = $this->getCouponByCode($request->coupon_code);


        if (!$coupon) {
            $notify[] = 'Invalid Coupon code';
            return responseError('coupon_not_found', $notify);
        }

        if ($coupon->applied_coupons_count >= $coupon->usage_limit_per_coupon) {
            $notify[] =  'Coupon usage limit reached.';
            return responseError('coupon_usage_limit_reached', $notify);
        }


        if ($coupon->user_applied_count >= $coupon->usage_limit_per_user) {
            $notify[] = 'Coupon usage limit reached.';
            return responseError('coupon_usage_limit_reached', $notify);
        }

        $course = Course::active()->find($request->course_id);

        if (!$course) {
            $notify[] = 'Invalid Course';
            return responseError('course_not_found', $notify);
        }

        $minimumSpend = $coupon->minimum_spend;
        $maximumSpend = $coupon->maximum_spend;
        $price = $course->price - $course->discount_price;


        if ($price < $minimumSpend) {
            $notify[] =  'Course price should be greater than or equal to ' . $minimumSpend;
            return responseError('course_price_error', $notify);
        }

        if ($price > $maximumSpend) {
            $notify[] = 'Course price should be less than or equal to ' . $maximumSpend;
            return responseError('course_price_error', $notify);
        }

        $couponDiscount =    $coupon->discountAmount($price);

        $notify[] = 'Coupon applied successfully';
        return responseSuccess('coupon_applied', $notify, [
            'coupon_code' => $coupon->coupon_code,
            'amount'      => getAmount($couponDiscount),
            'coupon'    => $coupon,

        ]);
    }

    public function getCouponByCode(string $code)
    {
        return Coupon::activeAndValid()->matchCode($code)
            ->withCount('appliedCoupons')
            ->withCount(['appliedCoupons as user_applied_count' => function ($appliedCoupon) {
                $appliedCoupon->where('user_id', auth()->id());
            }])->first();
    }
}
