<?php

namespace App\Services\Reminder;

use App\Models\ConsultationRequest;
use Carbon\Carbon;

class FreeClassReminderService
{
    public function sendRescheduleUpdate(ConsultationRequest $request): void
    {
        if (!$request->email_address || !$request->scheduled_at || !$request->zoom_join_url) {
            return;
        }

        $user = $this->pseudoUser($request);

        notify($user, 'FREE_CLASS_RESCHEDULED', [
            'student_name' => $request->parent_student_name,
            'class_datetime' => Carbon::parse($request->scheduled_at)->format('d M Y h:i A'),
            'class_time' => Carbon::parse($request->scheduled_at)->format('h:i A'),
            'duration_minutes' => (int) ($request->meeting_duration ?: 0),
            'teacher_name' => $this->teacherName($request),
            'zoom_link' => $request->zoom_join_url,
        ], ['email']);
    }

    public function sendConfirmation(ConsultationRequest $request, ?string $loginEmail = null, ?string $plainPassword = null): void
    {
        if (!$request->email_address || !$request->scheduled_at || !$request->zoom_join_url) {
            return;
        }

        if ($request->confirmation_sent_at) {
            return;
        }

        $user = $this->pseudoUser($request);

        notify($user, 'FREE_CLASS_CONFIRMATION', [
            'student_name' => $request->parent_student_name,
            'class_datetime' => Carbon::parse($request->scheduled_at)->format('d M Y h:i A'),
            'class_time' => Carbon::parse($request->scheduled_at)->format('h:i A'),
            'duration_minutes' => (int) ($request->meeting_duration ?: 60),
            'zoom_link' => $request->zoom_join_url,
        ], ['email']);

        // Send credentials in a dedicated email only for newly-created users.
        if (!empty($plainPassword)) {
            notify($user, 'FREE_CLASS_CREDENTIALS', [
                'student_name' => $request->parent_student_name,
                'login_email' => $loginEmail ?: $request->email_address,
                'login_password' => $plainPassword,
            ], ['email']);
        }

        $request->confirmation_sent_at = now();
        $request->save();
    }

    public function send24HourReminders(): int
    {
        $now = now();
        $windowStart = $now->copy()->addHours(24);
        $windowEnd = $windowStart->copy()->addMinute();

        $items = ConsultationRequest::with('assignedTeacher:id,firstname,lastname')
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereNotNull('zoom_join_url')
            ->whereNull('reminder_24h_sent_at')
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->get();

        foreach ($items as $item) {
            $user = $this->pseudoUser($item);

            notify($user, 'FREE_CLASS_REMINDER_24H', [
                'student_name' => $item->parent_student_name,
                'class_datetime' => Carbon::parse($item->scheduled_at)->format('d M Y h:i A'),
                'class_time' => Carbon::parse($item->scheduled_at)->format('h:i A'),
                'zoom_link' => $item->zoom_join_url,
            ], ['email']);

            $item->reminder_24h_sent_at = $now;
            $item->save();
        }

        return $items->count();
    }

    public function send1HourReminders(): int
    {
        $now = now();
        $windowStart = $now->copy()->addHour();
        $windowEnd = $windowStart->copy()->addMinute();

        $items = ConsultationRequest::with('assignedTeacher:id,firstname,lastname')
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->whereNotNull('zoom_join_url')
            ->whereNull('reminder_1h_sent_at')
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->get();

        foreach ($items as $item) {
            $user = $this->pseudoUser($item);

            notify($user, 'FREE_CLASS_REMINDER_1H', [
                'student_name' => $item->parent_student_name,
                'class_datetime' => Carbon::parse($item->scheduled_at)->format('d M Y h:i A'),
                'class_time' => Carbon::parse($item->scheduled_at)->format('h:i A'),
                'zoom_link' => $item->zoom_join_url,
            ], ['email']);

            $item->reminder_1h_sent_at = $now;
            $item->save();
        }

        return $items->count();
    }

    private function pseudoUser(ConsultationRequest $request): array
    {
        return [
            'username' => $request->email_address,
            'email' => $request->email_address,
            'fullname' => $request->parent_student_name,
        ];
    }

    private function teacherName(ConsultationRequest $request): string
    {
        $teacher = $request->assignedTeacher;
        if (!$teacher) {
            return 'AIClub Teacher';
        }

        return trim(($teacher->firstname ?? '') . ' ' . ($teacher->lastname ?? '')) ?: 'AIClub Teacher';
    }
}
