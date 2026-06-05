<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Lib\GoogleAuthenticator;
use Illuminate\Support\Facades\Storage;
use App\Lib\S3DirectUploader;
use App\Models\Course;
use App\Models\CourseComplete;
use App\Models\CourseLiveSession;
use App\Models\CoursePurchased;
use App\Models\Form;
use App\Models\Review;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserProgress;
use App\Models\Withdrawal;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class InstructorController extends Controller
{

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

        $totalStudent = User::whereHas('purchases', function ($query) {
            $query->where('payment_status', Status::PAYMENT_SUCCESS)
                ->where('instructor_id', auth()->id());
        })->count();


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

        $liveSessions = CourseLiveSession::with(['course', 'batch', 'user'])
            ->where('assigned_teacher_id', auth()->id())
            ->whereIn('status', ['scheduled', 'assigned', 'started'])
            ->orderBy('scheduled_at')
            ->limit(10)
            ->get();


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

        $user->save();

        $notify[] = 'Profile completed successfully';
        return responseSuccess('profile_completed', $notify, ['user' => $user]);
    }



    public function presignUpload(Request $request)
    {
        if (env('UPLOAD_DISK', 's3') !== 's3') {
            return responseError('upload_disk_invalid', ['Direct upload is available only for s3 disk']);
        }

        $validator = Validator::make($request->all(), [
            'purpose' => 'required|in:lecture_video,resource_file,course_image,course_intro,instructor_profile',
            'file_name' => 'required|string|max:255',
            'content_type' => 'required|string|max:255',
        ]);

        if ($validator->fails()) return responseError('validation_error', $validator->errors());

        $purpose = (string) $request->purpose;
        $original = (string) $request->file_name;
        $ext = strtolower((string) pathinfo($original, PATHINFO_EXTENSION));

        $map = [
            'lecture_video' => ['path' => getFilePath('video'), 'ext' => ['mp4', 'mkv', 'webm', 'mov', 'vob', 'avi', 'mpeg']],
            'resource_file' => ['path' => getFilePath('resources'), 'ext' => ['mp4', 'mkv', 'webm', 'mov', 'vob', 'avi', 'mpeg', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'pdf', 'ppt', 'pptx', 'zip', 'rar']],
            'course_image' => ['path' => getFilePath('courseImage'), 'ext' => ['jpg', 'jpeg', 'png']],
            'course_intro' => ['path' => getFilePath('courseIntro'), 'ext' => ['mp4', 'mkv', 'webm', 'mov', 'vob', 'avi', 'mpeg']],
            'instructor_profile' => ['path' => getFilePath('instructorProfile'), 'ext' => ['jpg', 'jpeg', 'png']],
        ];

        if (!in_array($ext, $map[$purpose]['ext'], true)) {
            return responseError('validation_error', ['file_name' => ['Invalid file extension for selected purpose.']]);
        }

        $safeName = S3DirectUploader::safeFileName($original);
        $presigned = S3DirectUploader::createPresignedPutUrl($map[$purpose]['path'], $safeName, (string) $request->content_type);

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
            'profile_image' => ['nullable', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
            'biography' => "nullable|max:255",
            'designation' => 'required',
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
        $user->zip = $request->zip;
        $user->biography = $request->biography;
        $user->designation = $request->designation;
        if ($request->hasFile('profile_image')) {
            $user->image = fileUploader($request->profile_image, getFIlePath('instructorProfile'), getFileSize('instructorProfile'), $user->profile_image);
        }

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
        $review->save();
  
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
