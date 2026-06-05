<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\ConsultationNotificationRecipient;
use App\Models\ConsultationRequest;
use App\Models\Course;
use App\Models\GeneralSetting;
use App\Models\Instructor;
use App\Services\TeacherNotificationService;
use App\Services\Zoom\LmsZoomService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConsultationRequestController extends Controller
{
    public function mailSetting()
    {
        return redirect()->route('admin.consultation.requests.index');
    }

    public function index()
    {
        $pageTitle = 'Consultation Requests';
        $emptyMessage = 'No consultation requests found';
        $requests = ConsultationRequest::searchable([
            'parent_student_name',
            'child_grade',
            'phone_number',
            'email_address',
            'source',
        ])->with(['assignedTeacher', 'course', 'scheduledByTeacher'])->orderBy('id', 'desc')->paginate(getPaginate());
        $teachers = Instructor::onlyTeachers()->active()->orderBy('firstname')->get();
        $courses = Course::where('status', Status::APPROVED)->orderBy('title')->get();
        $mailRecipients = ConsultationNotificationRecipient::orderBy('id', 'desc')->get();

        return view('admin.consultation_requests.index', compact('pageTitle', 'requests', 'teachers', 'courses', 'mailRecipients', 'emptyMessage'));
    }

    public function assignTeacher(Request $request, $id, LmsZoomService $zoomService, TeacherNotificationService $teacherNotificationService)
    {
        $request->validate([
            'assigned_teacher_id' => 'required|integer',
            'course_id' => 'required|integer',
            'scheduled_at' => 'required|date|after:now',
            'meeting_duration' => 'nullable|integer|min:15|max:240',
            'notes' => 'nullable|string|max:500',
        ]);

        $consultationRequest = ConsultationRequest::findOrFail($id);
        $teacher = Instructor::onlyTeachers()->active()->find($request->assigned_teacher_id);
        $course = Course::where('status', Status::APPROVED)->find($request->course_id);

        if (!$teacher) {
            $notify[] = ['error', 'Invalid teacher selected'];
            return back()->withNotify($notify);
        }

        if (!$course) {
            $notify[] = ['error', 'Invalid course selected'];
            return back()->withNotify($notify);
        }

        try {
            $scheduledAt = Carbon::parse($request->scheduled_at, config('zoom.meeting.timezone', config('app.timezone', 'UTC')));
        } catch (\Throwable $throwable) {
            $notify[] = ['error', 'Please select a valid schedule date and time'];
            return back()->withNotify($notify);
        }

        $duration = (int) ($request->meeting_duration ?: 30);
        $endAt = $scheduledAt->copy()->addMinutes($duration);
        $host = $zoomService->selectHost($scheduledAt, $endAt);

        if (!$host) {
            $notify[] = ['error', 'No Zoom host is available for the selected time'];
            return back()->withNotify($notify);
        }

        $hadExistingMeeting = !empty($consultationRequest->zoom_meeting_id);
        $previousTeacher = $consultationRequest->assignedTeacher;
        $previousTeacherId = (int) ($consultationRequest->assigned_teacher_id ?? 0);
        $zoomUpdateSkipped = false;
        try {
            if ($hadExistingMeeting) {
                $meeting = $zoomService->updateMeeting($consultationRequest->zoom_meeting_id, [
                    'meeting_name' => $zoomService->buildMeetingName($course->title, $scheduledAt, 1),
                    'start_time' => $scheduledAt,
                    'duration_minutes' => $duration,
                    'agenda' => $course->title,
                ]);
            } else {
                $meeting = $zoomService->createMeeting([
                    'host_email' => $host['email'],
                    'meeting_name' => $zoomService->buildMeetingName($course->title, $scheduledAt, 1),
                    'start_time' => $scheduledAt,
                    'duration_minutes' => $duration,
                    'agenda' => $course->title,
                ]);
            }
        } catch (\Throwable $throwable) {
            if ($hadExistingMeeting && str_contains(strtolower($throwable->getMessage()), 'does not contain scope')) {
                $zoomUpdateSkipped = true;
                $meeting = [
                    'id' => $consultationRequest->zoom_meeting_id,
                    'join_url' => $consultationRequest->zoom_join_url,
                    'host_email' => $consultationRequest->zoom_host_email,
                ];
            } else {
                $notify[] = ['error', $throwable->getMessage()];
                return back()->withNotify($notify);
            }
        }

        $consultationRequest->assigned_teacher_id = $teacher->id;
        $consultationRequest->course_id = $course->id;
        $consultationRequest->scheduled_at = $scheduledAt;
        $consultationRequest->meeting_duration = $duration;
        $consultationRequest->zoom_meeting_id = $meeting['id'] ?? $consultationRequest->zoom_meeting_id;
        $consultationRequest->zoom_join_url = $meeting['join_url'] ?? $consultationRequest->zoom_join_url;
        $consultationRequest->zoom_host_email = $meeting['host_email'] ?? $consultationRequest->zoom_host_email ?? $host['email'];
        $consultationRequest->notes = $request->notes;
        $consultationRequest->scheduled_by_teacher_id = null;
        $consultationRequest->status = 'scheduled';
        $consultationRequest->reminder_24h_sent_at = null;
        $consultationRequest->reminder_1h_sent_at = null;
        $consultationRequest->save();

        try {
            if ($previousTeacherId > 0 && $previousTeacherId !== (int) $teacher->id && $previousTeacher) {
                $teacherNotificationService->notifyFreeClassCancelledUnavailable($consultationRequest, $previousTeacher);
                $teacherNotificationService->notifyFreeClassAssigned($consultationRequest, $teacher, true);
            } elseif ($previousTeacherId === 0) {
                $teacherNotificationService->notifyFreeClassAssigned($consultationRequest, $teacher, false);
            }
        } catch (\Throwable $throwable) {
            Log::error('Failed to send consultation teacher notification', [
                'teacher_id' => $teacher->id,
                'teacher_email' => $teacher->email,
                'consultation_request_id' => $consultationRequest->id,
                'error' => $throwable->getMessage(),
            ]);
        }

        // Keep assignment successful even if Zoom update scope is missing.
        // Warning intentionally suppressed per product flow.
        if ($zoomUpdateSkipped) {
            // no-op
        }
        $notify[] = ['success', 'Teacher assigned and consultation scheduled successfully'];
        return back()->withNotify($notify);
    }

    public function updateMailSetting(Request $request)
    {
        $request->validate([
            'consultation_notification_email' => 'required|email:rfc|max:191',
        ]);

        $general = GeneralSetting::firstOrFail();
        $general->consultation_notification_email = strtolower(trim($request->consultation_notification_email));
        $general->save();

        $notify[] = ['success', 'Consultation recipient email updated successfully'];
        return back()->withNotify($notify);
    }

    public function storeMailRecipient(Request $request)
    {
        $request->validate([
            'email' => 'required|email:rfc|max:191|unique:consultation_notification_recipients,email',
        ]);

        ConsultationNotificationRecipient::create([
            'email' => strtolower(trim($request->email)),
            'is_active' => true,
        ]);

        $notify[] = ['success', 'Recipient added successfully'];
        return back()->withNotify($notify);
    }

    public function updateMailRecipient(Request $request, $id)
    {
        $request->validate([
            'email' => 'required|email:rfc|max:191|unique:consultation_notification_recipients,email,' . $id,
            'is_active' => 'nullable|boolean',
        ]);

        $recipient = ConsultationNotificationRecipient::findOrFail($id);
        $recipient->email = strtolower(trim($request->email));
        $recipient->is_active = (bool) $request->is_active;
        $recipient->save();

        $notify[] = ['success', 'Recipient updated successfully'];
        return back()->withNotify($notify);
    }

    public function deleteMailRecipient($id)
    {
        $recipient = ConsultationNotificationRecipient::findOrFail($id);
        $recipient->delete();

        $notify[] = ['success', 'Recipient deleted successfully'];
        return back()->withNotify($notify);
    }
}
