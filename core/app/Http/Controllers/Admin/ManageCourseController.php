<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\LiveClassScheduler;
use App\Models\Course;
use App\Models\CourseLiveBatch;
use App\Models\CourseLecture;
use App\Models\CourseLectureBatchRelease;
use App\Models\CourseLectureOneToOneRelease;
use App\Models\CourseLiveBooking;
use App\Models\CourseLiveTeacherAssignment;
use App\Models\CourseLiveSession;
use App\Models\CourseZoomBatch;
use App\Models\CoursePurchased;
use App\Models\CourseResource;
use App\Models\GetCertificateUser;
use App\Models\Instructor;
use App\Models\ZoomAuditLog;
use App\Services\Zoom\LmsZoomService;
use Carbon\Carbon;
use App\Services\TeacherNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ManageCourseController extends Controller
{


    public function index($instructorId = null)
    {
        $pageTitle = 'All Courses';
        $courses = $this->courseData(instructorId: $instructorId);
        return view('admin.courses.lists', compact('pageTitle', 'courses'));
    }

    public function pending($instructorId = null)
    {
        $pageTitle = 'Pending Courses';
        $courses = $this->courseData('pending', instructorId: $instructorId);
        return view('admin.courses.lists', compact('pageTitle', 'courses'));
    }
    public function approved($instructorId = null)
    {
        $pageTitle = 'Approved Courses';
        $courses = $this->courseData('approved', instructorId: $instructorId);
        return view('admin.courses.lists', compact('pageTitle', 'courses'));
    }

    public function rejected($instructorId = null)
    {
        $pageTitle = 'Rejected Courses';
        $courses = $this->courseData('rejected', instructorId: $instructorId);
        return view('admin.courses.lists', compact('pageTitle', 'courses'));
    }

    public function popularCourses($instructorId = null)
    {
        $pageTitle = 'Popular Courses';
        $courses = $this->courseData('popular', instructorId: $instructorId);
        return view('admin.courses.lists', compact('pageTitle', 'courses'));
    }


    public function trash()
    {
        $pageTitle = 'Trash Courses';
        $courses = Course::searchable(['title', 'instructor:username'])->with(['category', 'subcategory', 'instructor'])->orderBy('id', 'desc')->onlyTrashed()->paginate(getPaginate());
        return view('admin.courses.lists', compact('pageTitle', 'courses'));
    }


    public function restore($slug)
    {
        $course = Course::withTrashed()->where('slug', $slug)->first();
        if (!$course) {
            $notify[] = ['error', 'Invalid course'];
            return back()->withNotify($notify);
        }
        $course->restore();
        $notify[] = ['success', 'Course has been restored successfully'];
        return back()->withNotify($notify);
    }


    protected function courseData($scope = null, $instructorId = null)
    {
        if ($scope) {
            $courses = Course::$scope();
        } else {
            $courses = Course::query();
        }
        if ($instructorId) {
            $courses = $courses->where('instructor_id', $instructorId);
        }


        return $courses->searchable(['title', 'instructor:username', 'category:name', 'subcategory:name'])->with(['category', 'subcategory', 'instructor'])->orderBy('id', 'desc')->paginate(getPaginate());
    }




    public function details($slug)
    {
        $pageTitle = 'Course Details';

        $course = Course::withTrashed()->with('sections', 'lectures', 'quizzes', 'sections.curriculums.lectures', 'sections.curriculums.quizzes', 'sections.curriculums.quizzes.questions.options', 'objects', 'requirements', 'contents', 'sections.curriculums.lectures.resources', 'instructor', 'liveBatches.teachers.teacher')->where('slug', $slug)->first();


        if (!$course) {
            $notify[] = ['error', 'Invalid course'];
            return back()->withNotify($notify);
        }

        $enrolledUsers = CoursePurchased::where('payment_status', Status::PAYMENT_SUCCESS)->where('course_id', $course->id)->count();
        $availableInstructors = Instructor::active()->orderBy('firstname')->get();

        $courseTeachers = CourseLiveTeacherAssignment::with('teacher')
            ->where('course_id', $course->id)
            ->whereNull('batch_id')
            ->where('is_active', 1)
            ->get();

        $availableTeachersByBatch = [];
        foreach ($course->liveBatches as $batch) {
            $availableTeachersByBatch[$batch->id] = $courseTeachers->filter(function ($assignment) use ($batch) {
                return !LiveClassScheduler::hasBatchTeacherClash((int) $assignment->teacher_instructor_id, $batch->id);
            })->values();
        }

        $pendingBookings = CourseLiveBooking::with(['user', 'assignedTeacher', 'batch'])
            ->where('course_id', $course->id)
            ->whereIn('status', ['pending_student_schedule', 'pending_admin_assignment', 'assigned', 'zoom_conflict'])
            ->orderBy('id', 'desc')
            ->get();

        $zoomBatches = CourseZoomBatch::where('course_id', $course->id)
            ->orderByDesc('id')
            ->get(['id', 'title', 'status']);

        $releaseBatches = $zoomBatches->isNotEmpty()
            ? $zoomBatches
            : $course->liveBatches->map(function ($batch) {
                return (object) [
                    'id' => (int) $batch->id,
                    'title' => (string) ($batch->title ?: ('Batch #' . $batch->id)),
                ];
            });

        $zoomAuditLogs = ZoomAuditLog::where('course_id', $course->id)
            ->latest()
            ->limit(50)
            ->get();

        $availableTeachersByBooking = [];
        foreach ($pendingBookings as $booking) {
            $availableTeachersByBooking[$booking->id] = $courseTeachers->filter(function ($assignment) use ($booking) {
                if (!$booking->start_date || !$booking->start_time || !$booking->end_time) {
                    return true;
                }

                return !LiveClassScheduler::hasOneToOneClash((int) $assignment->teacher_instructor_id, $booking->start_date, $booking->start_time, $booking->end_time, $booking->id);
            })->values();
        }

        return view('admin.courses.details', compact('pageTitle', 'course', 'enrolledUsers', 'availableInstructors', 'courseTeachers', 'pendingBookings', 'availableTeachersByBatch', 'availableTeachersByBooking', 'zoomAuditLogs', 'releaseBatches'));
    }

    public function batchEnrollments($slug)
    {
        $pageTitle = 'Course Batch Enrollments';

        $course = Course::withTrashed()->with([
            'instructor',
            'zoomBatches' => function ($query) {
                $query->with([
                    'teacher:id,firstname,lastname,username,email',
                    'meetings.occurrences.teacher:id,firstname,lastname,username,email',
                ])->orderBy('id');
            },
            'liveBookings' => function ($query) {
                $query->whereHas('purchase', function ($purchaseQuery) {
                    $purchaseQuery->where('payment_status', Status::PAYMENT_SUCCESS);
                })->with([
                    'user:id,firstname,lastname,username,email',
                    'assignedTeacher:id,firstname,lastname,username,email',
                    'purchase:id,amount,payment_status',
                ])->orderByDesc('id');
            },
        ])->where('slug', $slug)->first();

        if (!$course) {
            $notify[] = ['error', 'Invalid course'];
            return back()->withNotify($notify);
        }

        $zoomBookings = collect($course->liveBookings)
            ->filter(function ($booking) {
                return !empty($booking->user_id) && (($booking->class_type ?? null) === 'group');
            })
            ->values();

        $batches = $course->zoomBatches->map(function ($batch) use ($zoomBookings) {
            $enrolledBookings = $zoomBookings
                ->filter(function ($booking) use ($batch) {
                    return (int) ($booking->batch_id ?? 0) === (int) $batch->id;
                })
                ->values();

            $teachers = collect([$batch->teacher])
                ->filter()
                ->map(function ($teacher) {
                    return optional($teacher)->fullname ?: optional($teacher)->username;
                })
                ->filter()
                ->unique()
                ->values();

            if ($teachers->isEmpty()) {
                $teachers = collect($batch->meetings)
                    ->flatMap(function ($meeting) {
                        return collect($meeting->occurrences ?: [])->map(function ($occurrence) {
                            return optional($occurrence->teacher)->fullname ?: optional($occurrence->teacher)->username;
                        });
                    })
                    ->filter()
                    ->unique()
                    ->values();
            }

            $firstMeeting = $batch->meetings->first();
            $meetingTime = $firstMeeting ? $firstMeeting->start_time : null;

            return [
                'id' => (int) $batch->id,
                'title' => (string) ($batch->title ?: ('Batch #' . $batch->id)),
                'start_date' => $meetingTime,
                'meeting_start_time' => $meetingTime ? $meetingTime->format('H:i') : null,
                'meeting_end_time' => $meetingTime ? $meetingTime->format('H:i') : null,
                'capacity' => 0,
                'teachers' => $teachers,
                'students_count' => $enrolledBookings->count(),
                'students' => $enrolledBookings->map(function ($booking) use ($teachers) {
                    return [
                        'name' => optional($booking->user)->fullname ?: trim((optional($booking->user)->firstname . ' ' . optional($booking->user)->lastname)),
                        'username' => optional($booking->user)->username,
                        'email' => optional($booking->user)->email,
                        'status' => (string) ($booking->status ?? 'N/A'),
                        'assigned_teacher' => optional($booking->assignedTeacher)->fullname ?: optional($booking->assignedTeacher)->username ?: $teachers->implode(', '),
                        'amount' => (float) (optional($booking->purchase)->amount ?? 0),
                        'user_id' => (int) ($booking->user_id ?? 0),
                    ];
                })->values(),
            ];
        })->values();

        return view('admin.courses.batch_enrollments', compact('pageTitle', 'course', 'batches'));
    }
    public function assignCourseTeacher(Request $request, $slug)
    {
        $request->validate([
            'teacher_instructor_id' => 'required|integer',
        ]);

        $course = Course::withTrashed()->where('slug', $slug)->firstOrFail();

        $teacher = Instructor::active()->find($request->teacher_instructor_id);
        if (!$teacher) {
            $notify[] = ['error', 'Invalid teacher'];
            return back()->withNotify($notify);
        }

        $exists = CourseLiveTeacherAssignment::where('course_id', $course->id)
            ->whereNull('batch_id')
            ->where('teacher_instructor_id', $teacher->id)
            ->exists();

        if ($exists) {
            $notify[] = ['error', 'Teacher already assigned to this course'];
            return back()->withNotify($notify);
        }

        CourseLiveTeacherAssignment::create([
            'course_id' => $course->id,
            'batch_id' => null,
            'teacher_instructor_id' => $teacher->id,
            'assigned_by_admin_id' => auth('admin')->id(),
            'is_active' => 1,
        ]);

        $notify[] = ['success', 'Teacher assigned to course successfully'];
        return back()->withNotify($notify);
    }

    public function removeCourseTeacher(Request $request, $slug)
    {
        $request->validate([
            'teacher_instructor_id' => 'required|integer',
        ]);

        $course = Course::withTrashed()->where('slug', $slug)->firstOrFail();

        $hasBatchAssignment = CourseLiveTeacherAssignment::where('course_id', $course->id)
            ->whereNotNull('batch_id')
            ->where('teacher_instructor_id', $request->teacher_instructor_id)
            ->where('is_active', 1)
            ->exists();

        if ($hasBatchAssignment) {
            $notify[] = ['error', 'Teacher is assigned to one or more batches. Remove batch assignments first.'];
            return back()->withNotify($notify);
        }

        $updated = CourseLiveTeacherAssignment::where('course_id', $course->id)
            ->whereNull('batch_id')
            ->where('teacher_instructor_id', $request->teacher_instructor_id)
            ->where('is_active', 1)
            ->update([
                'is_active' => 0,
            ]);

        if (!$updated) {
            $notify[] = ['error', 'Active teacher allocation not found for this course'];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', 'Teacher removed from course allocation successfully'];
        return back()->withNotify($notify);
    }

    public function assignBatchTeacher(Request $request, $batchId)
    {
        $request->validate([
            'teacher_instructor_id' => 'required|integer',
        ]);

        $batch = CourseLiveBatch::findOrFail($batchId);
        $teacherId = (int) $request->teacher_instructor_id;
        $this->ensureTeacherAssignedToCourse($batch->course_id, $teacherId);

        if (LiveClassScheduler::hasBatchTeacherClash($teacherId, $batch->id)) {
            $notify[] = ['error', 'Teacher is not available for this batch slot'];
            return back()->withNotify($notify);
        }

        // Enforce one batch -> one active instructor:
        // any previous active teacher allocation for this batch is deactivated
        // before assigning the new teacher.
        CourseLiveTeacherAssignment::where('course_id', $batch->course_id)
            ->where('batch_id', $batch->id)
            ->where('is_active', 1)
            ->where('teacher_instructor_id', '!=', $teacherId)
            ->update([
                'is_active' => 0,
            ]);

        CourseLiveTeacherAssignment::updateOrCreate(
            [
                'course_id' => $batch->course_id,
                'batch_id' => $batch->id,
                'teacher_instructor_id' => $teacherId,
            ],
            [
                'assigned_by_admin_id' => auth('admin')->id(),
                'is_active' => 1,
            ]
        );

        app(LmsZoomService::class)->syncTeacherToSessions($batch->course_id, $batch->id, $teacherId, auth('admin')->id());

        $teacher = Instructor::find($teacherId);
        if ($teacher) {
            app(TeacherNotificationService::class)->notifyBatchAssigned($teacher, $batch->loadMissing('course'));
        }

        $notify[] = ['success', 'Teacher assigned to batch successfully'];
        return back()->withNotify($notify);
    }

    public function assignBookingTeacher(Request $request, $bookingId)
    {
        if ($request->filled('start_time')) {
            $parsedStartTime = strtotime((string) $request->start_time);
            if ($parsedStartTime !== false) {
                $request->merge([
                    'start_time' => date('H:i', $parsedStartTime),
                ]);
            }
        }

        if ($request->filled('start_date')) {
            $parsedStartDate = strtotime((string) $request->start_date);
            if ($parsedStartDate !== false) {
                $request->merge([
                    'start_date' => date('Y-m-d', $parsedStartDate),
                ]);
            }
        }

        $request->validate([
            'teacher_instructor_id' => 'required|integer',
            'class_duration' => 'nullable|integer|min:1|max:480',
            'start_date' => 'nullable|date|after_or_equal:today',
            'start_time' => ['nullable', 'date_format:H:i', 'after_or_equal:09:00', 'before_or_equal:21:00', 'regex:/^\d{2}:(00|30)$/'],
            'recurrence_type' => 'nullable|in:none,daily,weekly,monthly',
            'repeat_every' => 'nullable|integer|min:1|max:90',
            'weekly_days' => 'nullable|array',
            'weekly_days.*' => 'integer|min:1|max:7',
            'end_type' => 'nullable|in:no_end,by,after',
            'end_date' => 'nullable|date',
            'occurrences' => 'nullable|integer|min:1|max:50',
            'lecture_schedule' => 'nullable|array',
            'lecture_schedule_date' => 'nullable|array',
            'lecture_schedule_time' => 'nullable|array',
        ]);

        $booking = CourseLiveBooking::with('course')->findOrFail($bookingId);
        $oldSchedule = [
            'start_date' => $booking->start_date,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'class_duration' => $booking->class_duration,
            'assigned_teacher_id' => $booking->assigned_teacher_id,
            'status' => $booking->status,
        ];
        $teacherId = (int) $request->teacher_instructor_id;
        $this->ensureTeacherAssignedToCourse($booking->course_id, $teacherId);

        $duration = (int) ($request->class_duration ?: $booking->class_duration ?: $booking->course?->default_class_duration ?: 60);
        $lectureScheduleInput = $request->lecture_schedule;
        if (!$lectureScheduleInput && ($request->filled('lecture_schedule_date') || $request->filled('lecture_schedule_time'))) {
            $dates = collect($request->lecture_schedule_date ?? []);
            $times = collect($request->lecture_schedule_time ?? []);
            $lectureScheduleInput = $dates->mapWithKeys(function ($date, $lectureId) use ($times) {
                $time = $times->get($lectureId);
                if (!$date || !$time) {
                    return [];
                }
                return [$lectureId => trim((string) $date) . ' ' . trim((string) $time) . ':00'];
            })->all();
        }

        $lectureSchedule = collect($lectureScheduleInput ?? [])
            ->mapWithKeys(function ($dateTime, $lectureId) {
                if (!$dateTime) {
                    return [];
                }
                $parsed = strtotime((string) $dateTime);
                if ($parsed === false) {
                    return [];
                }
                return [(int) $lectureId => date('Y-m-d H:i:s', $parsed)];
            })
            ->all();

        if ($booking->class_type === 'one_to_one') {
            $lectureSchedule = [];
        }

        $recurrenceType = strtolower((string) ($request->recurrence_type ?: 'none'));
        $recurrence = [
            'type' => $recurrenceType,
            'repeat_interval' => max(1, (int) ($request->repeat_every ?: 1)),
            'weekly_days' => collect($request->weekly_days ?? [])
                ->map(fn ($day) => (int) $day)
                ->filter(fn ($day) => $day >= 1 && $day <= 7)
                ->values()
                ->all(),
            'end_mode' => $request->end_type === 'by' ? 'date' : ($request->end_type === 'after' ? 'occurrences' : 'none'),
            'end_date_time' => $request->end_type === 'by' && $request->end_date ? $request->end_date . ' 23:59:59' : null,
            'end_times' => max(1, (int) ($request->occurrences ?: 1)),
        ];

        $startDate = $booking->class_type === 'one_to_one'
            ? ($request->start_date ?: $booking->start_date)
            : $booking->start_date;
        $startTime = $booking->class_type === 'one_to_one'
            ? ($request->start_time ?: $booking->start_time)
            : $booking->start_time;

        $liveLectureIds = $booking->course->liveLectures()->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($booking->class_type !== 'one_to_one' && $recurrenceType !== 'none' && empty($lectureSchedule)) {
            if (!$startDate || !$startTime) {
                $notify[] = ['error', 'Please select the first meeting date and time for recurring scheduling'];
                return back()->withNotify($notify);
            }

            $lectureSchedule = $this->buildRecurringLectureSchedule($liveLectureIds, $startDate, $startTime, $recurrence);
            if (empty($lectureSchedule)) {
                $notify[] = ['error', 'Unable to build recurring meeting schedule'];
                return back()->withNotify($notify);
            }
        }

        if ($booking->class_type === 'one_to_one' && !empty($lectureSchedule)) {
            $firstSlot = collect($lectureSchedule)->sort()->first();
            if ($firstSlot) {
                $startDate = date('Y-m-d', strtotime($firstSlot));
                $startTime = date('H:i', strtotime($firstSlot));
            }
        }

        $endTime = $startTime ? date('H:i', strtotime("+{$duration} minutes", strtotime($startTime))) : null;

        if ($booking->class_type === 'one_to_one' && (!$startDate || !$startTime)) {
            $notify[] = ['error', 'Please select one-to-one date and time before assigning teacher'];
            return back()->withNotify($notify);
        }

        if ($booking->class_type === 'one_to_one' && $startDate && $startTime && $endTime) {
            if ($startTime < '09:00' || $startTime > '21:00' || $endTime > '21:00') {
                $notify[] = ['error', 'Meeting time must be between 09:00 and 21:00'];
                return back()->withNotify($notify);
            }
            if (!preg_match('/^\d{2}:(00|30)$/', (string) $startTime)) {
                $notify[] = ['error', 'Meeting start time must be in 30-minute intervals'];
                return back()->withNotify($notify);
            }
            if (LiveClassScheduler::hasOneToOneClash($teacherId, $startDate, $startTime, $endTime, $booking->id)) {
                $notify[] = ['error', 'Teacher has a clashing one-to-one class at this slot'];
                return back()->withNotify($notify);
            }
        }

        if ($booking->class_type === 'one_to_one' && !empty($lectureSchedule)) {
            $liveLectures = $booking->course->liveLectures()->pluck('id')->map(fn($id) => (int) $id)->all();
            $missingSchedule = collect($liveLectures)->filter(fn ($lectureId) => empty($lectureSchedule[$lectureId]))->values();
            if ($missingSchedule->isNotEmpty()) {
                $notify[] = ['error', 'Please provide schedule date and time for every live lecture'];
                return back()->withNotify($notify);
            }

            $duplicateScheduleExists = collect($lectureSchedule)
                ->filter()
                ->countBy()
                ->contains(fn ($count) => $count > 1);

            if ($duplicateScheduleExists) {
                $notify[] = ['error', 'Two lectures cannot have the same date and time'];
                return back()->withNotify($notify);
            }

            foreach ($lectureSchedule as $slotDateTime) {
                $slotStartDate = date('Y-m-d', strtotime($slotDateTime));
                $slotStartTime = date('H:i', strtotime($slotDateTime));
                $slotEndTime = date('H:i', strtotime("+{$duration} minutes", strtotime($slotStartTime)));
                if ($slotStartTime < '09:00' || $slotStartTime > '21:00' || $slotEndTime > '21:00') {
                    $notify[] = ['error', 'Meeting time must be between 09:00 and 21:00 for all lecture slots'];
                    return back()->withNotify($notify);
                }
                if (!preg_match('/^\d{2}:(00|30)$/', $slotStartTime)) {
                    $notify[] = ['error', 'Lecture start time must be in 30-minute intervals'];
                    return back()->withNotify($notify);
                }

                if (LiveClassScheduler::hasOneToOneClash($teacherId, $slotStartDate, $slotStartTime, $slotEndTime, $booking->id)) {
                    $notify[] = ['error', 'Teacher has a clashing one-to-one class at one or more lecture slots'];
                    return back()->withNotify($notify);
                }
            }
        }

        $zoom = app(LmsZoomService::class);

        $normalizedRecurrence = null;
        if ($booking->class_type === 'one_to_one' && $recurrenceType !== 'none') {
            $normalizedRecurrence = $this->normalizeZoomRecurrence($recurrence, $startDate . ' ' . $startTime);
        }

        if ($booking->class_type === 'one_to_one') {
            try {
                $zoom->assertCourseBookingAvailable($booking->course, $startDate, $startTime, $duration, $lectureSchedule);
                $zoom->deleteSessionsForBooking($booking, auth('admin')->id(), 'admin');
            } catch (\Throwable $throwable) {
                $booking->status = 'zoom_conflict';
                $booking->notes = trim(($booking->notes ? $booking->notes . PHP_EOL : '') . 'Admin schedule failed: ' . $throwable->getMessage());
                $booking->save();

                $notify[] = ['error', $throwable->getMessage()];
                return back()->withNotify($notify);
            }
        }

        $booking->assigned_teacher_id = $teacherId;
        $booking->assigned_by_admin_id = auth('admin')->id();
        $booking->start_date = $startDate;
        $booking->start_time = $startTime;
        $booking->class_duration = $duration;
        $booking->end_time = $endTime;
        $booking->status = 'confirmed';
        $booking->notes = null;
        $booking->save();

        $availabilitySlots = $this->buildOneToOneAvailabilitySlots($startDate, $startTime, $duration, $recurrence);
        $availableTeacherIds = collect(\App\Models\Instructor::onlyTeachers()
            ->active()
            ->select('id')
            ->get())
            ->filter(function ($teacher) use ($availabilitySlots, $booking) {
                foreach ($availabilitySlots as $slot) {
                    if (LiveClassScheduler::hasTeacherConflict((int) $teacher->id, $slot['date'], $slot['start_time'], $slot['end_time'], $booking->id)) {
                        return false;
                    }
                }

                return true;
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($availableTeacherIds) || !in_array($teacherId, $availableTeacherIds, true)) {
            $booking->status = 'zoom_conflict';
            $booking->notes = trim(($booking->notes ? $booking->notes . PHP_EOL : '') . 'Selected teacher is not available for the chosen time or recurrence slots.');
            $booking->save();

            $notify[] = ['error', 'Selected teacher is not available for the chosen time or recurrence slots'];
            return back()->withNotify($notify);
        }

        if ($booking->class_type === 'one_to_one') {
            try {
                $zoom->generateBookingSessions($booking->course, $booking, $lectureSchedule, $normalizedRecurrence);
            } catch (\Throwable $throwable) {
                $booking->status = 'zoom_conflict';
                $booking->notes = trim(($booking->notes ? $booking->notes . PHP_EOL : '') . 'Admin schedule generate failed: ' . $throwable->getMessage());
                $booking->save();

                $notify[] = ['error', $throwable->getMessage()];
                return back()->withNotify($notify);
            }
        }

        $zoom->syncTeacherToBookingSessions($booking, $teacherId, auth('admin')->id());

        if ($booking->class_type === 'one_to_one') {
            $student = $booking->user;
            $teacher = Instructor::find($teacherId);
            $courseName = (string) ($booking->course?->title ?: 'N/A');
            $grade = (string) ($booking->course?->category?->name ?: $booking->course?->grade ?: 'N/A');
            $studentName = $student
                ? (trim((string) ($student->fullname ?: trim(($student->firstname ?? '') . ' ' . ($student->lastname ?? '')))) ?: 'Student')
                : 'Student';
            $teacherName = $teacher
                ? (trim((string) ($teacher->fullname ?: trim(($teacher->firstname ?? '') . ' ' . ($teacher->lastname ?? '')))) ?: 'Teacher')
                : 'Teacher';
            $dateTime = $booking->start_date && $booking->start_time
                ? Carbon::parse($booking->start_date . ' ' . $booking->start_time)->format('d M Y h:i A')
                : 'N/A';
            $newSchedule = [
                'start_date' => $booking->start_date,
                'start_time' => $booking->start_time,
                'end_time' => $booking->end_time,
                'class_duration' => $booking->class_duration,
                'assigned_teacher_id' => $booking->assigned_teacher_id,
                'status' => $booking->status,
            ];

            $hasChange = $oldSchedule['start_date'] !== $newSchedule['start_date']
                || $oldSchedule['start_time'] !== $newSchedule['start_time']
                || $oldSchedule['end_time'] !== $newSchedule['end_time']
                || (int) $oldSchedule['class_duration'] !== (int) $newSchedule['class_duration']
                || (int) $oldSchedule['assigned_teacher_id'] !== (int) $newSchedule['assigned_teacher_id'];

            if ($hasChange) {
                $oldDateTimeLabel = (!empty($oldSchedule['start_date']) && !empty($oldSchedule['start_time']))
                    ? Carbon::parse($oldSchedule['start_date'] . ' ' . $oldSchedule['start_time'])->format('d M Y h:i A')
                    : 'N/A';

                if ($student && !empty($student->email)) {
                    try {
                        notify($student, 'DEFAULT', [
                            'subject' => '1:1 Session Rescheduled - Updated Schedule',
                            'message' => 'Hello,<br><br>'
                                . 'Your 1:1 session has been rescheduled.<br><br>'
                                . 'Updated Session Details<br>'
                                . 'Student Name: ' . $studentName . '<br>'
                                . 'Course Name: ' . $courseName . '<br>'
                                . 'Grade: ' . $grade . '<br>'
                                . 'Previous Date & Time: ' . $oldDateTimeLabel . '<br>'
                                . 'New Date & Time: ' . $dateTime . '<br>'
                                . 'Teacher: ' . $teacherName . '<br><br>'
                                . 'Thank you,',
                        ], ['email'], false);
                    } catch (\Throwable $throwable) {
                        \Illuminate\Support\Facades\Log::warning('Failed to send 1:1 booking reschedule email to student', [
                            'booking_id' => $booking->id,
                            'error' => $throwable->getMessage(),
                        ]);
                    }
                }

                if ($teacher && !empty($teacher->email)) {
                    try {
                        notify($teacher, 'DEFAULT', [
                            'subject' => '1:1 Session Rescheduled - ' . $studentName,
                            'message' => 'Hello ' . $teacherName . ',<br><br>'
                                . 'The following 1:1 session has been rescheduled.<br><br>'
                                . 'Updated Session Details<br>'
                                . 'Student Name: ' . $studentName . '<br>'
                                . 'Grade: ' . $grade . '<br>'
                                . 'Course Name: ' . $courseName . '<br>'
                                . 'Previous Date & Time: ' . $oldDateTimeLabel . '<br>'
                                . 'New Date & Time: ' . $dateTime . '<br><br>'
                                . 'Thank you,',
                        ], ['email'], false);
                    } catch (\Throwable $throwable) {
                        \Illuminate\Support\Facades\Log::warning('Failed to send 1:1 booking reschedule email to teacher', [
                            'booking_id' => $booking->id,
                            'teacher_id' => $teacher->id,
                            'error' => $throwable->getMessage(),
                        ]);
                    }
                }

                $zoom->log('rescheduled', [
                    'course_id' => $booking->course_id,
                    'batch_id' => $booking->batch_id,
                    'actor_type' => 'admin',
                    'actor_id' => auth('admin')->id(),
                    'meta' => [
                        'booking_id' => $booking->id,
                        'class_type' => $booking->class_type,
                        'old' => $oldSchedule,
                        'new' => $newSchedule,
                    ],
                ]);
            } elseif ($teacher && !empty($teacher->email)) {
                try {
                    notify($teacher, 'DEFAULT', [
                        'subject' => '1:1 Session Assigned - ' . $studentName,
                        'message' => 'Hello ' . $teacherName . ',<br><br>'
                            . 'You have been assigned a new 1:1 session.<br><br>'
                            . 'Session Details<br>'
                            . 'Student Name: ' . $studentName . '<br>'
                            . 'Grade: ' . $grade . '<br>'
                            . 'Course Name: ' . $courseName . '<br>'
                            . 'Date & Time: ' . $dateTime . '<br><br>'
                            . 'Thank you,',
                    ], ['email'], false);
                } catch (\Throwable $throwable) {
                    \Illuminate\Support\Facades\Log::warning('Failed to send 1:1 assignment email to teacher', [
                        'booking_id' => $booking->id,
                        'teacher_id' => $teacher->id,
                        'error' => $throwable->getMessage(),
                    ]);
                }
            }
        }

        $notify[] = ['success', 'Teacher assigned to booking successfully'];
        return back()->withNotify($notify);
    }

    private function buildRecurringLectureSchedule(array $lectureIds, string $startDate, string $startTime, array $recurrence): array
    {
        $lectureIds = collect($lectureIds)->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->values();
        if ($lectureIds->isEmpty()) {
            return [];
        }

        $type = strtolower((string) ($recurrence['type'] ?? 'none'));
        $interval = max(1, (int) ($recurrence['repeat_interval'] ?? 1));
        $weeklyDays = collect($recurrence['weekly_days'] ?? [])
            ->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->values()
            ->all();

        $startAt = \Carbon\Carbon::parse($startDate . ' ' . $startTime);
        $cursor = $startAt->copy();
        $limit = $lectureIds->count();
        $endMode = strtolower((string) ($recurrence['end_mode'] ?? 'none'));
        $endDate = !empty($recurrence['end_date_time']) ? \Carbon\Carbon::parse($recurrence['end_date_time']) : null;
        $endTimes = max(1, (int) ($recurrence['end_times'] ?? $limit));
        $generatedSlots = [];

        if ($type === 'daily') {
            $attempts = 0;
            $maxAttempts = max($limit, $endTimes);
            while (count($generatedSlots) < $limit && $attempts < $maxAttempts) {
                if ($endDate && $cursor->gt($endDate)) {
                    break;
                }

                $generatedSlots[] = $cursor->copy();
                $cursor->addDays($interval);
                $attempts++;
            }
        } elseif ($type === 'weekly') {
            if (empty($weeklyDays)) {
                $weeklyDays = [((int) $startAt->dayOfWeek) + 1];
            }

            while (count($generatedSlots) < $limit) {
                if ($endDate && $cursor->gt($endDate)) {
                    break;
                }

                $weekDiff = (int) floor($startAt->copy()->startOfWeek()->diffInDays($cursor->copy()->startOfWeek()) / 7);
                $dayCode = ((int) $cursor->dayOfWeek) + 1;

                if ($cursor->gte($startAt) && $weekDiff % $interval === 0 && in_array($dayCode, $weeklyDays, true)) {
                    $generatedSlots[] = $cursor->copy();
                }

                $cursor->addDay();
            }
        } elseif ($type === 'monthly') {
            $attempts = 0;
            $maxAttempts = max($limit, $endTimes);
            while (count($generatedSlots) < $limit && $attempts < $maxAttempts) {
                if ($endDate && $cursor->gt($endDate)) {
                    break;
                }

                $generatedSlots[] = $cursor->copy();
                $cursor->addMonthsNoOverflow($interval);
                $attempts++;
            }
        }

        if (empty($generatedSlots)) {
            return [];
        }

        $schedule = [];
        foreach ($lectureIds as $index => $lectureId) {
            $slot = $generatedSlots[$index] ?? null;
            if (!$slot) {
                break;
            }
            $schedule[$lectureId] = $slot->format('Y-m-d H:i:s');
        }

        return $schedule;
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

        $endTimes = max(1, (int) ($input['end_times'] ?? ($input['occurrences'] ?? 1)));
        $endDateTime = null;
        if ($endMode === 'date' && !empty($input['end_date_time'])) {
            $endDateTime = \Carbon\Carbon::parse($input['end_date_time'])->toDateTimeString();
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

    private function normalizeOneToOneRecurrence(Request $request): array
    {
        $recurrenceType = strtolower((string) ($request->recurrence_type ?: 'none'));

        return [
            'type' => $recurrenceType,
            'repeat_interval' => max(1, (int) ($request->repeat_every ?: 1)),
            'weekly_days' => collect($request->weekly_days ?? [])
                ->map(fn ($day) => (int) $day)
                ->filter(fn ($day) => $day >= 1 && $day <= 7)
                ->values()
                ->all(),
            'end_mode' => $request->end_type === 'by' ? 'date' : ($request->end_type === 'after' ? 'occurrences' : 'none'),
            'end_date_time' => $request->end_type === 'by' && $request->end_date ? $request->end_date . ' 23:59:59' : null,
            'end_times' => max(1, (int) ($request->occurrences ?: 1)),
        ];
    }

    private function buildOneToOneAvailabilitySlots(string $startDate, string $startTime, int $duration, array $recurrence): array
    {
        $startAt = \Carbon\Carbon::parse($startDate . ' ' . $startTime);
        $type = strtolower((string) ($recurrence['type'] ?? 'none'));
        $interval = max(1, (int) ($recurrence['repeat_interval'] ?? 1));
        $weeklyDays = collect($recurrence['weekly_days'] ?? [])
            ->map(fn ($day) => (int) $day)
            ->filter(fn ($day) => $day >= 1 && $day <= 7)
            ->unique()
            ->values()
            ->all();
        $endMode = strtolower((string) ($recurrence['end_mode'] ?? 'none'));
        $endTimes = max(1, (int) ($recurrence['end_times'] ?? 1));
        $endDate = !empty($recurrence['end_date_time']) ? \Carbon\Carbon::parse($recurrence['end_date_time']) : null;

        $slots = [];

        if ($type === 'none') {
            $slots[] = [
                'date' => $startAt->toDateString(),
                'start_time' => $startAt->format('H:i'),
                'end_time' => $startAt->copy()->addMinutes($duration)->format('H:i'),
            ];

            return $slots;
        }

        if ($type === 'daily') {
            $cursor = $startAt->copy();
            $limit = $endMode === 'occurrences' ? $endTimes : max(60, $endTimes);
            while (count($slots) < $limit) {
                if ($endDate && $cursor->gt($endDate)) {
                    break;
                }

                $slots[] = [
                    'date' => $cursor->toDateString(),
                    'start_time' => $cursor->format('H:i'),
                    'end_time' => $cursor->copy()->addMinutes($duration)->format('H:i'),
                ];

                $cursor->addDays($interval);
            }
        } elseif ($type === 'weekly') {
            if (empty($weeklyDays)) {
                $weeklyDays = [((int) $startAt->dayOfWeek) + 1];
            }

            $cursor = $startAt->copy();
            $limit = $endMode === 'occurrences' ? $endTimes : max(120, $endTimes);
            while (count($slots) < $limit) {
                if ($endDate && $cursor->gt($endDate)) {
                    break;
                }

                $weekDiff = (int) floor($startAt->copy()->startOfWeek()->diffInDays($cursor->copy()->startOfWeek()) / 7);
                $dayCode = ((int) $cursor->dayOfWeek) + 1;

                if ($cursor->gte($startAt) && $weekDiff % $interval === 0 && in_array($dayCode, $weeklyDays, true)) {
                    $slots[] = [
                        'date' => $cursor->toDateString(),
                        'start_time' => $cursor->format('H:i'),
                        'end_time' => $cursor->copy()->addMinutes($duration)->format('H:i'),
                    ];
                }

                $cursor->addDay();
            }
        } else {
            $cursor = $startAt->copy();
            $limit = $endMode === 'occurrences' ? $endTimes : max(24, $endTimes);
            while (count($slots) < $limit) {
                if ($endDate && $cursor->gt($endDate)) {
                    break;
                }

                $slots[] = [
                    'date' => $cursor->toDateString(),
                    'start_time' => $cursor->format('H:i'),
                    'end_time' => $cursor->copy()->addMinutes($duration)->format('H:i'),
                ];

                $cursor->addMonthsNoOverflow($interval);
            }
        }

        return $slots;
    }

    private function ensureTeacherAssignedToCourse(int $courseId, int $teacherId): void
    {
        $assignment = CourseLiveTeacherAssignment::where('course_id', $courseId)
            ->whereNull('batch_id')
            ->where('teacher_instructor_id', $teacherId)
            ->first();

        if ($assignment) {
            if ((int) $assignment->is_active !== 1) {
                $assignment->is_active = 1;
                $assignment->assigned_by_admin_id = auth('admin')->id();
                $assignment->save();
            }
            return;
        }

        CourseLiveTeacherAssignment::create([
            'course_id' => $courseId,
            'batch_id' => null,
            'teacher_instructor_id' => $teacherId,
            'assigned_by_admin_id' => auth('admin')->id(),
            'is_active' => 1,
        ]);
    }

    public function oneToOneSchedule()
    {
        $pageTitle = 'One-To-One Meeting Schedule';
        $emptyMessage = 'No one-to-one bookings found';
        $search = trim((string) request('search'));

        $bookings = CourseLiveBooking::query()
            ->where('class_type', 'one_to_one')
            ->whereHas('purchase', function ($query) {
                $query->where('payment_status', Status::PAYMENT_SUCCESS);
            })
            ->with(['course.instructor', 'course.sections.curriculums.lectures', 'user', 'assignedTeacher', 'purchase'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($bookingQuery) use ($search) {
                    $bookingQuery->whereHas('course', function ($courseQuery) use ($search) {
                        $courseQuery->where('title', 'like', "%{$search}%");
                    })->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('username', 'like', "%{$search}%")
                            ->orWhere('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%");
                    });
                });
            })
            ->latest()
            ->paginate(getPaginate());

        $availableTeachersByBooking = [];
        $meetingSessionsByBooking = [];
        $allTeachers = Instructor::onlyTeachers()->active()->orderBy('firstname')->get(['id', 'firstname', 'lastname', 'username', 'email']);
        foreach ($bookings as $booking) {
            $courseTeachers = CourseLiveTeacherAssignment::with('teacher')
                ->where('course_id', $booking->course_id)
                ->whereNull('batch_id')
                ->where('is_active', 1)
                ->get();

            $availableTeachersByBooking[$booking->id] = $courseTeachers->values();

            $meetingSessionsByBooking[$booking->id] = CourseLiveSession::with('lecture')
                ->where('course_purchased_id', $booking->course_purchased_id)
                ->where('user_id', $booking->user_id)
                ->orderBy('session_number')
                ->get();
        }

        return view('admin.courses.one_to_one_schedule', compact('pageTitle', 'bookings', 'emptyMessage', 'availableTeachersByBooking', 'meetingSessionsByBooking', 'allTeachers'));
    }

    public function updateOneToOneSession(Request $request, $sessionId)
    {
        if ($request->filled('start_time')) {
            $parsedStartTime = strtotime((string) $request->start_time);
            if ($parsedStartTime !== false) {
                $request->merge([
                    'start_time' => date('H:i', $parsedStartTime),
                ]);
            }
        }

        if ($request->filled('start_date')) {
            $parsedStartDate = strtotime((string) $request->start_date);
            if ($parsedStartDate !== false) {
                $request->merge([
                    'start_date' => date('Y-m-d', $parsedStartDate),
                ]);
            }
        }

        $request->validate([
            'teacher_instructor_id' => 'required|integer',
            'start_date' => 'required|date|after_or_equal:today',
            'start_time' => ['required', 'date_format:H:i', 'after_or_equal:09:00', 'before_or_equal:21:00', 'regex:/^\d{2}:(00|30)$/'],
            'class_duration' => 'nullable|integer|min:1|max:480',
        ]);

        $session = CourseLiveSession::with(['course', 'batch', 'lecture'])->findOrFail($sessionId);
        $teacherId = (int) $request->teacher_instructor_id;
        $teacher = Instructor::onlyTeachers()->active()->find($teacherId);

        if (!$teacher) {
            $notify[] = ['error', 'Invalid teacher selected'];
            return back()->withNotify($notify);
        }

        $duration = (int) ($request->class_duration ?: $session->duration_minutes ?: $session->batch?->course?->default_class_duration ?: 60);
        $startDate = (string) $request->start_date;
        $startTime = (string) $request->start_time;
        $endTime = date('H:i', strtotime('+' . $duration . ' minutes', strtotime($startDate . ' ' . $startTime)));

        if ($startTime < '09:00' || $startTime > '21:00' || $endTime > '21:00') {
            $notify[] = ['error', 'Meeting time must be between 09:00 and 21:00'];
            return back()->withNotify($notify);
        }

        if (!preg_match('/^\d{2}:(00|30)$/', $startTime)) {
            $notify[] = ['error', 'Meeting start time must be in 30-minute intervals'];
            return back()->withNotify($notify);
        }

        if (LiveClassScheduler::hasTeacherConflict($teacherId, $startDate, $startTime, $endTime, null, null, null, $session->id)) {
            $notify[] = ['error', 'Selected teacher is not available for the chosen time'];
            return back()->withNotify($notify);
        }

        $zoom = app(LmsZoomService::class);
        $oldTeacherId = (int) ($session->assigned_teacher_id ?? 0);
        $oldStartAt = $session->scheduled_at ? Carbon::parse($session->scheduled_at) : null;
        $oldDuration = (int) ($session->duration_minutes ?? 0);
        $oldTeacher = $oldTeacherId > 0 ? Instructor::find($oldTeacherId) : null;
        $purchase = $session->course_purchased_id ? CoursePurchased::with('user')->find($session->course_purchased_id) : null;
        $student = $purchase?->user ?: $session->user;

        $session->assigned_teacher_id = $teacherId;
        $session->scheduled_at = Carbon::parse($startDate . ' ' . $startTime);
        $session->duration_minutes = $duration;
        $session->status = 'scheduled';
        $session->save();

        $zoomUpdateSkipped = false;
        try {
            if (!empty($session->zoom_meeting_id)) {
                $startAt = Carbon::parse($session->scheduled_at, config('zoom.meeting.timezone', config('app.timezone', 'UTC')));
                $zoom->updateMeeting($session->zoom_meeting_id, [
                    'meeting_name' => (string) ($session->meeting_name ?: optional($session->lecture)->title ?: 'Live Session'),
                    'start_time' => $startAt,
                    'duration_minutes' => $duration,
                    'agenda' => (string) (optional($session->lecture)->title ?: optional($session->course)->title ?: 'Live Session'),
                ]);

                try {
                    $details = $zoom->meetingDetails($session->zoom_meeting_id);
                    $session->zoom_join_url = $details['join_url'] ?? $session->zoom_join_url;
                    $session->zoom_host_email = $details['host_email'] ?? $session->zoom_host_email;
                    $session->zoom_host_user_id = $details['host_id'] ?? $session->zoom_host_user_id;
                    $session->save();
                } catch (\Throwable $ignored) {
                    // Keep the update successful even if Zoom details cannot be refreshed.
                }
            }
        } catch (\Throwable $throwable) {
            if (!empty($session->zoom_meeting_id) && str_contains(strtolower($throwable->getMessage()), 'does not contain scope')) {
                $zoomUpdateSkipped = true;
            } else {
                if ($oldStartAt) {
                    $session->scheduled_at = $oldStartAt;
                }
                $session->assigned_teacher_id = $oldTeacherId ?: $session->assigned_teacher_id;
                $session->duration_minutes = $oldDuration ?: $session->duration_minutes;
                $session->save();
                $notify[] = ['error', $throwable->getMessage()];
                return back()->withNotify($notify);
            }
        }

        if ($oldTeacherId !== $teacherId) {
            $session->status = 'assigned';
            $session->save();
        }

        $newStartAt = Carbon::parse($session->scheduled_at);
        $hasTimingChange = !$oldStartAt || $oldStartAt->ne($newStartAt) || (int) $oldDuration !== (int) $duration || $oldTeacherId !== $teacherId;
        if ($hasTimingChange) {
            $grade = (string) ($session->course?->category?->name ?: $session->course?->grade ?: 'N/A');
            $courseName = (string) ($session->course?->title ?: 'N/A');
            $teacherName = trim((string) ($teacher->fullname ?: trim(($teacher->firstname ?? '') . ' ' . ($teacher->lastname ?? '')))) ?: 'Teacher';
            $oldDateTimeLabel = $oldStartAt ? $oldStartAt->format('d M Y h:i A') : 'N/A';
            $newDateTimeLabel = $newStartAt->format('d M Y h:i A');

            if ($student && !empty($student->email)) {
                $studentName = trim((string) ($student->fullname ?: trim(($student->firstname ?? '') . ' ' . ($student->lastname ?? '')))) ?: 'Student';
                try {
                    notify($student, 'DEFAULT', [
                        'subject' => '1:1 Session Rescheduled - Updated Schedule',
                        'message' => 'Hello,<br><br>'
                            . 'Your 1:1 session has been rescheduled.<br><br>'
                            . 'Updated Session Details<br>'
                            . 'Student Name: ' . $studentName . '<br>'
                            . 'Course Name: ' . $courseName . '<br>'
                            . 'Grade: ' . $grade . '<br>'
                            . 'Previous Date & Time: ' . $oldDateTimeLabel . '<br>'
                            . 'New Date & Time: ' . $newDateTimeLabel . '<br>'
                            . 'Teacher: ' . $teacherName . '<br><br>'
                            . 'Thank you,',
                    ], ['email'], false);
                } catch (\Throwable $throwable) {
                    \Illuminate\Support\Facades\Log::warning('Failed to send 1:1 reschedule email to student', [
                        'session_id' => $session->id,
                        'error' => $throwable->getMessage(),
                    ]);
                }
            }

            if ($teacher && !empty($teacher->email)) {
                $studentNameForTeacher = $student ? (trim((string) ($student->fullname ?: trim(($student->firstname ?? '') . ' ' . ($student->lastname ?? '')))) ?: 'Student') : 'Student';
                try {
                    notify($teacher, 'DEFAULT', [
                        'subject' => '1:1 Session Rescheduled - ' . $studentNameForTeacher,
                        'message' => 'Hello ' . $teacherName . ',<br><br>'
                            . 'The following 1:1 session has been rescheduled.<br><br>'
                            . 'Updated Session Details<br>'
                            . 'Student Name: ' . $studentNameForTeacher . '<br>'
                            . 'Grade: ' . $grade . '<br>'
                            . 'Course Name: ' . $courseName . '<br>'
                            . 'Previous Date & Time: ' . $oldDateTimeLabel . '<br>'
                            . 'New Date & Time: ' . $newDateTimeLabel . '<br><br>'
                            . 'Thank you,',
                    ], ['email'], false);
                } catch (\Throwable $throwable) {
                    \Illuminate\Support\Facades\Log::warning('Failed to send 1:1 reschedule email to teacher', [
                        'session_id' => $session->id,
                        'error' => $throwable->getMessage(),
                    ]);
                }
            }
        }

        if ($zoomUpdateSkipped) {
            $notify[] = ['success', 'Session updated in system,'];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', 'One-to-one meeting updated successfully'];
        return back()->withNotify($notify);
    }

    public function oneToOneAvailableTeachers(Request $request, $bookingId)
    {
        if ($request->filled('start_time')) {
            $parsedStartTime = strtotime((string) $request->start_time);
            if ($parsedStartTime !== false) {
                $request->merge([
                    'start_time' => date('H:i', $parsedStartTime),
                ]);
            }
        }

        if ($request->filled('start_date')) {
            $parsedStartDate = strtotime((string) $request->start_date);
            if ($parsedStartDate !== false) {
                $request->merge([
                    'start_date' => date('Y-m-d', $parsedStartDate),
                ]);
            }
        }

        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'start_time' => ['required', 'date_format:H:i', 'after_or_equal:09:00', 'before_or_equal:21:00', 'regex:/^\d{2}:(00|30)$/'],
            'class_duration' => 'nullable|integer|min:1|max:480',
            'recurrence_type' => 'nullable|in:none,daily,weekly,monthly',
            'repeat_every' => 'nullable|integer|min:1|max:90',
            'weekly_days' => 'nullable|array',
            'weekly_days.*' => 'integer|min:1|max:7',
            'end_type' => 'nullable|in:no_end,by,after',
            'end_date' => 'nullable|date',
            'occurrences' => 'nullable|integer|min:1|max:50',
        ]);

        $booking = CourseLiveBooking::with('course')->findOrFail($bookingId);
        $duration = (int) ($request->class_duration ?: $booking->class_duration ?: $booking->course?->default_class_duration ?: 60);
        $recurrence = $this->normalizeOneToOneRecurrence($request);
        $slots = $this->buildOneToOneAvailabilitySlots((string) $request->start_date, (string) $request->start_time, $duration, $recurrence);

        if (empty($slots)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unable to build schedule slots from the selected criteria.',
            ], 422);
        }

        $teachers = \App\Models\Instructor::onlyTeachers()
            ->active()
            ->select('id', 'firstname', 'lastname', 'email', 'username', 'image')
            ->orderBy('firstname')
            ->get();

        $availableTeachers = $teachers->filter(function ($teacher) use ($slots, $booking) {
            foreach ($slots as $slot) {
                if (LiveClassScheduler::hasTeacherConflict((int) $teacher->id, $slot['date'], $slot['start_time'], $slot['end_time'], $booking->id)) {
                    return false;
                }
            }

            return true;
        })->values();

        return response()->json([
            'status' => 'success',
            'teachers' => $availableTeachers,
            'slot_count' => count($slots),
        ]);
    }


    public function approve($id)
    {
        $course = Course::where('id', $id)->where('status', Status::PENDING)->firstOrFail();
        $course->status = Status::APPROVED;
        $course->save();
        $notify[] = ['success', 'Course approved successfully'];
        return back()->withNotify($notify);
    }

    public function reject(Request $request, $id)
    {

        $course = Course::where('id', $id)->where('status','!=',Status::PENDING)->firstOrFail();
        $course->status = Status::REJECT;
        $course->reject_reason = $request->reason;

        $course->save();
        $notify[] = ['success', 'Course approved successfully'];
        return back()->withNotify($notify);
    }






    public function downloadResource($id)
    {
        $resource = CourseResource::find($id);

        if (!$resource) {
            $notify[] = ['error', 'Invalid Resource'];
            return back()->withNotify($notify);
        }

        $course = $resource->course;

        if (!$course) {
            $notify[] = ['error', 'Invalid Course'];
            return back()->withNotify($notify);
        }

        $file = $resource->file;
        $path = getFilePath('resources');
        $fullPath = $path . '/' . $file;
        $cloudDisk = env('UPLOAD_DISK', 's3');
        $isCloud = $cloudDisk === 's3';
        $cloudKey = ltrim($fullPath, '/');

        if ($isCloud && !Storage::disk($cloudDisk)->exists($cloudKey)) {
            $notify[] = 'resources not found';
            return back()->withNotify($notify);
        }

        if (!$isCloud && !file_exists($fullPath)) {

            $notify[] = 'resources not found';
            return back()->withNotify($notify);
        }

        $title = slug($resource->file);

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimetype = $isCloud
            ? (Storage::disk($cloudDisk)->mimeType($cloudKey) ?: 'application/octet-stream')
            : mime_content_type($fullPath);

        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET,');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        $downloadName = $title . '.' . $ext;
        if ($isCloud) {
            return response()->streamDownload(function () use ($cloudDisk, $cloudKey) {
                echo Storage::disk($cloudDisk)->get($cloudKey);
            }, $downloadName, [
                'Content-Type' => $mimetype,
            ]);
        }

        return response()->download($fullPath, $downloadName, [
            'Content-Type' => $mimetype,
        ]);
    }



    public function certificateTemplate()
    {
        $pageTitle = 'Certificate Template';
        return view('admin.course.certificate_template', compact('pageTitle'));
    }

    public function certificateTemplateUpdate(Request $request)
    {
        $request->validate([
            'certificate_template' => 'required'
        ]);

        $general = gs();
        $general->verify_url = $request->verify_url;
        $general->certificate_template = $request->certificate_template;
        $general->save();

        $notify[] = ['success', 'certificate template settings updated successfully'];
        return back()->withNotify($notify);
    }


    public function popular($slug)
    {
        $course = Course::where('slug', $slug)->first();

        if (!$course) {
            $notify[] = ['error', 'Invalid course'];
            return back()->withNotify($notify);
        }
        $course->is_populer = Status::YES;
        $course->save();
        $notify[] = ['success', 'Course popular status updated successfully'];
        return back()->withNotify($notify);
    }

    public function updateBatchLimit(Request $request, $slug)
    {
        $request->validate([
            'max_batches_per_course' => 'required|integer|min:1|max:100',
        ]);

        $course = Course::where('slug', $slug)->first();

        if (!$course) {
            $notify[] = ['error', 'Invalid course'];
            return back()->withNotify($notify);
        }

        $course->max_batches_per_course = (int) $request->max_batches_per_course;
        $course->save();

        $notify[] = ['success', 'Batch limit updated successfully'];
        return back()->withNotify($notify);
    }

    public function updateBatchCapacity(Request $request, $slug, $batchId)
    {
        $request->validate([
            'capacity' => 'required|integer|min:1|max:500',
        ]);

        $course = Course::where('slug', $slug)->first();

        if (!$course) {
            $notify[] = ['error', 'Invalid course'];
            return back()->withNotify($notify);
        }

        $batch = CourseLiveBatch::where('course_id', $course->id)->find($batchId);
        if (!$batch) {
            $notify[] = ['error', 'Invalid batch'];
            return back()->withNotify($notify);
        }

        $batch->capacity = (int) $request->capacity;
        $batch->save();

        $notify[] = ['success', 'Batch capacity updated successfully'];
        return back()->withNotify($notify);
    }

    public function releaseLectureForBatch(Request $request, $slug)
    {
        $request->validate([
            'lecture_id' => 'required|integer',
            'batch_id' => 'required|integer',
            'is_released' => 'required|boolean',
        ]);

        $course = Course::where('slug', $slug)->first();
        if (!$course) {
            $notify[] = ['error', 'Invalid course'];
            return back()->withNotify($notify);
        }

        $lecture = CourseLecture::where('course_id', $course->id)->find($request->lecture_id);
        if (!$lecture) {
            $notify[] = ['error', 'Invalid lecture'];
            return back()->withNotify($notify);
        }

        $batch = CourseLiveBatch::where('course_id', $course->id)->find($request->batch_id);
        if (!$batch) {
            $batch = CourseZoomBatch::where('course_id', $course->id)->find($request->batch_id);
        }
        if (!$batch) {
            $notify[] = ['error', 'Invalid batch'];
            return back()->withNotify($notify);
        }

        CourseLectureBatchRelease::updateOrCreate(
            [
                'course_lecture_id' => (int) $lecture->id,
                'batch_id' => (int) $batch->id,
            ],
            [
                'course_id' => (int) $course->id,
                'course_section_id' => (int) $lecture->course_section_id,
                'is_released' => (bool) $request->boolean('is_released'),
                'released_by_type' => 'admin',
                'released_by_id' => (int) auth('admin')->id(),
            ]
        );

        $notify[] = ['success', 'Lecture release updated for selected batch'];
        return back()->withNotify($notify);
    }

    public function releaseLectureForOneToOneStudent(Request $request, $bookingId)
    {
        $request->validate([
            'lecture_id' => 'required|integer',
            'is_released' => 'required|boolean',
        ]);

        $booking = CourseLiveBooking::with('course')->where('class_type', 'one_to_one')->find($bookingId);
        if (!$booking || !$booking->course) {
            $notify[] = ['error', 'Invalid one-to-one booking'];
            return back()->withNotify($notify);
        }

        $lecture = CourseLecture::where('course_id', $booking->course_id)->find($request->lecture_id);
        if (!$lecture) {
            $notify[] = ['error', 'Invalid lecture'];
            return back()->withNotify($notify);
        }

        CourseLectureOneToOneRelease::updateOrCreate(
            [
                'course_lecture_id' => (int) $lecture->id,
                'live_booking_id' => (int) $booking->id,
            ],
            [
                'course_id' => (int) $booking->course_id,
                'course_section_id' => (int) $lecture->course_section_id,
                'user_id' => (int) $booking->user_id,
                'is_released' => (bool) $request->boolean('is_released'),
                'released_by_type' => 'admin',
                'released_by_id' => (int) auth('admin')->id(),
            ]
        );

        $notify[] = ['success', 'One-to-one lecture release updated for selected student'];
        return back()->withNotify($notify);
    }

    public function removePopular($slug)
    {
        $course = Course::where('slug', $slug)->first();

        if (!$course) {
            $notify[] = ['error', 'Invalid course'];
            return back()->withNotify($notify);
        }
        $course->is_populer = Status::NO;
        $course->save();
        $notify[] = ['success', 'Course popular status updated successfully'];
        return back()->withNotify($notify);
    }



    public function enrolledCourses($id = 0)
    {
        $pageTitle = 'Enrolled Courses';
        $emptyMessage = 'No enrolled courses found';
        $enrolledCourses = CoursePurchased::where('payment_status', Status::PAYMENT_SUCCESS);
        if ($id > 0) {
            $enrolledCourses = $enrolledCourses->where('user_id', $id);
        }
        $enrolledCourses = $enrolledCourses->searchable(['user:username', 'course:title'])
            ->with([
                'user',
                'course' => function ($query) {
                    $query->withTrashed()->with(['category', 'subcategory']);
                },
            ])
            ->orderBy('id', 'desc')
            ->paginate(getPaginate());
        return view('admin.courses.enrolled_courses', compact('pageTitle', 'enrolledCourses', 'emptyMessage'));
    }

    public function oneToOneStudents()
    {
        $pageTitle = 'One To One Students';
        $emptyMessage = 'No one-to-one students found';
        $search = trim((string) request('search'));

        $courses = Course::query()
            ->with([
                'liveBookings' => function ($query) {
                    $query->where('class_type', 'one_to_one')
                        ->whereHas('purchase', function ($purchaseQuery) {
                            $purchaseQuery->where('payment_status', Status::PAYMENT_SUCCESS);
                        })
                        ->with(['user', 'assignedTeacher', 'purchase', 'sessions.lecture'])
                        ->latest();
                },
            ])
            ->whereHas('liveBookings', function ($query) use ($search) {
                $query->where('class_type', 'one_to_one')
                    ->whereHas('purchase', function ($purchaseQuery) {
                        $purchaseQuery->where('payment_status', Status::PAYMENT_SUCCESS);
                    });

                if ($search !== '') {
                    $query->where(function ($bookingQuery) use ($search) {
                        $bookingQuery->whereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('username', 'like', "%{$search}%")
                                ->orWhere('firstname', 'like', "%{$search}%")
                                ->orWhere('lastname', 'like', "%{$search}%");
                        });
                    });
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($courseQuery) use ($search) {
                    $courseQuery->where('title', 'like', "%{$search}%")
                        ->orWhereHas('instructor', function ($instructorQuery) use ($search) {
                            $instructorQuery->where('username', 'like', "%{$search}%");
                        });
                });
            })
            ->with('instructor')
            ->orderBy('title')
            ->paginate(getPaginate());

        $bookingIds = collect($courses->items())
            ->flatMap(function ($course) {
                return ($course->liveBookings ?? collect())->pluck('id');
            })
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $oneToOneReleaseMap = CourseLectureOneToOneRelease::whereIn('live_booking_id', $bookingIds)
            ->get(['live_booking_id', 'course_lecture_id', 'is_released'])
            ->mapWithKeys(function ($item) {
                return [((int) $item->live_booking_id) . ':' . ((int) $item->course_lecture_id) => (bool) $item->is_released];
            });

        return view('admin.courses.students_one_to_one', compact('pageTitle', 'emptyMessage', 'courses', 'oneToOneReleaseMap'));
    }

    public function groupStudents()
    {
        $pageTitle = 'Group Students';
        $emptyMessage = 'No group students found';
        $search = trim((string) request('search'));

        $courses = Course::query()
            ->with([
                'liveBatches' => function ($query) {
                    $query->where('class_type', 'group')
                        ->whereHas('bookings', function ($bookingQuery) {
                            $bookingQuery->where('class_type', 'group')
                                ->whereHas('purchase', function ($purchaseQuery) {
                                    $purchaseQuery->where('payment_status', Status::PAYMENT_SUCCESS);
                                });
                        })
                        ->with([
                            'bookings' => function ($bookingQuery) {
                                $bookingQuery->where('class_type', 'group')
                                    ->whereHas('purchase', function ($purchaseQuery) {
                                        $purchaseQuery->where('payment_status', Status::PAYMENT_SUCCESS);
                                    })
                                    ->with(['user', 'assignedTeacher', 'purchase'])
                                    ->latest();
                            },
                            'teachers.teacher',
                        ])
                        ->orderBy('start_date');
                },
            ])
            ->whereHas('liveBatches', function ($batchQuery) use ($search) {
                $batchQuery->where('class_type', 'group')
                    ->whereHas('bookings', function ($bookingQuery) use ($search) {
                        $bookingQuery->where('class_type', 'group')
                            ->whereHas('purchase', function ($purchaseQuery) {
                                $purchaseQuery->where('payment_status', Status::PAYMENT_SUCCESS);
                            });

                        if ($search !== '') {
                            $bookingQuery->whereHas('user', function ($userQuery) use ($search) {
                                $userQuery->where('username', 'like', "%{$search}%")
                                    ->orWhere('firstname', 'like', "%{$search}%")
                                    ->orWhere('lastname', 'like', "%{$search}%");
                            });
                        }
                    });
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($courseQuery) use ($search) {
                    $courseQuery->where('title', 'like', "%{$search}%")
                        ->orWhereHas('instructor', function ($instructorQuery) use ($search) {
                            $instructorQuery->where('username', 'like', "%{$search}%");
                        })
                        ->orWhereHas('liveBatches', function ($batchQuery) use ($search) {
                            $batchQuery->where('title', 'like', "%{$search}%");
                        });
                });
            })
            ->with('instructor')
            ->orderBy('title')
            ->paginate(getPaginate());

        return view('admin.courses.students_group', compact('pageTitle', 'emptyMessage', 'courses'));
    }


    public function certificatePreview($id)
    {
        $getCertificate = GetCertificateUser::searchable('user:username', 'course:title')
            ->with('user', 'course')
            ->findOrFail($id);
        $user = $getCertificate->user;
        if (!$user) {
            abort(404);
        }
        $course = $getCertificate->course;
        if (!$course) {
            abort(404);
        }
        $courseComplete = $course->completes()->where('user_id', $user->id)->first();
        if (!$courseComplete) {
            abort(404);
        }
        $url  = gs('verify_url') . '/' . $getCertificate->secret;
        $qrCodeUrl = certificateQr($url);
        $logo = '<img src="data:image/png;base64,' . base64_encode(file_get_contents(getFilePath('logoIcon') . '/logo_dark.png')) . '" />';
        $qrCode = '<img src="data:image/png;base64,' . base64_encode(file_get_contents($qrCodeUrl)) . '" />';
        $certificate = str_replace("{{student_name}}", $user->firstname . ' ' . $user->lastname, gs('certificate_template'));
        $certificate = str_replace("{{site_name}}", gs('site_name'), $certificate);
        $certificate = str_replace("{{site_logo}}", $logo, $certificate);
        $certificate = str_replace("{{qr_code}}", $qrCode, $certificate);
        $certificate = str_replace("{{course_title}}", $course->title, $certificate);
        $certificate = str_replace("{{instructor_name}}", $course->instructor->firstname . ' ' . $course->instructor->lastname, $certificate);
        $certificate = str_replace("{{course_completion_date}}", showDateTime($courseComplete->created_at, 'F j, Y'), $certificate);
        $certificate = str_replace("{{course_based_message}}", $course->congrats_message, $certificate);
        $data = [
            'certificate' => $certificate
        ];
        // Generate PDF
        $pdf = Pdf::loadView('certificate_pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption('dpi', 110)
            ->setOption('defaultFont', 'sans-serif');
        //
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="certificate.pdf"');
    }
}
