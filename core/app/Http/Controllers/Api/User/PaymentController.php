<?php


namespace App\Http\Controllers\Api\User;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gateway\PaymentController as GatewayPaymentController;
use App\Lib\FormProcessor;
use App\Models\Deposit;
use App\Models\GatewayCurrency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AdminNotification;
use App\Models\AppliedCoupon;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\CourseLiveBatch;
use App\Models\CourseLiveBooking;
use App\Models\CoursePurchased;
use App\Models\CourseZoomBatch;
use App\Models\CourseZoomMeeting;

class PaymentController extends Controller
{

    public function depositInsert(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0',
            'method_code' => 'required',
            'currency' => 'required',
            'course_id' => 'nullable|integer',
            'class_type' => 'nullable|in:group,one_to_one',
            'batch_id' => 'nullable|integer',
            'class_duration' => 'nullable|integer|min:1|max:480',
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user = auth()->user();
        $gate = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE)->withoutGlobalScopes();
        })->where('method_code', $request->method_code)->where('currency', $request->currency)->withoutGlobalScopes()->first();

        if (!$gate) {
            $notify[] = 'Invalid gateway';
            return responseError('invalid_gateway', $notify);
        }

 


    $coursePurchased = [];
    $liveBooking = null;
        $course = null;

        if ($request->course_id) {
            $course = Course::active()->find($request->course_id);
            if (!$course) {
                $notify[] = 'Course not found.';
                return responseError('invalid_course', $notify);
            }

            $enrollCourse = $course->purchases()->where('payment_status', Status::PAYMENT_SUCCESS)->where('user_id', auth()->id())->exists();
            if ($enrollCourse) {
                $notify[] = 'You have already enrolled this course.';
                return responseError('invalid_course', $notify);
            }
        }

        if ($course) {
            $batch = null;
            $classType = $request->class_type;
            if (!$classType && in_array($course->live_class_mode, ['group', 'one_to_one'])) {
                $classType = $course->live_class_mode;
            }

            if ($classType && $course->live_class_mode !== 'both' && $course->live_class_mode !== $classType) {
                $hasConfiguredPrice = ($classType === 'group' && ($course->group_price !== null || $course->price !== null))
                    || ($classType === 'one_to_one' && ($course->one_to_one_price !== null || $course->price !== null));

                if (!$hasConfiguredPrice) {
                    $notify[] = 'Selected class type is not available for this course.';
                    return responseError('invalid_class_type', $notify);
                }
            }

            $basePrice = $course->price;
            if ($classType === 'group') {
                $basePrice = $course->group_price ?? $course->price;
            } elseif ($classType === 'one_to_one') {
                $basePrice = $course->one_to_one_price ?? $course->price;
            }

            if ($course->discount_price > 0 && (float) $basePrice === (float) $course->price) {
                $amount = $basePrice - $course->discount_price;
            } else {
                $amount = $basePrice;
            }

            if ($classType === 'group') {
                if (!$request->filled('batch_id')) {
                    $notify[] = 'Please select a batch to enroll in this group class.';
                    return responseError('batch_required', $notify);
                }

                $batchQuery = CourseLiveBatch::where('course_id', $course->id)
                    ->where('class_type', 'group')
                    ->where('status', Status::ENABLE)
                    ->orderBy('start_date')
                    ->orderBy('meeting_start_time');

                $batch = (clone $batchQuery)->find($request->batch_id);
                $isZoomBatch = false;

                if (!$batch) {
                    $zoomBatch = CourseZoomBatch::where('course_id', $course->id)
                        ->where('status', '!=', 'cancelled')
                        ->find($request->batch_id);

                    if (!$zoomBatch) {
                        $notify[] = 'Selected batch is not available for this course.';
                        return responseError('invalid_batch', $notify);
                    }

                    $isZoomBatch = true;
                    $batch = $zoomBatch;
                }

                if ($isZoomBatch) {
                    $hasScheduledMeetings = CourseZoomMeeting::where('course_id', $course->id)
                        ->where('batch_id', $batch->id)
                        ->where('status', '!=', 'cancelled')
                        ->where(function ($query) {
                            $query->whereHas('occurrences', function ($occurrenceQuery) {
                                $occurrenceQuery->where('status', '!=', 'cancelled');
                            })->orWhereNotNull('zoom_join_url');
                        })
                        ->exists();

                    if (!$hasScheduledMeetings) {
                        $notify[] = 'Selected batch does not have scheduled Zoom sessions.';
                        return responseError('zoom_schedule_missing', $notify);
                    }
                } else {
                    if (!$batch->sessions()->whereIn('status', ['scheduled', 'assigned', 'started'])->exists()) {
                        $notify[] = 'Selected batch does not have scheduled Zoom sessions.';
                        return responseError('zoom_schedule_missing', $notify);
                    }
                }

                if (!$isZoomBatch) {
                    $capacity = (int) ($batch->capacity ?? 20);
                    $booked = CourseLiveBooking::where('batch_id', $batch->id)
                        ->whereIn('status', ['pending_payment', 'pending_admin_assignment', 'assigned', 'confirmed', 'zoom_conflict'])
                        ->count();

                    if ($capacity > 0 && $booked >= $capacity) {
                        $notify[] = 'Selected batch is already full.';
                        return responseError('batch_full', $notify);
                    }
                }
            }

            $coupon = null;

            if ($request->coupon_code) {
                $coupon =  Coupon::activeAndValid()->matchCode($request->coupon_code)
                    ->withCount('appliedCoupons')
                    ->withCount(['appliedCoupons as user_applied_count' => function ($appliedCoupon) {
                        $appliedCoupon->where('user_id', auth()->id());
                    }])->first();

                    
                if (!$coupon) {
                    $notify[] = 'Coupon not found.';
                    return responseError('invalid_coupon', $notify);
                }


                if ($coupon->applied_coupons_count >= $coupon->usage_limit_per_coupon) {
                    $notify[] =  'Coupon usage limit reached.';
                    return responseError('coupon_usage_limit_reached', $notify);
                }


                if ($coupon->user_applied_count >= $coupon->usage_limit_per_user) {
                    $notify[] = 'Coupon usage limit reached.';
                    return responseError('coupon_usage_limit_reached', $notify);
                }

                $minimumSpend = $coupon->minimum_spend;
                $maximumSpend = $coupon->maximum_spend;

                if ($amount < $minimumSpend) {
                    $notify[] = 'Course price should be greater than or equal to ' . $minimumSpend;
                    return responseError('course_price_error', $notify);
                }

                if ($amount > $maximumSpend) {
                    $notify[] =  'Course price should be less than or equal to ' . $maximumSpend;
                    return responseError('course_price_error', $notify);
                }

                $discount = $coupon->discountAmount($amount);
                $afterDiscount = $amount - $discount;
                $amount = $afterDiscount <= 0 ? 0 : $afterDiscount;
            }

            $coursePurchased = new CoursePurchased();
            $coursePurchased->user_id = $user->id;
            $coursePurchased->instructor_id = $course->instructor_id;
            $coursePurchased->course_id = $course->id;
            $coursePurchased->coupon_id = $coupon->id ?? 0;
            $coursePurchased->amount = $amount;
            $coursePurchased->discount = (float) $basePrice === (float) $course->price ? $course->discount_price : 0;
            $coursePurchased->coupon_discount =   $discount ?? 0;
            $coursePurchased->payment_status = Status::PENDING;
            $coursePurchased->save();

            if ($classType) {
                $duration = (int) ($request->class_duration ?: $course->default_class_duration ?: $course->meeting_duration ?: 60);
                $startTime = $classType === 'group' ? $batch?->meeting_start_time : null;
                $startDate = $classType === 'group' ? $batch?->start_date : null;

                $endTime = null;
                if ($startTime) {
                    $endTime = date('H:i', strtotime("+{$duration} minutes", strtotime($startTime)));
                }

                $liveBooking = new CourseLiveBooking();
                $liveBooking->course_id = $course->id;
                $liveBooking->course_purchased_id = $coursePurchased->id;
                $liveBooking->batch_id = $batch?->id;
                $liveBooking->user_id = $user->id;
                $liveBooking->class_type = $classType;
                $liveBooking->start_date = $startDate;
                $liveBooking->start_time = $startTime;
                $liveBooking->end_time = $endTime;
                $liveBooking->class_duration = $duration;
                $liveBooking->status = 'pending_payment';
                $liveBooking->save();
            }
        } else {
            $amount = $request->amount;
        }

        if ($gate->min_amount > $amount || $gate->max_amount < $amount) {
            $notify[] =  'Please follow deposit limit';
            return responseError('invalid_amount', $notify);
        }


        $charge = $gate->fixed_charge + ($amount * $gate->percent_charge / 100);
        $payable = $amount + $charge;
        $finalAmount = $payable * $gate->rate;

        $data = new Deposit();
        $data->from_api = 1;
        $data->is_web = $request->is_web ? 1 : 0;
        $data->user_id = $user->id;
        $data->course_id = $course->id ?? 0;
        $data->course_purchased_id = $coursePurchased->id ?? 0;
        $data->method_code = $gate->method_code;
        $data->method_currency = strtoupper($gate->currency);
        $data->amount = $amount;
        $data->charge = $charge;
        $data->rate = $gate->rate;
        $data->final_amount = $finalAmount;
        $data->btc_amount = 0;
        $data->btc_wallet = "";
        $data->success_url = $request->success_url;
        $data->failed_url = $request->failed_url;
        $data->trx = getTrx();
        $data->save();

    
        if ($request->coupon_code) {
        
            $this->saveAppliedCoupon($coupon, $coursePurchased);
        }


        if ($data->method_code == -1000) {
          
            $user = $data->user;
            if ($user->balance < $data->final_amount) {
                $notify[] = 'You\'ve no sufficient balance';
                return responseError('invalid_amount', $notify);
            }

            GatewayPaymentController::userDataUpdate($data);

            $notify[] =  'Payment captured successfully';
            return responseSuccess('payment_inserted', $notify, [
                'deposit' => $data,
            ]);
        }


        $notify[] =  'Deposit inserted';
        if ($request->is_web && $data->gateway->code < 1000) {
            $dirName = $data->gateway->alias;
            $new = 'App\\Http\\Controllers\\Gateway\\' . $dirName . '\\ProcessController';

            $gatewayData = $new::process($data);
            $gatewayData = json_decode($gatewayData);

            // for Stripe V3
            if (@$data->session) {
                $data->btc_wallet = $gatewayData->session->id;
                $data->save();
            }

            return responseSuccess('deposit_inserted', $notify, [
                'deposit' => $data->load('coursePurchase'),
                'gateway_data' => $gatewayData
            ]);
        }

        $data->load('gateway', 'gateway.form');


        return responseSuccess('deposit_inserted', $notify, [
            'deposit' => $data,
            'redirect_url' => route('deposit.app.confirm', encrypt($data->id))
        ]);
    }

    public function appPaymentConfirm(Request $request)
    {
        if (!gs('in_app_payment')) {
            $notify[] = 'In app purchase feature currently disable';
            return responseError('feature_disable', $notify);
        }
        $validator = Validator::make($request->all(), [
            'method_code'   => 'required|in:5001',
            'amount'        => 'required|numeric|gt:0',
            'currency'      => 'required|string',
            'purchase_token' => 'required',
            'package_name'   => 'required',
            'plan_id'     => 'required'
        ]);

        if ($validator->fails()) {
            return responseError('validation_error', $validator->errors());
        }

        $user = auth()->user();

        $deposit = Deposit::where('status', Status::PAYMENT_SUCCESS)->where('btc_wallet', $request->purchase_token)->exists();
        if ($deposit) {
            $notify[] =  'Payment already captured';
            return responseError('payment_captured', $notify);
        }


        if (!file_exists(getFilePath('appPurchase') . '/google_pay.json')) {
            $notify[] =  'Configuration file missing';
            return responseError('configuration_missing', $notify);
        }
        $configuration = getFilePath('appPurchase') . '/google_pay.json';
        $client          = new \Google_Client();
        $client->setAuthConfig($configuration);
        $client->setScopes([\Google_Service_AndroidPublisher::ANDROIDPUBLISHER]);
        $service = new \Google_Service_AndroidPublisher($client);

        $packageName   = $request->package_name;
        $productId     = $request->plan_id;
        $purchaseToken = $request->purchase_token;
        try {
            $response = $service->purchases_products->get($packageName, $productId, $purchaseToken);
        } catch (\Exception $e) {
            $errorMessage = @json_decode($e->getMessage())->error->message;
            $adminNotification = new AdminNotification();
            $adminNotification->user_id = $user->id;
            $adminNotification->title = 'In App Purchase Error: ' . $errorMessage;
            $adminNotification->click_url = '#';
            $adminNotification->save();


            $notify[] = 'Something went wrong';
            return responseError('invalid_purchase', $notify);
        }

        if ($response->getPurchaseState() != 0) {
            $notify[] = 'Invalid purchase';
            return responseError('invalid_purchase', $notify);
        }

        //the amount should be your product amount
        $amount = 10;
        $rate = $request->amount / $amount;


        $data = new Deposit();
        $data->user_id = $user->id;
        $data->method_code = $request->method_code;
        $data->method_currency = $request->currency;
        $data->amount = $amount;
        $data->charge = 0;
        $data->rate = $rate;
        $data->final_amount = $request->amount;
        $data->btc_amount = 0;
        $data->btc_wallet = $request->purchase_token;
        $data->trx = getTrx();
        $data->save();

        GatewayPaymentController::userDataUpdate($data);

        $notify[] = 'Payment confirmed successfully';
        return responseSuccess('payment_confirm', $notify);
    }
    public function manualDepositConfirm(Request $request)
    {
        $track = $request->track;
        $data = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();

        if (!$data) {
            $notify[] = 'Invalid request';
            return responseError('invalid_request', $notify);
        }

        $gatewayCurrency = $data->gatewayCurrency();
        $gateway = $gatewayCurrency->method;
        $formData = $gateway->form->form_data;

        $formProcessor = new FormProcessor();
        $validationRule = $formProcessor->valueValidation($formData);
        $request->validate($validationRule);
        $userData = $formProcessor->processFormData($request, $formData);

        $data->detail = $userData;
        $data->status = Status::PAYMENT_PENDING;
        $data->save();

        $coursePurchase = $data->coursePurchase;

        if ($coursePurchase) {
            $coursePurchase->payment_status = Status::PENDING;
            $coursePurchase->save();
        }

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = $data->user->id;
        $adminNotification->title     = 'Deposit request from ' . $data->user->username;
        $adminNotification->click_url = urlPath('admin.deposit.details', $data->id);
        $adminNotification->save();
    

      
            notify($data->user, 'DEPOSIT_REQUEST', [
                'method_name'     => $data->gatewayCurrency()->name,
                'method_currency' => $data->method_currency,
                'method_amount'   => showAmount($data->final_amount, currencyFormat: false),
                'amount'          => showAmount($data->amount, currencyFormat: false),
                'charge'          => showAmount($data->charge, currencyFormat: false),
                'rate'            => showAmount($data->rate, currencyFormat: false),
                'trx'             => $data->trx
            ]);
        

        $notify[] = 'You have deposit request has been taken';
        return responseSuccess('deposit_request_taken', $notify);
    }

    private function saveAppliedCoupon($coupon, $coursePurchased)
    {
        $appliedCoupon            = new AppliedCoupon();
        $appliedCoupon->user_id   = auth()->id();
        $appliedCoupon->coupon_id = $coupon->id;
        $appliedCoupon->course_purchased_id  = $coursePurchased->id;
        $appliedCoupon->course_id  = $coursePurchased->course_id;
        $appliedCoupon->amount    = $coursePurchased->coupon_discount;
        $appliedCoupon->save();
    }
}
