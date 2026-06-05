<?php

use Illuminate\Support\Facades\Route;



Route::namespace('Teacher')->name('api.teacher.')->prefix('teacher')->group(function () {

    Route::get('course/live-session/{sessionId}/start-redirect/{token}', 'CourseController@startLiveSessionRedirect');

    Route::namespace('Auth')->middleware('guest')->group(function () {
        Route::controller('LoginController')->group(function () {
            Route::post('login', 'login');
            Route::post('check-token', 'checkToken');
            Route::post('social-login', 'socialLogin');
        });
        Route::post('register', 'RegisterController@register');

   
        Route::controller('ForgotPasswordController')->group(function () {
            Route::post('password/email', 'sendResetCodeEmail');
            Route::post('password/verify-code', 'verifyCode');
            Route::post('password/reset', 'reset');
        });
    
    });


    Route::middleware(['auth:sanctum', 'ability:teacher'])->group(function () {

        Route::post('data-submit', 'InstructorController@userDataSubmit');
        //authorization
        Route::middleware('registration.complete')->controller('AuthorizationController')->group(function () {
            Route::get('authorization', 'authorization');
            Route::get('resend-verify/{type}', 'sendVerifyCode');
            Route::post('verify-email', 'emailVerification');
            Route::post('verify-mobile', 'mobileVerification');
            Route::post('verify-g2fa', 'g2faVerification');
        });

        Route::middleware(['check.status'])->group(function () {

            Route::middleware('registration.complete')->group(function () {

                Route::controller('InstructorController')->group(function () {

                    Route::get('dashboard', 'dashboard');

                    Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');
                    Route::post('profile-setting', 'submitProfile');
                    Route::post('change-password', 'submitPassword');
                    Route::post('account-setting', 'submitAccount');
                    Route::get('info', 'instructorInfo');

                    //KYC
                    Route::get('kyc-form', 'kycForm');
                    Route::get('kyc-data', 'kycData');
                    Route::post('kyc-submit', 'kycSubmit');

                    //Report
                    Route::any('deposit/history', 'depositHistory');
                    Route::get('transactions', 'transactions');

 
            

                    //2FA
                    Route::get('twofactor', 'show2faForm');
                    Route::post('twofactor/enable', 'create2fa');
                    Route::post('twofactor/disable', 'disable2fa');
                    Route::get('transactions','transactions')->name('transactions');

                    Route::post('delete-account', 'deleteAccount');
                    Route::get('purchase-history', 'purchaseHistory');
                    Route::get('student-overview', 'studentOverview');
                    Route::get('students', 'teacherStudents');
                    Route::post('students/release-lecture', 'releaseOneToOneLecture');
                

                    Route::get('review-list', 'reviewList');
                    Route::post('review-reply/{id}', 'reviewReply');
                    Route::get('leaves', 'leaveList');
                    Route::post('leaves/apply', 'applyLeave');
                    Route::get('unavailable-slots', 'unavailableSlotList');
                    Route::post('unavailable-slots/mark', 'markUnavailableSlot');
                });



                Route::controller('TicketController')->prefix('ticket')->group(function () {
                    Route::get('/', 'supportTicket');
                    Route::post('create', 'storeSupportTicket');
                    Route::get('view/{ticket}', 'viewTicket');
                    Route::post('reply/{id}', 'replyTicket');
                    Route::post('close/{id}', 'closeTicket');
                    Route::get('download/{attachment_id}', 'ticketDownload');
                });

                
                // Withdraw
                Route::controller('WithdrawController')->group(function () {
                    Route::middleware('kyc')->group(function () {
                        Route::get('withdraw-method', 'withdrawMethod');
                        Route::post('withdraw-request', 'withdrawStore');
                        Route::post('withdraw-request/confirm', 'withdrawSubmit');
                    });
                    Route::get('withdraw/history', 'withdrawLog');
                });


                Route::controller('CourseController')->prefix('course')->group(function () {

                    Route::get('list', 'list');
                    Route::get('demo-requests', 'demoRequests');
                    Route::post('demo-requests/{id}/schedule', 'scheduleDemoRequest');
                    Route::get('live-sessions', 'assignedLiveSessions');
                    Route::post('live-session/{sessionId}/start', 'startLiveSession');

                    Route::get('details/{slug}', 'courseDetails');
                    Route::post('sections/{sectionId}/release', 'toggleSectionRelease');
                    Route::post('lectures/{lectureId}/release', 'toggleLectureRelease');

                    Route::get('live-batches/{slug}', 'liveBatches');
                    Route::get('download-resource/{id}', 'downloadResource');
                    

                    //deleted endpoints

                });
            });
        });

        Route::get('logout', 'Auth\LoginController@logout');
    });
});
