<?php

namespace App\Http\Controllers;

use App\Models\Option;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;


class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['only' => ['logout']]);
    }

    public function guard()
    {
        return Auth::guard();
    }

    public function SMSSend(Request $request)
    {
        if (!$request->mobile) {
            return response()->json([
                'message' => 'Mobile number required.'
            ], Response::HTTP_BAD_REQUEST);
        }
        if (!($mobile = checkMobile($request->mobile))) {
            return response()->json([
                'message' => 'Invalid mobile number.'
            ], Response::HTTP_BAD_REQUEST);
        }
        $user = User::where('u_mobile', $mobile)->first();
        if ($user) {
            if ('blocked' == $user->u_status) {
                return response()->json([
                    'message' => 'You are blocked. Please contact customer care.'
                ], Response::HTTP_FORBIDDEN);
            }
            if ($request->referral) {
                return response()->json([
                    'refError' => true,
                    'message'  => sprintf("This phone number %s is already signed up with arogga, not eligible for referral bonus.\nReferral bonus is applicable for new customers only.", $user->u_mobile),
                ], Response::HTTP_ACCEPTED);
            }
            if (!$user->u_otp || Carbon::now() > $user->u_otp_time->addSeconds(180)) {
                if ('+8801000000007' == $mobile) {
                    $user->u_otp = 100007;
                } else {
                    $user->u_otp = random_int(1000, 9999);
                }
                $user->u_otp_time = Carbon::now();

                $user->update();
                // Send OTP SMS to mobile
                sendOTPSMS($user->u_mobile, $user->u_otp);
                $user->setMeta('failedTry', 0);
            }
        } else {
            $user             = new User();
            $user->u_mobile   = $mobile;
            $user->u_otp      = random_int(1000, 9999);
            $user->u_otp_time = Carbon::now();

            if ($request->fcm_token) {
                $user->fcm_token = $request->fcm_token;
            }

            do {
                $u_referrer = randToken('distinct', 6);

            } while (User::where('u_referrer', $u_referrer)->first());

            $u_r_uid = 0;
            if ($request->referral) {
                if ($r_user = User::where('u_referrer', $request->referral)->first()) {
                    $u_r_uid      = $r_user->u_id;
                    $user->u_cash = '0.00'; //May be give him some cash as he came from referral

                    $refBonus = changableData('refBonus');
                    if ($r_user->fcm_token) {
                        $title   = "Congrats!";
                        $message =
                            "{$mobile} has joined using your referral code. Once he places an order with Arogga you will receive {$refBonus} Taka referral bonus.";
                        sendNotification($r_user->fcm_token, $title, $message);
                    }
                } else {
                    return response()->json([
                        'message' => 'Invalid referral code.'
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            $user->u_referrer = $u_referrer;
            $user->u_r_uid    = $u_r_uid;
            $user->save();
            $user->setMeta('failedTry', 0);


            // Send OTP SMS to mobile
            sendOTPSMS($user->u_mobile, $user->u_otp);
            return response()->json([
                'newUser' => true,
                'message' => 'SMS sent to your mobile number.'
            ], Response::HTTP_ACCEPTED);
        }
        return response()->json([
            'message' => 'SMS sent to your mobile number.'
        ], Response::HTTP_ACCEPTED);
    }

    public function SMSVerify(Request $request)
    {
        $mobile    = isset($request->mobile) ? checkMobile($request->mobile) : '';
        $otp       = $request->otp ?? '';
        $fcm_token = $request->fcm_token ?? '';
        $referral  = (isset($request->referral) && 'undefined' != $request->referral) ? $request->referral : '';

        if (!$mobile || !$otp) {
            return response()->json([
                'message' => 'Mobile number and OTP required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user      = User::where('u_mobile', $mobile)->first();
        $failedTry = (!$user) ? 0 : (int)$user->getMeta('failedTry');
        if (!$user) {
            return response()->json([
                'message' => 'Invalid Mobile Number.'
            ], Response::HTTP_BAD_REQUEST);
        } elseif ($failedTry >= 5) {
            return response()->json([
                'message' => 'Too many failed login attempts. Please try again after 5 minutes.'
            ], Response::HTTP_BAD_REQUEST);
        } elseif (Carbon::now() > $user->u_otp_time->addSeconds(300)) {
            return response()->json([
                'message' => 'OTP Expired, Please try again.'
            ], Response::HTTP_BAD_REQUEST);
        } elseif (!$user->u_otp || (int)$user->u_otp !== (int)$otp) {
            $user->setMeta('failedTry', ++$failedTry);
            return response()->json([
                'message' => 'Error verifying your code. Please input correct code from SMS'
            ], Response::HTTP_BAD_REQUEST);
        }
        if ('blocked' == $user->u_status) {
            return response()->json([
                'message' => 'You are blocked. Please contact customer care.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->u_otp = 0;
        if ($fcm_token) {
            $user->fcm_token = $fcm_token;
        }
        if ($referral && !$user->u_r_uid && Carbon::now() < $user->u_created->addMinutes(60)) {
            if ($r_user = User::where('u_referrer', $request->referral)->first()) {
                $user->u_r_uid = $r_user->u_id;
                //$user->u_cash = '0.00'; //May be give him some cash as he came from referral

                $refBonus = changableData('refBonus');
                if ($r_user->fcm_token) {
                    $title   = "Congrats!";
                    $message =
                        "{$mobile} has joined using your referral code. Once he places an order with Arogga you will receive {$refBonus} Taka referral bonus.";
                    sendNotification($r_user->fcm_token, $title, $message);
                }
            } else {
                return response()->json([
                    'message' => 'Invalid referral code.'
                ], Response::HTTP_BAD_REQUEST);
            }
        }
        $user->update();
        $user->setMeta('failedTry', 0);

        $option       = Option::where('option_name', 'smsSentCount')->first();
        $smsSentCount = ($option) ? (int)$option->option_value : 0;
        if ($smsSentCount > 0) {
            Option::where('option_name', 'smsSentCount')->update([
                'option_value' => --$smsSentCount,
            ]);
        }

        $token             = JWTAuth::fromUser($user);
        $data              = $user->toArray();
        $data['authToken'] = $token;
        $data['u_pic_url'] = getProfilePicUrl($user->u_id);
        return response()->json([
            'user' => $data
        ], Response::HTTP_BAD_REQUEST);
    }

    public function adminSMSSend(Request $request) {
        header("Access-Control-Allow-Origin: *");

        $mobile = $request->mobile ?? '';

        if ( ! $mobile ){
            return response()->json([
                'message' => 'Mobile number required.'
            ], Response::HTTP_BAD_REQUEST);

        }
        if( ! ( $mobile = checkMobile( $mobile ) ) ) {
            return response()->json([
                'message' => 'Invalid mobile number.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = User::where( 'u_mobile', $mobile )->first();
        if( $user ) {
            if( ! $user->canDo( 'backendAccess' ) ) {
                return response()->json([
                    'message' => 'Your account does not have admin access.'
                ], Response::HTTP_BAD_REQUEST);
            }
            if (!$user->u_otp || Carbon::now() > $user->u_otp_time->addSeconds(180)) {
                $user->u_otp = random_int(1000, 9999);
                $user->u_otp_time = Carbon::now();
                $user->update();
                // Send OTP SMS to mobile
                sendOTPSMS($user->u_mobile, $user->u_otp);
                $user->setMeta('failedTry', 0);
            }

        } else {
            return response()->json([
                'message' => 'Invalid mobile number.'
            ], Response::HTTP_BAD_REQUEST);
        }
        return response()->json([
            'status' => "success",
            'message' => 'SMS sent to your mobile number.'
        ], Response::HTTP_BAD_REQUEST);
    }

    public function adminSMSVerify(Request $request) {
        \header("Access-Control-Allow-Origin: *");

        $mobile = isset( $request->mobile ) ? checkMobile( $request->mobile ) : '';
        $otp = $request->otp ?? '';

        if ( ! $mobile || ! $otp ){
            return response()->json([
                'message' => 'Mobile number and OTP required.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user      = User::where('u_mobile', $mobile)->first();
        $failedTry = (!$user) ? 0 :(int) $user->getMeta('failedTry');
        if (!$user) {
            return response()->json([
                'message' => 'Invalid Mobile Number.'
            ], Response::HTTP_BAD_REQUEST);
        } elseif ($failedTry >= 5) {
            return response()->json([
                'message' => 'Too many failed login attempts. Please try again after 5 minutes.'
            ], Response::HTTP_BAD_REQUEST);
        } elseif (Carbon::now() > $user->u_otp_time->addSeconds(300)) {
            return response()->json([
                'message' => 'OTP Expired, Please try again.'
            ], Response::HTTP_BAD_REQUEST);
        } elseif (!$user->u_otp || (int)$user->u_otp !== (int)$otp) {
            $user->setMeta('failedTry', ++$failedTry);
            return response()->json([
                'message' => 'Error verifying your code. Please input correct code from SMS'
            ], Response::HTTP_BAD_REQUEST);
        }
        if( ! $user->canDo( 'backendAccess' ) ) {
            return response()->json([
                'message' => 'Your account does not have admin access.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->u_otp = 0;
        $user->update();
        $user->setMeta('failedTry', 0);

        $option       = Option::where('option_name', 'smsSentCount')->first();
        $smsSentCount = ($option) ? (int)$option->option_value : 0;
        if ($smsSentCount > 0) {
            Option::where('option_name', 'smsSentCount')->update([
                'option_value' => --$smsSentCount,
            ]);
        }

        $token             = JWTAuth::fromUser($user);
        $data              = $user->toArray();
        $data['authToken'] = $token;
        $data['u_pic_url'] = getProfilePicUrl($user->u_id);
        return response()->json([
            'user' => $data
        ], Response::HTTP_BAD_REQUEST);
    }

    public function logout()
    {
        if ($user = $this->guard()->user()) {
            $user->update(['u_token' => \bin2hex(\random_bytes(6))]);
            $this->guard()->logout();
            return response()->json([
                'message' => 'Successfully Logged out.'
            ], Response::HTTP_ACCEPTED);
        } else {
            return response()->json([
                'message' => 'You are not Logged in.'
            ], Response::HTTP_UNAUTHORIZED);
        }
    }
}
