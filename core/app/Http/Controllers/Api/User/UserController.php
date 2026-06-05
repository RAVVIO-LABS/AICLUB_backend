<?php

namespace App\Http\Controllers\Api\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Lib\GoogleAuthenticator;
use App\Lib\S3DirectUploader;
use App\Models\CourseComplete;
use App\Models\CourseLiveSession;
use App\Models\CourseZoomBatch;
use App\Models\CourseZoomMeeting;
use App\Models\CourseZoomMeetingOccurrence;
use App\Models\ConsultationRequest;
use App\Models\Form;
use App\Models\Goal;
use App\Models\Transaction;
use App\Models\UserActivity;
use App\Models\UserGoal;
use App\Models\UserProgress;
use App\Rules\FileTypeValidate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{

    public function dashboard()
    {
        $user = auth()->user();
        $purchases = $user->purchases()
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->with(['course.curriculums', 'liveBooking'])
            ->get();
        $purchaseCourses = $user->purchases()
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->with(['course', 'liveBooking.batch'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($purchase) {
                $batch = optional($purchase->liveBooking)->batch;
                $batchId = (int) optional($purchase->liveBooking)->batch_id;
                $batchStart = null;
                $batchEnd = null;

                if (!$batch && $batchId > 0 && $purchase->course_id) {
                    $batch = CourseZoomBatch::select('id', 'title')->find($batchId);

                    $occurrenceQuery = CourseZoomMeetingOccurrence::whereHas('meeting', function ($query) use ($purchase, $batchId) {
                        $query->where('course_id', $purchase->course_id)
                            ->where('batch_id', $batchId);
                    })
                        ->where('status', '!=', 'cancelled')
                        ->whereNotNull('start_time');

                    $batchStart = $occurrenceQuery->min('start_time');
                    $batchEnd = $occurrenceQuery->max('start_time');
                }

                $purchase->batch = $batch;
                $purchase->batch_start_date = optional($batch)->start_date
                    ?: optional($purchase->liveBooking)->start_date
                    ?: $batchStart;
                $purchase->batch_end_date = optional($batch)->end_date
                    ?: $batchEnd;
                return $purchase;
            });


        $totalInstructor = $purchases->groupBy('course.instructor_id')->count();

        $totalCurriculum = $purchases->sum(function ($purchase) {
            return $purchase->course->curriculums->count();
        });

        $completedCurriculum = UserProgress::where('user_id', $user->id)->count();

        if ($totalCurriculum === 0) {
            $percentage = 0;
        } else {
            $percentage = ($completedCurriculum / $totalCurriculum) * 100;
        }

        $userGoal = UserGoal::where('user_id', $user->id)->first();

        $startOfWeek = Carbon::now()->startOfWeek();
        $endOfWeek = Carbon::now()->endOfWeek();

        $activityCount = UserActivity::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->count();
            
        $goal = UserGoal::where('user_id', $user->id)->first() ;


        $activeWeeks = UserActivity::where('user_id', $user->id)
            ->selectRaw("YEARWEEK(created_at, 1) as week, COUNT(DISTINCT DATE(created_at)) as active_days")
            ->groupBy('week')
            ->having('active_days', '>=', $goal->days ?? 0)
            ->count();

        $completeCourse =  CourseComplete::where('user_id',auth()->id())->count();

        $upcomingClasses = collect();
        foreach ($purchases as $purchase) {
            if (!$purchase->course_id) {
                continue;
            }

            $selectedBatchId = (int) optional($purchase->liveBooking)->batch_id;

            $sessions = CourseZoomMeetingOccurrence::with([
                'meeting:id,course_id,batch_id,zoom_join_url,status',
                'meeting.batch:id,title',
            ])
                ->whereHas('meeting', function ($query) use ($purchase, $selectedBatchId) {
                    $query->where('course_id', $purchase->course_id);
                    if ($selectedBatchId > 0) {
                        $query->where('batch_id', $selectedBatchId);
                    }
                })
                ->where('status', '!=', 'cancelled')
                ->whereNotNull('start_time')
                ->get()
                ->map(function ($occurrence) use ($purchase) {
                $meeting = $occurrence->meeting;
                return [
                    'id' => 'zoom-occurrence-' . $occurrence->id,
                    'occurrence_id' => (int) $occurrence->id,
                    'meeting_id' => (int) optional($meeting)->id,
                    'course_id' => $meeting->course_id,
                    'course_title' => $purchase->course?->title,
                    'course_slug' => $purchase->course?->slug,
                    'batch_id' => (int) ($meeting->batch_id ?? 0),
                    'batch_title' => optional($meeting->batch)->title,
                    'lecture_title' => $occurrence->title,
                    'scheduled_at' => $occurrence->start_time,
                    'duration_minutes' => (int) $occurrence->duration_minutes,
                    'zoom_join_url' => $occurrence->zoom_join_url ?: ($meeting->zoom_join_url ?? null),
                    'status' => $occurrence->status ?: ($meeting->status ?: 'scheduled'),
                ];
            });

            $upcomingClasses = $upcomingClasses->merge($sessions);
        }

        $demoClasses = ConsultationRequest::with(['assignedTeacher:id,firstname,lastname,username,email', 'course:id,title,slug'])
            ->where('email_address', $user->email)
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->get()
            ->map(function ($request) {
                return [
                    'id' => 'demo-class-' . $request->id,
                    'request_id' => (int) $request->id,
                    'meeting_id' => (int) optional($request->zoom_meeting_id),
                    'course_id' => (int) ($request->course_id ?? 0),
                    'course_title' => $request->course?->title ?: 'Free Demo Class',
                    'course_slug' => $request->course?->slug,
                    'batch_id' => 0,
                    'batch_title' => 'Free Demo Class',
                    'lecture_title' => 'Free Demo Class',
                    'scheduled_at' => $request->scheduled_at,
                    'duration_minutes' => (int) ($request->meeting_duration ?: 30),
                    'zoom_join_url' => $request->zoom_join_url,
                    'status' => $request->status,
                    'session_type' => 'demo',
                    'teacher_name' => trim((string) ($request->assignedTeacher?->firstname ?? '') . ' ' . (string) ($request->assignedTeacher?->lastname ?? '')) ?: null,
                ];
            });

        $upcomingClasses = $upcomingClasses->merge($demoClasses);

        $upcomingClasses = $upcomingClasses
            ->filter(function ($session) {
                return !empty($session['scheduled_at']) && strtotime((string) $session['scheduled_at']) >= now()->subMinutes(5)->getTimestamp();
            })
            ->sortBy('scheduled_at')
            ->values();



        $notify[] = 'Dashboard data get';
        return responseSuccess('dashboard_data', $notify, [
            'total_enroll' => count($purchases),
            'percentage' => round($percentage),
            'total_instructor' => $totalInstructor,
            'user_goal' => $userGoal,
            'purchase_courses' => $purchaseCourses,
            'completed_curriculum' => $completedCurriculum,
            'activity_count' => $activityCount,
            'active_weeks' => $activeWeeks,
            'complete_course' => $completeCourse,
            'upcoming_classes' => $upcomingClasses,
            
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
        $user->save();

        $notify[] = 'Profile completed successfully';
        return responseSuccess('profile_completed', $notify, ['user' => $user]);
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
        $form = Form::where('act', 'kyc')->first();
        $notify[] = 'KYC field is below';
        return responseSuccess('kyc_form', $notify, ['form' => $form->form_data]);
    }

    public function kycSubmit(Request $request)
    {
        $form = Form::where('act', 'kyc')->first();
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

    public function depositHistory(Request $request)
    {
        $deposits = auth()->user()->deposits();
        if ($request->search) {
            $deposits = $deposits->where('trx', $request->search);
        }
        $deposits = $deposits->with(['gateway'])->orderBy('id', 'desc')->paginate(getPaginate());
        $notify[] = 'Deposit data';
        return responseSuccess('deposits', $notify, ['deposits' => $deposits]);
    }

    public function transactions(Request $request)
    {
        $remarks = Transaction::distinct('remark')->get('remark');
        $transactions = Transaction::where('user_id', auth()->id())->where('instructor_id', 0);

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


    public function presignUpload(Request $request)
    {
        if (env('UPLOAD_DISK', 's3') !== 's3') {
            return responseError('upload_disk_invalid', ['Direct upload is available only for s3 disk']);
        }

        $validator = Validator::make($request->all(), [
            'purpose' => 'required|in:user_profile',
            'file_name' => 'required|string|max:255',
            'content_type' => 'required|string|max:255',
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $original = (string) $request->file_name;
        $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return responseError('validation_error', ['file_name' => ['Invalid file extension for selected purpose.']]);
        }

        $safeName = S3DirectUploader::safeFileName($original);
        $presigned = S3DirectUploader::createPresignedPutUrl(getFilePath('userProfile'), $safeName, (string) $request->content_type);

        $notify[] = 'Presigned upload URL generated';
        return responseSuccess('upload_presigned', $notify, [
            'upload_url' => $presigned['upload_url'],
            'key' => $presigned['key'],
            'file_name' => $presigned['file_name'],
            'headers' => [
                'Content-Type' => (string) $request->content_type,
            ],
        ]);
    }
    public function submitProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'firstname' => 'required',
            'lastname' => 'required',
            'profile_image'=>['nullable',new FileTypeValidate(['jpg','jpeg','png'])],
            'biography'=> "nullable|max:255",
            'profile_image_uploaded_filename' => 'nullable|string|max:255'
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
        $user->biography = $request->biography;
        if($request->hasFile('profile_image')){
            $user->profile_image = fileUploader($request->profile_image, getFIlePath('userProfile'), getFileSize('userProfile'), $user->profile_image);
        }

        $user->save();

        $notify[] = 'Profile updated successfully';
        return responseSuccess('profile_updated', $notify,[
            'user'=>$user,
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
            $user->save();
            $notify[] = 'Password changed successfully';
            return responseSuccess('password_changed', $notify);
        } else {
            $notify[] = 'The password doesn\'t match!';
            return responseError('validation_error', $notify);
        }
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
            $user->save();
            $notify[] = 'Two factor authenticator deactivated successfully';
            return responseSuccess('2fa_qr', $notify);
        } else {
            $notify[] = 'Wrong verification code';
            return responseError('wrong_verification', $notify);
        }
    }

  



    public function userInfo()
    {
        $notify[] = 'User information';
        return responseSuccess('user_info', $notify, ['user' => auth()->user()]);
    }

    public function deleteAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agree' => 'required',
        ]);
        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user = auth()->user();
        $user->username = 'deleted_' . $user->username;
        $user->email = 'deleted_' . $user->email;
        $user->provider_id = 'deleted_' . $user->provider_id;
        $user->save();

        $user->tokens()->delete();

        $notify[] = 'Account deleted successfully';
        return responseSuccess('account_deleted', $notify);
    }


    public function goals()
    {
        $goals = Goal::active()->get();
        $userGoal = UserGoal::where('user_id', auth()->id())->first();

        $notify[] = 'Goals';
        return responseSuccess('goals', $notify, [
            'goals' => $goals,
            'user_goal' => $userGoal,
        ]);
    }


    public function saveGoal(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'goal' => 'required',
            'days' => 'required|between:1,7'
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }
        $goal = Goal::active()->find($request->goal);
        if (!$goal) {
            $notify[] = 'Goal not found';
            return responseError('goal_not_found', $notify);
        }

        $userGoal = $request->user_goal_id ? UserGoal::find($request->user_goal_id) : new UserGoal();
        $userGoal->user_id = auth()->id();
        $userGoal->goal_id = $request->goal;
        $userGoal->days = $request->days;
        $userGoal->save();

        $notify[] = 'Goal saved successfully';
        return responseSuccess('goal_saved', $notify, [
            'user_goal' => $userGoal,
        ]);
    }



    public function downloadAttachment($fileHash)
    {

        try {
            $filePath = decrypt($fileHash);
        } catch (\Exception $e) {
            $notify[] = 'Invalid file';
            return responseError('invalid_failed', $notify);
        }
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $title = slug(gs('site_name')) . '-attachments.' . $extension;
        $cloudDisk = env('UPLOAD_DISK', 's3');
        $isCloud = $cloudDisk === 's3';
        $cloudKey = ltrim($filePath, '/');
        try {
            $mimetype = $isCloud
                ? (Storage::disk($cloudDisk)->mimeType($cloudKey) ?: 'application/octet-stream')
                : mime_content_type($filePath);
        } catch (\Exception $e) {
            $notify[] = 'File downloaded failed';
            return responseError('download_failed', $notify);
        }
        if (!headers_sent()) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET,');
            header('Access-Control-Allow-Headers: Content-Type');
        }
        if ($isCloud) {
            if (!Storage::disk($cloudDisk)->exists($cloudKey)) {
                $notify[] = 'File downloaded failed';
                return responseError('download_failed', $notify);
            }

            return response()->streamDownload(function () use ($cloudDisk, $cloudKey) {
                echo Storage::disk($cloudDisk)->get($cloudKey);
            }, $title, [
                'Content-Type' => $mimetype,
            ]);
        }

        return response()->download($filePath, $title, [
            'Content-Type' => $mimetype,
        ]);
    }
}
