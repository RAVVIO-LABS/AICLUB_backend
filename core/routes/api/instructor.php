<?php

use Illuminate\Support\Facades\Route;



Route::namespace('Instructor')->name('api.instructor.')->prefix('instructor')->group(function () {

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


    Route::middleware(['auth:sanctum', 'ability:instructor'])->group(function () {

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
                    Route::post('upload/presign', 'presignUpload');
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
                

                    Route::get('review-list', 'reviewList');
                    Route::post('review-reply/{id}', 'reviewReply');
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
                    Route::post('create', 'create');
                    Route::post('check-slug', 'checkSlug');
                    Route::post('intended-learners/{slug}', 'intendedLearners');
                    Route::post('add-section/{slug}', 'addSection');
                    Route::post('add-lecture/{slug}', 'addLecture');
                    Route::post('add-lecture-video/{slug}', 'addLectureVideo');
                    Route::post('add-lecture-article/{slug}', 'addLectureArticle');
                    Route::post('add-quiz/{slug}', 'addQuiz');
                    Route::post('add-quiz-option/{slug}', 'addQuizOption');

                    Route::get('details/{slug}', 'courseDetails');
                    Route::get('zoom-batches/{slug}', 'zoomBatches');
                    Route::post('zoom-batches/{slug}', 'createZoomBatch');
                    Route::get('delete-zoom-batch/{id}', 'deleteZoomBatch');
                    Route::get('zoom-meetings/{slug}', 'zoomMeetings');
                    Route::post('zoom-meetings/{slug}', 'createZoomMeeting');
                    Route::post('update-zoom-meeting/{id}', 'updateZoomMeeting');
                    Route::get('delete-zoom-meeting/{id}', 'deleteZoomMeeting');
                    Route::post('update-zoom-meeting-occurrence/{id}', 'updateZoomMeetingOccurrence');
                    Route::post('cancel-zoom-meeting-occurrence/{id}', 'cancelZoomMeetingOccurrence');
                    Route::post('available-teachers/{slug}', 'availableTeachers');

                    Route::post('save-content/{slug}', 'saveContent');
                    Route::post('save-price/{slug}', 'savePrice');
                    Route::post('save-message/{slug}', 'saveMessage');
                    Route::post('save-resources/{slug} ', 'saveResources');
                    Route::get('download-resource/{id} ', 'downloadResource');
                    

                    //deleted endpoints
                    Route::get('delete-section/{id?}', 'deleteSection');
                    Route::get('delete-lecture/{id?}', 'deleteLecture');
                    Route::get('delete-quiz/{id?}', 'deleteQuiz');
                    Route::get('delete-question/{id?}', 'deleteQuestion');
                    Route::post('course-submit/{slug}', 'courseSubmit');
                    Route::get('delete/{slug}', 'deleteCourse');
                    Route::get('delete-resource/{id}', 'deleteResource');

                });
            });
        });

        Route::get('logout', 'Auth\LoginController@logout');
    });
});
