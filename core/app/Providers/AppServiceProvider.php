<?php

namespace App\Providers;

use App\Constants\Status;
use App\Lib\Searchable;
use App\Models\AdminNotification;
use App\Models\Course;
use App\Models\CourseLiveBooking;
use App\Models\ConsultationRequest;
use App\Models\Deposit;
use App\Models\Frontend;
use App\Models\Instructor;
use App\Models\SupportTicket;
use App\Models\Subscriber;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Builder::mixin(new Searchable);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Force Blade compiled views to /tmp to avoid storage permission mismatch between CLI and web runtime.
        $fallbackCompiledPath = sys_get_temp_dir() . '/aiclub_blade_views';
        if (!is_dir($fallbackCompiledPath)) {
            @mkdir($fallbackCompiledPath, 0775, true);
        }
        if (is_dir($fallbackCompiledPath) && is_writable($fallbackCompiledPath)) {
            config(['view.compiled' => $fallbackCompiledPath]);
        }

        if (!cache()->get('SystemInstalled')) {
            $envFilePath = base_path('.env');
            if (!file_exists($envFilePath)) {
                header('Location: install');
                exit;
            }
            $envContents = file_get_contents($envFilePath);
            if (empty($envContents)) {
                header('Location: install');
                exit;
            } else {
                cache()->put('SystemInstalled', true);
            }
        }


        $viewShare['emptyMessage'] = 'Data not found';
        view()->share($viewShare);


        view()->composer('admin.partials.sidenav', function ($view) {
            $view->with([
                'bannedUsersCount'           => User::banned()->count(),
                'emailUnverifiedUsersCount' => User::emailUnverified()->count(),
                'mobileUnverifiedUsersCount'   => User::mobileUnverified()->count(),
                'kycUnverifiedUsersCount'   => User::kycUnverified()->count(),
                'kycPendingUsersCount'   => User::kycPending()->count(),

                'bannedInstructorsCount'           => Instructor::onlyInstructors()->banned()->count(),
                'emailUnverifiedInstructorsCount' => Instructor::onlyInstructors()->emailUnverified()->count(),
                'mobileUnverifiedInstructorsCount'   => Instructor::onlyInstructors()->mobileUnverified()->count(),
                'kycUnverifiedInstructorsCount'   => Instructor::onlyInstructors()->kycUnverified()->count(),
                'kycPendingInstructorsCount'   => Instructor::onlyInstructors()->kycPending()->count(),
                'pendingApprovalInstructorsCount' => Instructor::onlyInstructors()
                    ->where('status', Status::USER_BAN)
                    ->where(function ($query) {
                        $query->whereNull('ban_reason')->orWhere('ban_reason', '');
                    })
                    ->count(),

                'bannedTeachersCount'           => Instructor::onlyTeachers()->banned()->count(),
                'emailUnverifiedTeachersCount' => Instructor::onlyTeachers()->emailUnverified()->count(),
                'mobileUnverifiedTeachersCount'   => Instructor::onlyTeachers()->mobileUnverified()->count(),
                'kycUnverifiedTeachersCount'   => Instructor::onlyTeachers()->kycUnverified()->count(),
                'kycPendingTeachersCount'   => Instructor::onlyTeachers()->kycPending()->count(),
                'pendingApprovalTeachersCount' => Instructor::onlyTeachers()
                    ->where('status', Status::USER_BAN)
                    ->where(function ($query) {
                        $query->whereNull('ban_reason')->orWhere('ban_reason', '');
                    })
                    ->count(),


                'pendingTicketCount'         => SupportTicket::whereIN('status', [Status::TICKET_OPEN, Status::TICKET_REPLY])->count(),
                'pendingDepositsCount'    => Deposit::pending()->count(),
                'pendingWithdrawCount'    => Withdrawal::pending()->count(),
                'pendingCourseCount'    => Course::pending()->count(),
                'pendingOneToOneScheduleRequestsCount' => CourseLiveBooking::where('class_type', 'one_to_one')
                    ->whereIn('status', ['pending_student_schedule', 'pending_admin_assignment', 'zoom_conflict'])
                    ->count(),
                'pendingConsultationRequestsCount' => ConsultationRequest::whereIn('status', ['pending_admin_assignment', 'assigned_to_teacher'])
                    ->count(),
                'newSubscribersCount' => Subscriber::whereDate('created_at', now()->toDateString())->count(),
                'updateAvailable'    => version_compare(gs('available_version'),systemDetails()['version'],'>') ? 'v'.gs('available_version') : false,
            ]);
        });

        view()->composer('admin.partials.topnav', function ($view) {
            $view->with([
                'adminNotifications' => AdminNotification::where('is_read', Status::NO)->with('user')->orderBy('id', 'desc')->take(10)->get(),
                'adminNotificationCount' => AdminNotification::where('is_read', Status::NO)->count(),
            ]);
        });

        view()->composer('partials.seo', function ($view) {
            $seo = Frontend::where('data_keys', 'seo.data')->first();
            $view->with([
                'seo' => $seo ? $seo->data_values : $seo,
            ]);
        });


        Paginator::useBootstrapFive();
    }
}
