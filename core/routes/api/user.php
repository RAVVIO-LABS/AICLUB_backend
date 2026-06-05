<?php

use Illuminate\Support\Facades\Route;

Route::namespace('User')->name('api.')->group(function () {

    Route::namespace('Auth')->group(function () {
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

    Route::middleware(['auth:sanctum', 'ability:user'])->group(function () {

        Route::post('user-data-submit', 'UserController@userDataSubmit');
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
            

                Route::controller('UserController')->group(function () {
                    Route::get('dashboard', 'dashboard');
                    Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');
                    Route::post('profile-setting', 'submitProfile');
                    Route::post('upload/presign', 'presignUpload');
                    Route::post('change-password', 'submitPassword');

                    Route::get('user-info', 'userInfo');
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

                    Route::post('delete-account', 'deleteAccount');

                    Route::get('goals', 'goals');
                    Route::post('save-goal', 'saveGoal');

                });


                Route::controller('CourseController')->group(function () {
                    Route::get('enrolled-courses', 'enrolledCourse');
                    Route::get('watch-course/{slug}', 'watchCourse');
                    Route::post('lecture-complete/{id}', 'lectureComplete');
                    Route::get('quiz-view/{slug}', 'quizView');
                    Route::post('quiz-submit', 'quizSubmit');
                    Route::get('resource-download/{id}', 'ResourcesDownload');
                    Route::post('user-activity','userActivity');
                    Route::post('review-submit/{slug}', 'reviewSubmit');
                    Route::post('one-to-one-schedule/{slug}', 'scheduleOneToOne');

                    Route::get('course-complete/{slug}', 'courseComplete');
                    
                    Route::get('download/{slug}', 'certificateDownload')->name('download');
                    Route::post('check-coupon', 'checkCoupon');
                

                });

                // Payment
                Route::controller('PaymentController')->group(function () {
                    Route::post('deposit/insert', 'depositInsert');
                    Route::post('app/payment/confirm', 'appPaymentConfirm');
                    Route::post('manual/confirm', 'manualDepositConfirm');
                });


                Route::controller('TicketController')->prefix('ticket')->group(function () {
                    Route::get('/', 'supportTicket');
                    Route::post('create', 'storeSupportTicket');
                    Route::get('view/{ticket}', 'viewTicket');
                    Route::post('reply/{id}', 'replyTicket');
                    Route::post('close/{id}', 'closeTicket');
                    Route::get('download/{attachment_id}', 'ticketDownload');
                });
            });
        });

        Route::get('logout', 'Auth\LoginController@logout');
    });
});
