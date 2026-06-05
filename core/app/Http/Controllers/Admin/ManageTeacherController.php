<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\LiveClassScheduler;
use App\Models\Course;
use App\Models\CourseLiveBooking;
use App\Models\CourseLiveSession;
use App\Models\CourseLiveTeacherAssignment;
use App\Models\CourseZoomMeetingOccurrence;
use App\Models\ConsultationRequest;
use App\Models\CoursePurchased;
use App\Models\Deposit;
use App\Models\Instructor;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\TeacherLeave;
use App\Models\TeacherLeaveNotificationRecipient;
use App\Models\TeacherUnavailabilitySlot;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Rules\FileTypeValidate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManageTeacherController extends Controller
{
    public function leaveRequests()
    {
        $pageTitle = 'Teacher Leave Requests';
        $leaves = TeacherLeave::with('teacher:id,firstname,lastname,username,email')
            ->latest()
            ->paginate(getPaginate());
        $mailRecipients = TeacherLeaveNotificationRecipient::orderBy('id', 'desc')->get();

        return view('admin.teachers.leaves', compact('pageTitle', 'leaves', 'mailRecipients'));
    }

    public function storeLeaveRecipient(Request $request)
    {
        $request->validate([
            'email' => 'required|email:rfc|max:191|unique:teacher_leave_notification_recipients,email',
        ]);

        TeacherLeaveNotificationRecipient::create([
            'email' => strtolower(trim($request->email)),
            'is_active' => true,
        ]);

        $notify[] = ['success', 'Recipient added successfully'];
        return back()->withNotify($notify);
    }

    public function updateLeaveRecipient(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email:rfc|max:191|unique:teacher_leave_notification_recipients,email,' . $id,
            'is_active' => 'nullable|boolean',
        ]);

        $recipient = TeacherLeaveNotificationRecipient::findOrFail($id);
        $recipient->email = strtolower(trim($request->email));
        $recipient->is_active = (bool) $request->is_active;
        $recipient->save();

        $notify[] = ['success', 'Recipient updated successfully'];
        return back()->withNotify($notify);
    }

    public function deleteLeaveRecipient($id)
    {
        $recipient = TeacherLeaveNotificationRecipient::findOrFail($id);
        $recipient->delete();

        $notify[] = ['success', 'Recipient deleted successfully'];
        return back()->withNotify($notify);
    }

    public function updateLeaveStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $leave = TeacherLeave::findOrFail($id);
        $leave->status = $request->status;
        $leave->admin_note = $request->admin_note;
        $leave->save();

        $notify[] = ['success', 'Leave request updated successfully'];
        return back()->withNotify($notify);
    }

    public function availability(Request $request)
    {
        $pageTitle = 'Teacher Availability';
        $checked = false;
        $teachers = collect();
        $filters = [
            'date' => '',
            'start_time' => '',
            'duration' => 60,
            'unavailable_only' => 0,
        ];

        if ($request->filled('date') || $request->filled('start_time') || $request->filled('duration')) {
            $request->validate([
                'date' => 'required|date',
                'start_time' => ['required', 'date_format:H:i', 'after_or_equal:09:00', 'before_or_equal:21:00', 'regex:/^\d{2}:(00|30)$/'],
                'duration' => 'required|integer|min:1|max:480',
                'unavailable_only' => 'nullable|in:0,1',
            ]);

            $checked = true;
            $filters = [
                'date' => $request->date,
                'start_time' => $request->start_time,
                'duration' => (int) $request->duration,
                'unavailable_only' => (int) $request->input('unavailable_only', 0),
            ];

            $windowStart = Carbon::parse($request->date . ' ' . $request->start_time);
            $windowEnd = $windowStart->copy()->addMinutes((int) $request->duration);
            $endTime = $windowEnd->format('H:i');

            $teachers = Instructor::onlyTeachers()
                ->active()
                ->select('id', 'firstname', 'lastname', 'username', 'email')
                ->orderBy('firstname')
                ->get()
                ->map(function ($teacher) use ($windowStart, $windowEnd, $request, $endTime) {
                    $allSessions = $this->teacherScheduleSessions((int) $teacher->id);
                    $sessionConflicts = $allSessions->filter(function ($session) use ($windowStart, $windowEnd) {
                        if (!$session->scheduled_at) {
                            return false;
                        }

                        $sessionStart = Carbon::parse($session->scheduled_at);
                        $sessionEnd = $sessionStart->copy()->addMinutes((int) ($session->duration_minutes ?? 0));

                        return $sessionStart->lt($windowEnd) && $sessionEnd->gt($windowStart);
                    })->values();

                    $bookingConflicts = CourseLiveBooking::with(['course:id,title', 'user:id,firstname,lastname,username'])
                        ->where('assigned_teacher_id', $teacher->id)
                        ->where('class_type', 'one_to_one')
                        ->where('start_date', (string) $request->date)
                        ->whereIn('status', ['pending_admin_assignment', 'assigned', 'confirmed'])
                        ->get()
                        ->filter(function ($booking) use ($request, $endTime) {
                            if (!$booking->start_time || !$booking->end_time) {
                                return false;
                            }

                            return LiveClassScheduler::timeOverlap(
                                (string) $request->start_time,
                                (string) $endTime,
                                (string) $booking->start_time,
                                (string) $booking->end_time
                            );
                        })
                        ->values();

                    $bookingConflict = LiveClassScheduler::hasOneToOneClash(
                        (int) $teacher->id,
                        (string) $request->date,
                        (string) $request->start_time,
                        (string) $endTime
                    );

                    $unavailableSlotConflicts = TeacherUnavailabilitySlot::where('teacher_instructor_id', (int) $teacher->id)
                        ->where(function ($query) use ($windowStart, $windowEnd) {
                            $query->whereBetween('start_at', [$windowStart, $windowEnd])
                                ->orWhereBetween('end_at', [$windowStart, $windowEnd])
                                ->orWhere(function ($inner) use ($windowStart, $windowEnd) {
                                    $inner->where('start_at', '<=', $windowStart)
                                        ->where('end_at', '>=', $windowEnd);
                                });
                        })
                        ->orderBy('start_at')
                        ->get();

                    $unavailableSlotConflict = $unavailableSlotConflicts->isNotEmpty();

                    $teacher->session_conflict_count = $sessionConflicts->count();
                    $teacher->booking_conflict = $bookingConflict;
                    $teacher->unavailable_slot_conflict = $unavailableSlotConflict;
                    $teacher->is_available = $sessionConflicts->isEmpty() && !$bookingConflict && !$unavailableSlotConflict;
                    $teacher->conflicting_sessions = $sessionConflicts;
                    $teacher->booking_conflicts = $bookingConflicts;
                    $teacher->unavailable_slot_conflicts = $unavailableSlotConflicts;
                    $teacher->full_schedule = $allSessions->sortBy('scheduled_at')->values();

                    return $teacher;
                });

            if ((int) $request->input('unavailable_only', 0) === 1) {
                $teachers = $teachers->filter(function ($teacher) {
                    return (bool) ($teacher->unavailable_slot_conflict ?? false);
                })->values();
            }
        }

        return view('admin.teachers.availability', compact('pageTitle', 'teachers', 'filters', 'checked'));
    }

    protected function teacherScheduleSessions(int $teacherId)
    {
        $allocatedAssignments = CourseLiveTeacherAssignment::where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->get();

        $allocatedBatchIds = $allocatedAssignments->pluck('batch_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $allocatedCourseIds = $allocatedAssignments->pluck('course_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $scheduledByAssignedTeacher = CourseLiveSession::with(['course:id,title', 'lecture:id,title', 'batch:id,title'])
            ->whereIn('status', ['scheduled', 'assigned', 'started'])
            ->where('assigned_teacher_id', $teacherId)
            ->get();

        $scheduledByAllocatedBatch = collect();
        if ($allocatedBatchIds->isNotEmpty()) {
            $scheduledByAllocatedBatch = CourseLiveSession::with(['course:id,title', 'lecture:id,title', 'batch:id,title'])
                ->whereIn('status', ['scheduled', 'assigned', 'started'])
                ->whereIn('batch_id', $allocatedBatchIds->all())
                ->get();
        }

        $scheduledByAllocatedCourse = collect();
        if ($allocatedCourseIds->isNotEmpty()) {
            $scheduledByAllocatedCourse = CourseLiveSession::with(['course:id,title', 'lecture:id,title', 'batch:id,title'])
                ->whereIn('status', ['scheduled', 'assigned', 'started'])
                ->whereIn('course_id', $allocatedCourseIds->all())
                ->get();
        }

        $standaloneMeetings = CourseZoomMeetingOccurrence::with(['meeting.course:id,title', 'meeting.batch:id,title'])
            ->where('teacher_instructor_id', $teacherId)
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('start_time')
            ->get()
            ->map(function ($occurrence) {
                $meeting = $occurrence->meeting;
                if (!$meeting) {
                    return null;
                }

                return (object) [
                    'id' => 'standalone-' . $occurrence->id,
                    'course_id' => (int) $meeting->course_id,
                    'course' => $meeting->course,
                    'lecture' => (object) ['title' => (string) ($occurrence->title ?: 'Standalone Zoom Meeting')],
                    'batch' => $meeting->batch,
                    'session_number' => 'S' . $occurrence->id,
                    'scheduled_at' => $occurrence->start_time,
                    'duration_minutes' => (int) $occurrence->duration_minutes,
                    'zoom_join_url' => $occurrence->zoom_join_url ?: $meeting->zoom_join_url,
                    'status' => $occurrence->status ?: 'scheduled',
                ];
            })
            ->filter();

        return collect($scheduledByAssignedTeacher->all())
            ->merge($scheduledByAllocatedBatch->all())
            ->merge($scheduledByAllocatedCourse->all())
            ->merge($standaloneMeetings->all())
            ->unique('id')
            ->values();
    }


    public function allTeachers()
    {
        $pageTitle = 'All Teachers';
        $teachers = $this->teacherData();
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }

    public function activeTeachers()
    {
        $pageTitle = 'Active Teachers';
        $teachers = $this->teacherData('active');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }

    public function pendingApprovalTeachers()
    {
        $pageTitle = 'Pending Approval Teachers';
        $teachers = $this->teacherData('pendingApproval');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.pending_approval', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }

    public function bannedTeachers()
    {
        $pageTitle = 'Banned Teachers';
        $teachers = $this->teacherData('banned');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }

    public function emailUnverifiedTeachers()
    {
        $pageTitle = 'Email Unverified Users';
        $teachers = $this->teacherData('emailUnverified');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }

    public function kycUnverifiedTeachers()
    {
        $pageTitle = 'KYC Unverified Users';
        $teachers = $this->teacherData('kycUnverified');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }

    public function kycPendingTeachers()
    {
        $pageTitle = 'KYC Pending Users';
        $teachers = $this->teacherData('kycPending');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }

    public function emailVerifiedTeachers()
    {
        $pageTitle = 'Email Verified Users';
        $teachers = $this->teacherData('emailVerified');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }


    public function mobileUnverifiedTeachers()
    {
        $pageTitle = 'Mobile Unverified Users';
        $teachers = $this->teacherData('mobileUnverified');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }


    public function mobileVerifiedTeachers()
    {
        $pageTitle = 'Mobile Verified Users';
        $teachers = $this->teacherData('mobileVerified');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }


    public function teachersWithBalance()
    {
        $pageTitle = 'Users with Balance';
        $teachers = $this->teacherData('withBalance');
        $pendingLeaveCount = $this->pendingLeaveCount();
        return view('admin.teachers.list', compact('pageTitle', 'teachers', 'pendingLeaveCount'));
    }

    protected function pendingLeaveCount(): int
    {
        return TeacherLeave::where('status', 'pending')->count();
    }


    protected function teacherData($scope = null){
        $instructors = Instructor::onlyTeachers();

        if ($scope === 'pendingApproval') {
            $instructors = $instructors
                ->where('status', Status::USER_BAN)
                ->where(function ($query) {
                    $query->whereNull('ban_reason')->orWhere('ban_reason', '');
                });
        } elseif ($scope === 'banned') {
            $instructors = $instructors
                ->where('status', Status::USER_BAN)
                ->where(function ($query) {
                    $query->whereNotNull('ban_reason')->where('ban_reason', '!=', '');
                });
        } elseif ($scope) {
            $instructors = $instructors->$scope();
        }

        $teachers = $instructors
            ->searchable(['username','email'])
            ->withCount('courses')
            ->withSum([
                'transactions as total_earnings_amount' => function ($query) {
                    $query->where('remark', 'payment_received');
                }
            ], 'amount')
            ->orderBy('id','desc')
            ->paginate(getPaginate());

        $this->attachTeacherPerformanceStats($teachers->getCollection());

        return $teachers;
    }

    protected function attachTeacherPerformanceStats($teachers): void
    {
        $teachers->transform(function ($teacher) {
            $teacher->performance_stats = $this->teacherPerformanceStats((int) $teacher->id);
            return $teacher;
        });
    }

    protected function teacherAssignmentScope(int $teacherId): array
    {
        $activeAssignments = CourseLiveTeacherAssignment::where('teacher_instructor_id', $teacherId)
            ->where('is_active', 1)
            ->get(['course_id', 'batch_id']);

        return [
            'course_ids' => $activeAssignments->pluck('course_id')->filter()->map(fn ($id) => (int) $id)->unique()->values(),
            'batch_ids' => $activeAssignments->pluck('batch_id')->filter()->map(fn ($id) => (int) $id)->unique()->values(),
        ];
    }

    protected function teacherPerformanceStats(int $teacherId): array
    {
        $assignmentScope = $this->teacherAssignmentScope($teacherId);
        $assignedCourseIds = $assignmentScope['course_ids'];
        $assignedBatchIds = $assignmentScope['batch_ids'];

        $oneToOneBookingsQuery = CourseLiveBooking::query()
            ->where('assigned_teacher_id', $teacherId)
            ->where('class_type', 'one_to_one')
            ->whereHas('purchase', function ($query) {
                $query->where('payment_status', Status::PAYMENT_SUCCESS);
            });

        $groupBookingsQuery = CourseLiveBooking::query()
            ->where('class_type', 'group')
            ->whereHas('purchase', function ($query) {
                $query->where('payment_status', Status::PAYMENT_SUCCESS);
            })
            ->where(function ($query) use ($assignedBatchIds, $assignedCourseIds) {
                $hasBatch = $assignedBatchIds->isNotEmpty();
                $hasCourse = $assignedCourseIds->isNotEmpty();

                if (!$hasBatch && !$hasCourse) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                if ($hasBatch) {
                    $query->whereIn('batch_id', $assignedBatchIds->all());
                }

                if ($hasCourse) {
                    $hasBatch
                        ? $query->orWhereIn('course_id', $assignedCourseIds->all())
                        : $query->whereIn('course_id', $assignedCourseIds->all());
                }
            });

        $oneToOneStudentCount = (clone $oneToOneBookingsQuery)->distinct('user_id')->count('user_id');
        $groupStudentCount = (clone $groupBookingsQuery)->distinct('user_id')->count('user_id');
        $assignedStudentsCount = $oneToOneStudentCount + $groupStudentCount;

        $oneToOnePurchaseIds = (clone $oneToOneBookingsQuery)->pluck('course_purchased_id')->filter()->unique()->values();
        $groupPurchaseIds = (clone $groupBookingsQuery)->pluck('course_purchased_id')->filter()->unique()->values();

        $oneToOneCompletedCount = 0;
        if ($oneToOnePurchaseIds->isNotEmpty()) {
            $oneToOneCompletedCount = CourseLiveSession::whereIn('course_purchased_id', $oneToOnePurchaseIds->all())
                ->whereIn('status', ['started', 'completed'])
                ->count();
        }

        $groupCompletedCount = 0;
        if ($groupPurchaseIds->isNotEmpty()) {
            $groupCompletedCount = CourseLiveSession::whereIn('course_purchased_id', $groupPurchaseIds->all())
                ->whereIn('status', ['started', 'completed'])
                ->count();
        }

        $oneToOneCancelledCount = (clone $oneToOneBookingsQuery)->where(function ($query) {
            $query->where('status', 'like', '%cancel%')
                ->orWhereIn('status', ['rejected', 'zoom_conflict']);
        })->count();

        $groupCancelledCount = (clone $groupBookingsQuery)->where(function ($query) {
            $query->where('status', 'like', '%cancel%')
                ->orWhereIn('status', ['rejected', 'zoom_conflict']);
        })->count();

        $trialClassCount = ConsultationRequest::where('assigned_teacher_id', $teacherId)->count();

        return [
            'assigned_students' => (int) $assignedStudentsCount,
            'trial_classes' => (int) $trialClassCount,
            'one_to_one_completed' => (int) $oneToOneCompletedCount,
            'group_completed' => (int) $groupCompletedCount,
            'one_to_one_cancelled' => (int) $oneToOneCancelledCount,
            'group_cancelled' => (int) $groupCancelledCount,
        ];
    }


    public function students(Request $request, $id)
    {
        $teacher = Instructor::onlyTeachers()->findOrFail($id);
        $pageTitle = 'Assigned Students - ' . $teacher->username;
        $search = trim((string) $request->search);

        $assignmentScope = $this->teacherAssignmentScope((int) $teacher->id);
        $assignedCourseIds = $assignmentScope['course_ids'];
        $assignedBatchIds = $assignmentScope['batch_ids'];

        $bookings = CourseLiveBooking::with([
            'user:id,firstname,lastname,username,email',
            'course:id,title',
            'batch:id,title',
            'purchase:id,user_id,course_id,amount,payment_status',
        ])
            ->whereHas('purchase', function ($query) {
                $query->where('payment_status', Status::PAYMENT_SUCCESS);
            })
            ->where(function ($query) use ($teacher, $assignedBatchIds, $assignedCourseIds) {
                $query->where(function ($inner) use ($teacher) {
                    $inner->where('class_type', 'one_to_one')
                        ->where('assigned_teacher_id', (int) $teacher->id);
                });

                $hasBatch = $assignedBatchIds->isNotEmpty();
                $hasCourse = $assignedCourseIds->isNotEmpty();

                if ($hasBatch || $hasCourse) {
                    $query->orWhere(function ($inner) use ($assignedBatchIds, $assignedCourseIds, $hasBatch, $hasCourse) {
                        $inner->where('class_type', 'group')
                            ->where(function ($groupQuery) use ($assignedBatchIds, $assignedCourseIds, $hasBatch, $hasCourse) {
                                if ($hasBatch) {
                                    $groupQuery->whereIn('batch_id', $assignedBatchIds->all());
                                }

                                if ($hasCourse) {
                                    $hasBatch
                                        ? $groupQuery->orWhereIn('course_id', $assignedCourseIds->all())
                                        : $groupQuery->whereIn('course_id', $assignedCourseIds->all());
                                }
                            });
                    });
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                        ->orWhereHas('course', function ($courseQuery) use ($search) {
                            $courseQuery->where('title', 'like', "%{$search}%");
                        })
                        ->orWhereHas('batch', function ($batchQuery) use ($search) {
                            $batchQuery->where('title', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('id')
            ->paginate(getPaginate())
            ->appends(['search' => $search]);

        $bookingCollection = collect($bookings->items());
        $purchaseIds = $bookingCollection->pluck('course_purchased_id')->filter()->map(fn ($value) => (int) $value)->unique()->values();
        $groupBatchIds = $bookingCollection->where('class_type', 'group')->pluck('batch_id')->filter()->map(fn ($value) => (int) $value)->unique()->values();
        $userEmails = $bookingCollection->map(fn ($booking) => strtolower((string) optional($booking->user)->email))->filter()->unique()->values();

        $sessionsByPurchase = collect();
        if ($purchaseIds->isNotEmpty()) {
            $sessionsByPurchase = CourseLiveSession::whereIn('course_purchased_id', $purchaseIds->all())
                ->orderBy('scheduled_at')
                ->get()
                ->groupBy('course_purchased_id');
        }

        $sessionsByBatch = collect();
        if ($groupBatchIds->isNotEmpty()) {
            $sessionsByBatch = CourseLiveSession::whereIn('batch_id', $groupBatchIds->all())
                ->orderBy('scheduled_at')
                ->get()
                ->groupBy('batch_id');
        }

        $trialRequestsByEmail = collect();
        if ($userEmails->isNotEmpty()) {
            $trialRequestsByEmail = ConsultationRequest::where('assigned_teacher_id', (int) $teacher->id)
                ->whereIn('email_address', $userEmails->all())
                ->orderByDesc('scheduled_at')
                ->get()
                ->groupBy(function ($item) {
                    return strtolower((string) $item->email_address);
                });
        }

        $studentRows = $bookingCollection->map(function ($booking) use ($sessionsByPurchase, $sessionsByBatch, $trialRequestsByEmail) {
            $isGroup = $booking->class_type === 'group';
            $sessionCollection = $isGroup && !empty($booking->batch_id)
                ? collect($sessionsByBatch->get((int) $booking->batch_id, collect()))
                : collect($sessionsByPurchase->get((int) $booking->course_purchased_id, collect()));

            $coveredCount = $sessionCollection->whereIn('status', ['started', 'completed'])->count();
            $totalSessions = $sessionCollection->count();
            $zoomLinks = $sessionCollection->pluck('zoom_join_url')->filter()->unique()->values();
            $kidEmail = strtolower((string) optional($booking->user)->email);
            $trialRequests = collect($trialRequestsByEmail->get($kidEmail, collect()));
            $trialCompletedAt = optional(
                $trialRequests->first(function ($trial) {
                    return !empty($trial->scheduled_at) && Carbon::parse($trial->scheduled_at)->lte(now());
                })
            )->scheduled_at;

            return [
                'student_name' => optional($booking->user)->fullname ?? trim((optional($booking->user)->firstname . ' ' . optional($booking->user)->lastname)),
                'student_username' => optional($booking->user)->username,
                'student_email' => optional($booking->user)->email,
                'course_title' => optional($booking->course)->title,
                'batch_title' => optional($booking->batch)->title,
                'class_type' => (string) $booking->class_type,
                'booking_status' => (string) $booking->status,
                'sessions_covered' => $coveredCount,
                'sessions_left' => max($totalSessions - $coveredCount, 0),
                'last_session_at' => $sessionCollection->max('scheduled_at'),
                'trial_completed_at' => $trialCompletedAt,
                'zoom_links' => $zoomLinks,
                'amount' => (float) (optional($booking->purchase)->amount ?? 0),
                'user_id' => (int) ($booking->user_id ?? 0),
            ];
        })->values();

        return view('admin.teachers.students', compact('pageTitle', 'teacher', 'bookings', 'studentRows', 'search'));
    }

    public function detail($id)
    {
        $teacher = Instructor::onlyTeachers()->findOrFail($id);
        $pageTitle = 'Teacher Detail - '.$teacher->username;

        $totalEarning = Transaction::where('instructor_id',$teacher->id)->where('remark','payment_received')->sum('amount');
        $totalWithdrawals = Withdrawal::where('instructor_id',$teacher->id)->approved()->sum('amount');
        $totalTransaction = Transaction::where('instructor_id',$teacher->id)->count();
        $countries = json_decode(file_get_contents(resource_path('views/partials/country.json')));

        
        $allocatedAssignments = CourseLiveTeacherAssignment::with(['course', 'batch'])
            ->where('teacher_instructor_id', $teacher->id)
            ->where('is_active', 1)
            ->get();

        $allocatedCourses = $allocatedAssignments
            ->pluck('course')
            ->filter()
            ->unique('id')
            ->values();

        $allocatedBatchIds = $allocatedAssignments->pluck('batch_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $allocatedCourseIds = $allocatedCourses->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        $scheduledByAssignedTeacher = CourseLiveSession::with(['course', 'lecture', 'batch'])
            ->where('assigned_teacher_id', $teacher->id)
            ->get();

        $scheduledByAllocatedBatch = collect();
        if ($allocatedBatchIds->isNotEmpty()) {
            $scheduledByAllocatedBatch = CourseLiveSession::with(['course', 'lecture', 'batch'])
                ->whereIn('batch_id', $allocatedBatchIds->all())
                ->get();
        }

        $scheduledByAllocatedCourse = collect();
        if ($allocatedCourseIds->isNotEmpty()) {
            $scheduledByAllocatedCourse = CourseLiveSession::with(['course', 'lecture', 'batch'])
                ->whereIn('course_id', $allocatedCourseIds->all())
                ->get();
        }

        $standaloneMeetings = CourseZoomMeetingOccurrence::with(['meeting.course', 'meeting.batch'])
            ->where('teacher_instructor_id', $teacher->id)
            ->whereNotNull('start_time')
            ->get()
            ->map(function ($occurrence) {
                $meeting = $occurrence->meeting;
                if (!$meeting) {
                    return null;
                }

                return (object) [
                    'id' => 'standalone-' . $occurrence->id,
                    'course_id' => (int) $meeting->course_id,
                    'course' => $meeting->course,
                    'lecture' => (object) ['title' => (string) ($occurrence->title ?: 'Standalone Zoom Meeting')],
                    'batch' => $meeting->batch,
                    'session_number' => 'S' . $occurrence->id,
                    'scheduled_at' => $occurrence->start_time,
                    'duration_minutes' => (int) $occurrence->duration_minutes,
                    'zoom_join_url' => $occurrence->zoom_join_url ?: $meeting->zoom_join_url,
                    'status' => $occurrence->status ?: 'scheduled',
                ];
            })
            ->filter();

        $scheduledLecturesFlat = collect($scheduledByAssignedTeacher->all())
            ->merge($scheduledByAllocatedBatch->all())
            ->merge($scheduledByAllocatedCourse->all())
            ->merge($standaloneMeetings->all())
            ->unique('id')
            ->sortBy('scheduled_at')
            ->values();

        $scheduledLectures = $scheduledLecturesFlat->groupBy('course_id');
        $performanceStats = $this->teacherPerformanceStats((int) $teacher->id);

        return view('admin.teachers.detail', compact(
            'pageTitle',
            'teacher',
            'totalEarning',
            'totalWithdrawals',
            'totalTransaction',
            'countries',
            'allocatedAssignments',
            'allocatedCourses',
            'scheduledLecturesFlat',
            'scheduledLectures',
            'performanceStats'
        ));
    }


    public function calendar(Request $request, $id)
    {
        $teacher = Instructor::onlyTeachers()->findOrFail($id);

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->addDays(30)->endOfDay();

        if ($to->lt($from)) {
            return response()->json([
                'status' => 'error',
                'message' => ['Invalid date range: to date must be after from date.'],
            ], 422);
        }

        $sessionsByTeacher = CourseLiveSession::with(['course:id,title', 'batch:id,title', 'user:id,firstname,lastname,username'])
            ->where('assigned_teacher_id', (int) $teacher->id)
            ->whereBetween('scheduled_at', [$from, $to])
            ->whereIn('status', ['scheduled', 'assigned', 'started', 'completed'])
            ->get();

        
        $standaloneOccurrences = CourseZoomMeetingOccurrence::with(['meeting:id,course_id,batch_id,zoom_join_url,status', 'meeting.course:id,title', 'meeting.batch:id,title'])
            ->where('teacher_instructor_id', (int) $teacher->id)
            ->whereBetween('start_time', [$from, $to])
            ->where('status', '!=', 'cancelled')
            ->get();

        // Match teacher dashboard: show only teacher-manual unavailable slots here.
        $unavailableSlots = TeacherUnavailabilitySlot::where('teacher_instructor_id', (int) $teacher->id)
            ->whereNull('source_type')
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('start_at', [$from, $to])
                    ->orWhereBetween('end_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->where('start_at', '<=', $from)->where('end_at', '>=', $to);
                    });
            })
            ->orderBy('start_at')
            ->limit(200)
            ->get();

        $sessionItems = collect($sessionsByTeacher->all())
            ->unique('id')
            ->sortBy('scheduled_at')
            ->values()
            ->map(function ($session) {
                return [
                    'type' => 'class',
                    'id' => (string) $session->id,
                    'title' => (string) optional($session->course)->title,
                    'batch' => (string) (optional($session->batch)->title ?? ''),
                    'student' => (string) (optional($session->user)->fullname ?? optional($session->user)->username ?? ''),
                    'start_at' => optional($session->scheduled_at)?->toDateTimeString(),
                    'end_at' => optional($session->scheduled_at)?->copy()->addMinutes((int) ($session->duration_minutes ?? 0))->toDateTimeString(),
                    'duration_minutes' => (int) ($session->duration_minutes ?? 0),
                    'status' => (string) ($session->status ?? 'scheduled'),
                    'zoom_join_url' => (string) ($session->zoom_join_url ?? ''),
                ];
            });

        $occurrenceItems = $standaloneOccurrences->map(function ($occurrence) {
            $meeting = $occurrence->meeting;
            return [
                'type' => 'standalone',
                'id' => 'occurrence-' . (string) $occurrence->id,
                'title' => (string) ($occurrence->title ?: optional($meeting?->course)->title ?: 'Standalone Meeting'),
                'batch' => (string) (optional($meeting?->batch)->title ?? ''),
                'student' => '',
                'start_at' => optional($occurrence->start_time)?->toDateTimeString(),
                'end_at' => optional($occurrence->start_time)?->copy()->addMinutes((int) ($occurrence->duration_minutes ?? 0))->toDateTimeString(),
                'duration_minutes' => (int) ($occurrence->duration_minutes ?? 0),
                'status' => (string) ($occurrence->status ?? 'scheduled'),
                'zoom_join_url' => (string) ($occurrence->zoom_join_url ?: ($meeting->zoom_join_url ?? '')),
            ];
        });

        $unavailableItems = $unavailableSlots->map(function ($slot) {
            return [
                'type' => 'unavailable',
                'id' => 'unavailable-' . (string) $slot->id,
                'title' => 'Unavailable Slot',
                'batch' => '',
                'student' => '',
                'start_at' => optional($slot->start_at)?->toDateTimeString(),
                'end_at' => optional($slot->end_at)?->toDateTimeString(),
                'duration_minutes' => optional($slot->start_at) && optional($slot->end_at) ? (int) $slot->start_at->diffInMinutes($slot->end_at) : 0,
                'status' => 'blocked',
                'reason' => (string) ($slot->reason ?? ''),
                'zoom_join_url' => '',
            ];
        });

        $items = $sessionItems
            ->merge($occurrenceItems)
            ->merge($unavailableItems)
            ->sortBy('start_at')
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'teacher' => [
                    'id' => (int) $teacher->id,
                    'name' => $teacher->fullname,
                    'username' => $teacher->username,
                ],
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'items' => $items,
            ],
        ]);
    }

    public function kycDetails($id)
    {
        $pageTitle = 'KYC Details';
        $teacher = Instructor::onlyTeachers()->findOrFail($id);
        return view('admin.teachers.kyc_detail', compact('pageTitle','teacher'));
    }

    public function kycApprove($id)
    {
        $instructor = Instructor::onlyTeachers()->findOrFail($id);
        $instructor->kv = Status::KYC_VERIFIED;
        $instructor->save();

        notify($instructor,'KYC_APPROVE',[]);

        $notify[] = ['success','KYC approved successfully'];
        return to_route('admin.teachers.kyc.pending')->withNotify($notify);
    }

    public function kycReject(Request $request,$id)
    {
        $request->validate([
            'reason'=>'required'
        ]);
        $instructor = Instructor::onlyTeachers()->findOrFail($id);
        $instructor->kv = Status::KYC_UNVERIFIED;
        $instructor->kyc_rejection_reason = $request->reason;
        $instructor->save();

        notify($instructor,'KYC_REJECT',[
            'reason'=>$request->reason
        ]);

        $notify[] = ['success','KYC rejected successfully'];
        return to_route('admin.teachers.kyc.pending')->withNotify($notify);
    }


    public function update(Request $request, $id)
    {
        $instructor = Instructor::onlyTeachers()->findOrFail($id);
        $countryData = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $countryArray   = (array)$countryData;
        $countries      = implode(',', array_keys($countryArray));

        $countryCode    = $request->country;
        $country        = $countryData->$countryCode->country;
        $dialCode       = $countryData->$countryCode->dial_code;

        $request->validate([
            'firstname' => 'required|string|max:40',
            'lastname' => 'required|string|max:40',
            'email' => 'required|email|string|max:40|unique:users,email,' . $instructor->id,
            'mobile' => 'required|string|max:40',
            'country' => 'required|in:'.$countries,
        ]);

        $exists = Instructor::where('mobile',$request->mobile)->where('dial_code',$dialCode)->where('id','!=',$instructor->id)->exists();
        if ($exists) {
            $notify[] = ['error', 'The mobile number already exists.'];
            return back()->withNotify($notify);
        }

        $instructor->mobile = $request->mobile;
        $instructor->firstname = $request->firstname;
        $instructor->lastname = $request->lastname;
        $instructor->email = $request->email;

        $instructor->address = $request->address;
        $instructor->city = $request->city;
        $instructor->state = $request->state;
        $instructor->zip = $request->zip;
        $instructor->country_name = @$country;
        $instructor->dial_code = $dialCode;
        $instructor->country_code = $countryCode;

        $instructor->ev = $request->ev ? Status::VERIFIED : Status::UNVERIFIED;
        $instructor->sv = $request->sv ? Status::VERIFIED : Status::UNVERIFIED;
        $instructor->ts = $request->ts ? Status::ENABLE : Status::DISABLE;
        if (!$request->kv) {
            $instructor->kv = Status::KYC_UNVERIFIED;
            if ($instructor->kyc_data) {
                foreach ($instructor->kyc_data as $kycData) {
                    if ($kycData->type == 'file') {
                        fileManager()->removeFile(getFilePath('verify').'/'.$kycData->value);
                    }
                }
            }
            $instructor->kyc_data = null;
        }else{
            $instructor->kv = Status::KYC_VERIFIED;
        }
        $instructor->save();

        $notify[] = ['success', 'Academy details updated successfully'];
        return back()->withNotify($notify);
    }

    public function addSubBalance(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'act' => 'required|in:add,sub',
            'remark' => 'required|string|max:255',
        ]);

        $instructor = Instructor::onlyTeachers()->findOrFail($id);
        $amount = $request->amount;
        $trx = getTrx();

        $transaction = new Transaction();

        if ($request->act == 'add') {
            $instructor->balance += $amount;

            $transaction->trx_type = '+';
            $transaction->remark = 'balance_add';

            $notifyTemplate = 'BAL_ADD';

            $notify[] = ['success', 'Balance added successfully'];

        } else {
            if ($amount > $instructor->balance) {
                $notify[] = ['error', $instructor->username . ' doesn\'t have sufficient balance.'];
                return back()->withNotify($notify);
            }

            $instructor->balance -= $amount;

            $transaction->trx_type = '-';
            $transaction->remark = 'balance_subtract';

            $notifyTemplate = 'BAL_SUB';
            $notify[] = ['success', 'Balance subtracted successfully'];
        }

        $instructor->save();

        $transaction->user_id = $instructor->id;
        $transaction->amount = $amount;
        $transaction->post_balance = $instructor->balance;
        $transaction->charge = 0;
        $transaction->trx =  $trx;
        $transaction->details = $request->remark;
        $transaction->save();

        notify($instructor, $notifyTemplate, [
            'trx' => $trx,
            'amount' => showAmount($amount,currencyFormat:false),
            'remark' => $request->remark,
            'post_balance' => showAmount($instructor->balance,currencyFormat:false)
        ]);

        return back()->withNotify($notify);
    }

    public function login($id){
        Auth::guard('instructor')->loginUsingId($id);
        return to_route('instructor.dashboard');
    }

 

    public function status(Request $request,$id)
    {
        $instructor = Instructor::onlyTeachers()->findOrFail($id);
        if ($instructor->status == Status::USER_ACTIVE) {
            $request->validate([
                'reason'=>'required|string|max:255'
            ]);
            $instructor->status = Status::USER_BAN;
            $instructor->ban_reason = $request->reason;
            $notify[] = ['success','User banned successfully'];
        }else{
            $instructor->status = Status::USER_ACTIVE;
            $instructor->ban_reason = null;
            $notify[] = ['success','User unbanned successfully'];
        }
        $instructor->save();
        return back()->withNotify($notify);

    }

    public function approveRequest($id)
    {
        $teacher = Instructor::onlyTeachers()->findOrFail($id);
        $teacher->status = Status::USER_ACTIVE;
        $teacher->ban_reason = null;
        $teacher->save();

        $notify[] = ['success', 'Teacher request approved successfully'];
        return back()->withNotify($notify);
    }

    public function rejectRequest(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $teacher = Instructor::onlyTeachers()->findOrFail($id);
        $teacher->status = Status::USER_BAN;
        $teacher->ban_reason = $request->reason ?: 'Registration request rejected by admin';
        $teacher->save();

        $notify[] = ['success', 'Teacher request rejected successfully'];
        return back()->withNotify($notify);
    }


    public function showNotificationSingleForm($id)
    {
        $teacher = Instructor::onlyTeachers()->findOrFail($id);
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning','Notification options are disabled currently'];
            return to_route('admin.teachers.detail',$teacher->id)->withNotify($notify);
        }
        $pageTitle = 'Send Notification to ' . $teacher->username;
        return view('admin.teachers.notification_single', compact('pageTitle', 'teacher'));
    }

    public function sendNotificationSingle(Request $request, $id)
    {
        $request->validate([
            'message' => 'required',
            'via'     => 'required|in:email,sms,push',
            'subject' => 'required_if:via,email,push',
            'image'   => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ]);

        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        $imageUrl = null;
        if($request->via == 'push' && $request->hasFile('image')){
            $imageUrl = fileUploader($request->image, getFilePath('push'));
        }

        $template = NotificationTemplate::where('act', 'DEFAULT')->where($request->via.'_status', Status::ENABLE)->exists();
        if(!$template){
            $notify[] = ['warning', 'Default notification template is not enabled'];
            return back()->withNotify($notify);
        }

        $instructor = Instructor::onlyTeachers()->findOrFail($id);
        notify($instructor,'DEFAULT',[
            'subject'=>$request->subject,
            'message'=>$request->message,
        ],[$request->via],pushImage:$imageUrl);
        $notify[] = ['success', 'Notification sent successfully'];
        return back()->withNotify($notify);
    }

    public function showNotificationAllForm()
    {
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }

        $notifyToInstructor = collect(Instructor::instructorNotify())->map(function ($label) {
            return str_replace(['Instructors', 'Instructor'], ['Teachers', 'Teacher'], $label);
        })->toArray();
        $instructors        = Instructor::onlyTeachers()->active()->count();
        $pageTitle    = 'Notification to Verified Teachers';

        if (session()->has('SEND_NOTIFICATION') && !request()->email_sent) {
            session()->forget('SEND_NOTIFICATION');
        }

        return view('admin.teachers.notification_all', compact('pageTitle', 'instructors', 'notifyToInstructor'));
    }

    public function sendNotificationAll(Request $request)
    {
        $request->validate([
            'via'                          => 'required|in:email,sms,push',
            'message'                      => 'required',
            'subject'                      => 'required_if:via,email,push',
            'start'                        => 'required|integer|gte:1',
            'batch'                        => 'required|integer|gte:1',
            'being_sent_to'                => 'required',
            'cooling_time'                 => 'required|integer|gte:1',
            'number_of_days'               => 'required_if:being_sent_to,notLoginInstructors|integer|gte:0',
            'image'                        => ["nullable", 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
        ], [
            'number_of_days.required_if'               => "Number of days field is required",
        ]);
        
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning', 'Notification options are disabled currently'];
            return to_route('admin.dashboard')->withNotify($notify);
        }
        

        $template = NotificationTemplate::where('act', 'DEFAULT')->where($request->via.'_status', Status::ENABLE)->exists();
        if(!$template){
            $notify[] = ['warning', 'Default notification template is not enabled'];
            return back()->withNotify($notify);
        }
        
   
        
        if ($request->being_sent_to == 'selectedInstructors') {
            if (session()->has("SEND_NOTIFICATION")) {
                $request->merge(['instructor' => session()->get('SEND_NOTIFICATION')['instructor']]);
            } else {
                
                if (!$request->instructor || !is_array($request->instructor) || empty($request->instructor)) {
                  
                    $notify[] = ['error', "Ensure that the teacher field is populated when sending an email to the designated teacher group"];
                    return back()->withNotify($notify);
                }
            }
        }

        $scope          = $request->being_sent_to;
        $instructorQuery      = Instructor::onlyTeachers()->oldest()->active()->$scope();

        if (session()->has("SEND_NOTIFICATION")) {
            $totalInstructorCount = session('SEND_NOTIFICATION')['total_instructor'];
        } else {
            $totalInstructorCount = (clone $instructorQuery)->count() - ($request->start-1);
        }

        if ($totalInstructorCount <= 0) {
            $notify[] = ['error', "Notification recipients were not found among the selected teacher base."];
            return back()->withNotify($notify);
        }

        $imageUrl = null;

        if ($request->via == 'push' && $request->hasFile('image')) {
            if (session()->has("SEND_NOTIFICATION")) {
                $request->merge(['image' => session()->get('SEND_NOTIFICATION')['image']]);
            }
            if ($request->hasFile("image")) {
                $imageUrl = fileUploader($request->image, getFilePath('push'));
            }
        }

        $instructors = (clone $instructorQuery)->skip($request->start - 1)->limit($request->batch)->get();

        foreach ($instructors as $instructor) {
            notify($instructor, 'DEFAULT', [
                'subject' => $request->subject,
                'message' => $request->message,
            ], [$request->via], pushImage: $imageUrl);
        }

        return $this->sessionForNotification($totalInstructorCount, $request);
    }


    private function sessionForNotification($totalInstructorCount, $request)
    {
 
        if (session()->has('SEND_NOTIFICATION')) {
            $sessionData                = session("SEND_NOTIFICATION");
            $sessionData['total_sent'] += $sessionData['batch'];
        } else {
            $sessionData               = $request->except('_token');
            $sessionData['total_sent'] = $request->batch;
            $sessionData['total_instructor'] = $totalInstructorCount;
        }

        $sessionData['start'] = $sessionData['total_sent'] + 1;

        if ($sessionData['total_sent'] >= $totalInstructorCount) {
            session()->forget("SEND_NOTIFICATION");
            $message = ucfirst($request->via) . " notifications were sent successfully";
            $url     = route("admin.teachers.notification.all");
        } else {
            session()->put('SEND_NOTIFICATION', $sessionData);
            $message = $sessionData['total_sent'] . " " . $sessionData['via'] . "  notifications were sent successfully";
            $url     = route("admin.teachers.notification.all") . "?email_sent=yes";
        }
        $notify[] = ['success', $message];
        return redirect($url)->withNotify($notify);
    }

    public function countBySegment($methodName){
        return Instructor::onlyTeachers()->active()->$methodName()->count();
    }

    public function list()
    {
        $query = Instructor::onlyTeachers()->active();

        if (request()->search) {
            $query->where(function ($q) {
                $q->where('email', 'like', '%' . request()->search . '%')->orWhere('username', 'like', '%' . request()->search . '%');
            });
        }
        $teachers = $query->orderBy('id', 'desc')->paginate(getPaginate());
        return response()->json([
            'success' => true,
            'teachers'   => $teachers,
            'more'    => $teachers->hasMorePages()
        ]);
    }

    public function notificationLog($id){
        $instructor = Instructor::onlyTeachers()->findOrFail($id);
        $pageTitle = 'Notifications Sent to '.$instructor->username;
        $logs = NotificationLog::where('instructor_id',$id)->with('user')->orderBy('id','desc')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle','logs','instructor'));
    }


}
