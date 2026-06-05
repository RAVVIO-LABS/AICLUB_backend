<?php

namespace App\Services\Reminder;

use App\Constants\Status;
use App\Models\CourseLiveSession;
use App\Models\CoursePurchased;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ClassSessionReminderService
{
    public function sendDueReminders(): array
    {
        $now = now();
        $windowStart = $now->copy()->addHour();
        $windowEnd = $windowStart->copy()->addMinute();

        $sessions = CourseLiveSession::with([
            'course:id,title',
            'batch:id,title,zoom_meeting_link',
            'teacher:id,firstname,lastname',
        ])
            ->whereNull('reminder_sent_at')
            ->whereNotNull('scheduled_at')
            ->whereIn('status', ['scheduled', 'assigned'])
            ->whereBetween('scheduled_at', [$windowStart, $windowEnd])
            ->get();

        $sessionsProcessed = 0;
        $emailsSent = 0;

        foreach ($sessions as $session) {
            $users = $this->recipientUsersForSession($session);

            foreach ($users as $user) {
                $zoomLink = $session->zoom_join_url ?: ($session->batch->zoom_meeting_link ?? '-');

                notify($user, 'CLASS_SESSION_REMINDER', [
                    'student_name' => $user->fullname ?? $user->username ?? 'Student',
                    'course_name' => $session->course->title ?? 'N/A',
                    'batch_name' => $session->batch->title ?? 'N/A',
                    'class_time' => Carbon::parse($session->scheduled_at)->format('h:i A'),
                    'class_datetime' => Carbon::parse($session->scheduled_at)->format('d M Y h:i A'),
                    'teacher_name' => $this->teacherName($session),
                    'zoom_link' => $zoomLink,
                    'app_link' => 'https://india.aiclub.world',
                    'website_link' => 'https://india.aiclub.world',
                ], ['email']);

                $emailsSent++;
            }

            $session->reminder_sent_at = $now;
            $session->save();
            $sessionsProcessed++;
        }

        return [
            'sessions' => $sessionsProcessed,
            'emails' => $emailsSent,
        ];
    }

    private function recipientUsersForSession(CourseLiveSession $session): Collection
    {
        if ($session->user_id) {
            return User::where('id', $session->user_id)
                ->where('status', Status::USER_ACTIVE)
                ->whereNotNull('email')
                ->get();
        }

        if ($session->batch_id) {
            return User::whereIn('id', function ($query) use ($session) {
                $query->select('user_id')
                    ->from('course_live_bookings')
                    ->where('batch_id', $session->batch_id)
                    ->whereNotIn('status', ['pending_payment', 'cancelled']);
            })
                ->where('status', Status::USER_ACTIVE)
                ->whereNotNull('email')
                ->get();
        }

        return User::whereIn('id', function ($query) use ($session) {
            $query->select('user_id')
                ->from((new CoursePurchased())->getTable())
                ->where('course_id', $session->course_id)
                ->where('payment_status', Status::PAYMENT_SUCCESS);
        })
            ->where('status', Status::USER_ACTIVE)
            ->whereNotNull('email')
            ->get();
    }

    private function teacherName(CourseLiveSession $session): string
    {
        if ($session->teacher) {
            return trim(($session->teacher->firstname ?? '') . ' ' . ($session->teacher->lastname ?? '')) ?: 'N/A';
        }

        if ($session->batch) {
            $assignment = $session->batch->teachers()->with('teacher:id,firstname,lastname')->first();
            if ($assignment && $assignment->teacher) {
                return trim(($assignment->teacher->firstname ?? '') . ' ' . ($assignment->teacher->lastname ?? '')) ?: 'N/A';
            }
        }

        return 'N/A';
    }
}
