<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::controller('AppController')->group(function () {
    Route::get('general-setting', 'generalSetting');
    Route::get('get-countries', 'getCountries');
    Route::get('language/{key?}', 'getLanguage');
    Route::get('policies', 'policies');
    Route::get('policy/{slug}', 'policyContent');
    Route::get('blog-details/{slug}', 'blogDetails');
    Route::get('faq', 'faq');
    Route::get('seo', 'seo');
    Route::get('get-extension/{act}', 'getExtension');
    Route::post('contact', 'submitContact');
    Route::post('consultation-request', 'submitConsultationRequest');
    Route::get('cookie', 'cookie');
    Route::post('cookie/accept', 'cookieAccept');
    Route::get('custom-pages', 'customPages');
    Route::get('custom-page/{slug}', 'customPageData');
    Route::get('sections/{key?}', 'allSections');
    Route::get('ticket/{ticket}', 'viewTicket');
    Route::post('ticket/ticket-reply/{id}', 'replyTicket');
    Route::post('subscription', 'subscription');
    Route::get('categories', 'categories');
    Route::get('popular-courses', 'popularCourses');
    Route::get('top-courses', 'topCourses');
    Route::get('short-courses', 'shortCourses');
    Route::get('course-detail/{slug}', 'courseDetail');
    Route::get('deposit/methods', 'methods');
    Route::get('all-courses/{slug?}', 'allCourses');
    Route::get('instructor-profile/{username}', 'instructorProfile');
    Route::post('save-keyword', 'saveKeyword');
    Route::get('keyword', 'keyword');
    Route::get('blogs', 'blogs');
    Route::get('latest-course', 'latestCourse');
    Route::get('verify-certificate/{secret}', 'verifyCertificate')->name('verify.certificate');
});

@include('user.php');
@include('instructor.php');
@include('teacher.php');
