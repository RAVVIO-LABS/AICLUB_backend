<?php
namespace App\Traits;

use App\Constants\Status;

trait instructorNotify
{
    public static function instructorNotify(){
        return [
            'allInstructors'              => 'All Instructors',
            'selectedInstructors'         => 'Selected Instructors',
            'kycUnverified'         => 'Kyc Unverified Instructors',
            'kycVerified'           => 'Kyc Verified Instructors',
            'kycPending'            => 'Kyc Pending Instructors',
            'withBalance'           => 'With Balance Instructors',
            'emptyBalanceInstructors'     => 'Empty Balance Instructors',
            'twoFaDisableInstructors'     => '2FA Disable Instructors',
            'twoFaEnableInstructors'      => '2FA Enable Instructors',
            'hasWithdrawInstructors'      => 'Withdraw Instructors',
            'pendingWithdrawInstructors'  => 'Pending Withdraw Instructors',
            'rejectedWithdrawInstructors' => 'Rejected Withdraw Instructors',
            'hasCoursesInstructors' => 'Instructors who have courses',
            'hasNotCoursesInstructors' => 'Instructors who has no courses',
            'hasApprovedCoursesInstructors' => 'Instructors who have approved courses',
            'hasPendingCoursesInstructors' => 'Instructors who have pending courses',
            'hasRejectedCoursesInstructors' => 'Instructors who have rejected courses',
            'pendingTicketUser'     => 'Pending Ticket Instructors',
            'answerTicketUser'      => 'Answer Ticket Instructors',
            'closedTicketUser'      => 'Closed Ticket Instructors',

            'notLoginInstructors'         => 'Last Few Days Not Login Instructors',
        ];
    }

    public function scopeSelectedInstructors($query)
    {
        return $query->whereIn('id', request()->instructor ?? []);
    }

    public function scopeAllInstructors($query)
    {
        return $query;
    }

    public function scopeEmptyBalanceInstructors($query)
    {
        return $query->where('balance', '<=', 0);
    }

    public function scopeTwoFaDisableInstructors($query)
    {
        return $query->where('ts', Status::DISABLE);
    }

    public function scopeTwoFaEnableInstructors($query)
    {
        return $query->where('ts', Status::ENABLE);
    }



    public function scopeHasWithdrawInstructors($query)
    {
        return $query->whereHas('withdrawals', function ($q) {
            $q->approved();
        });
    }

  

    public function scopePendingWithdrawInstructors($query)
    {
        return $query->whereHas('withdrawals', function ($q) {
            $q->pending();
        });
    }

    public function scopeRejectedWithdrawInstructors($query)
    {
        return $query->whereHas('withdrawals', function ($q) {
            $q->rejected();
        });
    }


    public function scopeHasCoursesInstructors($query)
    {
        return $query->whereHas('courses');
    }


    public function scopeHasNotCoursesInstructors($query)
    {
        return $query->whereDoesntHave('courses', function ($q) {
            $q->approved();
        });
    }

    public function scopeHasApprovedCoursesInstructors($query)
    {
        return $query->whereHas('courses', function ($q) {
            $q->approved();
        });
    }


    public function scopeHasPendingCoursesInstructors($query)
    {
        return $query->whereHas('courses', function ($q) {
            $q->pending();
        });
    }

    public function scopeHasRejectedCoursesInstructors($query)
    {
        return $query->whereHas('courses', function ($q) {
            $q->rejected();
        });
    }


    public function scopeNotLoginInstructors($query)
    {
        return $query->whereDoesntHave('loginLogs', function ($q) {
            $q->whereDate('created_at', '>=', now()->subDays(request()->number_of_days ?? 10));
        });
    }

    public function scopeKycVerified($query)
    {
        return $query->where('kv', Status::KYC_VERIFIED);
    }

    
    public function scopePendingTicketUser($query)
    {
        return $query->whereHas('tickets', function ($q) {
            $q->whereIn('status', [Status::TICKET_OPEN, Status::TICKET_REPLY]);
        });
    }

    public function scopeClosedTicketUser($query)
    {
        return $query->whereHas('tickets', function ($q) {
            $q->where('status', Status::TICKET_CLOSE);
        });
    }

    public function scopeAnswerTicketUser($query)
    {
        return $query->whereHas('tickets', function ($q) {

            $q->where('status', Status::TICKET_ANSWER);
        });
    }




}
