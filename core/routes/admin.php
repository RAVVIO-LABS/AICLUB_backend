<?php

use Illuminate\Support\Facades\Route;


Route::namespace('Auth')->group(function () {
    Route::middleware('admin.guest')->group(function () {
        Route::controller('LoginController')->group(function () {
            Route::get('/', 'showLoginForm')->name('login');
            Route::post('login/submit', 'login')->name('submit.login');
            Route::get('logout', 'logout')->middleware('admin')->withoutMiddleware('admin.guest')->name('logout');
        });

        // Admin Password Reset
        Route::controller('ForgotPasswordController')->prefix('password')->name('password.')->group(function () {
            Route::get('reset', 'showLinkRequestForm')->name('reset');
            Route::post('reset', 'sendResetCodeEmail');
            Route::get('code-verify', 'codeVerify')->name('code.verify');
            Route::post('verify-code', 'verifyCode')->name('verify.code');
        });

        Route::controller('ResetPasswordController')->group(function () {
            Route::get('password/reset/{token}', 'showResetForm')->name('password.reset.form');
            Route::post('password/reset/change', 'reset')->name('password.change');
        });
    });
});

Route::middleware('admin')->group(function () {
    Route::controller('AdminController')->group(function () {
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('chart/deposit-withdraw', 'depositAndWithdrawReport')->name('chart.deposit.withdraw');
        Route::get('chart/transaction', 'transactionReport')->name('chart.transaction');
        Route::get('profile', 'profile')->name('profile');
        Route::post('profile', 'profileUpdate')->name('profile.update');
        Route::get('password', 'password')->name('password');
        Route::post('password', 'passwordUpdate')->name('password.update');

        //Notification
        Route::get('notifications', 'notifications')->name('notifications');

        Route::get('notification/read/{id}', 'notificationRead')->name('notification.read');
        Route::get('notifications/read-all', 'readAllNotification')->name('notifications.read.all');
        Route::post('notifications/delete-all', 'deleteAllNotification')->name('notifications.delete.all');
        Route::post('notifications/delete-single/{id}', 'deleteSingleNotification')->name('notifications.delete.single');

        Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');

        Route::get('chart/category', 'cartCategory')->name('chart.category');
        Route::get('certificate-issue', 'certificateIssue')->name('certificate.issue');
        Route::get('keywords', 'keywords')->name('keywords');
        Route::post('update-keyword/{id}', 'updateKeyword')->name('update.keyword');
        Route::post('delete-keyword/{id}', 'deleteKeyword')->name('delete.keyword');

        Route::get('top-selling-courses', 'topSellingCourses')->name('top.selling.courses');

    });

    // Users Manager
    Route::controller('ManageUsersController')->name('users.')->prefix('users')->group(function () {
        Route::get('/', 'allUsers')->name('all');
        Route::get('active', 'activeUsers')->name('active');
        Route::get('banned', 'bannedUsers')->name('banned');
        Route::get('email-verified', 'emailVerifiedUsers')->name('email.verified');
        Route::get('email-unverified', 'emailUnverifiedUsers')->name('email.unverified');
        Route::get('mobile-unverified', 'mobileUnverifiedUsers')->name('mobile.unverified');
        Route::get('kyc-unverified', 'kycUnverifiedUsers')->name('kyc.unverified');
        Route::get('kyc-pending', 'kycPendingUsers')->name('kyc.pending');
        Route::get('mobile-verified', 'mobileVerifiedUsers')->name('mobile.verified');
        Route::get('with-balance', 'usersWithBalance')->name('with.balance');

        Route::get('detail/{id}', 'detail')->name('detail');
        Route::get('kyc-data/{id}', 'kycDetails')->name('kyc.details');
        Route::post('kyc-approve/{id}', 'kycApprove')->name('kyc.approve');
        Route::post('kyc-reject/{id}', 'kycReject')->name('kyc.reject');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('add-sub-balance/{id}', 'addSubBalance')->name('add.sub.balance');
        Route::get('send-notification/{id}', 'showNotificationSingleForm')->name('notification.single');
        Route::post('send-notification/{id}', 'sendNotificationSingle')->name('notification.single');
        Route::get('login/{id}', 'login')->name('login');
        Route::post('status/{id}', 'status')->name('status');

        Route::get('send-notification', 'showNotificationAllForm')->name('notification.all');
        Route::post('send-notification', 'sendNotificationAll')->name('notification.all.send');
        Route::get('list', 'list')->name('list');
        Route::get('count-by-segment/{methodName}', 'countBySegment')->name('segment.count');
        Route::get('notification-log/{id}', 'notificationLog')->name('notification.log');
    });



    //manage instructor controller 
    Route::controller('ManageInstructorController')->name('instructors.')->prefix('academy')->group(function () {
        Route::get('students', 'students')->name('students');
        Route::post('students/release-one-to-one-lecture/{bookingId}', 'releaseOneToOneLecture')->name('students.release.one.to.one.lecture');
        Route::get('pending', 'pendingApprovalInstructors')->name('pending.approval');
        Route::post('approve-request/{id}', 'approveRequest')->name('approve.request');
        Route::post('reject-request/{id}', 'rejectRequest')->name('reject.request');
        Route::get('/', 'allInstructors')->name('all');
        Route::get('active', 'activeInstructors')->name('active');
        Route::get('banned', 'bannedInstructors')->name('banned');
        Route::get('email-verified', 'emailVerifiedInstructors')->name('email.verified');
        Route::get('email-unverified', 'emailUnverifiedInstructors')->name('email.unverified');
        Route::get('mobile-unverified', 'mobileUnverifiedInstructors')->name('mobile.unverified');
        Route::get('kyc-unverified', 'kycUnverifiedInstructors')->name('kyc.unverified');
        Route::get('kyc-pending', 'kycPendingInstructors')->name('kyc.pending');
        Route::get('mobile-verified', 'mobileVerifiedInstructors')->name('mobile.verified');
        Route::get('with-balance', 'instructorsWithBalance')->name('with.balance');

        Route::get('detail/{id}', 'detail')->name('detail');
        Route::get('kyc-data/{id}', 'kycDetails')->name('kyc.details');
        Route::post('kyc-approve/{id}', 'kycApprove')->name('kyc.approve');
        Route::post('kyc-reject/{id}', 'kycReject')->name('kyc.reject');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('add-sub-balance/{id}', 'addSubBalance')->name('add.sub.balance');
        Route::get('send-notification/{id}', 'showNotificationSingleForm')->name('notification.single');
        Route::post('send-notification/{id}', 'sendNotificationSingle')->name('notification.single');
        Route::get('login/{id}', 'login')->name('login');
        Route::post('status/{id}', 'status')->name('status');

        Route::get('send-notification', 'showNotificationAllForm')->name('notification.all');
        Route::post('send-notification', 'sendNotificationAll')->name('notification.all.send');
        Route::get('list', 'list')->name('list');
        Route::get('count-by-segment/{methodName}', 'countBySegment')->name('segment.count');
        Route::get('notification-log/{id}', 'notificationLog')->name('notification.log');

    });

    //manage teacher controller 
    Route::controller('ManageTeacherController')->name('teachers.')->prefix('teachers')->group(function () {
        Route::get('availability', 'availability')->name('availability');
        Route::get('detail/{id}/calendar', 'calendar')->name('detail.calendar');
        Route::get('detail/{id}/students', 'students')->name('students');
        Route::get('leaves', 'leaveRequests')->name('leaves');
        Route::post('leaves/{id}/status', 'updateLeaveStatus')->name('leaves.status');
        Route::post('leaves/recipients', 'storeLeaveRecipient')->name('leaves.recipients.store');
        Route::post('leaves/recipients/{id}', 'updateLeaveRecipient')->name('leaves.recipients.update');
        Route::post('leaves/recipients/{id}/delete', 'deleteLeaveRecipient')->name('leaves.recipients.delete');
        Route::get('pending-approval', 'pendingApprovalTeachers')->name('pending.approval');
        Route::post('approve-request/{id}', 'approveRequest')->name('approve.request');
        Route::post('reject-request/{id}', 'rejectRequest')->name('reject.request');
        Route::get('/', 'allTeachers')->name('all');
        Route::get('active', 'activeTeachers')->name('active');
        Route::get('banned', 'bannedTeachers')->name('banned');
        Route::get('email-verified', 'emailVerifiedTeachers')->name('email.verified');
        Route::get('email-unverified', 'emailUnverifiedTeachers')->name('email.unverified');
        Route::get('mobile-unverified', 'mobileUnverifiedTeachers')->name('mobile.unverified');
        Route::get('kyc-unverified', 'kycUnverifiedTeachers')->name('kyc.unverified');
        Route::get('kyc-pending', 'kycPendingTeachers')->name('kyc.pending');
        Route::get('mobile-verified', 'mobileVerifiedTeachers')->name('mobile.verified');
        Route::get('with-balance', 'teachersWithBalance')->name('with.balance');

        Route::get('detail/{id}', 'detail')->name('detail');
        Route::get('kyc-data/{id}', 'kycDetails')->name('kyc.details');
        Route::post('kyc-approve/{id}', 'kycApprove')->name('kyc.approve');
        Route::post('kyc-reject/{id}', 'kycReject')->name('kyc.reject');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('add-sub-balance/{id}', 'addSubBalance')->name('add.sub.balance');
        Route::get('send-notification/{id}', 'showNotificationSingleForm')->name('notification.single');
        Route::post('send-notification/{id}', 'sendNotificationSingle')->name('notification.single');
        Route::get('login/{id}', 'login')->name('login');
        Route::post('status/{id}', 'status')->name('status');

        Route::get('send-notification', 'showNotificationAllForm')->name('notification.all');
        Route::post('send-notification', 'sendNotificationAll')->name('notification.all.send');
        Route::get('list', 'list')->name('list');
        Route::get('count-by-segment/{methodName}', 'countBySegment')->name('segment.count');
        Route::get('notification-log/{id}', 'notificationLog')->name('notification.log');

    });

    // Category
    Route::controller('ManageCategoryController')->prefix('categories')->name('categories.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('save/{id?}', 'save')->name('save');
        Route::post('status/{id?}', 'status')->name('status');
        Route::get('sub-categories', 'subCategories')->name('sub.categories');
        Route::post('sub-categories-save/{id?}', 'subCategoriesSave')->name('sub.categories.save');
        Route::post('sub-categories-status/{id?}', 'subCategoriesStatus')->name('sub.categories.status');
    });


    Route::controller('ManageGoalController')->prefix('goal')->name('goal.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('save/{id?}', 'save')->name('save');
        Route::post('status/{id?}', 'status')->name('status');
    });




    // Subscriber
    Route::controller('SubscriberController')->prefix('subscriber')->name('subscriber.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('send-email', 'sendEmailForm')->name('send.email');
        Route::post('remove/{id}', 'remove')->name('remove');
        Route::post('send-email', 'sendEmail')->name('send.email');
    });

    // Deposit Gateway
    Route::name('gateway.')->prefix('gateway')->group(function () {
        // Automatic Gateway
        Route::controller('AutomaticGatewayController')->prefix('automatic')->name('automatic.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{code}', 'update')->name('update');
            Route::post('remove/{id}', 'remove')->name('remove');
            Route::post('status/{id}', 'status')->name('status');
        });


        // Manual Methods
        Route::controller('ManualGatewayController')->prefix('manual')->name('manual.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('new', 'create')->name('create');
            Route::post('new', 'store')->name('store');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{id}', 'update')->name('update');
            Route::post('status/{id}', 'status')->name('status');
        });
    });


    // DEPOSIT SYSTEM
    Route::controller('DepositController')->prefix('payment')->name('deposit.')->group(function () {
        Route::get('all/{user_id?}', 'deposit')->name('list');
        Route::get('pending/{user_id?}', 'pending')->name('pending');
        Route::get('rejected/{user_id?}', 'rejected')->name('rejected');
        Route::get('approved/{user_id?}', 'approved')->name('approved');
        Route::get('successful/{user_id?}', 'successful')->name('successful');
        Route::get('initiated/{user_id?}', 'initiated')->name('initiated');
        Route::get('details/{id}', 'details')->name('details');
        Route::post('reject', 'reject')->name('reject');
        Route::post('approve/{id}', 'approve')->name('approve');
    });


    // WITHDRAW SYSTEM
    Route::name('withdraw.')->prefix('withdraw')->group(function () {

        Route::controller('WithdrawalController')->name('data.')->group(function () {
            Route::get('pending/{user_id?}', 'pending')->name('pending');
            Route::get('approved/{user_id?}', 'approved')->name('approved');
            Route::get('rejected/{user_id?}', 'rejected')->name('rejected');
            Route::get('all/{user_id?}', 'all')->name('all');
            Route::get('details/{id}', 'details')->name('details');
            Route::post('approve', 'approve')->name('approve');
            Route::post('reject', 'reject')->name('reject');
        });


        // Withdraw Method
        Route::controller('WithdrawMethodController')->prefix('method')->name('method.')->group(function () {
            Route::get('/', 'methods')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('create', 'store')->name('store');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('edit/{id}', 'update')->name('update');
            Route::post('status/{id}', 'status')->name('status');
        });
    });

    // Report
    Route::controller('ReportController')->prefix('report')->name('report.')->group(function () {
        Route::get('transaction/{user_id?}', 'transaction')->name('transaction');
   
        Route::get('login/history', 'loginHistory')->name('login.history');
       
        Route::get('login/ipHistory/{ip}', 'loginIpHistory')->name('login.ipHistory');
        Route::get('notification/history', 'notificationHistory')->name('notification.history');
  
        Route::get('email/detail/{id}', 'emailDetails')->name('email.details');
    });


    // Admin Support
    Route::controller('SupportTicketController')->prefix('ticket')->name('ticket.')->group(function () {
        Route::get('/', 'tickets')->name('index');
        Route::get('pending', 'pendingTicket')->name('pending');
        Route::get('closed', 'closedTicket')->name('closed');
        Route::get('answered', 'answeredTicket')->name('answered');
        Route::get('view/{id}', 'ticketReply')->name('view');
        Route::post('reply/{id}', 'replyTicket')->name('reply');
        Route::post('close/{id}', 'closeTicket')->name('close');
        Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
        Route::post('delete/{id}', 'ticketDelete')->name('delete');
    });

    Route::controller('ConsultationRequestController')->prefix('consultation-requests')->name('consultation.requests.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('mail-setting', 'updateMailSetting')->name('mail.setting.update');
        Route::post('mail-recipients', 'storeMailRecipient')->name('mail.recipients.store');
        Route::post('mail-recipients/{id}', 'updateMailRecipient')->name('mail.recipients.update');
        Route::post('mail-recipients/{id}/delete', 'deleteMailRecipient')->name('mail.recipients.delete');
        Route::post('assign-teacher/{id}', 'assignTeacher')->name('assign.teacher');
    });
    Route::get('mail-setting', 'ConsultationRequestController@mailSetting')->name('mail.setting');


    // Language Manager
    Route::controller('LanguageController')->prefix('language')->name('language.')->group(function () {
        Route::get('/', 'langManage')->name('manage');
        Route::post('/', 'langStore')->name('manage.store');
        Route::post('delete/{id}', 'langDelete')->name('manage.delete');
        Route::post('update/{id}', 'langUpdate')->name('manage.update');
        Route::get('edit/{id}', 'langEdit')->name('key');
        Route::post('import', 'langImport')->name('import.lang');
        Route::post('store/key/{id}', 'storeLanguageJson')->name('store.key');
        Route::post('delete/key/{id}', 'deleteLanguageJson')->name('delete.key');
        Route::post('update/key/{id}', 'updateLanguageJson')->name('update.key');
        Route::get('get-keys', 'getKeys')->name('get.key');
    });

    Route::controller('GeneralSettingController')->group(function () {

        Route::get('system-setting', 'systemSetting')->name('setting.system');

        // General Setting
        Route::get('general-setting', 'general')->name('setting.general');
        Route::post('general-setting', 'generalUpdate');

        Route::get('setting/social/credentials', 'socialiteCredentials')->name('setting.socialite.credentials');
        Route::post('setting/social/credentials/update/{key}', 'updateSocialiteCredential')->name('setting.socialite.credentials.update');
        Route::post('setting/social/credentials/status/{key}', 'updateSocialiteCredentialStatus')->name('setting.socialite.credentials.status.update');

        //configuration
        Route::get('setting/system-configuration', 'systemConfiguration')->name('setting.system.configuration');
        Route::post('setting/system-configuration', 'systemConfigurationSubmit');

        // Logo-Icon
        Route::get('setting/logo-icon', 'logoIcon')->name('setting.logo.icon');
        Route::post('setting/logo-icon', 'logoIconUpdate')->name('setting.logo.icon');



        //Cookie
        Route::get('cookie', 'cookie')->name('setting.cookie');
        Route::post('cookie', 'cookieSubmit');

        //maintenance_mode
        Route::get('maintenance-mode', 'maintenanceMode')->name('maintenance.mode');
        Route::post('maintenance-mode', 'maintenanceModeSubmit');

  
    });


    Route::controller('CronConfigurationController')->name('cron.')->prefix('cron')->group(function () {
        Route::get('index', 'cronJobs')->name('index');
        Route::post('store', 'cronJobStore')->name('store');
        Route::post('update', 'cronJobUpdate')->name('update');
        Route::post('delete/{id}', 'cronJobDelete')->name('delete');
        Route::get('schedule', 'schedule')->name('schedule');
        Route::post('schedule/store', 'scheduleStore')->name('schedule.store');
        Route::post('schedule/status/{id}', 'scheduleStatus')->name('schedule.status');
        Route::get('schedule/pause/{id}', 'schedulePause')->name('schedule.pause');
        Route::get('schedule/logs/{id}', 'scheduleLogs')->name('schedule.logs');
        Route::post('schedule/log/resolved/{id}', 'scheduleLogResolved')->name('schedule.log.resolved');
        Route::post('schedule/log/flush/{id}', 'logFlush')->name('log.flush');
    });


    //KYC setting
    Route::controller('KycController')->group(function () {
        Route::get('kyc-setting', 'setting')->name('kyc.setting');
        Route::post('kyc-setting', 'settingUpdate');

        Route::get('instructor-kyc-setting', 'instructorKycSetting')->name('instructor.kyc.setting');
        Route::post('instructor-kyc-setting', 'instructorKycSettingUpdate');
    });

    //Notification Setting
    Route::name('setting.notification.')->controller('NotificationController')->prefix('notification')->group(function () {
        //Template Setting
        Route::get('global/email', 'globalEmail')->name('global.email');
        Route::post('global/email/update', 'globalEmailUpdate')->name('global.email.update');

        Route::get('global/sms', 'globalSms')->name('global.sms');
        Route::post('global/sms/update', 'globalSmsUpdate')->name('global.sms.update');

   

        Route::get('templates', 'templates')->name('templates');
        Route::get('template/edit/{type}/{id}', 'templateEdit')->name('template.edit');
        Route::post('template/update/{type}/{id}', 'templateUpdate')->name('template.update');
        Route::post('template/test-send/{id}', 'templateTestSend')->name('template.test.send');

        //Email Setting
        Route::get('email/setting', 'emailSetting')->name('email');
        Route::post('email/setting', 'emailSettingUpdate');
        Route::post('email/test', 'emailTest')->name('email.test');

        //SMS Setting
        Route::get('sms/setting', 'smsSetting')->name('sms');
        Route::post('sms/setting', 'smsSettingUpdate');
        Route::post('sms/test', 'smsTest')->name('sms.test');
    });

    // Plugin
    Route::controller('ExtensionController')->prefix('extensions')->name('extensions.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('update/{id}', 'update')->name('update');
        Route::post('status/{id}', 'status')->name('status');
    });

    Route::controller('ManageCourseController')->name('courses.')->prefix('courses')->group(function () {
        Route::get('approved/{instructorId?}', 'approved')->name('approved');
        Route::get('pending/{instructorId?}', 'pending')->name('pending');
        Route::get('rejected/{instructorId?}', 'rejected')->name('rejected');
        Route::get('popular-lists/{instructorId?}', 'popularCourses')->name('popular.list');
        // Route::get('students/one-to-one', 'oneToOneStudents')->name('students.one.to.one');
        Route::get('one-to-one-schedule', 'oneToOneSchedule')->name('one.to.one.schedule');
        Route::post('one-to-one-schedule/{bookingId}/available-teachers', 'oneToOneAvailableTeachers')->name('one.to.one.available.teachers');
        Route::post('one-to-one-schedule/session/{sessionId}', 'updateOneToOneSession')->name('one.to.one.session.update');
        Route::get('students/group', 'groupStudents')->name('students.group');
        Route::get('trashed', 'trash')->name('trash');
        Route::post('restore/{slug}', 'restore')->name('restore');
        Route::get('details/{slug}', 'details')->name('details');
        Route::get('details/{slug}/batch-enrollments', 'batchEnrollments')->name('batch.enrollments');
        Route::get('download-resource/{id}', 'downloadResource')->name('resourse.download');
        Route::post('assign-course-teacher/{slug}', 'assignCourseTeacher')->name('assign.course.teacher');
        Route::post('remove-course-teacher/{slug}', 'removeCourseTeacher')->name('remove.course.teacher');
        Route::post('assign-batch-teacher/{batchId}', 'assignBatchTeacher')->name('assign.batch.teacher');
        Route::post('assign-booking-teacher/{bookingId}', 'assignBookingTeacher')->name('assign.booking.teacher');
        Route::post('update-batch-limit/{slug}', 'updateBatchLimit')->name('update.batch.limit');
        Route::post('update-batch-capacity/{slug}/{batchId}', 'updateBatchCapacity')->name('update.batch.capacity');
        Route::post('release-lecture/{slug}', 'releaseLectureForBatch')->name('release.lecture');
        Route::post('release-one-to-one-lecture/{bookingId}', 'releaseLectureForOneToOneStudent')->name('release.one.to.one.lecture');
        Route::post('approve/{id}', 'approve')->name('approve');
        Route::post('Reject/{id}', 'reject')->name('reject');
        Route::post('popular/{id}', 'popular')->name('popular');
        Route::post('remove-popular/{id}', 'removePopular')->name('remove.popular');
        Route::get('enrolled/{id?}', 'enrolledCourses')->name('enrolled');
        Route::get('/{instructorId?}', 'index')->name('index');
    });



    Route::controller('ManageCourseController')->name('certificate.')->prefix('certificate')->group(function () {
        Route::get('template', 'certificateTemplate')->name('template');
        Route::post('template/update', 'certificateTemplateUpdate')->name('template.update');
        Route::get('preview/{id?}', 'certificatePreview')->name('preview');

    });

    //Coupons
    Route::controller('CouponController')->prefix('promotion/coupon')->name('coupon.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::get('edit/{id}', 'edit')->name('edit');
        Route::post('save/{id}', 'save')->name('store');
        Route::post('change-status/{id}', 'changeStatus')->name('status');
    });




    //System Information
    Route::controller('SystemController')->name('system.')->prefix('system')->group(function () {
        Route::get('info', 'systemInfo')->name('info');
        Route::get('server-info', 'systemServerInfo')->name('server.info');
        Route::get('optimize', 'optimize')->name('optimize');
        Route::get('optimize-clear', 'optimizeClear')->name('optimize.clear');
    });

    Route::controller('ZoomHealthController')->name('system.zoom.health.')->prefix('system/zoom-health')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('run', 'run')->name('run');
    });


    // SEO
    Route::get('seo', 'FrontendController@seoEdit')->name('seo');


    // Frontend
    Route::name('frontend.')->prefix('frontend')->group(function () {

        Route::controller('FrontendController')->group(function () {
            Route::get('index', 'index')->name('index');
            Route::get('templates', 'templates')->name('templates');
            Route::post('templates', 'templatesActive')->name('templates.active');
            Route::get('frontend-sections/{key?}', 'frontendSections')->name('sections');
            Route::post('frontend-content/{key}', 'frontendContent')->name('sections.content');
            Route::get('frontend-element/{key}/{id?}', 'frontendElement')->name('sections.element');
            Route::get('frontend-slug-check/{key}/{id?}', 'frontendElementSlugCheck')->name('sections.element.slug.check');
            Route::get('frontend-element-seo/{key}/{id}', 'frontendSeo')->name('sections.element.seo');
            Route::post('frontend-element-seo/{key}/{id}', 'frontendSeoUpdate');
            Route::post('remove/{id}', 'remove')->name('remove');
        });

        // Page Builder
        Route::controller('PageBuilderController')->group(function () {
            Route::get('manage-pages', 'managePages')->name('manage.pages');
            Route::get('manage-pages/check-slug/{id?}', 'checkSlug')->name('manage.pages.check.slug');
            Route::post('manage-pages', 'managePagesSave')->name('manage.pages.save');
            Route::post('manage-pages/update', 'managePagesUpdate')->name('manage.pages.update');
            Route::post('manage-pages/delete/{id}', 'managePagesDelete')->name('manage.pages.delete');
            Route::get('manage-section/{id}', 'manageSection')->name('manage.section');
            Route::post('manage-section/{id}', 'manageSectionUpdate')->name('manage.section.update');

            Route::get('manage-seo/{id}', 'manageSeo')->name('manage.pages.seo');
            Route::post('manage-seo/{id}', 'manageSeoStore');
        });
    });
});
