<?php

use App\Models\Option;
use GuzzleHttp\Client;

const ACTIVE_SMS_GATEWAYS = [ 'ALPHA', 'GREENWEB', 'BULK71', 'MDL'];

if (!function_exists('randToken')) {

    function randToken( $type, $length ){
        switch ( $type ) {
            case 'alpha':
                $string = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
            case 'hexdec':
                $string = '0123456789abcdef';
                break;
            case 'numeric':
                $string = '0123456789';
                break;
            case 'distinct':
                $string = '2345679ACDEFHJKLMNPRSTUVWXYZ';
                break;
            case 'alnumlc':
                $string = '0123456789abcdefghijklmnopqrstuvwxyz';
                break;
            case 'alnum':
            default:
                $string = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                break;
        }
        $max = strlen($string);
        $token = '';
        for ($i=0; $i < $length; $i++) {
            $token .= $string[random_int(0, $max-1)];
        }

        return $token;
    }
}
if (!function_exists('changableData')) {

    function changableData( $get ){
        $array = [
            'refBonus' => 40,
        ];
        if( $get && isset( $array[ $get ] ) ){
            return $array[ $get ];
        }
        return '';
    }
}
if (!function_exists('checkMobile')) {

    function checkMobile(string $mobile): string
    {
        if (!preg_match('/(^(\+8801|008801|8801|01))(\d){9}$/', $mobile)) {
            return '';
        }
        $mobile = '+88' . substr($mobile, -11);
        return $mobile;
    }
}
if (!function_exists('sendSMS')) {

    function sendSMS($mobile, $message, $gateway = ACTIVE_SMS_GATEWAYS[0])
    {
        if (!env('MAIN')) {
            return false;
        }
        if (!$mobile || !$message) {
            return false;
        }

        $url  = '';
        $data = [];

        switch ($gateway) {
            case 'ALPHA':
                $data = [
                    'u'   => 'arogga',
                    'h'   => env('ALPHA_SMS_KEY'),
                    'op'  => 'pv',
                    'to'  => $mobile,
                    'msg' => $message
                ];
                $url  = 'https://alphasms.biz/index.php?app=ws';
                break;
            case 'GREENWEB':
                $data = [
                    'token'   => env('GREENWEB_SMS_KEY'),
                    'to'      => $mobile,
                    'message' => $message
                ];
                $url  = 'http://api.greenweb.com.bd/api.php';
                break;
            case 'BULK71':
                $data = [
                    'api_key'    => env('BULK71_SMS_KEY'),
                    'mobile_no'  => $mobile,
                    'message'    => $message,
                    'User_Email' => 'testshamimhasan@gmail.com',
                    'sender_id'  => '47',
                ];
                $url  = 'https://71bulksms.com/sms_api/bulk_sms_sender.php';
                break;
            case 'MDL':
                $data = [
                    'api_key'  => env('MDL_SMS_KEY'),
                    'senderid' => env('MDL_SENDER_ID'),
                    'label'    => 'transactional',
                    'type'     => 'text',
                    'contacts' => $mobile,
                    'msg'      => $message
                ];
                $url  = 'http://premium.mdlsms.com/smsapi';
                break;

            default:
                return false;
                break;
        }
        if (!$url || !$data || !\is_array($data)) {
            return false;
        }
        try {
            $client = new Client(['verify' => false, 'http_errors' => false]);
            $client->post($url, [
                'form_params' => $data,
            ]);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}

if (!function_exists('sendOTPSMS')) {

    function sendOTPSMS(string $mobile, $otp)
    {
        if (!$mobile || !$otp) {
            return false;
        }
        if (0 === \strpos($mobile, '+880100000000')) {
            return false;
        }

        if (!env('MAIN')) {
            return false;
        }

        $option = Option::where('option_name', 'smsSentCount')->first();
        $smsSentCount = ($option) ? (int) $option->option_value : 0;
        Option::updateOrCreate([
            'option_name' => 'smsSentCount',
            'option_value' => ++$smsSentCount,
        ]);

        $smsSentCount = floor($smsSentCount / 20);

        $gateway = ACTIVE_SMS_GATEWAYS[$smsSentCount % count(ACTIVE_SMS_GATEWAYS)];

        $message = "Your Arogga OTP is: {$otp}\nUID:7UvkiTFw3Ha";

        sendSMS($mobile, $message, $gateway);
    }
}
if (!function_exists('sendNotification')) {
    function sendNotification( $fcm_token, $title, $message, $extraData = [] ) {
        if( !$fcm_token || ! $title || ! $message ) {
            return false;
        }
        if( !MAIN ) {
            return false;
        }
        try {
            $client = new Client([
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'key='. env('FCM_SERVER_KEY'),
                ],
                'http_errors' => false,
            ]);
            $client->post('https://fcm.googleapis.com/fcm/send',
                ['body' => json_encode([
                    'notification' => [
                        'title' => $title,
                        'body' => $message,
                        'sound' => 'default',
                        'badge' => '1',
                        //'icon' => 'https://api.arogga.com/static/icon.png',
                        //'image' => 'https://api.arogga.com/static/logo.png',
                    ],
                    'data' => [
                        'title' => $title,
                        'body' => $message,
                        'extraData' => $extraData,
                    ],
                    'to' => $fcm_token
                ])]
            );
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}

if (!function_exists('getProfilePicUrl')) {
    function getProfilePicUrl( $u_id ){
        $url = '';
        if( ! $u_id ){
            return $url;
        }
        $path = \sprintf( '/users/%d/%d-*.{jpg,jpeg,png,gif}', \floor( $u_id / 1000 ), $u_id );
        $image_path = '';
        foreach( glob( asset($path), GLOB_BRACE ) as $image ){
            $image_path = $image;
            break;
        }
        if ( $image_path ) {
            $url = $image_path ;
        }
        return $url;
    }
}

