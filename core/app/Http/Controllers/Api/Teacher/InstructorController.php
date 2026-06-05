<?php

namespace App\Http\Controllers\Api\Teacher;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\LiveClassScheduler;
use App\Lib\FormProcessor;
use App\Lib\GoogleAuthenticator;
use App\Models\Course;
use App\Models\CourseComplete;
use App\Models\CourseLecture;
use App\Models\CourseLectureOneToOneRelease;
use App\Models\CourseSection;
use App\Models\CourseLiveSession;
use App\Models\CourseLiveTeacherAssignment;
use App\Models\CourseLiveBatch;
use App\Models\CourseLiveBooking;
use App\Models\CoursePurchased;
use App\Models\CourseZoomBatch;
use App\Models\CourseZoomMeetingOccurrence;
use App\Models\Form;
use App\Models\Review;
use App\Models\AdminNotification;
use App\Models\TeacherLeave;
use App\Models\TeacherLeaveNotificationRecipient;
use App\Models\TeacherUnavailabilitySlot;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserProgress;
use App\Models\Withdrawal;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class InstructorController extends Controller
{
    public function leaveList()
    {
        $leaves = TeacherLeave::where('teacher_instructor_id', auth()->id())
            ->latest()
            ->limit(100)
            ->get();

        $notify[] = 'Teacher leave list';
        return responseSuccess('teacher_leave_list', $notify, [
            'leaves' => $leaves,
        ]);
    }

    public function applyLeave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'from_date' => 'required|date|after_or_equal:today',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $blockStart = now();
        $blockEnd = $blockStart->copy()->addHours(6);
        if (LiveClassScheduler::hasUpcomingMeetingWithinLeaveRange(
            (int) auth()->id(),
            $blockStart,
            $blockEnd,
            (string) $request->from_date,
            (string) $request->to_date
        )) {
            $notify[] = 'You cannot apply for leave within 6 hours of a scheduled meeting.';
            return responseError('leave_blocked', $notify);
        }

        $hasOverlap = TeacherLeave::where('teacher_instructor_id', auth()->id())
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) use ($request) {
                $query->whereBetween('from_date', [$request->from_date, $request->to_date])
                    ->orWhereBetween('to_date', [$request->from_date, $request->to_date])
                    ->orWhere(function ($inner) use ($request) {
                        $inner->where('from_date', '<=', $request->from_date)
                            ->where('to_date', '>=', $request->to_date);
                    });
            })
            ->exists();

        if ($hasOverlap) {
            $notify[] = 'You already have a pending or approved leave request in this date range.';
            return responseError('leave_overlap', $notify);
        }

        $leave = TeacherLeave::create([
            'teacher_instructor_id' => auth()->id(),
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
            'reason' => trim((string) $request->reason),
            'status' => 'pending',
        ]);

        $teacher = auth()->user();

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = 0;
        $adminNotification->instructor_id = (int) $teacher->id;
        $adminNotification->title = 'New teacher leave request from ' . $teacher->fullname;
        $adminNotification->click_url = urlPath('admin.teachers.leaves');
        $adminNotification->save();

        if (gs('en')) {
            $recipients = TeacherLeaveNotificationRecipient::where('is_active', true)
                ->pluck('email')
                ->filter()
                ->all();

            $payload = [
                'teacher_name' => (string) $teacher->fullname,
                'teacher_username' => (string) $teacher->username,
                'date_range' => $request->from_date . ' - ' . $request->to_date,
                'reason' => trim((string) $request->reason),
            ];

            foreach (array_unique($recipients) as $recipientEmail) {
                try {
                    $recipient = [
                        'fullname' => $recipientEmail,
                        'username' => $recipientEmail,
                        'email' => $recipientEmail,
                    ];

                    notify($recipient, 'DEFAULT', [
                        'subject' => 'Teacher Leave Request - AIClub',
                        'message' => 'A teacher has submitted a leave request.<br><br>'
                            . 'Teacher: ' . $payload['teacher_name'] . ($payload['teacher_username'] ? ' (@' . $payload['teacher_username'] . ')' : '') . '<br>'
                            . 'Date Range: ' . $payload['date_range'] . '<br>'
                            . 'Reason: ' . $payload['reason'] . '<br><br>'
                            . 'Please review the request in the admin panel.',
                    ], ['email'], false);
                } catch (\Throwable $throwable) {
                    Log::error('Failed to send leave notification email to recipient', [
                        'recipient' => $recipientEmail,
                        'teacher_id' => $teacher->id,
                        'leave_id' => $leave->id,
                        'error' => $throwable->getMessage(),
                    ]);
                }
            }
        }

        $notify[] = 'Leave request submitted successfully';
        return responseSuccess('leave_submitted', $notify, [
            'leave' => $leave,
        ]);
    }

    public function unavailableSlotList()
    {
        $slots = TeacherUnavailabilitySlot::where('teacher_instructor_id', auth()->id())
            ->whereNull('source_type')
            ->orderBy('start_at', 'desc')
            ->limit(200)
            ->get();

        $notify[] = 'Teacher unavailable slots';
        return responseSuccess('teacher_unavailable_slots', $notify, [
            'slots' => $slots,
        ]);
    }

    public function markUnavailableSlot(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $startAt = Carbon::parse($request->start_at);
        $endAt = Carbon::parse($request->end_at);

        if ($startAt->lt(now()->startOfDay())) {
            $notify[] = 'Unavailable slot date cannot be in the past.';
            return responseError('validation_error', $notify);
        }

        if ($startAt->toDateString() !== $endAt->toDateString()) {
            $notify[] = 'Unavailable slot must be within the same day.';
            return responseError('validation_error', $notify);
        }

        $startTime = $startAt->format('H:i');
        $endTime = $endAt->format('H:i');

        if ($startTime < '09:00' || $endTime > '21:00') {
            $notify[] = 'Unavailable slot time must be between 09:00 and 21:00.';
            return responseError('validation_error', $notify);
        }
        if (((int) $startAt->format('i')) % 30 !== 0 || ((int) $endAt->format('i')) % 30 !== 0) {
            $notify[] = 'Unavailable slot must be in 30-minute intervals.';
            return responseError('validation_error', $notify);
        }

        $teacherId = (int) auth()->id();
        $slotDate = $startAt->toDateString();

        if (LiveClassScheduler::hasTeacherConflict($teacherId, $slotDate, $startTime, $endTime)) {
            $notify[] = 'You have a scheduled meeting in this time slot. You cannot mark it unavailable.';
            return responseError('meeting_conflict', $notify);
        }

        $overlap = TeacherUnavailabilitySlot::where('teacher_instructor_id', $teacherId)
            ->where(function ($query) use ($startAt, $endAt) {
                $query->whereBetween('start_at', [$startAt, $endAt])
                    ->orWhereBetween('end_at', [$startAt, $endAt])
                    ->orWhere(function ($inner) use ($startAt, $endAt) {
                        $inner->where('start_at', '<=', $startAt)
                            ->where('end_at', '>=', $endAt);
                    });
            })
            ->exists();

        if ($overlap) {
            $notify[] = 'This unavailable slot overlaps with an existing unavailable slot.';
            return responseError('slot_overlap', $notify);
        }

        $slot = TeacherUnavailabilitySlot::create([
            'teacher_instructor_id' => $teacherId,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'reason' => trim((string) ($request->reason ?? '')) ?: null,
        ]);

        $notify[] = 'Unavailable slot marked successfully';
        return responseSuccess('unavailable_slot_marked', $notify, [
            'slot' => $slot,
        ]);
    }


    public function dashboard()
    {

        $enrollCourses = CoursePurchased::where('instructor_id', auth()->id())
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->count();

        $totalEarning = CoursePurchased::where('instructor_id', auth()->id())
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->sum('amount');

        $activeCourse = Course::where('instructor_id', auth()->id())
            ->where('status', Status::YES)
            ->count();

        $totalWithdraw = Withdrawal::where('status', Status::PAYMENT_SUCCESS)
            ->where('instructor_id', auth()->id())
            ->sum('amount');

        $purchased = CoursePurchased::where('instructor_id', auth()->id())
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->selectRaw('SUM(amount) AS amount, DATE_FORMAT(created_at, "%Y-%m") AS month')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $data = $purchased->map(function ($item) {
            return [
                'month' => showDateTime($item->month, 'M'),
                'purchased' => showAmount($item->amount, currencyFormat: false),
            ];
        });

        $assignments = CourseLiveTeacherAssignment::where('teacher_instructor_id', auth()->id())
            ->where('is_active', 1)
            ->get();

        $allocatedBatchIds = $assignments->pluck('batch_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $allocatedCourseIds = $assignments->pluck('course_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $zoomAllocatedBatchIds = CourseZoomBatch::where('teacher_instructor_id', auth()->id())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->merge($allocatedBatchIds)
            ->filter()
            ->unique()
            ->values();

        $byTeacher = CourseLiveSession::with(['course', 'batch', 'user'])
            ->whereIn('status', ['scheduled', 'assigned', 'started'])
            ->where('assigned_teacher_id', auth()->id())
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->get();

        $byBatch = collect();
        if ($allocatedBatchIds->isNotEmpty()) {
            $byBatch = CourseLiveSession::with(['course', 'batch', 'user'])
                ->whereIn('status', ['scheduled', 'assigned', 'started'])
                ->whereIn('batch_id', $allocatedBatchIds->all())
                ->where(function ($query) {
                    $query->whereNull('assigned_teacher_id')
                        ->orWhere('assigned_teacher_id', auth()->id());
                })
                ->where('scheduled_at', '>=', now()->startOfDay())
                ->get();
        }

        $byCourse = collect();
        if ($allocatedCourseIds->isNotEmpty()) {
            $byCourse = CourseLiveSession::with(['course', 'batch', 'user'])
                ->whereIn('status', ['scheduled', 'assigned', 'started'])
                ->whereIn('course_id', $allocatedCourseIds->all())
                ->whereNull('batch_id')
                ->where(function ($query) {
                    $query->whereNull('assigned_teacher_id')
                        ->orWhere('assigned_teacher_id', auth()->id());
                })
                ->where('scheduled_at', '>=', now()->startOfDay())
                ->get();
        }

        $liveSessions = $byTeacher
            ->merge($byBatch)
            ->merge($byCourse)
            ->unique('id')
            ->sortBy('scheduled_at')
            ->values();

        $zoomOccurrences = CourseZoomMeetingOccurrence::with([
            'meeting:id,course_id,batch_id,zoom_join_url,status',
            'meeting.course:id,title',
            'meeting.batch:id,title,teacher_instructor_id',
        ])
            ->where(function ($query) use ($zoomAllocatedBatchIds) {
                $query->where('teacher_instructor_id', auth()->id());

                if ($zoomAllocatedBatchIds->isNotEmpty()) {
                    $query->orWhereHas('meeting', function ($meetingQuery) use ($zoomAllocatedBatchIds) {
                        $meetingQuery->whereIn('batch_id', $zoomAllocatedBatchIds->all());
                    });
                }
            })
            ->whereHas('meeting', function ($meetingQuery) {
                $meetingQuery->where('status', '!=', 'cancelled');
            })
            ->whereNotNull('start_time')
            ->where('status', '!=', 'cancelled')
            ->where('start_time', '>=', now()->startOfDay())
            ->orderBy('start_time')
            ->get()
            ->map(function ($occurrence) {
                $meeting = $occurrence->meeting;
                return [
                    'id' => 'standalone-occurrence-' . $occurrence->id,
                    'course' => $meeting?->course,
                    'batch' => $meeting?->batch,
                    'user' => null,
                    'session_number' => null,
                    'scheduled_at' => $occurrence->start_time,
                    'duration_minutes' => (int) $occurrence->duration_minutes,
                    'zoom_join_url' => $occurrence->zoom_join_url ?: ($meeting->zoom_join_url ?? null),
                    'status' => $occurrence->status ?: ($meeting->status ?? 'scheduled'),
                    'lecture_title' => $occurrence->title,
                ];
            });

        $liveSessions = collect($liveSessions
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'course' => $session->course,
                    'batch' => $session->batch,
                    'user' => $session->user,
                    'session_number' => $session->session_number,
                    'scheduled_at' => $session->scheduled_at,
                    'duration_minutes' => $session->duration_minutes,
                    'zoom_join_url' => $session->zoom_join_url,
                    'status' => $session->status,
                ];
            })
            ->all())
            ->merge($zoomOccurrences)
            ->sortBy('scheduled_at')
            ->values()
            ->take(50);

        $allocatedStudentsCount = 0;
        if ($allocatedCourseIds->isNotEmpty()) {
            $allocatedStudentsCount = CoursePurchased::whereIn('course_id', $allocatedCourseIds->all())
                ->where('payment_status', Status::PAYMENT_SUCCESS)
                ->distinct('user_id')
                ->count('user_id');
        }

        $sessionStudentsCount = $liveSessions
            ->pluck('user_id')
            ->filter()
            ->unique()
            ->count();

        $totalStudent = max($allocatedStudentsCount, $sessionStudentsCount);

        $notify[] = 'Dashboard';
        return responseSuccess('dashboard', $notify, [
            'enroll_courses' => $enrollCourses,
            'total_earning' => $totalEarning,
            'active_course' => $activeCourse,
            'total_student' => $totalStudent,
            'total_withdraw' => $totalWithdraw,
            'purchase_data' => $data,
            'live_sessions' => $liveSessions,
        ]);
    }


    public function userDataSubmit(Request $request)
    {

        $user = auth()->user();

        if ($user->profile_complete == Status::YES) {
            $notify[] = 'You\'ve already completed your profile';
            return responseError('already_completed', $notify);
        }


        $countryData  = (array)json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryCodes = implode(',', array_keys($countryData));
        $mobileCodes  = implode(',', array_column($countryData, 'dial_code'));
        $countries    = implode(',', array_column($countryData, 'country'));


        $validator = Validator::make($request->all(), [
            'country_code' => 'required|in:' . $countryCodes,
            'country'      => 'required|in:' . $countries,
            'mobile_code'  => 'required|in:' . $mobileCodes,
            'username'     => 'required|unique:users|min:6',
            'mobile'       => ['required', 'regex:/^([0-9]*)$/', Rule::unique('users')->where('dial_code', $request->mobile_code)],
        ]);


        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        if (preg_match("/[^a-z0-9_]/", trim($request->username))) {
            $notify[] = 'No special character, space or capital letters in username';
            return responseError('validation_error', $notify);
        }

        $user->country_code = $request->country_code;
        $user->mobile       = $request->mobile;
        $user->username     = $request->username;

        $user->address = $request->address;
        $user->city = $request->city;
        $user->state = $request->state;
        $user->zip = $request->zip;
        $user->country_name = @$request->country;
        $user->dial_code = $request->mobile_code;

        $user->profile_complete = Status::YES;

        $user->role_type = 'teacher';
        $user->save();

        $notify[] = 'Profile completed successfully';
        return responseSuccess('profile_completed', $notify, ['user' => $user]);
    }


    public function submitProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required',
            'lastname' => 'required',
            'profile_image' => ['nullable', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
            'biography' => "nullable|max:255",
            'designation' => 'required'
        ], [
            'firstname.required' => 'The first name field is required',
            'lastname.required' => 'The last name field is required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user = auth()->user();

        $user->firstname = $request->firstname;
        $user->lastname = $request->lastname;

        $user->address = $request->address;
        $user->city = $request->city;
        $user->state = $request->state;
        $user->zip = $request->zip;
        $user->zip = $request->zip;
        $user->biography = $request->biography;
        $user->designation = $request->designation;
        if ($request->hasFile('profile_image')) {
            $user->image = fileUploader($request->profile_image, getFIlePath('instructorProfile'), getFileSize('instructorProfile'), $user->profile_image);
        }

        $user->role_type = 'teacher';
        $user->save();

        $notify[] = 'Profile updated successfully';
        return responseSuccess('profile_updated', $notify, [
            'user' => $user,
        ]);
    }

    public function submitAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'website_url' => 'nullable|url',
            'facebook_url' => 'nullable|url',
            'instagram_url' => 'nullable|url',
            'linkedin_url' => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user = auth()->user();

        $user->account = [
            'website_url' => $request->website_url,
            'facebook_url' => $request->facebook_url,
            'instagram_url' => $request->instagram_url,
            'linkedin_url' => $request->linkedin_url,
        ];

        $user->role_type = 'teacher';
        $user->save();

        $notify[] = 'Account details updated successfully';
        return responseSuccess('account_updated', $notify, [
            'user' => $user,
        ]);
    }

    public function submitPassword(Request $request)
    {
        $passwordValidation = Password::min(6);
        if (gs('secure_password')) {
            $passwordValidation = $passwordValidation->mixedCase()->numbers()->symbols()->uncompromised();
        }

        $validator = Validator::make($request->all(), [
            'current_password' => 'required',
            'password' => ['required', 'confirmed', $passwordValidation]
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user = auth()->user();
        if (Hash::check($request->current_password, $user->password)) {
            $password = Hash::make($request->password);
            $user->password = $password;
            $user->role_type = 'teacher';
        $user->save();
            $notify[] = 'Password changed successfully';
            return responseSuccess('password_changed', $notify);
        } else {
            $notify[] = 'The password doesn\'t match!';
            return responseError('validation_error', $notify);
        }
    }





    public function instructorInfo()
    {
        $notify[] = 'Instructor information';
        return responseSuccess('instructor_info', $notify, ['user' => auth()->user()]);
    }


    public function purchaseHistory()
    {
        $purchases = CoursePurchased::where('instructor_id', auth()->id())
            ->with(['course','user'])->searchable(['course:title', 'trx'])->where('payment_status', Status::PAYMENT_SUCCESS)
            ->orderBy('created_at', 'desc')
            ->paginate(getPaginate());

        $notify[] = 'Purchase history';
        return responseSuccess('purchase_history', $notify, [
            'purchases' => $purchases,

        ]);
    }
    public function studentOverview()
    {
        $totalCourse = Course::where('instructor_id', auth()->id())
            ->where('status', Status::YES)
            ->count();
    
        $totalStudentEnrolled = User::whereHas('purchases', function ($query) {
            $query->where('payment_status', Status::PAYMENT_SUCCESS)
                ->where('instructor_id', auth()->id());
        })->count();
    
        $students = User::whereHas('purchases.course')->with([
            'purchases' => function ($query) {
                $query->where('payment_status', Status::PAYMENT_SUCCESS)
                    ->where('instructor_id', auth()->id());
            },
            'purchases.course.curriculums'
        ])->paginate(getPaginate()); 
        
        $studentProgress = $students->getCollection()->map(function ($student) {
            return $student->purchases->map(function ($purchase) use ($student) {
                $totalCurriculum = $purchase->course->curriculums->count();
                $completedCurriculum = UserProgress::where('user_id', $student->id)
                    ->where('course_id', $purchase->course->id)
                    ->count();
                $percentage = ($totalCurriculum > 0) ? ($completedCurriculum / $totalCurriculum) * 100 : 0;
    
                return [
                    'student_id' => $student->id,
                    'name' => $student->firstname . ' ' . $student->lastname,
                    'email' => $student->email,
                    'course_title' => $purchase->course->title,
                    'purchase_date' => $purchase->created_at,
                    'purchase_id' => $purchase->id,
                    'progress' => round($percentage, 2)
                ];
            });
        })->flatten(1); // Flatten to avoid nested arrays
    
        $completeCourse = CourseComplete::where('instructor_id', auth()->id())->count();
        $notify[] = 'Student overview';
    
        return responseSuccess('student_overview', $notify, [
            'students' => $students,
            'total_courses' => $totalCourse,
            'total_students_enrolled' => $totalStudentEnrolled,
            'student_progress' => $studentProgress, 
            'complete_course' => $completeCourse,
            'pagination' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function teacherStudents(Request $request)
    {
        $teacherId = (int) auth()->id();
        $classType = trim((string) $request->get('class_type', 'all'));
        $search = trim((string) $request->get('search', ''));

        $courseIds = CourseLiveTeacherAssignment::where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->pluck('course_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $query = CourseLiveBooking::query()
            ->whereHas('purchase', function ($purchaseQuery) {
                $purchaseQuery->where('payment_status', Status::PAYMENT_SUCCESS);
            })
            ->where(function ($bookingQuery) use ($teacherId, $courseIds) {
                $bookingQuery->where('assigned_teacher_id', $teacherId);
                if (!empty($courseIds)) {
                    $bookingQuery->orWhereIn('course_id', $courseIds);
                }
            })
            ->with([
                'user:id,firstname,lastname,username,email',
                'course:id,title,slug',
                'course.sections.curriculums.lectures:id,course_id,course_section_id,title',
                'batch:id,title',
                'purchase:id,amount,payment_status,created_at',
            ])
            ->orderByDesc('id');

        if (in_array($classType, ['one_to_one', 'group'])) {
            $query->where('class_type', $classType);
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('firstname', 'like', "%{$search}%")
                        ->orWhere('lastname', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('course', function ($courseQuery) use ($search) {
                    $courseQuery->where('title', 'like', "%{$search}%");
                });
            });
        }

        $students = $query->paginate(getPaginate());
        $courseIdsInPage = collect($students->items())
            ->pluck('course_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $batchIds = collect($students->items())
            ->pluck('batch_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $liveBatchTitles = CourseLiveBatch::whereIn('id', $batchIds)
            ->pluck('title', 'id')
            ->mapWithKeys(fn ($title, $id) => [(int) $id => (string) $title]);
        $zoomBatchTitles = CourseZoomBatch::whereIn('id', $batchIds)
            ->pluck('title', 'id')
            ->mapWithKeys(fn ($title, $id) => [(int) $id => (string) $title]);

        $students->setCollection(
            $students->getCollection()->map(function ($booking) use ($liveBatchTitles, $zoomBatchTitles) {
                $batchId = (int) ($booking->batch_id ?? 0);
                $resolvedTitle = '';
                if ($batchId > 0) {
                    $resolvedTitle = (string) ($liveBatchTitles[$batchId] ?? $zoomBatchTitles[$batchId] ?? '');
                }
                $booking->batch_title = $resolvedTitle;
                return $booking;
            })
        );

        $lectureOptionsByCourseId = collect();
        if (!empty($courseIdsInPage)) {
            $sectionTitlesById = CourseSection::whereIn('course_id', $courseIdsInPage)
                ->pluck('title', 'id');

            $lectureOptionsByCourseId = CourseLecture::whereIn('course_id', $courseIdsInPage)
                ->orderBy('id')
                ->get(['id', 'course_id', 'course_section_id', 'title'])
                ->groupBy('course_id')
                ->map(function ($lectures) use ($sectionTitlesById) {
                    return $lectures->map(function ($lecture) use ($sectionTitlesById) {
                        return [
                            'id' => (int) $lecture->id,
                            'title' => (string) $lecture->title,
                            'section_title' => (string) ($sectionTitlesById[(int) $lecture->course_section_id] ?? ''),
                        ];
                    })->values();
                });
        }

        $students->setCollection(
            $students->getCollection()->map(function ($booking) use ($lectureOptionsByCourseId) {
                $booking->lecture_options = $lectureOptionsByCourseId[(int) $booking->course_id] ?? collect();
                return $booking;
            })
        );

        $bookingIds = collect($students->items())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $oneToOneReleaseMap = CourseLectureOneToOneRelease::whereIn('live_booking_id', $bookingIds)
            ->get(['live_booking_id', 'course_lecture_id', 'is_released'])
            ->mapWithKeys(function ($item) {
                return [((int) $item->live_booking_id) . ':' . ((int) $item->course_lecture_id) => (bool) $item->is_released];
            });

        $notify[] = 'Teacher students';
        return responseSuccess('teacher_students', $notify, [
            'students' => $students,
            'one_to_one_release_map' => $oneToOneReleaseMap,
            'filters' => [
                'class_type' => $classType,
                'search' => $search,
            ],
        ]);
    }

    public function releaseOneToOneLecture(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'live_booking_id' => 'required|integer',
            'lecture_id' => 'required|integer',
            'is_released' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $teacherId = (int) auth()->id();
        $booking = CourseLiveBooking::where('id', (int) $request->live_booking_id)
            ->where('class_type', 'one_to_one')
            ->first();
        if (!$booking) {
            $notify[] = 'Invalid one-to-one booking';
            return responseError('booking_not_found', $notify);
        }

        $hasCourseAllocation = CourseLiveTeacherAssignment::where('course_id', $booking->course_id)
            ->where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->exists();
        $isDirectTeacher = (int) $booking->assigned_teacher_id === $teacherId;
        if (!$hasCourseAllocation && !$isDirectTeacher) {
            $notify[] = 'Student is not allocated to you';
            return responseError('not_allocated', $notify);
        }

        $lecture = CourseLecture::where('course_id', $booking->course_id)
            ->find((int) $request->lecture_id);
        if (!$lecture) {
            $notify[] = 'Invalid lecture';
            return responseError('lecture_not_found', $notify);
        }

        $release = CourseLectureOneToOneRelease::updateOrCreate(
            [
                'course_lecture_id' => (int) $lecture->id,
                'live_booking_id' => (int) $booking->id,
            ],
            [
                'course_id' => (int) $booking->course_id,
                'course_section_id' => (int) $lecture->course_section_id,
                'user_id' => (int) $booking->user_id,
                'is_released' => (bool) $request->boolean('is_released'),
                'released_by_type' => 'teacher',
                'released_by_id' => $teacherId,
            ]
        );

        $notify[] = $release->is_released
            ? 'Lecture released for one-to-one student successfully'
            : 'Lecture hidden for one-to-one student successfully';

        return responseSuccess('one_to_one_lecture_release_updated', $notify, [
            'live_booking_id' => (int) $booking->id,
            'lecture_id' => (int) $lecture->id,
            'is_released' => (bool) $release->is_released,
        ]);
    }

    public function kycForm()
    {
        if (auth()->user()->kv == Status::KYC_PENDING) {
            $notify[] = 'Your KYC is under review';
            return responseError('under_review', $notify);
        }
        if (auth()->user()->kv == Status::KYC_VERIFIED) {
            $notify[] = 'You are already KYC verified';
            return responseError('already_verified', $notify);
        }
        $form = Form::where('act', 'instructor_kyc')->first();
        $notify[] = 'KYC field is below';
        return responseSuccess('kyc_form', $notify, ['form' => $form->form_data]);
    }

    public function kycSubmit(Request $request)
    {
        $form = Form::where('act', 'instructor_kyc')->first();
        if (!$form) {
            $notify[] = 'Invalid KYC request';
            return responseError('invalid_request', $notify);
        }
        $formData = $form->form_data;
        $formProcessor = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);

        $validator = Validator::make($request->all(), $validationRule);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }
        $user = auth()->user();
        foreach (@$user->kyc_data ?? [] as $kycData) {
            if ($kycData->type == 'file') {
                fileManager()->removeFile(getFilePath('verify') . '/' . $kycData->value);
            }
        }
        $userData = $formProcessor->processFormData($request, $formData);

        $user->kyc_data = $userData;
        $user->kyc_rejection_reason = null;
        $user->kv = Status::KYC_PENDING;
        $user->role_type = 'teacher';
        $user->save();

        $notify[] = 'KYC data submitted successfully';
        return responseSuccess('kyc_submitted', $notify, ['kyc_data' => $user->kyc_data]);
    }

    public function kycData()
    {
        $user = auth()->user();

        $kycData = $user->kyc_data ?? [];

        $kycValues = [];
        foreach ($kycData as $kycInfo) {


            if (!$kycInfo->value) {
                continue;
            }
            if ($kycInfo->type == 'checkbox') {
                $value = implode(', ', $kycInfo->value);
            } elseif ($kycInfo->type == 'file') {
                $value = encrypt(getFilePath('verify') . '/' . $kycInfo->value);
            } else {
                $value = $kycInfo->value;
            }

            $kycValues[] = [
                'name' => $kycInfo->name,
                'type' => $kycInfo->type,
                'value' => $value
            ];
        }
        $notify[] = 'KYC data';
        return responseSuccess('kyc_data', $notify, ['kyc_data' => $kycValues]);
    }


    public function show2faForm()
    {
        $ga = new GoogleAuthenticator();
        $user = auth()->user();
        $secret = $ga->createSecret();
        $qrCodeUrl = $ga->getQRCodeGoogleUrl($user->username . '@' . gs('site_name'), $secret);
        $notify[] = '2FA Qr';
        return responseSuccess('2fa_qr', $notify, [
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
        ]);
    }

    public function create2fa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'secret' => 'required',
            'code' => 'required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user = auth()->user();
        $response = verifyG2fa($user, $request->code, $request->secret);
        if ($response) {
            $user->tsc = $request->secret;
            $user->ts = Status::ENABLE;
            $user->role_type = 'teacher';
        $user->save();

            $notify[] = 'Google authenticator activated successfully';
            return responseSuccess('2fa_qr', $notify);
        } else {
            $notify[] = 'Wrong verification code';
            return responseError('wrong_verification', $notify);
        }
    }

    public function disable2fa(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user = auth()->user();
        $response = verifyG2fa($user, $request->code);
        if ($response) {
            $user->tsc = null;
            $user->ts = Status::DISABLE;
            $user->role_type = 'teacher';
        $user->save();
            $notify[] = 'Two factor authenticator deactivated successfully';
            return responseSuccess('2fa_qr', $notify);
        } else {
            $notify[] = 'Wrong verification code';
            return responseError('wrong_verification', $notify);
        }
    }


    public function reviewList()
    {
        $reviews = Review::where('instructor_id', auth()->id())->searchable(['course:title'])->with('user','course')
            ->paginate(getPaginate());

        $notify[] = 'Review list';
        return responseSuccess('review_list', $notify, [
            'reviews' => $reviews,
        ]);
    }


    public function reviewReply(Request $request, $id){
        $review = Review::find($id);
        if (!$review) {
            $notify[] = 'Invalid review ID';
            return responseError('invalid_id', $notify);
        }
        $validator = Validator::make($request->all(), [
           'reply' =>'required',
        ]);
        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }
        if($review->instructor_reply_date){
            $notify[] = 'Reply already submitted';
            return responseError('reply_already_submitted', $notify);
        }

        $review->instructor_answer = $request->reply;
        $review->instructor_reply_date = now();
        $review->role_type = 'teacher';
        $user->save();
  
        $notify[] = 'Reply submitted successfully';
        return responseSuccess('reply_submitted', $notify,[
            'instructor_answer' => $review->instructor_answer,
            'instructor_reply_date' => $review->instructor_reply_date,
        ]);
    }


    public function transactions(Request $request)
    {
        $remarks = Transaction::distinct('remark')->get('remark');
        $transactions = Transaction::where('instructor_id', auth()->id());

        if ($request->search) {
            $transactions = $transactions->where('trx', $request->search);
        }


        if ($request->type) {
            $type = $request->type == 'plus' ? '+' : '-';
            $transactions = $transactions->where('trx_type', $type);
        }

        if ($request->remark) {
            $transactions = $transactions->where('remark', $request->remark);
        }

        $transactions = $transactions->orderBy('id', 'desc')->paginate(getPaginate());
        $notify[] = 'Transactions data';
        return responseSuccess('transactions', $notify, [
            'transactions' => $transactions,
            'remarks' => $remarks,
        ]);
    }




    public function overviewDetail($id){
        $coursePurchase = CoursePurchased::where('payment_status', status::PAYMENT_SUCCESS)->find($id);
        if (!$coursePurchase) {
            $notify[] = 'Invalid course purchase ID';
            return responseError('invalid_id', $notify);
        }
        $user = User::find(  $coursePurchase->user_id );
        if (!$user) {
            $notify[] = 'Invalid user ID';
            return responseError('invalid_id', $notify);
        }
        $course = Course::where('instructor_id', auth()->id())->find($coursePurchase->course_id);
        if (!$course) {
            $notify[] = 'Invalid course ID';
            return responseError('invalid_id', $notify);
        }
        $totalCurriculum = $course->curriculums()->count();
        $completedCurriculum = UserProgress::where('user_id', $user->id)->whereHas('course', function($query) use ($course) {
            return $query->where('instructor_id', auth()->id())->where('course_id', $course->id);
        })->count();
        if ($totalCurriculum === 0) {
            $percentage = 0;
        } else {
            $percentage = ($completedCurriculum / $totalCurriculum) * 100;
        }
        $totalQuizParticipation = $user->quizsubmit()
        ->where('course_id', $course->id)
        ->distinct('quiz_id')
        ->count('quiz_id');
        $notify[] = 'Overview detail';
        return responseSuccess('overview_detail', $notify, [
            'user' => $user,
            'percentage' => round($percentage, 2),
            'total_quiz_participation' => $totalQuizParticipation,
            'total_curriculum' => $totalCurriculum,
            'completed_curriculum' => $completedCurriculum,
            'course'=>$course,
        ]);
    }


}
