<?php

namespace App\Http\Controllers\Gateway;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Models\AdminNotification;
use App\Models\ConsultationNotificationRecipient;
use App\Models\CourseLiveBatch;
use App\Models\CoursePurchased;
use App\Models\Deposit;
use App\Models\CourseLiveBooking;
use App\Models\CourseZoomBatch;
use App\Models\GatewayCurrency;
use App\Models\Instructor;
use App\Notifications\BatchCourseEnrollmentNotification;
use App\Notifications\OneToOneCourseEnrollmentNotification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class PaymentController extends Controller
{
    public function deposit()
    {
        $gatewayCurrency = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->with('method')->orderby('name')->get();
        $pageTitle = 'Deposit Methods';
        return view('Template::user.payment.deposit', compact('gatewayCurrency', 'pageTitle'));
    }

    public function depositInsert(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|gt:0',
            'gateway' => 'required',
            'currency' => 'required',
        ]);


        $user = auth()->user();
        $gate = GatewayCurrency::whereHas('method', function ($gate) {
            $gate->where('status', Status::ENABLE);
        })->where('method_code', $request->gateway)->where('currency', $request->currency)->first();
        if (!$gate) {
            $notify[] = ['error', 'Invalid gateway'];
            return back()->withNotify($notify);
        }

        if ($gate->min_amount > $request->amount || $gate->max_amount < $request->amount) {
            $notify[] = ['error', 'Please follow deposit limit'];
            return back()->withNotify($notify);
        }




        $charge = $gate->fixed_charge + ($request->amount * $gate->percent_charge / 100);
        $payable = $request->amount + $charge;
        $finalAmount = $payable * $gate->rate;

        $data = new Deposit();
        $data->user_id = $user->id;
        $data->method_code = $gate->method_code;
        $data->method_currency = strtoupper($gate->currency);
        $data->amount = $request->amount;
        $data->charge = $charge;
        $data->rate = $gate->rate;
        $data->final_amount = $finalAmount;
        $data->btc_amount = 0;
        $data->btc_wallet = "";
        $data->trx = getTrx();
        $data->success_url = route('user.deposit.history');
        $data->failed_url = route('user.deposit.history');
        $data->save();
        session()->put('Track', $data->trx);
        return to_route('user.deposit.confirm');
    }


    public function appDepositConfirm($hash)
    {
        try {
            $id = decrypt($hash);
        } catch (\Exception $ex) {
            abort(404);
        }
        $data = Deposit::where('id', $id)->where('status', Status::PAYMENT_INITIATE)->orderBy('id', 'DESC')->firstOrFail();
        $user = User::findOrFail($data->user_id);
        auth()->login($user);
        session()->put('Track', $data->trx);
        return to_route('user.deposit.confirm');
    }


    public function depositConfirm()
    {
        $track = session()->get('Track');
        $deposit = Deposit::where('trx', $track)->where('status', Status::PAYMENT_INITIATE)->orderBy('id', 'DESC')->with('gateway')->firstOrFail();

        if ($deposit->method_code >= 1000) {
            return to_route('user.deposit.manual.confirm');
        }


        $dirName = $deposit->gateway->alias;
        $new = __NAMESPACE__ . '\\' . $dirName . '\\ProcessController';

        $data = $new::process($deposit);
        $data = json_decode($data);


        if (isset($data->error)) {
            $notify[] = ['error', $data->message];
            return back()->withNotify($notify);
        }
        if (isset($data->redirect)) {
            return redirect($data->redirect_url);
        }

        // for Stripe V3
        if (@$data->session) {
            $deposit->btc_wallet = $data->session->id;
            $deposit->save();
        }

        $pageTitle = 'Payment Confirm';
        return view("Template::$data->view", compact('data', 'pageTitle', 'deposit'));
    }


    private static function sendOneToOneEnrollmentNotification(CoursePurchased $coursePurchased, Deposit $deposit): void
    {
        self::configureConsultationMailTransport();
        $recipients = ConsultationNotificationRecipient::where('is_active', true)->pluck('email')->all();
        $legacyRecipient = strtolower(trim((string) gs('consultation_notification_email')));

        if ($legacyRecipient && filter_var($legacyRecipient, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $legacyRecipient;
        }

        foreach (array_unique($recipients) as $recipientEmail) {
            try {
                Notification::route('mail', $recipientEmail)
                    ->notify(new OneToOneCourseEnrollmentNotification($coursePurchased, $deposit));
            } catch (\Throwable $throwable) {
                Log::error('Failed to send 1:1 course enrollment notification email', [
                    'recipient' => $recipientEmail,
                    'course_purchased_id' => $coursePurchased->id,
                    'deposit_id' => $deposit->id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }
    }

    private static function sendBatchEnrollmentNotification(CoursePurchased $coursePurchased, Deposit $deposit, CourseLiveBooking $booking): void
    {
        self::configureConsultationMailTransport();
        $recipients = ConsultationNotificationRecipient::where('is_active', true)->pluck('email')->all();
        $legacyRecipient = strtolower(trim((string) gs('consultation_notification_email')));

        if ($legacyRecipient && filter_var($legacyRecipient, FILTER_VALIDATE_EMAIL)) {
            $recipients[] = $legacyRecipient;
        }

        $liveBatch = null;
        $zoomBatch = null;

        if ($booking->batch_id) {
            $liveBatch = CourseLiveBatch::with(['teachers.teacher'])->find((int) $booking->batch_id);
            if (!$liveBatch) {
                $zoomBatch = CourseZoomBatch::with(['teacher', 'meetings.occurrences'])->find((int) $booking->batch_id);
            }
        }

        foreach (array_unique($recipients) as $recipientEmail) {
            try {
                Notification::route('mail', $recipientEmail)
                    ->notify(new BatchCourseEnrollmentNotification($coursePurchased, $deposit, $liveBatch, $zoomBatch));
            } catch (\Throwable $throwable) {
                Log::error('Failed to send batch course enrollment notification email', [
                    'recipient' => $recipientEmail,
                    'course_purchased_id' => $coursePurchased->id,
                    'deposit_id' => $deposit->id,
                    'batch_id' => $booking->batch_id,
                    'error' => $throwable->getMessage(),
                ]);
            }
        }
    }

    private static function configureConsultationMailTransport(): void
    {
        $mailConfig = gs('mail_config');
        if (!$mailConfig || empty($mailConfig->name)) {
            return;
        }

        if ($mailConfig->name === 'smtp') {
            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $mailConfig->host ?? config('mail.mailers.smtp.host'),
                'mail.mailers.smtp.port' => (int) ($mailConfig->port ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.encryption' => $mailConfig->enc ?? config('mail.mailers.smtp.encryption'),
                'mail.mailers.smtp.username' => $mailConfig->username ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $mailConfig->password ?? config('mail.mailers.smtp.password'),
                'mail.from.address' => gs('email_from') ?: config('mail.from.address'),
                'mail.from.name' => gs('email_from_name') ?: config('mail.from.name'),
            ]);
            return;
        }

        if ($mailConfig->name === 'php') {
            config([
                'mail.default' => 'sendmail',
                'mail.from.address' => gs('email_from') ?: config('mail.from.address'),
                'mail.from.name' => gs('email_from_name') ?: config('mail.from.name'),
            ]);
        }
    }

    public static function userDataUpdate($deposit, $isManual = null)
    {
        if ($deposit->status == Status::PAYMENT_INITIATE || $deposit->status == Status::PAYMENT_PENDING) {
            $deposit->status = Status::PAYMENT_SUCCESS;
            $deposit->save();

            $user = User::find($deposit->user_id);
            $user->balance += $deposit->amount;
            $user->save();

            $methodName = $deposit->methodName();

            $transaction = new Transaction();
            $transaction->user_id = $deposit->user_id;
            $transaction->amount = $deposit->amount;
            $transaction->post_balance = $user->balance;
            $transaction->charge = $deposit->charge;
            $transaction->trx_type = '+';
            $transaction->details = 'Deposit Via ' . $methodName;
            $transaction->trx = $deposit->trx;
            $transaction->remark = 'deposit';
            $transaction->save();

            if (!$isManual) {
                $adminNotification = new AdminNotification();
                $adminNotification->user_id = $user->id;
                $adminNotification->title = 'Deposit successful via ' . $methodName;
                $adminNotification->click_url = urlPath('admin.deposit.successful');
                $adminNotification->save();
            }

            notify($user, $isManual ? 'DEPOSIT_APPROVE' : 'DEPOSIT_COMPLETE', [
                'method_name' => $methodName,
                'method_currency' => $deposit->method_currency,
                'method_amount' => showAmount($deposit->final_amount, currencyFormat: false),
                'amount' => showAmount($deposit->amount, currencyFormat: false),
                'charge' => showAmount($deposit->charge, currencyFormat: false),
                'rate' => showAmount($deposit->rate, currencyFormat: false),
                'trx' => $deposit->trx,
                'course_name' => optional($deposit->course)->title ?: 'N/A',
                'post_balance' => showAmount($user->balance)
            ]);


            if ($deposit->course) {
                $user = User::find($deposit->user_id);
                $user->balance -= $deposit->amount;
                $user->save();

                $instructor  = Instructor::find($deposit->course->instructor_id);
                $instructor->balance += $deposit->amount + $deposit->coursePurchase->coupon_discount;
                $instructor->save();

                $coursePurchased = $deposit->coursePurchase;

                $coursePurchased->payment_status = Status::PAYMENT_SUCCESS;
                $coursePurchased->trx = $deposit->trx;
                $coursePurchased->save();

                $booking = CourseLiveBooking::where('course_purchased_id', $coursePurchased->id)->first();
                if ($booking && $booking->status === 'pending_payment') {
                    if ($booking->class_type === 'group') {
                        $booking->status = 'confirmed';
                    } else {
                        $booking->status = 'pending_student_schedule';
                        $booking->start_date = null;
                        $booking->start_time = null;
                        $booking->end_time = null;
                    }
                    $booking->save();
                }

                if ($booking && $booking->class_type === 'one_to_one') {
                    self::sendOneToOneEnrollmentNotification($coursePurchased, $deposit);
                }

                if ($booking && $booking->class_type === 'group') {
                    self::sendBatchEnrollmentNotification($coursePurchased, $deposit, $booking);
                }

                $transaction = new Transaction();
                $transaction->user_id = $deposit->user_id;
                $transaction->amount = $deposit->amount;
                $transaction->post_balance = $user->balance;
                $transaction->charge = $deposit->charge;
                $transaction->trx_type = '-';
                $transaction->details = 'Course Purchase Via ' . $methodName;
                $transaction->trx = $deposit->trx;
                $transaction->remark = 'payment_send';
                $transaction->save();

                // notify
                notify($instructor, 'PAYMENT_SEND', [
                    'title' => $coursePurchased->course->title,
                    'amount' => showAmount($deposit->amount, currencyFormat: false),
                    'charge' => showAmount($deposit->charge, currencyFormat: false),
                    'rate' => showAmount($deposit->rate, currencyFormat: false),
                    'trx' => $deposit->trx,
                    'post_balance' => showAmount($instructor->balance)
                ]);


                $transaction = new Transaction();
                $transaction->instructor_id = $instructor->id;
                $transaction->amount = $deposit->amount;
                $transaction->post_balance =$instructor->balance;
                $transaction->charge = $deposit->charge;
                $transaction->trx_type = '+';
                $transaction->details = 'Payment Received from ' . $user->fullname;
                $transaction->trx = $deposit->trx;
                $transaction->remark = 'payment_received';
                $transaction->save();

                // notify
                notify($instructor, 'INSTRUCTOR_PAYMENT_RECEIVED', [
                    'name' => $user->fullname,
                    'title' => $coursePurchased->course->title,
                    'amount' => showAmount($deposit->amount, currencyFormat: false),
                    'charge' => showAmount($deposit->charge, currencyFormat: false),
                    'rate' => showAmount($deposit->rate, currencyFormat: false),
                    'trx' => $deposit->trx,
                    'post_balance' => showAmount($instructor->balance)
                ]);
            }
        }
    }

    public function manualDepositConfirm()
    {
        $track = session()->get('Track');
        $data = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        abort_if(!$data, 404);
        if ($data->method_code > 999) {
            $pageTitle = 'Confirm Deposit';
            $method = $data->gatewayCurrency();
            $gateway = $method->method;
            return view('Template::user.payment.manual', compact('data', 'pageTitle', 'method', 'gateway'));
        }
        abort(404);
    }

    public function manualDepositUpdate(Request $request)
    {
        $track = session()->get('Track');
        $data = Deposit::with('gateway')->where('status', Status::PAYMENT_INITIATE)->where('trx', $track)->first();
        abort_if(!$data, 404);
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


        $adminNotification = new AdminNotification();
        $adminNotification->user_id = $data->user->id;
        $adminNotification->title = 'Deposit request from ' . $data->user->username;
        $adminNotification->click_url = urlPath('admin.deposit.details', $data->id);
        $adminNotification->save();

        notify($data->user, 'DEPOSIT_REQUEST', [
            'method_name' => $data->gatewayCurrency()->name,
            'method_currency' => $data->method_currency,
            'method_amount' => showAmount($data->final_amount, currencyFormat: false),
            'amount' => showAmount($data->amount, currencyFormat: false),
            'charge' => showAmount($data->charge, currencyFormat: false),
            'rate' => showAmount($data->rate, currencyFormat: false),
            'trx' => $data->trx
        ]);

        $notify[] = ['success', 'You have deposit request has been taken'];
        return to_route('user.deposit.history')->withNotify($notify);
    }
}
