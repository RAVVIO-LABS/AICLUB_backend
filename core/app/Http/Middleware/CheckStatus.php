<?php

namespace App\Http\Middleware;

use Closure;
use Auth;
use App\Models\Instructor;

class CheckStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if ($guard) {
            $check = Auth::guard($guard)->check();
            $user = auth()->guard($guard)->user();
        } else {
            $guard = 'user';
            $user = auth()->user();
            $check = Auth::check();
        }
        
        if ($check) {
         
            if ($user->status  && $user->ev  && $user->sv  && $user->tv) {
                return $next($request);
            } else {
                $isInstructorAccount = $user instanceof Instructor || ($user->role_type ?? null) === 'instructor';
                $isPendingAcademy = !($user->status) && $isInstructorAccount
                    && (empty($user->ban_reason));

                if ($request->is('api/*')) {
                    if ($isPendingAcademy) {
                        $notify[] = 'Approval is pending from admin.';
                        return response()->json([
                            'remark' => 'pending_approval',
                            'status' => 'error',
                            'message' => ['error' => $notify],
                            'data' => [
                                'user' => $user,
                            ],
                        ], 403);
                    }

                    $notify[] = 'You need to verify your account first. Please logout and re-login';
                    return response()->json([
                        'remark'=>'unverified',
                        'status'=>'error',
                        'message'=>['error'=>$notify],
                        'data'=>[
                            'user'=>$user
                        ],
                    ]);
                }else{
                    if ($isPendingAcademy) {
                        return response('Approval is pending from admin.', 403);
                    }
                    return to_route("$guard.authorization");
                }
            }
        }
        abort(403);
    }
}
