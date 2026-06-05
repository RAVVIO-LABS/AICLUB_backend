<?php

namespace App\Http\Controllers\Api;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Category;
use App\Models\ConsultationRequest;
use App\Models\ConsultationNotificationRecipient;
use App\Models\Course;
use App\Models\CourseLectureBatchRelease;
use App\Models\CourseLectureOneToOneRelease;
use App\Models\CoursePurchased;
use App\Models\Extension;
use App\Models\Frontend;
use App\Models\GatewayCurrency;
use App\Models\GetCertificateUser;
use App\Models\Instructor;
use App\Models\Language;
use App\Models\Page;
use App\Models\SearchKeyword;
use App\Models\SubCategory;
use App\Models\Subscriber;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\ContactAcknowledgementNotification;
use App\Notifications\ContactMessageToRecipientNotification;
use App\Notifications\ConsultationBookedNotification;
use App\Services\Reminder\FreeClassReminderService;
use App\Services\Zoom\LmsZoomService;
use App\Traits\SupportTicketManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AppController extends Controller
{
    use SupportTicketManager;

    public function __construct()
    {
        $this->userType     = 'user';
        $this->column       = 'user_id';
        $this->user = auth()->user();
        $this->apiRequest = true;
    }

    public function allSections($key = null)
{
   
    return Frontend::where(function ($query) use ($key) {
        if ($key == 'maintenance') {
            $query->where('data_keys', "{$key}.data");
        } else {
            $query->where('data_keys', 'like', '%.content');
        }
    })->get()->map(function ($content) {
        $sectionKey = explode('.', $content->data_keys)[0];
        $elements = Frontend::where('data_keys', "{$sectionKey}.element")->get();
        


        return [
            'key' => $sectionKey,
            'content' => $content,
            'elements' => $elements,
        ];
    });
}


    public function generalSetting()
    {
        $notify[] = 'General setting data';

        $data = [
            'general_setting' => gs(),
            'social_login_redirect' => route('user.social.login.callback', ''),
        ];

        return responseSuccess('general_setting', $notify, $data);
    }

    public function getCountries()
    {
        $countryData = json_decode(file_get_contents(resource_path('views/partials/country.json')));
        $notify[] = 'Country List';
        foreach ($countryData as $k => $country) {
            $countries[] = [
                'country' => $country->country,
                'dial_code' => $country->dial_code,
                'country_code' => $k,
            ];
        }

        return responseSuccess('country_data', $notify, [
            'countries' => $countries,
        ]);
    }

    public function getLanguage($code = null)
    {
        $languages = Language::get();
        $languageCodes = $languages->pluck('code')->toArray();

        if (($code && !in_array($code, $languageCodes))) {
            $notify[] = 'Invalid code given';
            return response()->json([
                'remark' => 'validation_error',
                'status' => 'error',
                'message' => ['error' => $notify]
            ]);
        }

        if (!$code) {
            $code = Language::where('is_default', Status::YES)->first()?->code ?? 'en';
        }

        $jsonFile = file_get_contents(resource_path('lang/' . $code . '.json'));


        if (!$jsonFile) {
            $notify[] = 'Language file not found';
            return responseError('language_file_not_found', $notify);
        }



        $notify[] = 'Language';

        return responseSuccess('language', $notify, [
            'languages' => $languages,
            'file' => json_decode($jsonFile) ?? [],
            'code' => $code,
            'image_path' => getFilePath('language')
        ]);
    }

    public function policies()
    {
        $policies = getContent('policy_pages.element', orderById: true);
        $notify[] = 'All policies';

        return responseSuccess('policy_data', $notify, [
            'policies' => $policies,
        ]);
    }


    public function policyContent($slug)
    {
        $policy = Frontend::where('slug', $slug)->where('data_keys', 'policy_pages.element')->first();
        if (!$policy) {
            $notify[] = 'Policy not found';
            return responseError('policy_not_found', $notify);
        }

        $seoContents = $policy->seo_content;
        $seoImage = @$seoContents->image ? frontendImage('policy_pages', $seoContents->image, getFileSize('seo'), true) : null;
        $notify[] = 'Policy content';

        return responseSuccess('policy_content', $notify, [
            'policy' => $policy,
            'seo_content' => $seoContents,
            'seo_image' => $seoImage
        ]);
    }


    public function faq()
    {
        $faq = getContent('faq.element', orderById: true);
        $notify[] = 'FAQ';
        return responseSuccess('faq', $notify, ['faq' => $faq]);
    }

    public function seo()
    {
        $notify[] = 'Global SEO data';
        $seo = Frontend::where('data_keys', 'seo.data')->first();
        return responseSuccess('seo', $notify, ['seo_content' => $seo]);
    }


    public function getExtension($act)
    {
        $notify[] = 'Extension Data';
        $extension = Extension::where('status', Status::ENABLE)->where('act', $act)->first()?->makeVisible('shortcode');
        return responseSuccess('extension', $notify, [
            'extension' => $extension,
            'custom_captcha' => $act == 'custom-captcha' ? loadCustomCaptcha() : null
        ]);
    }

    public function submitContact(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required',
            'subject' => 'required|string|max:255',
            'message' => 'required',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        if (!verifyCaptcha()) {
            $notify[] = 'Invalid captcha provided';
            return responseError('captcha_error', $notify);
        }

        $random = getNumber();

        $ticket = new SupportTicket();
        $ticket->user_id = 0;
        $ticket->name = $request->name;
        $ticket->email = $request->email;
        $ticket->priority = Status::PRIORITY_MEDIUM;

        $ticket->ticket = $random;
        $ticket->subject = $request->subject;
        $ticket->last_reply = Carbon::now();
        $ticket->status = Status::TICKET_OPEN;
        $ticket->save();

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = 0;
        $adminNotification->title = 'A new contact message has been submitted';
        $adminNotification->click_url = urlPath('admin.ticket.view', $ticket->id);
        $adminNotification->save();

        $message = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->message = $request->message;
        $message->save();

        $this->configureConsultationMailTransport();

        $recipients = ConsultationNotificationRecipient::where('is_active', true)->pluck('email')->all();
        $legacyRecipient = strtolower(trim((string) gs('consultation_notification_email')));
        if ($legacyRecipient && filter_var($legacyRecipient, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $legacyRecipient;
        }

        $contactPayload = [
            'name' => $request->name,
            'email' => strtolower(trim((string) $request->email)),
            'subject' => $request->subject,
            'message' => $request->message,
        ];

        foreach (array_unique($recipients) as $recipientEmail) {
            try {
                Notification::route('mail', $recipientEmail)
                    ->notify(new ContactMessageToRecipientNotification($contactPayload));
            } catch (\Throwable $throwable) {
                Log::error('Failed to send contact notification email to recipient', [
                    'recipient' => $recipientEmail,
                    'ticket_id' => $ticket->id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        try {
            Notification::route('mail', $contactPayload['email'])
                ->notify(new ContactAcknowledgementNotification($request->name));
        } catch (\Throwable $throwable) {
            Log::error('Failed to send contact acknowledgment email', [
                'email' => $contactPayload['email'],
                'ticket_id' => $ticket->id,
                'error' => $throwable->getMessage(),
            ]);
        }

        $notify[] = 'Contact form submitted successfully';
        return responseSuccess('contact_form_submitted', $notify, ['ticket' => $ticket]);
    }

    public function submitConsultationRequest(Request $request, LmsZoomService $zoomService, FreeClassReminderService $freeClassReminderService)
    {
        $rawPhone = preg_replace('/[\s\-()]/', '', (string) $request->phone_number);
        if (preg_match('/^[6-9][0-9]{9}$/', $rawPhone)) {
            $rawPhone = '+91' . $rawPhone;
        }

        $request->merge([
            'phone_number' => $rawPhone,
            'email_address' => strtolower(trim((string) $request->email_address)),
        ]);

        $validator = Validator::make($request->all(), [
            'parent_student_name' => 'required|string|max:120',
            'child_grade' => 'required|string|max:30',
            'phone_number' => ['required', 'regex:/^\+91[6-9][0-9]{9}$/'],
            'email_address' => 'required|email:rfc|max:120',
            'preferred_at' => 'required|date|after:now',
        ], [
            'phone_number.regex' => 'Please enter a valid Indian mobile number with +91 country code.',
            'email_address.email' => 'Please provide a valid email address.',
            'preferred_at.required' => 'Please select your preferred date and time.',
            'preferred_at.after' => 'Please select a future date and time.',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $preferredAt = Carbon::parse($request->preferred_at);
        $minAllowedAt = now()->addHours(48);
        if ($preferredAt->lt($minAllowedAt)) {
            $notify[] = 'Preferred date and time must be at least 48 hours from now.';
            return responseError('validation_error', $notify);
        }
        if ($preferredAt->isSunday()) {
            $notify[] = 'Sunday slots are not available. Please choose Monday to Saturday.';
            return responseError('validation_error', $notify);
        }
        $preferredTime = $preferredAt->format('H:i');
        if ($preferredTime < '10:00' || $preferredTime > '19:00') {
            $notify[] = 'Preferred time must be between 10:00 and 19:00.';
            return responseError('validation_error', $notify);
        }
        if (((int) $preferredAt->format('i')) % 30 !== 0) {
            $notify[] = 'Preferred time must be in 30-minute intervals.';
            return responseError('validation_error', $notify);
        }

        $durationMinutes = 60;
        $endAt = $preferredAt->copy()->addMinutes($durationMinutes);
        $host = $zoomService->selectHost($preferredAt, $endAt);
        if (!$host) {
            $notify[] = 'No Zoom host is available for the selected time.';
            return responseError('validation_error', $notify);
        }

        try {
            $meetingName = 'AIClub Free Class - ' . trim((string) $request->child_grade);
            $meeting = $zoomService->createMeeting([
                'host_email' => $host['email'],
                'meeting_name' => $meetingName,
                'start_time' => $preferredAt,
                'duration_minutes' => $durationMinutes,
                'agenda' => $meetingName,
            ]);
        } catch (\Throwable $throwable) {
            $notify[] = $throwable->getMessage();
            return responseError('zoom_create_failed', $notify);
        }

        $loginEmail = $request->email_address;
        $plainPassword = null;
        $studentUser = User::where('email', $request->email_address)->first();
        if (!$studentUser) {
            $plainPassword = Str::upper(Str::random(10));
            $nameParts = preg_split('/\s+/', trim((string) $request->parent_student_name));
            $firstName = trim((string) ($nameParts[0] ?? 'Student'));
            $lastName = trim((string) implode(' ', array_slice($nameParts ?: [], 1)));
            $mobileWithoutCode = preg_replace('/^\+91/', '', (string) $request->phone_number);

            $studentUser = new User();
            $studentUser->firstname = $firstName;
            $studentUser->lastname = $lastName ?: null;
            $studentUser->email = $request->email_address;
            $studentUser->dial_code = '+91';
            $studentUser->mobile = $mobileWithoutCode;
            $studentUser->username = null;
            $studentUser->password = Hash::make($plainPassword);
            $studentUser->status = Status::USER_ACTIVE;
            $studentUser->kv = gs('kv') ? Status::UNVERIFIED : Status::VERIFIED;
            $studentUser->profile_complete = Status::NO;
            $studentUser->ev = Status::VERIFIED;
            $studentUser->sv = Status::VERIFIED;
            $studentUser->ts = Status::DISABLE;
            $studentUser->tv = Status::VERIFIED;
            $studentUser->save();
        }

        $consultationRequest = new ConsultationRequest();
        $consultationRequest->parent_student_name = $request->parent_student_name;
        $consultationRequest->child_grade = $request->child_grade;
        $consultationRequest->phone_number = $request->phone_number;
        $consultationRequest->email_address = $request->email_address;
        $consultationRequest->preferred_at = $preferredAt;
        $consultationRequest->scheduled_at = $preferredAt;
        $consultationRequest->meeting_duration = $durationMinutes;
        $consultationRequest->zoom_meeting_id = $meeting['id'] ?? null;
        $consultationRequest->zoom_join_url = $meeting['join_url'] ?? null;
        $consultationRequest->zoom_host_email = $meeting['host_email'] ?? ($host['email'] ?? null);
        $consultationRequest->status = 'scheduled';
        $consultationRequest->source = $request->source ?? 'hero_modal';
        $consultationRequest->confirmation_sent_at = null;
        $consultationRequest->reminder_24h_sent_at = null;
        $consultationRequest->reminder_1h_sent_at = null;
        $consultationRequest->save();

        $this->configureConsultationMailTransport();

        $recipients = ConsultationNotificationRecipient::where('is_active', true)->pluck('email')->all();
        $legacyRecipient = strtolower(trim((string) gs('consultation_notification_email')));
        if ($legacyRecipient && filter_var($legacyRecipient, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $legacyRecipient;
        }

        foreach (array_unique($recipients) as $recipientEmail) {
            try {
                Notification::route('mail', $recipientEmail)
                    ->notify(new ConsultationBookedNotification($consultationRequest));
            } catch (\Throwable $throwable) {
                Log::error('Failed to send consultation booking notification email', [
                    'recipient' => $recipientEmail,
                    'consultation_request_id' => $consultationRequest->id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }

        $freeClassReminderService->sendConfirmation($consultationRequest, $loginEmail, $plainPassword);

        $adminNotification = new AdminNotification();
        $adminNotification->user_id = 0;
        $adminNotification->title = 'New consultation request received';
        $adminNotification->click_url = route('admin.consultation.requests.index');
        $adminNotification->save();

        $notify[] = 'Consultation request submitted successfully';
        return responseSuccess('consultation_request_submitted', $notify, [
            'consultation_request' => $consultationRequest,
        ]);
    }

    private function configureConsultationMailTransport(): void
    {
        $mailConfig = gs('mail_config');
        if (!$mailConfig || empty($mailConfig->name)) {
            return;
        }

        if ($mailConfig->name === 'smtp') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $mailConfig->host ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => (int) ($mailConfig->port ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.encryption' => $mailConfig->enc ?? config('mail.mailers.smtp.encryption'),
                'mail.mailers.smtp.username' => $mailConfig->username ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $mailConfig->password ?? config('mail.mailers.smtp.password'),
                'mail.from.address' => gs('email_from') ?: config('mail.from.address'),
                'mail.from.name' => gs('email_from_name') ?: config('mail.from.name'),
            ]);
            return;
        }

        if ($mailConfig->name === 'php') {
            config([
                'mail.default' => 'sendmail',
                'mail.from.address' => gs('email_from') ?: config('mail.from.address'),
                'mail.from.name' => gs('email_from_name') ?: config('mail.from.name'),
            ]);
        }
    }

    public function cookie()
    {
        $cookie = Frontend::where('data_keys', 'cookie.data')->first();
        $notify[] = 'Cookie policy';

        return responseSuccess('cookie_data', $notify, [
            'cookie' => $cookie
        ]);
    }

    public function cookieAccept()
    {
        Cookie::queue('gdpr_cookie', gs('site_name'), 43200);
        $notify[] = 'Cookie accepted';

        return responseSuccess('cookie_accepted', $notify);
    }


    public function customPages()
    {
        $pages = Page::where('tempname', activeTemplate())->get();
        $notify[] = 'Custom pages';
        return responseSuccess('custom_pages', $notify, [
            'pages' => $pages
        ]);
    }


    public function customPageData($slug)
    {
        if ($slug == 'home') $slug = '/';

        // default is home page, the where clause for default page is removed
        $page = Page::where('tempname', activeTemplate())->where('slug', $slug)->first();

        if (!$page) {
            $notify[] = 'Page not found';
            return responseError('page_not_found', $notify);
        }

        $seoContents = $page->seo_content;
        $seoImage = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        $notify[] = 'Custom page';
        return responseSuccess('custom_page', $notify, [
            'page' => $page,
            'seo_content' => $seoContents,
            'seo_image' => $seoImage
        ]);
    }

    public function sectionData($section)
    {

        $content = Frontend::where('data_keys', "{$section}.content")->first();

        $elements = Frontend::where('data_keys', "{$section}.element")->select('id', 'data_values')->get();

        $dataContent = Frontend::where('data_keys', "{$section}.data")->first();

        $data = [
            'data' => @$dataContent->data_values,
            'content' => @$content->data_values,
            'elements' => $elements->map(function ($element) {
                return $element->data_values;
            })
        ];

        $notify[] = 'Section data';
        return responseSuccess('section_data', $notify, $data);
    }


    public function blogs()
    {
        $blogs = Frontend::where('data_keys', 'blog.element')->paginate(getPaginate(12));
        $notify[] = 'Blog page data';
        return responseSuccess('blogs', $notify, [
            'blogs' => $blogs,

        ]);
    }


    public function blogDetails($slug)
    {
        $blog = Frontend::where('slug', $slug)->where('data_keys', 'blog.element')->first();


        if (!$blog) {
            $notify[] = 'blog not found';
            return responseError('blog_not_found', $notify);
        }
        $latestBlogs = Frontend::where('slug', '!=', $slug)->where('data_keys', 'blog.element')->InRandomOrder()->take(6)->get();
        $pageTitle = $blog->data_values->title;
        $seoContents = $blog->seo_content;
        $seoImage = @$seoContents->image ? frontendImage('blog', $seoContents->image, getFileSize('seo'), true) : null;

        $notify[] = 'Blog details';
        return responseSuccess('blog_details', $notify, [
            'blog' => $blog,
            'latest_blogs' => $latestBlogs,
            'pageTitle' => $pageTitle,
            'seo_content' => $seoContents,
            'seo_image' => $seoImage
        ]);
    }





    public function subscription(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $subscriber = Subscriber::where('email', $request->email)->first();

        if ($subscriber) {
            $notify[] = 'Email already subscribed';
            return responseError('email_already_subscribed', $notify);
        }

        $subscriber = new Subscriber();
        $subscriber->email = $request->email;
        $subscriber->save();

        $notify[] = 'Email subscribed successfully';
        return responseSuccess('email_subscribed', $notify);
    }

    public function categories()
    {
        $categories = Category::with('subCategories')->active()->get();
        $notify[] = ' Category data get';
        return responseSuccess('categories', $notify, [
            'categories' => $categories,
        ]);
    }


    public function popularCourses()
    {
        $categories = Category::whereHas('courses', function ($query) {
            $query->active()->where('is_populer', Status::YES);
        })->with([
            'courses' => function ($query) {
                $query->active()->where('is_populer', Status::YES)->with('sections');
            }
        ])->orderBy('id', 'desc')->get();


        $notify[] = 'Popular courses data get';
        return responseSuccess('popular_courses', $notify, [
            'categories' => $categories,
        ]);
    }



    public function courseDetail($slug)
    {
        $course = Course::with(
            'sections',
            'lectures',
            'quizzes',
            'sections.curriculums.lectures',
            'sections.curriculums.quizzes',
            'sections.curriculums.quizzes.questions.options',
            'objects',
            'requirements',
            'contents',
            'sections.curriculums.lectures.resources',
            'instructor',
            'purchases',
            'category',
            'liveBatches.teachers.teacher',
            'zoomBatches.teacher:id,firstname,lastname,email',
            'zoomBatches.meetings.occurrences'
        )->where('slug', $slug)->first();

        if (!$course) {
            $notify[] = ['error', 'Invalid course'];
            return responseError('course_not_found', $notify);
        }


        $enrollCourse = false;
        if (request()->user_id) {
            $user = User::active()->find(request()->user_id);
            $enrollCourse = $course->purchases()->where('payment_status', Status::PAYMENT_SUCCESS)->where('user_id', request()->user_id)->exists();
        }

        // Do not expose syllabus/curriculum data before enrollment.
        if (!$enrollCourse) {
            $course->setRelation('sections', collect());
            $course->setRelation('lectures', collect());
            $course->setRelation('quizzes', collect());
        } else {
            // Enrolled students can only access sections released by teacher/admin.
            $releasedSections = $course->sections
                ->filter(function ($section) {
                    return (int) ($section->is_released_to_students ?? 1) === 1;
                })
                ->values();
            $releasedSectionIds = $releasedSections->pluck('id')->all();

            $course->setRelation('sections', $releasedSections);
            $course->setRelation('lectures', $course->lectures
                ->whereIn('course_section_id', $releasedSectionIds)
                ->values());
            $course->setRelation('quizzes', $course->quizzes
                ->whereIn('course_section_id', $releasedSectionIds)
                ->values());

            $selectedBatchId = 0;
            $classType = '';
            if (request()->user_id) {
                $purchase = CoursePurchased::where('user_id', request()->user_id)
                    ->where('course_id', $course->id)
                    ->where('payment_status', Status::PAYMENT_SUCCESS)
                    ->with('liveBooking')
                    ->first();
                $selectedBatchId = (int) optional($purchase?->liveBooking)->batch_id;
                $classType = (string) optional($purchase?->liveBooking)->class_type;
            }

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
                $course->setRelation('quizzes', $course->quizzes
                    ->whereIn('course_section_id', $visibleSectionIds)
                    ->values());
            }

            if ($classType === 'one_to_one' && optional($purchase?->liveBooking)->id && request()->user_id) {
                $liveBookingId = (int) $purchase->liveBooking->id;
                $releasedLectureIds = CourseLectureOneToOneRelease::where('course_id', $course->id)
                    ->where('live_booking_id', $liveBookingId)
                    ->where('user_id', (int) request()->user_id)
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
                $course->setRelation('quizzes', $course->quizzes
                    ->whereIn('course_section_id', $visibleSectionIds)
                    ->values());
            }
        }

        $course->zoom_meeting_link = null;
        $course->batch_number = null;

        if ($course->liveBatches) {
            $course->liveBatches->transform(function ($batch) {
                $batch->zoom_meeting_link = null;
                return $batch;
            });
        }

        if ($course->zoomBatches) {
            $course->zoomBatches->transform(function ($batch) {
                $batch->setRelation('meetings', $batch->meetings->sortBy('start_time')->values());
                return $batch;
            });
        }

        $enrolledUsers = User::whereHas('purchases', function ($query) use ($course) {
            $query->where('payment_status', Status::PAYMENT_SUCCESS)->where('course_id', $course->id);
        })->latest()->take(4)->get();


        $notify[] = 'Course details fetched successfully';
        return responseSuccess('course_details', $notify, [
            'course' => $course,
            'enroll_course' => $enrollCourse,
            'enrolled_users' => $enrolledUsers
        ]);
    }


    public function methods()
    {
        $gatewayCurrency = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE)->withoutGlobalScopes();
        })->with('method')->orderby('name')->withoutGlobalScopes()->get();


        $notify[] = 'Payment Methods';

        return responseSuccess('deposit_methods', $notify, [
            'methods' => $gatewayCurrency,
            'image_path' => getFilePath('gateway')
        ]);
    }

    public function allCourses($slug = null)
    {

        $levels = request()->has('level') ? explode(',', request()->level) : [];
        $categoryFilters = request()->has('categories') ? explode(',', request()->categories) : [];
        $subCategoryFilters = request()->has('sub_categories') ? explode(',', request()->sub_categories) : [];
     

       

        $query = Course::active()->searchable(['title', 'instructor:username', 'category:name', 'subcategory:name'])->with(['instructor', 'sections'])
            ->active();


        if (request()->has('sort')) {
            if (request()->sort === 'new') {
                $query->orderBy('created_at', 'desc');
            } elseif (request()->sort === 'popular') {
                $query->where('is_populer', Status::YES)->withAvg('reviews', 'rating')
                    ->orderBy('reviews_avg_rating', 'desc');
            } elseif (request()->sort === 'rate') {
                $query->withAvg('reviews', 'rating')
                    ->orderBy('reviews_avg_rating', 'desc');
            }
            else if(request()->sort === 'high_to_low'){
                $query->orderBy('price', 'desc');
            }else if(request()->sort == 'low_to_high'){
                $query->orderBy('price', 'asc');
            }

        } else {
            $query->orderBy('id', 'DESC');
        }

        if(request()->rating){
            $rating =  request()->rating;
            $query->whereHas('reviews', function ($q) use ($rating) {
                $q->where('rating','>=', $rating);
            });
        }




        if (!empty($levels) && !in_array(0, $levels)) {
            $query->whereIn('level', $levels);
        }
        if (!empty($categoryFilters)) {
            $query->whereIn('category_id', $categoryFilters);
        }
        if (!empty($subCategoryFilters)) {
            $query->whereIn('sub_category_id', $subCategoryFilters);
        }
        if ($slug) {
            $query->whereHas('category', function ($q) use ($slug) {
                $q->where('slug', $slug);
            });
        }


        $beginnerCourseCount = (clone $query)->where('level', 1)->count();
        $intermediateCourseCount = (clone $query)->where('level', 2)->count();
        $advanceCourseCount = (clone $query)->where('level', 3)->count();
        $totalCourseCount = (clone $query)->count();


        $courses = $query->paginate(getPaginate(21));


        $trendingCategories = Category::active()
            ->whereHas('courses', function ($q) {
                $q->active();
            })
            ->where('is_trending',1)
            ->limit(3)
            ->get();


        $categories = Category::active()
            ->whereHas('courses', function ($q) {
                $q->active();
            })
            ->get();

        $subCategories = SubCategory::active()
            ->whereHas('courses', function ($q) {
                $q->active();
            })
            ->get();


        $notify = ['All courses fetched successfully'];
        
        return responseSuccess('all_courses', $notify, [
            'courses' => $courses,
            'categories' => $categories,
            'sub_categories' => $subCategories,
            'total_course' => $totalCourseCount,
            'beginner_course_count' => $beginnerCourseCount,
            'intermediate_course_count' => $intermediateCourseCount,
            'advance_course_count' => $advanceCourseCount,
            'trending_categories' => $trendingCategories,
        ]);
    }



    public function topCourses()
    {
        $topSellingCourses = Course::with(['sections'])
            ->active()
            ->withCount(['purchases' => function ($q) {
                $q->where('payment_status', Status::PAYMENT_SUCCESS);
            }])
            ->orderByDesc('purchases_count')
            ->take(10)
            ->get();

        $notify[] = 'Top courses fetched successfully';
        return responseSuccess('top_courses', $notify, [
            'top_selling_courses' => $topSellingCourses,
        ]);
    }

    public function shortCourses()
    {
        $courses = Course::with(['sections'])
            ->active()
            ->orderBy('id', 'desc')
            ->take(10)->get();

        $notify[] = 'Short courses fetched successfully';
        return responseSuccess('short_courses', $notify, [
            'courses' => $courses,
        ]);
    }


    public function latestCourse()
    {
        $courses = Course::active()
            ->orderBy('id', 'desc')
            ->take(5)->get();

        $notify[] = 'Latest courses fetched successfully';
        return responseSuccess('short_courses', $notify, [
            'courses' => $courses,
        ]);
    }



    public function instructorProfile($username)
    {
        $instructor = Instructor::active()->where('username', $username)->first();
        if (!$instructor) {
            $notify[] = ['error', 'Invalid Instructor'];
            return responseError('instructor_not_found', $notify);
        }

        $reviews  =  $instructor->reviews;

        $avgRating = $reviews->avg('rating');

        $totalCourses = $instructor->courses()->active()->count();


        $courseIds =    $instructor->courses()->active()->pluck('id');


        $totalEnrolledUsers = CoursePurchased::whereIn('course_id', $courseIds)
            ->where('payment_status', Status::PAYMENT_SUCCESS)
            ->distinct('user_id')
            ->count('user_id');

        $courses = $instructor->courses()->active()->get();

        $notify[] = 'Instructor profile fetched successfully';
        return responseSuccess('instructor_profile', $notify, [
            'instructor' => $instructor,
            'avg_rating' =>round($avgRating,1),
            'reviews' => count($reviews),
            'total_courses' => $totalCourses,
            'total_enrolled_users' => $totalEnrolledUsers,
            'courses' => $courses,

        ]);
    }

    public function saveKeyword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'keyword' => 'required|string|max:255',
        ]);
        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $keyword = SearchKeyword::where('keyword', $request->keyword)->first();

        if ($keyword) {
            $keyword->count += 1;
        } else {
            $keyword = new SearchKeyword();
            $keyword->keyword = $request->keyword;
            $keyword->count = 1;
        }

        $keyword->save();

        $notify[] = 'Keyword saved successfully';
        return responseSuccess('keyword_saved', $notify);
    }

    public function keyword()
    {
        $keywords = SearchKeyword::orderBy('count', 'desc')->take(10)->get();
        $notify[] = 'Keywords fetched successfully';
        return responseSuccess('keywords', $notify, ['keywords' => $keywords]);
    }



    public function verifyCertificate($secret)
    {
        $getCertificateData = GetCertificateUser::where('secret', $secret)->first();

        if (!$getCertificateData) {
            $notify[] =  'Invalid Secret';
            return responseError('invalid_secret', $notify);
        }

        $course = Course::active()->with('instructor')->find($getCertificateData->course_id);
        if (!$course) {
            $notify[] =  'Invalid Course';
            return responseError('course_not_found', $notify);
        }

        $user = User::active()->find($getCertificateData->user_id);
        if (!$user) {
            $notify[] =  'Invalid User';
            return responseError('user_not_found', $notify);
        }

        $enrollCourse = CoursePurchased::where('user_id', $user->id)->where('course_id', $course->id)->where('payment_status', Status::PAYMENT_SUCCESS)->exists();

        if (!$enrollCourse) {
            $notify[] =  'You need to enroll in this course to access the certificate';
            return responseError('enroll_course_failed', $notify);
        }

        $courseComplete = $course->completes()->where('user_id', $user->id)->first();

        if (!$courseComplete) {
            $notify[] =  'You have not completed this course yet';
            return responseError('course_not_completed', $notify);
        }

        $notify = "Certificate Successfully Verify.";

        return responseSuccess('verify_certificate', $notify, [
            'user' => $user,
            'course' => $course,
            'course_complete' => $courseComplete,
            'get_certificate_user' => $getCertificateData,

        ]);
    }
}
