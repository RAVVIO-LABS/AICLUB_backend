<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\ConsultationRequest;
use App\Models\Course;
use App\Models\CourseLecture;
use App\Models\CourseLectureOneToOneRelease;
use App\Models\CourseSection;
use App\Models\CourseLiveBooking;
use App\Models\CourseLiveSession;
use App\Models\CoursePurchased;
use App\Models\Deposit;
use App\Models\Instructor;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use App\Rules\FileTypeValidate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ManageInstructorController extends Controller
{
    public function students(Request $request)
    {
        $pageTitle = 'Student';
        $search = trim((string) $request->search);
        $instructorId = (int) $request->get('instructor_id', 0);

        $instructors = Instructor::query()
            ->where('role_type', 'instructor')
            ->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname', 'username']);

        $purchases = CoursePurchased::with([
            'user:id,firstname,lastname,username,email',
            'course:id,title,instructor_id',
            'course.sections.curriculums.lectures:id,course_id,course_section_id,title',
            'course.instructor:id,firstname,lastname,username',
            'liveBooking:id,course_purchased_id,class_type,batch_id,user_id,status',
        ])
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->whereHas('course', function ($query) use ($instructorId) {
                if ($instructorId > 0) {
                    $query->where('instructor_id', $instructorId);
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('course', function ($courseQuery) use ($search) {
                        $courseQuery->where('title', 'like', "%{$search}%");
                    })->orWhereHas('course.instructor', function ($instructorQuery) use ($search) {
                        $instructorQuery->where('firstname', 'like', "%{$search}%")
                            ->orWhere('lastname', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%");
                    });
                });
            })
            ->latest('id')
            ->paginate(getPaginate())
            ->appends([
                'search' => $search,
                'instructor_id' => $instructorId,
            ]);

        $purchaseCollection = collect($purchases->items());
        $purchaseIds = $purchaseCollection->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();
        $userIds = $purchaseCollection->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $courseIds = $purchaseCollection->pluck('course_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        $totalCoursesBoughtByKid = collect();
        if ($userIds->isNotEmpty()) {
            $totalCoursesBoughtByKid = CoursePurchased::whereIn('user_id', $userIds->all())
                ->where('payment_status', Status::PAYMENT_SUCCESS)
                ->selectRaw('user_id, COUNT(DISTINCT course_id) as total_courses_bought')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $bookings = collect();
        if ($purchaseIds->isNotEmpty()) {
            $bookings = CourseLiveBooking::whereIn('course_purchased_id', $purchaseIds->all())
                ->get()
                ->keyBy('course_purchased_id');
        }
        $bookingCourseIds = $bookings->pluck('course_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $allCourseIds = $courseIds->merge($bookingCourseIds)->unique()->values();

        $groupBatchIds = $bookings->filter(function ($booking) {
            return $booking->class_type === 'group' && !empty($booking->batch_id);
        })->pluck('batch_id')->map(fn ($id) => (int) $id)->unique()->values();

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

        $userEmails = $purchaseCollection->map(function ($purchase) {
            return strtolower((string) optional($purchase->user)->email);
        })->filter()->unique()->values();

        $trialRequestsByEmail = collect();
        if ($userEmails->isNotEmpty()) {
            $trialRequestsByEmail = ConsultationRequest::whereIn('email_address', $userEmails->all())
                ->orderByDesc('scheduled_at')
                ->get()
                ->groupBy(function ($item) {
                    return strtolower((string) $item->email_address);
                });
        }

        $oneToOneBookingIds = $bookings->filter(function ($booking) {
            return $booking && $booking->class_type === 'one_to_one';
        })->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        $oneToOneReleaseMap = collect();
        if ($oneToOneBookingIds->isNotEmpty()) {
            $oneToOneReleaseMap = CourseLectureOneToOneRelease::whereIn('live_booking_id', $oneToOneBookingIds->all())
                ->get(['live_booking_id', 'course_lecture_id', 'is_released'])
                ->mapWithKeys(function ($item) {
                    return [((int) $item->live_booking_id) . ':' . ((int) $item->course_lecture_id) => (bool) $item->is_released];
                });
        }

        $lectureOptionsByCourseId = collect();
        if ($allCourseIds->isNotEmpty()) {
            $sectionTitlesById = CourseSection::whereIn('course_id', $allCourseIds->all())
                ->pluck('title', 'id');

            $lectureOptionsByCourseId = CourseLecture::whereIn('course_id', $allCourseIds->all())
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

        $studentRows = $purchaseCollection->map(function ($purchase) use ($bookings, $sessionsByPurchase, $sessionsByBatch, $totalCoursesBoughtByKid, $trialRequestsByEmail) {
            $booking = $bookings->get($purchase->id);
            $classType = $booking?->class_type;
            $isGroup = $classType === 'group';
            $isOneToOne = $classType === 'one_to_one';

            $sessionCollection = collect();
            if ($isGroup && !empty($booking?->batch_id)) {
                $sessionCollection = collect($sessionsByBatch->get($booking->batch_id, collect()));
            } else {
                $sessionCollection = collect($sessionsByPurchase->get($purchase->id, collect()));
            }

            $coveredCount = $sessionCollection->whereIn('status', ['started', 'completed'])->count();
            $totalSessions = $sessionCollection->count();
            $leftSessions = max($totalSessions - $coveredCount, 0);
            $lastSessionAt = $sessionCollection->max('scheduled_at');
            $sessionZoomLinks = $sessionCollection->pluck('zoom_join_url')->filter()->unique()->values();

            $kidEmail = strtolower((string) optional($purchase->user)->email);
            $trialRequests = collect($trialRequestsByEmail->get($kidEmail, collect()));
            $trialCompletedAt = optional(
                $trialRequests->first(function ($trial) {
                    return !empty($trial->scheduled_at) && Carbon::parse($trial->scheduled_at)->lte(now());
                })
            )->scheduled_at;

            $courseInstructor = optional(optional($purchase->course)->instructor);
            $courseInstructorName = trim(($courseInstructor->firstname ?? '') . ' ' . ($courseInstructor->lastname ?? ''));

            $resolvedCourseId = (int) ($booking?->course_id ?: $purchase->course_id);
            $lectureOptions = $lectureOptionsByCourseId[$resolvedCourseId] ?? collect();
            if ($lectureOptions->isEmpty() && $resolvedCourseId > 0) {
                $sectionTitlesByIdFallback = CourseSection::where('course_id', $resolvedCourseId)->pluck('title', 'id');
                $lectureOptions = CourseLecture::where('course_id', $resolvedCourseId)
                    ->orderBy('id')
                    ->get(['id', 'course_id', 'course_section_id', 'title'])
                    ->map(function ($lecture) use ($sectionTitlesByIdFallback) {
                        return [
                            'id' => (int) $lecture->id,
                            'title' => (string) $lecture->title,
                            'section_title' => (string) ($sectionTitlesByIdFallback[(int) $lecture->course_section_id] ?? ''),
                        ];
                    })->values();
            }

            return [
                'kid_name' => optional($purchase->user)->fullname ?? trim((optional($purchase->user)->firstname . ' ' . optional($purchase->user)->lastname)),
                'kid_username' => optional($purchase->user)->username,
                'kid_email' => optional($purchase->user)->email,
                'course_title' => optional($purchase->course)->title,
                'class_type' => $classType ?: 'N/A',
                'sessions_covered' => $coveredCount,
                'sessions_left' => $leftSessions,
                'last_session_at' => $lastSessionAt,
                'course_instructor' => $courseInstructorName ?: ($courseInstructor->username ?? 'N/A'),
                'trial_completed_at' => $trialCompletedAt,
                'courses_bought' => (int) optional($totalCoursesBoughtByKid->get($purchase->user_id))->total_courses_bought,
                'trial_zoom_links' => $trialRequests->pluck('zoom_join_url')->filter()->unique()->values(),
                'one_to_one_zoom_links' => $isOneToOne ? $sessionZoomLinks : collect(),
                'group_zoom_links' => $isGroup ? $sessionZoomLinks : collect(),
                'booking_id' => (int) ($booking?->id ?? 0),
                'lecture_options' => $lectureOptions,
            ];
        })->values();

        return view('admin.instructors.students', compact('pageTitle', 'studentRows', 'purchases', 'instructors', 'search', 'instructorId', 'oneToOneReleaseMap'));
    }

    public function releaseOneToOneLecture(Request $request, $bookingId)
    {
        $request->validate([
            'lecture_id' => 'required|integer',
            'is_released' => 'required|boolean',
        ]);

        $booking = CourseLiveBooking::where('id', (int) $bookingId)
            ->where('class_type', 'one_to_one')
            ->first();
        if (!$booking) {
            $notify[] = ['error', 'Invalid one-to-one booking'];
            return back()->withNotify($notify);
        }

        $lecture = CourseLecture::where('course_id', $booking->course_id)->find((int) $request->lecture_id);
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

        $notify[] = ['success', 'One-to-one lecture release updated'];
        return back()->withNotify($notify);
    }


    public function allInstructors()
    {
        $pageTitle = 'All Academy';
        $instructors = $this->instructorData();
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }

    public function activeInstructors()
    {
        $pageTitle = 'Active Academy';
        $instructors = $this->instructorData('active');
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }

    public function pendingApprovalInstructors()
    {
        $pageTitle = 'Pending Approval Academy';
        $instructors = Instructor::onlyInstructors()
            ->where('status', Status::USER_BAN)
            ->where(function ($query) {
                $query->whereNull('ban_reason')->orWhere('ban_reason', '');
            })
            ->searchable(['username', 'email'])
            ->with(['courses', 'transactions'])
            ->orderBy('id', 'desc')
            ->paginate(getPaginate());

        $emptyMessage = 'No pending academy requests found';
        return view('admin.instructors.pending_approval', compact('pageTitle', 'instructors', 'emptyMessage'));
    }

    public function bannedInstructors()
    {
        $pageTitle = 'Banned Academy';
        $instructors = $this->instructorData('banned');
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }

    public function emailUnverifiedInstructors()
    {
        $pageTitle = 'Email Unverified Academy';
        $instructors = $this->instructorData('emailUnverified');
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }

    public function kycUnverifiedInstructors()
    {
        $pageTitle = 'KYC Unverified Academy';
        $instructors = $this->instructorData('kycUnverified');
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }

    public function kycPendingInstructors()
    {
        $pageTitle = 'KYC Pending Academy';
        $instructors = $this->instructorData('kycPending');
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }

    public function emailVerifiedInstructors()
    {
        $pageTitle = 'Email Verified Academy';
        $instructors = $this->instructorData('emailVerified');
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }


    public function mobileUnverifiedInstructors()
    {
        $pageTitle = 'Mobile Unverified Academy';
        $instructors = $this->instructorData('mobileUnverified');
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }


    public function mobileVerifiedInstructors()
    {
        $pageTitle = 'Mobile Verified Academy';
        $instructors = $this->instructorData('mobileVerified');
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }


    public function instructorsWithBalance()
    {
        $pageTitle = 'Academy with Balance';
        $instructors = $this->instructorData('withBalance');
        return view('admin.instructors.list', compact('pageTitle', 'instructors'));
    }


    protected function instructorData($scope = null){
        if ($scope) {
            $instructors = Instructor::$scope();
        }else{
            $instructors = Instructor::query();
        }
        return $instructors->searchable(['username','email'])->orderBy('id','desc')->paginate(getPaginate());
    }


    public function detail($id)
    {
        $instructor = Instructor::findOrFail($id);
        $pageTitle = 'Academy Detail - '.$instructor->username;

        $totalEarning = Transaction::where('instructor_id',$instructor->id)->where('remark','payment_received')->sum('amount');
        $totalWithdrawals = Withdrawal::where('instructor_id',$instructor->id)->approved()->sum('amount');
        $totalTransaction = Transaction::where('instructor_id',$instructor->id)->count();
        $countries = json_decode(file_get_contents(resource_path('views/partials/country.json')));

        
        $course['total_course']        = Course::where('instructor_id', $instructor->id)->count();
        $course['total_approved_course']       = Course::where('instructor_id', $instructor->id)->approved()->count();
        $course['total_pending_course']      = Course::where('instructor_id', $instructor->id)->pending()->count();
        $course['total_rejected_course']        = Course::where('instructor_id', $instructor->id)->rejected()->count();

        $purchases = CoursePurchased::with([
            'user:id,firstname,lastname,username,email',
            'course:id,title,instructor_id',
            'course.instructor:id,firstname,lastname,username',
            'liveBooking:id,course_purchased_id,class_type,batch_id,user_id,status',
        ])
            ->whereHas('course', function ($query) use ($instructor) {
                $query->where('instructor_id', $instructor->id);
            })
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->latest('id')
            ->get();

        $userIds = $purchases->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $purchaseIds = $purchases->pluck('id')->map(fn ($id) => (int) $id)->unique()->values();

        $totalCoursesBoughtByKid = collect();
        if ($userIds->isNotEmpty()) {
            $totalCoursesBoughtByKid = CoursePurchased::whereIn('user_id', $userIds->all())
                ->where('payment_status', Status::PAYMENT_SUCCESS)
                ->selectRaw('user_id, COUNT(DISTINCT course_id) as total_courses_bought')
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        $bookings = CourseLiveBooking::whereIn('course_purchased_id', $purchaseIds->all())
            ->get()
            ->keyBy('course_purchased_id');

        $groupBatchIds = $bookings->filter(function ($booking) {
            return $booking->class_type === 'group' && !empty($booking->batch_id);
        })->pluck('batch_id')->map(fn ($id) => (int) $id)->unique()->values();

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

        $userEmails = $purchases->map(function ($purchase) {
            return strtolower((string) optional($purchase->user)->email);
        })->filter()->unique()->values();

        $trialRequestsByEmail = collect();
        if ($userEmails->isNotEmpty()) {
            $trialRequestsByEmail = ConsultationRequest::whereIn('email_address', $userEmails->all())
                ->orderByDesc('scheduled_at')
                ->get()
                ->groupBy(function ($item) {
                    return strtolower((string) $item->email_address);
                });
        }

        $kidCourseRows = $purchases->map(function ($purchase) use ($bookings, $sessionsByPurchase, $sessionsByBatch, $totalCoursesBoughtByKid, $trialRequestsByEmail) {
            $booking = $bookings->get($purchase->id);
            $classType = $booking?->class_type;
            $isGroup = $classType === 'group';
            $isOneToOne = $classType === 'one_to_one';

            $sessionCollection = collect();
            if ($isGroup && !empty($booking?->batch_id)) {
                $sessionCollection = collect($sessionsByBatch->get($booking->batch_id, collect()));
            } else {
                $sessionCollection = collect($sessionsByPurchase->get($purchase->id, collect()));
            }

            $coveredStatuses = ['started', 'completed'];
            $coveredCount = $sessionCollection->whereIn('status', $coveredStatuses)->count();
            $totalSessions = $sessionCollection->count();
            $leftSessions = max($totalSessions - $coveredCount, 0);
            $lastSessionAt = $sessionCollection->max('scheduled_at');
            $sessionZoomLinks = $sessionCollection->pluck('zoom_join_url')->filter()->unique()->values();

            $kidEmail = strtolower((string) optional($purchase->user)->email);
            $trialRequests = collect($trialRequestsByEmail->get($kidEmail, collect()));
            $trialCompletedAt = optional(
                $trialRequests->first(function ($trial) {
                    return !empty($trial->scheduled_at) && Carbon::parse($trial->scheduled_at)->lte(now());
                })
            )->scheduled_at;
            $trialZoomLinks = $trialRequests->pluck('zoom_join_url')->filter()->unique()->values();

            $groupZoomLinks = $isGroup ? $sessionZoomLinks : collect();
            $oneToOneZoomLinks = $isOneToOne ? $sessionZoomLinks : collect();

            $courseInstructor = optional(optional($purchase->course)->instructor);
            $courseInstructorName = trim(($courseInstructor->firstname ?? '') . ' ' . ($courseInstructor->lastname ?? ''));

            return [
                'kid_name' => optional($purchase->user)->fullname ?? trim((optional($purchase->user)->firstname . ' ' . optional($purchase->user)->lastname)),
                'kid_username' => optional($purchase->user)->username,
                'kid_email' => optional($purchase->user)->email,
                'course_title' => optional($purchase->course)->title,
                'class_type' => $classType ?: 'N/A',
                'sessions_covered' => $coveredCount,
                'sessions_left' => $leftSessions,
                'last_session_at' => $lastSessionAt,
                'course_instructor' => $courseInstructorName ?: ($courseInstructor->username ?? 'N/A'),
                'trial_completed_at' => $trialCompletedAt,
                'courses_bought' => (int) optional($totalCoursesBoughtByKid->get($purchase->user_id))->total_courses_bought,
                'trial_zoom_links' => $trialZoomLinks,
                'one_to_one_zoom_links' => $oneToOneZoomLinks,
                'group_zoom_links' => $groupZoomLinks,
            ];
        })->values();
        
        return view('admin.instructors.detail', compact('pageTitle', 'instructor','totalEarning','totalWithdrawals','totalTransaction','countries','course', 'kidCourseRows'));
    }


    public function kycDetails($id)
    {
        $pageTitle = 'KYC Details';
        $instructor = Instructor::findOrFail($id);
        return view('admin.instructors.kyc_detail', compact('pageTitle','instructor'));
    }

    public function kycApprove($id)
    {
        $instructor = Instructor::findOrFail($id);
        $instructor->kv = Status::KYC_VERIFIED;
        $instructor->save();

        notify($instructor,'KYC_APPROVE',[]);

        $notify[] = ['success','KYC approved successfully'];
        return to_route('admin.instructors.kyc.pending')->withNotify($notify);
    }

    public function kycReject(Request $request,$id)
    {
        $request->validate([
            'reason'=>'required'
        ]);
        $instructor = Instructor::findOrFail($id);
        $instructor->kv = Status::KYC_UNVERIFIED;
        $instructor->kyc_rejection_reason = $request->reason;
        $instructor->save();

        notify($instructor,'KYC_REJECT',[
            'reason'=>$request->reason
        ]);

        $notify[] = ['success','KYC rejected successfully'];
        return to_route('admin.instructors.kyc.pending')->withNotify($notify);
    }


    public function update(Request $request, $id)
    {
        $instructor = Instructor::findOrFail($id);
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

        $instructor = instructor::findOrFail($id);
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

 

    public function status(Request $request,$id)
    {
        $instructor = Instructor::findOrFail($id);
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
        $instructor = Instructor::onlyInstructors()->findOrFail($id);
        $instructor->status = Status::USER_ACTIVE;
        $instructor->ban_reason = null;
        $instructor->save();

        $notify[] = ['success', 'Academy request approved successfully'];
        return back()->withNotify($notify);
    }

    public function rejectRequest(Request $request, $id)
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $instructor = Instructor::onlyInstructors()->findOrFail($id);
        $instructor->status = Status::USER_BAN;
        $instructor->ban_reason = $request->reason ?: 'Registration request rejected by admin';
        $instructor->save();

        $notify[] = ['success', 'Academy request rejected successfully'];
        return back()->withNotify($notify);
    }

    public function showNotificationSingleForm($id)
    {
        $instructor = Instructor::findOrFail($id);
        if (!gs('en') && !gs('sn') && !gs('pn')) {
            $notify[] = ['warning','Notification options are disabled currently'];
            return to_route('admin.instructors.detail',$instructor->id)->withNotify($notify);
        }
        $pageTitle = 'Send Notification to ' . $instructor->username;
        return view('admin.instructors.notification_single', compact('pageTitle', 'instructor'));
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

        $instructor = Instructor::findOrFail($id);
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

        $notifyToInstructor = Instructor::instructorNotify();
        $instructors        = Instructor::active()->count();
        $pageTitle    = 'Notification to Verified Academy';

        if (session()->has('SEND_NOTIFICATION') && !request()->email_sent) {
            session()->forget('SEND_NOTIFICATION');
        }

        return view('admin.instructors.notification_all', compact('pageTitle', 'instructors', 'notifyToInstructor'));
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
                  
                    $notify[] = ['error', "Ensure that the instructor field is populated when sending an email to the designated instructor group"];
                    return back()->withNotify($notify);
                }
            }
        }

        $scope          = $request->being_sent_to;
        $instructorQuery      = Instructor::oldest()->active()->$scope();

        if (session()->has("SEND_NOTIFICATION")) {
            $totalInstructorCount = session('SEND_NOTIFICATION')['total_instructor'];
        } else {
            $totalInstructorCount = (clone $instructorQuery)->count() - ($request->start-1);
        }

        if ($totalInstructorCount <= 0) {
            $notify[] = ['error', "Notification recipients were not found among the selected instructor base."];
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
            $url     = route("admin.instructors.notification.all");
        } else {
            session()->put('SEND_NOTIFICATION', $sessionData);
            $message = $sessionData['total_sent'] . " " . $sessionData['via'] . "  notifications were sent successfully";
            $url     = route("admin.instructors.notification.all") . "?email_sent=yes";
        }
        $notify[] = ['success', $message];
        return redirect($url)->withNotify($notify);
    }

    public function countBySegment($methodName){
        return Instructor::active()->$methodName()->count();
    }

    public function list()
    {
        $query = Instructor::active();

        if (request()->search) {
            $query->where(function ($q) {
                $q->where('email', 'like', '%' . request()->search . '%')->orWhere('username', 'like', '%' . request()->search . '%');
            });
        }
        $instructors = $query->orderBy('id', 'desc')->paginate(getPaginate());
        return response()->json([
            'success' => true,
            'instructors'   => $instructors,
            'more'    => $instructors->hasMorePages()
        ]);
    }

    public function notificationLog($id){
        $instructor = Instructor::findOrFail($id);
        $pageTitle = 'Notifications Sent to '.$instructor->username;
        $logs = NotificationLog::where('instructor_id',$id)->with('user')->orderBy('id','desc')->paginate(getPaginate());
        return view('admin.reports.notification_history', compact('pageTitle','logs','instructor'));
    }


}
