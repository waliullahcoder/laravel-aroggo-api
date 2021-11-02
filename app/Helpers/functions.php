<?php

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use App\Models\Option;
use App\Models\Medicine;
use App\Models\Discount;
use App\Models\Location;
use App\Models\Ledger;
use App\Models\OMedicine;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Meta;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Cache\Cache;

const ACTIVE_SMS_GATEWAYS = ['ALPHA', 'GREENWEB', 'BULK71', 'MDL'];

if (!function_exists('randToken')) {

    function randToken($type, $length)
    {
        switch ($type) {
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
        $max   = strlen($string);
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $string[random_int(0, $max - 1)];
        }

        return $token;
    }
}
if (!function_exists('changableData')) {

    function changableData($get)
    {
        $array = [
            'refBonus' => 40,
        ];
        if ($get && isset($array[$get])) {
            return $array[$get];
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

        $option       = Option::where('option_name', 'smsSentCount')->first();
        $smsSentCount = ($option) ? (int)$option->option_value : 0;
        Option::updateOrCreate([
            'option_name'  => 'smsSentCount',
            'option_value' => ++$smsSentCount,
        ]);

        $smsSentCount = floor($smsSentCount / 20);

        $gateway = ACTIVE_SMS_GATEWAYS[$smsSentCount % count(ACTIVE_SMS_GATEWAYS)];

        $message = "Your Arogga OTP is: {$otp}\nUID:7UvkiTFw3Ha";

        sendSMS($mobile, $message, $gateway);
    }
}
if (!function_exists('sendNotification')) {
    function sendNotification($fcm_token, $title, $message, $extraData = [])
    {
        if (!$fcm_token || !$title || !$message) {
            return false;
        }
        if (!MAIN) {
            return false;
        }
        try {
            $client = new Client([
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'key=' . env('FCM_SERVER_KEY'),
                ],
                'http_errors' => false,
            ]);
            $client->post('https://fcm.googleapis.com/fcm/send',
                ['body' => json_encode([
                    'notification' => [
                        'title' => $title,
                        'body'  => $message,
                        'sound' => 'default',
                        'badge' => '1',
                        //'icon' => 'https://api.arogga.com/static/icon.png',
                        //'image' => 'https://api.arogga.com/static/logo.png',
                    ],
                    'data'         => [
                        'title'     => $title,
                        'body'      => $message,
                        'extraData' => $extraData,
                    ],
                    'to'           => $fcm_token
                ])]
            );
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}

if (!function_exists('getProfilePicUrl')) {
    function getProfilePicUrl($u_id)
    {
        $url = '';
        if (!$u_id) {
            return $url;
        }
        $path       = \sprintf('/users/%d/%d-*.{jpg,jpeg,png,gif}', \floor($u_id / 1000), $u_id);
        $image_path = '';
        foreach (glob(asset($path), GLOB_BRACE) as $image) {
            $image_path = $image;
            break;
        }
        if ($image_path) {
            $url = $image_path;
        }
        return $url;
    }
}

if (!function_exists('maybeJsonDecode')) {
    function maybeJsonDecode($value)
    {
        if (!is_string($value)) {
            return $value;
        }
        if (strlen($value) < 2) {
            return $value;
        }
        if (0 !== \strpos($value, '{') && 0 !== \strpos($value, '[')) {
            return $value;
        }

        $json_data = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $value;
        }
        return $json_data;
    }
}

if (!function_exists('notFoundURL')) {
    function notFoundURL()
    {
        return response()->json([
            'status'  => 'Fail',
            'message' => 'Nothing Found',
            'data'    => [],
        ]);
    }
}

if (!function_exists('maybeJsonEncode')) {
    function maybeJsonEncode($value)
    {
        if (is_array($value) || is_object($value)) {
            return \json_encode($value);
        }
        return $value;
    }
}

if (!function_exists('isCold')) {
    function isCold($m_g_id)
    {
        $cold_g_ids = [820, 821, 822, 824, 1888, 2003, 2005, 2006, 2007, 2008, 2773, 2776, 2784, 2925, 3501];

        if ($m_g_id && in_array($m_g_id, $cold_g_ids)) {
            return true;
        } else {
            return false;
        }
    }
}


if (!function_exists('getS3Bucket')) {
    function getS3Bucket()
    {
        //return MAIN ? 'arogga' : 'arogga-staging'; //OLD
        return true ? 'arogga' : 'arogga-staging'; //Me
    }
}


if (!function_exists('getS3Url')) {
    function getS3Url($key, $width = '', $height = '', $watermark = false)
    {
        if (!$key) {
            return '';
        }
        $edits = [];
        if ($width && $height) {
            $edits['resize'] = [
                "width"  => $width,
                "height" => $height,
                "fit"    => "outside",
            ];
        }
        if ($watermark) {
            $edits['overlayWith'] = [
                'bucket' => getS3Bucket(),
                'key'    => 'misc/wm.png',
                'alpha'  => 90,
            ];
        }
        $s3_params = [
            "bucket" => getS3Bucket(),
            "key"    => $key,
            "edits"  => $edits,
        ];
        $base64    = base64_encode(json_encode($s3_params));
        //return CDN_URL . '/' . $base64;//old
        return true . '/' . $base64;//me
    }
}


if (!function_exists('getPicUrl')) {
    function getPicUrl($images)
    {
        $url = '';
        if ($images && is_array($images)) {
            foreach ($images as $image) {
                $url = getS3Url($image['s3key'] ?? '', 200, 200);
                break;
            }
        }
        return $url;
    }
}


if (!function_exists('cartData')) {
    function cartData($user, $medicines, $d_code = '', $order = null, $offline = false, $args = [])
    {
        $subtotal          = '0.00';
        $total             = '0.00';
        $saving            = 0;
        $d_amount          = '0.00';
        $a_amount          = '0.00';
        $rx_req            = false;
        $cold              = false;
        $m_return          = [];
        $additions         = [];
        $deductions        = [];
        $free_delivery     = false;
        $prev_applied_cash = 0;
        $o_status          = '';

        $u_id = $user ? $user->u_id : 0;

        if ($order) {
            $o_status = $order->o_status;
            $o_data   = (array)$order->getMeta('o_data');

            if (isset($o_data['deductions']['cash'])) {
                $prev_applied_cash = $o_data['deductions']['cash']['amount'];
            }
        }

        if (empty($args['s_address']) || empty($args['s_address']['district'])) {
            $args['s_address']['district'] = 'Dhaka City';
        }

        if (!is_array($medicines)) {
            $medicines = [];
        }

        foreach ($medicines as $key => $value) {
            if (is_array($value)) {
                $m_id     = isset($value['m_id']) ? (int)$value['m_id'] : 0;
                $quantity = isset($value['qty']) ? (int)$value['qty'] : 0;
            } else {
                $m_id     = (int)$key;
                $quantity = (int)$value;
            }

            if (!($medicine = getMedicine($m_id))) {
                continue;
            }
            if (isset($m_return[$m_id])) {
                continue;
            }
            if (!$medicine->m_rob && !$o_status) {
                $quantity = 0;
            }
            if ((bool)$medicine->m_rx_req) {
                $rx_req = true;
            }
            $isCold = $medicine->isCold();
            if ($isCold) {
                $cold = true;
            }

            $price   = $medicine->m_price ? round($medicine->m_price * $quantity, 2) : 0;
            $d_price = $medicine->m_d_price ? round($medicine->m_d_price * $quantity, 2) : 0;
            $d_price = $offline ? $price : $d_price;

            if ($isCold && $args['s_address']['district'] != 'Dhaka City') {
                $quantity = 0;
                $price    = 0;
                $d_price  = 0;
            }

            $m_return[$m_id] = [
                'qty'      => $quantity,
                'm_id'     => $m_id,
                'name'     => $medicine->m_name,
                'strength' => $medicine->m_strength,
                'form'     => $medicine->m_form,
                'unit'     => $medicine->m_unit,
                'price'    => $price,
                'd_price'  => $d_price,
                'rx_req'   => $medicine->m_rx_req,
                'pic_url'  => $medicine->m_pic_url,
                'cold'     => $isCold,
                'min'      => $medicine->m_min,
                'max'      => $medicine->m_max,
            ];
            $subtotal        += $price;
            $saving          += ($price - $d_price);
        }
        if ($saving) {
            $d_amount             += $saving;
            $deductions['saving'] = [
                'amount' => round($saving, 2),
                'text'   => 'Discount applied',
                'info'   => '',
            ];
        }
        $discount = getDiscount($d_code);

        if ($discount && $discount->canUserUse($u_id)) {
            if ('percent' === $discount->d_type || 'firstPercent' === $discount->d_type) {
                $amount = (($subtotal - $d_amount) / 100) * $discount->d_amount;
                if (!empty($discount->d_max)) {
                    $amount = min($discount->d_max, $amount);
                }
            } elseif ('fixed' === $discount->d_type || 'firstFixed' === $discount->d_type) {
                $amount = $discount->d_amount;
            } elseif ('free_delivery' === $discount->d_type) {
                $amount        = 0; //For free delivery we will calculate later
                $free_delivery = true;
            } else {
                $amount = 0;
                //$free_delivery = false;
            }
            $d_amount += \round($amount, 2);

            $deductions['discount'] = [
                'amount' => \round($amount, 2),
                'text'   => "Coupon applied ($discount->d_code)",
                'info'   => !empty($free_delivery) ? 'Free Delivery' : '',
            ];
        }
        if ($user && ($user->u_cash || $prev_applied_cash)) {
            if (($subtotal - $d_amount) > 0) {
                $amount   = \round($subtotal - $d_amount, 2);
                $amount   = \round(\min($amount, $user->u_cash + $prev_applied_cash), 2);
                $d_amount += $amount;

                $deductions['cash'] = [
                    'amount' => $amount,
                    'text'   => 'arogga cash applied',
                    'info'   => '',
                ];
            } else {
                /*
                $deductions['cash'] = [
                    'amount' => '0.00',
                    'text' => 'arogga cash',
                    'info' => 'To use arogga cash order more than ৳499',
                ];
                */
            }

        }
        if (!empty($args['man_discount'])) {
            $amount   = \round($args['man_discount'], 2);
            $d_amount += $amount;

            $deductions['man_discount'] = [
                'amount' => $amount,
                'text'   => 'Manual discount applied',
                'info'   => '',
            ];
        }

        if (!$offline && !$free_delivery) {
            if ('Dhaka City' == $args['s_address']['district']) {
                $trigger_delivery_fee = 999;
                $delivery_fee         = 39;
            } else {
                //Other districts
                $trigger_delivery_fee = 2999;
                $delivery_fee         = 99;
            }
            if (($subtotal - $d_amount) < $trigger_delivery_fee) {
                $additions['delivery'] = [
                    'amount' => $delivery_fee,
                    'text'   => sprintf('Delivery charge (%s)', 'Dhaka City' == $args['s_address']['district'] ? 'Inside Dhaka' : 'Outside Dhaka'),
                    'info'   => sprintf('To get free delivery order more than ৳%d', $trigger_delivery_fee),
                ];
                $a_amount              += $delivery_fee;
            } else {
                $additions['delivery'] = [
                    'amount' => '00',
                    'text'   => 'Delivery charge',
                    'info'   => '',
                ];
            }
        }

        if (!empty($args['man_addition'])) {
            $amount   = \round($args['man_addition'], 2);
            $a_amount += $amount;

            $deductions['man_addition'] = [
                'amount' => $amount,
                'text'   => 'Manual addition applied',
                'info'   => '',
            ];
        }
        $subtotal    = \round($subtotal, 2);
        $total       = \round($subtotal - $d_amount + $a_amount, 2);
        $total_floor = \floor($total);
        if ($total_floor < $total) {
            $deductions['rounding'] = [
                'amount' => \round($total - $total_floor, 2),
                'text'   => 'Rounding Off',
                'info'   => '',
            ];
            $d_amount               += \round($total - $total_floor, 2);
            $total                  = $total_floor;
        }

        if ($total > 4999) {
            $cash_back = 100;
        } elseif ($total > 3999) {
            $cash_back = 80;
        } elseif ($total > 2999) {
            $cash_back = 60;
        } elseif ($total > 1999) {
            $cash_back = 40;
        } elseif ($total > 999) {
            $cash_back = 20;
        } else {
            $cash_back = 0;
        }

        if ($offline) {
            $cash_back = 0;
        }

        $data = [
            'medicines'   => $m_return,
            'deductions'  => $deductions,
            'additions'   => $additions,
            'subtotal'    => \round($subtotal, 2),
            'total'       => \round($total, 2),
            'a_amount'    => \round($a_amount, 2),
            'd_amount'    => \round($d_amount, 2),
            'total_items' => count($m_return),
            'rx_req'      => $rx_req,
            'cold'        => $cold,
            'cash_back'   => $cash_back,
            'a_message'   => '', //additional message, Show just above purchase button
        ];

        return $data;
    }
}


if (!function_exists('getAreas')) {
    function getAreas($division, $district)
    {
        if (!$division || !$district) {
            return [];
        }

        $locations = Location::getLocations();
        if (!isset($locations[$division]) || !isset($locations[$division][$district])) {
            return [];
        }

        return array_unique(array_filter(array_keys($locations[$division][$district])));
    }
}


if (!function_exists('isLocationValid')) {
    function isLocationValid($division, $district, $area)
    {
        $areas = getAreas($division, $district);

        if (in_array($area, $areas)) {
            return true;
        } else {
            return false;
        }
    }
}

if (!function_exists('randToken')) {
    function randToken($type, $length)
    {
        switch ($type) {
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
        $max   = strlen($string);
        $token = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $string[random_int(0, $max - 1)];
        }

        return $token;
    }
}


if (!function_exists('ModifyOrderMedicines')) {
    function ModifyOrderMedicines($order, $medicineQty, $prev_order_data = null)
    {
        if (!$order || !$order->o_id) {
            return false;
        }

        $new_ids     = [];
        $insert      = [];
        $old_data    = [];
        $o_is_status = '';

        $query =
            DB::instance()->select('t_o_medicines', ['o_id' => $order->o_id], 'm_id, m_qty, m_price, m_d_price, s_price, om_status');
        while ($old = $query->fetch()) {
            $old_data[$old['m_id']] = $old;
        }

        foreach ($medicineQty as $id_qty) {
            $m_id     = isset($id_qty['m_id']) ? (int)$id_qty['m_id'] : 0;
            $quantity = isset($id_qty['qty']) ? (int)$id_qty['qty'] : 0;

            if (!($medicine = Medicine::getMedicine($m_id))) {
                continue;
            }
            $new_ids[] = $medicine->m_id;

            if (isset($old_data[$medicine->m_id])) {
                $change = [];
                if ($quantity != $old_data[$medicine->m_id]['m_qty']) {
                    $change['m_qty'] = $quantity;
                    $order->addHistory('Medicine Qty Change', sprintf('%s %s - %s', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass($old_data[$medicine->m_id]['m_qty'], $medicine)), sprintf('%s %s - %s', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass($quantity, $medicine)));
                    if ($prev_order_data && $order->o_ph_id) {
                        //update inventory
                        if ('available' == $old_data[$medicine->m_id]['om_status']) {
                            if ($old_data[$medicine->m_id]['m_qty'] >= $quantity) {
                                Inventory::qtyUpdateByPhMid($order->o_ph_id, $medicine->m_id, $old_data[$medicine->m_id]['m_qty'] - $quantity);
                                //medicine quantity removed note
                                if ('delivering' === $order->o_status) {
                                    $o_i_note =
                                        sprintf('%s %s - %s removed during delivering', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass(($old_data[$medicine->m_id]['m_qty'] - $quantity), $medicine));
                                    $order->appendMeta('o_i_note', $o_i_note);
                                    if (!$o_is_status) {
                                        $o_is_status = 'delivered';
                                    }
                                }
                            } else {
                                $inventory = Inventory::getByPhMid($order->o_ph_id, $medicine->m_id);
                                if (($inventory->i_qty + $old_data[$medicine->m_id]['m_qty']) >= $quantity) {
                                    Inventory::qtyUpdateByPhMid($order->o_ph_id, $medicine->m_id, $old_data[$medicine->m_id]['m_qty'] - $quantity);
                                } else {
                                    Inventory::qtyUpdateByPhMid($order->o_ph_id, $medicine->m_id, $old_data[$medicine->m_id]['m_qty']);
                                    $change['om_status'] = 'later';
                                }
                                //medicine quantity added note
                                if ('delivering' === $order->o_status) {
                                    $o_i_note =
                                        sprintf('%s %s - %s added during delivering', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass(($quantity - $old_data[$medicine->m_id]['m_qty']), $medicine));
                                    $order->appendMeta('o_i_note', $o_i_note);
                                    if ('packing' !== $o_is_status) {
                                        $o_is_status = 'packing';
                                    }
                                }
                            }
                        } elseif ('later' == $old_data[$medicine->m_id]['om_status']) {
                            $inventory = Inventory::getByPhMid($order->o_ph_id, $medicine->m_id);
                            if ($inventory && $inventory->i_qty >= $quantity) {
                                $inventory->i_qty = $inventory->i_qty - $quantity;
                                $inventory->update();
                                $change['s_price']   = $inventory->i_price;
                                $change['om_status'] = 'available';
                            }
                        }
                    }
                }
                if ($medicine->m_price != $old_data[$medicine->m_id]['m_price']) {
                    $change['m_price'] = $medicine->m_price;
                }
                if ($medicine->m_d_price != $old_data[$medicine->m_id]['m_d_price']) {
                    $change['m_d_price'] = $medicine->m_d_price;
                }
                if ($change) {
                    DB::instance()->update('t_o_medicines', $change, ['o_id' => $order->o_id, 'm_id' => $medicine->m_id]);
                }
            } else {
                $insert_data = [
                    'o_id'      => $order->o_id,
                    'm_id'      => $medicine->m_id,
                    'm_unit'    => $medicine->m_unit,
                    'm_price'   => $medicine->m_price ?: 0,
                    'm_d_price' => $medicine->m_d_price ?: 0,
                    'm_qty'     => $quantity,
                ];

                if ($prev_order_data) {
                    $inventory = Inventory::getByPhMid($order->o_ph_id, $medicine->m_id);
                    if ($inventory && $inventory->i_qty >= $quantity) {
                        $inventory->i_qty = $inventory->i_qty - $quantity;
                        $inventory->update();
                        $insert_data['s_price']   = $inventory->i_price;
                        $insert_data['om_status'] = 'available';
                    } else {
                        $insert_data['s_price']   = 0;
                        $insert_data['om_status'] = 'later';
                    }
                    if ('delivering' === $order->o_status) {
                        $o_i_note =
                            sprintf('%s %s - %s added during delivering', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass($quantity, $medicine));
                        $order->appendMeta('o_i_note', $o_i_note);
                        if ('packing' != $o_is_status) {
                            $o_is_status = 'packing';
                        }
                    }
                    $order->addHistory('Medicine Add', '', sprintf('%s %s - %s', $medicine->m_name, $medicine->m_strength, Functions::qtyTextClass($quantity, $medicine)));
                }
                $insert[] = $insert_data;
            }
        }
        $d_ids = \array_diff(\array_keys($old_data), $new_ids);

        if ($d_ids) {
            $in = str_repeat('?,', count($d_ids) - 1) . '?';

            $query = DB::db()->prepare("DELETE FROM t_o_medicines WHERE o_id = ? AND m_id IN ($in)");
            $query->execute(\array_merge([$order->o_id], $d_ids));
            if ($prev_order_data && $order->o_ph_id) {
                $o_i_note = '';
                foreach ($d_ids as $d_id) {
                    if ('available' == $old_data[$d_id]['om_status']) {
                        //update inventory
                        Inventory::qtyUpdateByPhMid($order->o_ph_id, $d_id, $old_data[$d_id]['m_qty']);

                        if ('delivering' === $order->o_status) {
                            if (!($d_medicine = Medicine::getMedicine($d_id))) {
                                continue;
                            }
                            $o_i_note =
                                sprintf('%s %s - %s removed during delivering', $d_medicine->m_name, $d_medicine->m_strength, Functions::qtyTextClass($old_data[$d_id]['m_qty'], $d_medicine));
                            $order->appendMeta('o_i_note', $o_i_note);
                            if (!$o_is_status) {
                                $o_is_status = 'delivered';
                            }
                        }
                    }
                    if ($del_medicine = Medicine::getMedicine($d_id)) {
                        $order->addHistory('Medicine Remove', sprintf('%s %s - %s', $del_medicine->m_name, $del_medicine->m_strength, Functions::qtyTextClass($old_data[$d_id]['m_qty'], $del_medicine)));
                    }
                }
            }
        }
        if ($o_is_status && (!$order->o_is_status || 'solved' === $order->o_is_status)) {
            $order->update(['o_is_status' => $o_is_status]);
        }
        DB::instance()->insertMultiple('t_o_medicines', $insert);

        setInternalOrderStatus($order);

        return true;
    }
}


if (!function_exists('getIdByLocation')) {
    function getIdByLocation($type, $division, $district, $area)
    {
        if (!$division || !$district || !$area) {
            return 0;
        }
        if (!in_array($type, ['l_de_id', 'l_postcode', 'l_id'])) {
            return 0;
        }

        $locations = Location::getLocations();
        if (!isset($locations[$division]) || !isset($locations[$division][$district]) || !isset($locations[$division][$district][$area])) {
            return 0;
        }
        return (int)$locations[$division][$district][$area][$type];
    }
}


if (!function_exists('getZoneByLocationId')) {
    function getZoneByLocationId($l_id)
    {
        if (!$l_id || !is_numeric($l_id)) {
            return '';
        }

        $locations = Location::getLocations();

        foreach ($locations as $division => $v1) {
            foreach ($v1 as $district => $v2) {
                foreach ($v2 as $area => $v3) {
                    if (!empty($v3) && $l_id == $v3['l_id']) {
                        return $v3['l_zone'];
                    }
                }
            }
        }
        return '';
    }
}

if (!function_exists('jwtEncode')) {
    function jwtEncode( $payload ){
        return JWT::encode( $payload, env('JWT_TOKEN_KEY'));
    }
}

if (!function_exists('jwtDecode')) {
    function jwtDecode($token)
    {
        try {
            $payload = (array)JWT::decode( $token, env('JWT_TOKEN_KEY'), array('HS256'));

            return $payload;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('getAddressByPostcode')) {
    function getAddressByPostcode($post_code, $map_area = '')
    {
        if (!$post_code || !is_numeric($post_code)) {
            return [];
        }
        $locations = getLocations();

        foreach ($locations as $division => $v1) {
            foreach ($v1 as $district => $v2) {
                foreach ($v2 as $area => $v3) {
                    if (!empty($v3) && !empty($v3['l_postcode']) && $post_code == $v3['l_postcode']) {
                        $return = [
                            'division' => $division,
                            'district' => $district,
                            'area'     => $area,
                        ];
                        if ($area == $map_area) {
                            break 3;
                        }
                    }
                }
            }
        }
        return $return;
    }
}


if (!function_exists('getProfilePicUrl')) {
    function getProfilePicUrl($u_id)
    {
        $url = '';
        if (!$u_id) {
            return $url;
        }
        $path       = \sprintf('/users/%d/%d-*.{jpg,jpeg,png,gif}', \floor($u_id / 1000), $u_id);
        $image_path = '';
        foreach (glob(STATIC_DIR . $path, GLOB_BRACE) as $image) {
            $image_path = $image;
            break;
        }
        if ($image_path) {
            $url = str_replace(STATIC_DIR, STATIC_URL, $image_path);
        }
        return $url;
    }
}


if (!function_exists('getS3')) {
    function getS3()
    {
        // Instantiate an Amazon S3 client.
        $s3 = new S3Client([
            'version'     => 'latest',
            'region'      => 'ap-southeast-1',
            'credentials' => [
                'key'    => S3_KEY,
                'secret' => S3_SECRET,
            ],
        ]);
        return $s3;
    }
}

if (!function_exists('getPresignedUrl')) {
    function getPresignedUrl($s3Key)
    {
        $url = '';
        try {
            $s3      = getS3();
            $cmd     = $s3->getCommand('GetObject', [
                'Bucket' => getS3Bucket(),
                'Key'    => $s3Key
            ]);
            $request = $s3->createPresignedRequest($cmd, '+6 days 23 hours');
            $url     = (string)$request->getUri();
        } catch (S3Exception $e) {
            error_log($e->getAwsErrorMessage());
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
        return $url;
    }
}

if (!function_exists('getDivisions')) {
    function getDivisions()
    {
        $locations = getLocations();
        return array_unique(array_filter(array_keys($locations)));
    }
}

if (!function_exists('getDistricts')) {
    function getDistricts($division)
    {
        if (!$division) {
            return [];
        }

        $locations = Location::getLocations();
        if (!isset($locations[$division])) {
            return [];
        }

        return array_unique(array_filter(array_keys($locations[$division])));
    }
}

if (!function_exists('getAreas')) {
    function getAreas($division, $district)
    {
        if (!$division || !$district) {
            return [];
        }

        $locations = getLocations();
        if (!isset($locations[$division]) || !isset($locations[$division][$district])) {
            return [];
        }

        return array_unique(array_filter(array_keys($locations[$division][$district])));
    }
}

if (!function_exists('pointInPolygon')) {
    function pointInPolygon($p, $polygon)
    {
        if (!$p || !\is_array($p) || !\is_array($polygon)) {
            return false;
        }
        //if you operates with (hundred)thousands of points
        //set_time_limit(60);
        $c  = 0;
        $p1 = $polygon[0];
        $n  = count($polygon);

        for ($i = 1; $i <= $n; $i++) {
            $p2 = $polygon[$i % $n];
            if ($p[1] > min($p1[1], $p2[1])
                && $p[1] <= max($p1[1], $p2[1])
                && $p[0] <= max($p1[0], $p2[0])
                && $p1[1] != $p2[1]) {
                $xinters = ($p[1] - $p1[1]) * ($p2[0] - $p1[0]) / ($p2[1] - $p1[1]) + $p1[0];
                if ($p1[0] == $p2[0] || $p[0] <= $xinters) {
                    $c++;
                }
            }
            $p1 = $p2;
        }
        // if the number of edges we passed through is even, then it's not in the poly.
        return $c % 2 != 0;
    }
}

if (!function_exists('isInside')) {
    function isInside($lat, $long, $area = false)
    {
        $locations = [
            'dhaka'      => [
                [23.663722, 90.456617],
                [23.710720, 90.509878],
                [23.782770, 90.473925],
                [23.826433, 90.488741],
                [23.901250, 90.448270],
                [23.883042, 90.398767],
                [23.901272, 90.384341],
                [23.882106, 90.348942],
                [23.752972, 90.328691],
                [23.709614, 90.363012],
                [23.706776, 90.404532],
            ],
            'chittagong' => [
                [22.368476, 91.753262],
                [22.371652, 91.754636],
                [22.430376, 91.871818],
                [22.429093, 91.890720],
                [22.416709, 91.883488],
                [22.403997, 91.890347],
                [22.332232, 91.863481],
                [22.307491, 91.800634],
                [22.281449, 91.796849],
                [22.262373, 91.838371],
                [22.224910, 91.801293],
                [22.273198, 91.763899],
            ],
        ];

        $isInside = false;
        foreach ($locations as $city => $location) {
            if ($area && $area != $city) {
                continue;
            }
            if (pointInPolygon([$lat, $long], $location)) {
                $isInside = true;
                break;
            }
        }
        return $isInside;
    }
}


if (!function_exists('getPharmacyZones')) {
    function getPharmacyZones($ph_id)
    {
        if (!$ph_id || !is_numeric($ph_id)) {
            return [];
        }
        $return    = [];
        $locations = getLocations();

        foreach ($locations as $division => $v1) {
            foreach ($v1 as $district => $v2) {
                foreach ($v2 as $area => $v3) {
                    if (!empty($v3) && $ph_id == $v3['l_ph_id'] && !in_array($v3['l_zone'], $return)) {
                        $return[] = $v3['l_zone'];
                    }
                }
            }
        }
        sort($return);
        return $return;
    }
}


if (!function_exists('ledgerCreate')) {
    function ledgerCreate($reason, $amount, $type)
    {
        if (\in_array($type, ['collection', 'input', 'Share Money Deposit', 'Directors Loan', 'Other Credit'])) {
            $amount = \abs($amount);
        } else {
            $amount = \abs($amount) * -1;
        }
        $data = [
            'l_uid'     => Auth::id(),
            'l_created' => \date('Y-m-d H:i:s'),
            'l_reason'  => \mb_strimwidth($reason, 0, 255, '...'),
            'l_type'    => $type,
            'l_amount'  => \round($amount, 2),
        ];
        $data = Ledger::insert($data);
        return $data;
    }

}

if (!function_exists('checkMobile')) {
    function checkMobile($mobile)
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


if (!function_exists('modifyMedicineImages')) {
    function modifyMedicineImages($m_id, $images)
    {
        if (!$m_id || !is_array($images)) {
            return false;
        }
        if (!($medicine = Medicine::getMedicine($m_id))) {
            return false;
        }
        // Instantiate an Amazon S3 client.
        $s3 = getS3();

        $s3_key_prefix = \sprintf('medicine/%s/%s-', \floor($m_id / 1000), $m_id);

        if ($images && is_array($images)) {
            $imgArray = [];
            $limit    = 8;
            foreach ($images as $file) {
                if (!$limit) {
                    break;
                }
                if (!$file || !is_array($file)) {
                    continue;
                }
                if (0 === strpos($file['src'], 'http')) {
                    if (!empty($file['s3key']) && strpos($file['s3key'], $s3_key_prefix) !== false && $s3->doesObjectExist(getS3Bucket(), $file['s3key'])) {
                        array_push($imgArray, [
                            'title' => $file['title'],
                            's3key' => $file['s3key']
                        ]);
                        $limit--;
                    }
                } else {
                    $mime = @mime_content_type($file['src']);
                    if (!$mime) {
                        continue;
                    }
                    $ext = strtolower(explode('/', $mime)[1]);
                    if (!$ext || !in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        continue;
                    }
                    $title = trim($medicine->m_name . ' ' . $medicine->m_strength);
                    $name  = preg_replace('/[^a-zA-Z0-9\-\._]/', '-', $title);
                    $name  = explode('.', $name)[0];
                    $name  = trim(preg_replace('/-+/', '-', $name), '-');
                    $s3key = \sprintf('%s%s-%s.%s', $s3_key_prefix, $name, randToken('alnumlc', 4), $ext);

                    $imgstring = trim(explode(',', $file['src'])[1]);
                    $imgstring = str_replace(' ', '+', $imgstring);
                    if (strlen($imgstring) < 12 || strlen($imgstring) > 10 * 1024 * 1024) {
                        continue;
                    }
                    // Upload file. The file size are determined by the SDK.
                    try {
                        $s3->putObject([
                            'Bucket'      => getS3Bucket(),
                            'Key'         => $s3key,
                            'Body'        => base64_decode($imgstring),
                            'ContentType' => $mime,
                        ]);
                        //$s3->upload( Functions::getS3Bucket(), $s3key, base64_decode( $imgstring ) );
                        array_push($imgArray, [
                            'title' => $title,
                            's3key' => $s3key
                        ]);
                        $limit--;
                    } catch (S3Exception $e) {
                        error_log($e->getAwsErrorMessage());
                        continue;
                    }
                }
            }
            // if( $medicine->setMeta( 'images', $imgArray ) ){
            //     \OA\Search\Medicine::init()->update( $medicine->m_id, [ 'images' => $imgArray, 'imagesCount' => count( $imgArray ) ] );
            //     Cache::instance()->incr( 'suffixForMedicines' );
            // }
        }
        return true;
    }

}
if (!function_exists('getPicUrlsAdmin')) {
    function getPicUrlsAdmin($images)
    {
        if ($images && is_array($images)) {
            foreach ($images as &$image) {
                $image['src'] = getS3Url($image['s3key'] ?? '', 200, 200);
            }
            unset($image);
        } else {
            $images = [];
        }
        return $images;
    }
}

if (!function_exists('getS3Url')) {
    function getS3Url($key, $width = '', $height = '', $watermark = false)
    {
        if (!$key) {
            return '';
        }
        $edits = [];
        if ($width && $height) {
            $edits['resize'] = [
                "width"  => $width,
                "height" => $height,
                "fit"    => "outside",
            ];
        }
        if ($watermark) {
            $edits['overlayWith'] = [
                'bucket' => getS3Bucket(),
                'key'    => 'misc/wm.png',
                'alpha'  => 90,
            ];
        }
        $s3_params = [
            "bucket" => getS3Bucket(),
            "key"    => $key,
            "edits"  => $edits,
        ];
        $base64    = base64_encode(json_encode($s3_params));
        return CDN_URL . '/' . $base64;
    }
}


if (!function_exists('ModifyOrderMedicines')) {
    function ModifyOrderMedicines($order, $medicineQty, $prev_order_data = null)
    {
        if (!$order || !$order->o_id) {
            return false;
        }

        $new_ids     = [];
        $insert      = [];
        $old_data    = [];
        $o_is_status = '';

        $old = OMedicine::where('o_id', $order->o_id)->get();
        while ($old) {
            $old_data[$old['m_id']] = $old;
        }

        foreach ($medicineQty as $id_qty) {
            $m_id     = isset($id_qty['m_id']) ? (int)$id_qty['m_id'] : 0;
            $quantity = isset($id_qty['qty']) ? (int)$id_qty['qty'] : 0;

            if (!($medicine = Medicine::getMedicine($m_id))) {
                continue;
            }
            $new_ids[] = $medicine->m_id;

            if (isset($old_data[$medicine->m_id])) {
                $change = [];
                if ($quantity != $old_data[$medicine->m_id]['m_qty']) {
                    $change['m_qty'] = $quantity;
                    $order->addHistory('Medicine Qty Change', sprintf('%s %s - %s', $medicine->m_name, $medicine->m_strength, qtyTextClass($old_data[$medicine->m_id]['m_qty'], $medicine)), sprintf('%s %s - %s', $medicine->m_name, $medicine->m_strength, qtyTextClass($quantity, $medicine)));
                    if ($prev_order_data && $order->o_ph_id) {
                        //update inventory
                        if ('available' == $old_data[$medicine->m_id]['om_status']) {
                            if ($old_data[$medicine->m_id]['m_qty'] >= $quantity) {
                                Inventory::qtyUpdateByPhMid($order->o_ph_id, $medicine->m_id, $old_data[$medicine->m_id]['m_qty'] - $quantity);
                                //medicine quantity removed note
                                if ('delivering' === $order->o_status) {
                                    $o_i_note =
                                        sprintf('%s %s - %s removed during delivering', $medicine->m_name, $medicine->m_strength, qtyTextClass(($old_data[$medicine->m_id]['m_qty'] - $quantity), $medicine));
                                    $order->appendMeta('o_i_note', $o_i_note);
                                    if (!$o_is_status) {
                                        $o_is_status = 'delivered';
                                    }
                                }
                            } else {
                                $inventory = Inventory::getByPhMid($order->o_ph_id, $medicine->m_id);
                                if (($inventory->i_qty + $old_data[$medicine->m_id]['m_qty']) >= $quantity) {
                                    Inventory::qtyUpdateByPhMid($order->o_ph_id, $medicine->m_id, $old_data[$medicine->m_id]['m_qty'] - $quantity);
                                } else {
                                    Inventory::qtyUpdateByPhMid($order->o_ph_id, $medicine->m_id, $old_data[$medicine->m_id]['m_qty']);
                                    $change['om_status'] = 'later';
                                }
                                //medicine quantity added note
                                if ('delivering' === $order->o_status) {
                                    $o_i_note =
                                        sprintf('%s %s - %s added during delivering', $medicine->m_name, $medicine->m_strength, qtyTextClass(($quantity - $old_data[$medicine->m_id]['m_qty']), $medicine));
                                    $order->appendMeta('o_i_note', $o_i_note);
                                    if ('packing' !== $o_is_status) {
                                        $o_is_status = 'packing';
                                    }
                                }
                            }
                        } elseif ('later' == $old_data[$medicine->m_id]['om_status']) {
                            $inventory = Inventory::getByPhMid($order->o_ph_id, $medicine->m_id);
                            if ($inventory && $inventory->i_qty >= $quantity) {
                                $inventory->i_qty = $inventory->i_qty - $quantity;
                                $inventory->update();
                                $change['s_price']   = $inventory->i_price;
                                $change['om_status'] = 'available';
                            }
                        }
                    }
                }
                if ($medicine->m_price != $old_data[$medicine->m_id]['m_price']) {
                    $change['m_price'] = $medicine->m_price;
                }
                if ($medicine->m_d_price != $old_data[$medicine->m_id]['m_d_price']) {
                    $change['m_d_price'] = $medicine->m_d_price;
                }
                if ($change) {
                    // DB::instance()->update( 't_o_medicines', $change, [ 'o_id' => $order->o_id, 'm_id' => $medicine->m_id ] );
                }
            } else {
                $insert_data = [
                    'o_id'      => $order->o_id,
                    'm_id'      => $medicine->m_id,
                    'm_unit'    => $medicine->m_unit,
                    'm_price'   => $medicine->m_price ?: 0,
                    'm_d_price' => $medicine->m_d_price ?: 0,
                    'm_qty'     => $quantity,
                ];

                if ($prev_order_data) {
                    $inventory = Inventory::getByPhMid($order->o_ph_id, $medicine->m_id);
                    if ($inventory && $inventory->i_qty >= $quantity) {
                        $inventory->i_qty = $inventory->i_qty - $quantity;
                        $inventory->update();
                        $insert_data['s_price']   = $inventory->i_price;
                        $insert_data['om_status'] = 'available';
                    } else {
                        $insert_data['s_price']   = 0;
                        $insert_data['om_status'] = 'later';
                    }
                    if ('delivering' === $order->o_status) {
                        $o_i_note =
                            sprintf('%s %s - %s added during delivering', $medicine->m_name, $medicine->m_strength, qtyTextClass($quantity, $medicine));
                        $order->appendMeta('o_i_note', $o_i_note);
                        if ('packing' != $o_is_status) {
                            $o_is_status = 'packing';
                        }
                    }
                    $order->addHistory('Medicine Add', '', sprintf('%s %s - %s', $medicine->m_name, $medicine->m_strength, qtyTextClass($quantity, $medicine)));
                }
                $insert[] = $insert_data;
            }
        }
        $d_ids = \array_diff(\array_keys($old_data), $new_ids);

        if ($d_ids) {
            OMedicine::where('o_id', $order->o_id)->where('m_id', $d_ids)->dalete();
            if ($prev_order_data && $order->o_ph_id) {
                $o_i_note = '';
                foreach ($d_ids as $d_id) {
                    if ('available' == $old_data[$d_id]['om_status']) {
                        //update inventory
                        Inventory::qtyUpdateByPhMid($order->o_ph_id, $d_id, $old_data[$d_id]['m_qty']);

                        if ('delivering' === $order->o_status) {
                            if (!($d_medicine = Medicine::getMedicine($d_id))) {
                                continue;
                            }
                            $o_i_note =
                                sprintf('%s %s - %s removed during delivering', $d_medicine->m_name, $d_medicine->m_strength, qtyTextClass($old_data[$d_id]['m_qty'], $d_medicine));
                            $order->appendMeta('o_i_note', $o_i_note);
                            if (!$o_is_status) {
                                $o_is_status = 'delivered';
                            }
                        }
                    }
                    if ($del_medicine = Medicine::getMedicine($d_id)) {
                        $order->addHistory('Medicine Remove', sprintf('%s %s - %s', $del_medicine->m_name, $del_medicine->m_strength, qtyTextClass($old_data[$d_id]['m_qty'], $del_medicine)));
                    }
                }
            }
        }
        if ($o_is_status && (!$order->o_is_status || 'solved' === $order->o_is_status)) {
            $order->update(['o_is_status' => $o_is_status]);
        }
        OMedicine::insert('t_o_medicines', $insert);

        setInternalOrderStatus($order);

        return true;
    }
}
if (!function_exists('setInternalOrderStatus')) {
    function setInternalOrderStatus($order)
    {
        if (!$order || 'confirmed' != $order->o_status || !in_array($order->o_i_status, ['ph_fb', 'packing'])) {
            return false;
        }
        $statuses = OMedicine::where('o_id', $order->o_id)->where('m_qty', '>', 0)->get();

        if ('ph_fb' == $order->o_i_status) {
            if (count($statuses) === 1 && 'available' == $statuses[0]) {
                $order->update(['o_i_status' => 'packing']);
            }
        } elseif ('packing' == $order->o_i_status) {
            if (count($statuses) > 1 || 'available' != $statuses[0]) {
                $order->update(['o_i_status' => 'ph_fb']);
            }
        }
    }
}

if (!function_exists('modifyPrescriptionsImages')) {
    function modifyPrescriptionsImages($o_id, $images)
    {
        if (!$o_id || !is_array($images)) {
            return false;
        }

        if (!($order = Order::getOrder($o_id))) {
            return false;
        }

        // Instantiate an Amazon S3 client.
        $s3 = getS3();

        $s3_key_prefix = \sprintf('order/%s/%s-', \floor($o_id / 1000), $o_id);

        if ($images && is_array($images)) {
            $imgArray = [];
            $limit    = 8;
            $i        = 1;
            foreach ($images as $file) {
                if (!$limit) {
                    break;
                }
                if (!$file || !is_array($file)) {
                    continue;
                }
                if (0 === strpos($file['src'], 'http')) {
                    if (!empty($file['s3key']) && strpos($file['s3key'], $s3_key_prefix) !== false /* && $s3->doesObjectExist( Functions::getS3Bucket(), $file['s3key'] ) */) {
                        array_push($imgArray, $file['s3key']);
                        $limit--;
                    }
                } else {
                    $mime = @mime_content_type($file['src']);
                    if (!$mime) {
                        continue;
                    }
                    $ext = strtolower(explode('/', $mime)[1]);
                    if (!$ext || !in_array($ext, ['jpg', 'jpeg', 'png'])) {
                        continue;
                    }

                    $s3key = \sprintf('%s%s.%s', $s3_key_prefix, $i++ . randToken('alnumlc', 12), $ext);

                    $imgstring = trim(explode(',', $file['src'])[1]);
                    $imgstring = str_replace(' ', '+', $imgstring);
                    if (strlen($imgstring) < 12 || strlen($imgstring) > 10 * 1024 * 1024) {
                        continue;
                    }
                    // Upload file. The file size are determined by the SDK.
                    try {
                        $s3->putObject([
                            'Bucket'      => getS3Bucket(),
                            'Key'         => $s3key,
                            'Body'        => base64_decode($imgstring),
                            'ContentType' => $mime,
                        ]);
                        //$s3->upload( Functions::getS3Bucket(), $s3key, base64_decode( $imgstring ) );
                        array_push($imgArray, $s3key);
                        $limit--;
                    } catch (S3Exception $e) {
                        error_log($e->getAwsErrorMessage());
                        continue;
                    }
                }
            }
            if ($imgArray) {
                $order->setMeta('prescriptions', $imgArray);
                $oldMeta = Meta::get('user', $order->u_id, 'prescriptions');
                Meta::set('user', $order->u_id, 'prescriptions', ($oldMeta && is_array($oldMeta)) ? array_unique(array_merge($imgArray, $oldMeta)) : $imgArray);
            }
        }
        return true;
    }
}


if (!function_exists('getOrderPicUrlsAdmin')) {
    function getOrderPicUrlsAdmin($o_id, $s3keys)
    {
        $imgArray = [];
        if ($s3keys && is_array($s3keys)) {
            foreach ($s3keys as $s3key) {
                array_push($imgArray, [
                    'title' => $o_id,
                    's3key' => $s3key,
                    'src'   => getPresignedUrl($s3key),
                ]);
            }
        }
        return $imgArray;
    }
}

if (!function_exists('reOrder')) {
    function reOrder($o_id)
    {
        if (!$o_id || !($order = Order::getOrder($o_id))) {
            return false;
        }
        if (!$order->u_id || !($user = User::getUser($order->u_id))) {
            return false;
        }

        $d_code        = (string)$order->getMeta('d_code');
        $s_address     = $order->getMeta('s_address') ?: [];
        $prescriptions = $order->getMeta('prescriptions');
        $medicineQty   = $order->medicineQty;

        $discount = Discount::getDiscount($d_code);

        if (!$discount || !$discount->canUserUse($user->u_id)) {
            $d_code = '';
        }

        $cart_data = cartData($user, $medicineQty, $d_code, null, false, ['s_address' => $s_address]);

        if (isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
            $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
        }
        if (isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
            $cart_data['additions']['delivery']['info'] =
                str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
        }
        $c_medicines = $cart_data['medicines'];
        unset($cart_data['medicines']);

        $o_data                = $order->toArray();
        $o_data['o_subtotal']  = $cart_data['subtotal'];
        $o_data['o_addition']  = $cart_data['a_amount'];
        $o_data['o_deduction'] = $cart_data['d_amount'];
        $o_data['o_total']     = $cart_data['total'];
        $o_data['o_delivered'] = '0000-00-00 00:00:00';
        $o_data['o_status']    = 'processing';
        $o_data['o_i_status']  = 'processing';
        $o_data['o_is_status'] = '';
        $o_data['o_priority']  = false;
        if (!in_array($o_data['o_payment_method'], ['cod', 'online'])) {
            $o_data['o_payment_method'] = 'online';
        }

        $newOrder = new Order;
        $newOrder->insert($o_data);
        ModifyOrderMedicines($newOrder, $c_medicines);
        $meta = [
            'o_data'    => $cart_data,
            'o_secret'  => randToken('alnumlc', 16),
            's_address' => $s_address,
            'd_code'    => $d_code,
        ];

        $imgArray = [];
        if ($prescriptions && is_array($prescriptions)) {
            $i = 1;
            foreach ($prescriptions as $prescription_s3key) {
                $array    = explode('.', $prescription_s3key);
                $ext      = end($array);
                $fileName = \sprintf('%s-%s.%s', $newOrder->o_id, $i++ . randToken('alnumlc', 12), $ext);

                $s3key = uploadToS3($newOrder->o_id, '', 'order', $fileName, '', $prescription_s3key);
                if ($s3key) {
                    array_push($imgArray, $s3key);
                }
            }
        }
        if (count($imgArray)) {
            $meta['prescriptions'] = $imgArray;
        }
        $newOrder->insertMetas($meta);
        $newOrder->addHistory('Created', 'Created through re-order');

        $cash_back = $newOrder->cashBackAmount();

        //again get user. User data may changed.
        $user = User::getUser($newOrder->u_id);

        if ($cash_back) {
            $user->u_p_cash = $user->u_p_cash + $cash_back;
        }
        if (isset($cart_data['deductions']['cash'])) {
            $user->u_cash = $user->u_cash - $cart_data['deductions']['cash']['amount'];
        }
        $user->update();

        return $newOrder;
    }
}


if (!function_exists('qtyTextClass')) {
    function qtyTextClass($qty, $medicine)
    {
        if (!$medicine) {
            return '';
        }
        if ($medicine->m_form == $medicine->m_unit) {
            $s = ($qty === 1) ? '' : 's';
            return $qty . ' ' . $medicine->m_unit . $s;
        }
        if ($medicine->m_unit == 10 . ' ' . $medicine->m_form . 's') {
            return $qty * 10 . ' ' . $medicine->m_form . 's';
        }

        return $qty . 'x' . $medicine->m_unit;
    }
}


if (!function_exists('checkOrdersForInventory')) {
    function checkOrdersForInventory($ph_m_ids)
    {
        DB::db()->beginTransaction();
        try {
            if ($ph_m_ids) {
                foreach ($ph_m_ids as $ph_id => $m_ids) {

                    $om = DB::table('t_o_medicines')
                        ->innerJoin('t_orders', 't_orders.o_id', '=', 't_o_medicines.o_id')
                        ->orderBy('t_orders.o_id', 'desc')
                        ->where('t_o_medicines.m_id', $m_ids)
                        ->where('t_o_medicines.om_status', '=', ['pending', 'later'])
                        ->where('t_o_medicines.o_ph_id', $ph_id)
                        ->where('t_o_medicines.o_status', '=', ['confirmed', 'delivering'])
                        ->where('t_o_medicines.o_i_status', '=', ['ph_fb', 'confirmed'])
                        ->get();

                    while ($om) {
                        if (($inventory =
                                Inventory::getByPhMid($ph_id, $om['m_id'])) && $inventory->i_qty >= $om['m_qty']) {
                            $inventory->i_qty = $inventory->i_qty - $om['m_qty'];
                            $inventory->update();
                            DB::instance()->update('t_o_medicines', ['om_status' => 'available', 's_price' => $inventory->i_price], ['om_id' => $om['om_id']]);
                        }
                    }
                }
            }

            DB::db()->commit();
        } catch (\PDOException $e) {
            DB::db()->rollBack();
            \error_log($e->getMessage());
            //Response::instance()->sendMessage( 'Something wrong, Please try again.' );
        }
    }
}


if (!function_exists('checkOrdersForPacking')) {
    function checkOrdersForPacking()
    {
        $om           = DB::table('t_o_medicines')
            ->innerJoin('t_orders', 't_orders.o_id', '=', 't_o_medicines.o_id')
            ->where('t_o_medicines.o_status', '=', 'confirmed')
            ->where('t_o_medicines.o_i_status', '=', 'ph_fb')
            ->where('t_o_medicines.m_qty', 0)
            ->get();
        $oid_statuses = [];
        while ($om) {
            $oid_statuses[$om['o_id']][] = $om['om_status'];
        }
        $confirmed_orders = [];
        foreach ($oid_statuses as $o_id => $statuses) {
            if (count($statuses) === 1 && 'available' == $statuses[0]) {
                $confirmed_orders[] = $o_id;
            }
        }
        $confirmed_orders = array_filter(array_unique($confirmed_orders));

        if (count($confirmed_orders) > 1000) {
            $confirmed_orders = array_slice($confirmed_orders, 0, 1000);
        }
        foreach ($confirmed_orders as $o_id) {
            if ($order = Order::getOrder($o_id)) {
                $order->update(['o_i_status' => 'packing']);
            }
        }
    }
}

if (!function_exists('getLedgerFiles')) {
    function getLedgerFiles($images)
    {
        if (!$images || !is_array($images)) {
            return [];
        }
        foreach ($images as $key => $image) {
            $images[$key]['src'] = getPresignedUrl($image['s3key']);
        }
        return $images;
    }
}

if (!function_exists('qtyText')) {
    function qtyText( $qty, $medicine ) {
        if( !$medicine ){
            return '';
        }
        if( $medicine['form'] == $medicine['unit'] ) {
            $s = ( $qty === 1 ) ? '' : 's';
            return $qty . ' ' . $medicine['unit'] . $s;
        }
        if( $medicine['unit'] == 10 . ' ' . $medicine['form'] . 's' ) {
            return $qty*10 . ' ' . $medicine['form'] . 's';
        }

        return $qty . 'x' . $medicine['unit'];
    }
}

if (!function_exists('modifyLedgerFiles')) {
    function modifyLedgerFiles($l_id, $ledgerFIles)
    {
        if (!$l_id || !is_array($ledgerFIles)) {
            return false;
        }
        // Instantiate an Amazon S3 client.
        $s3 = getS3();

        $s3_key_prefix = \sprintf('ledger/%s/%s-', \floor($l_id / 1000), $l_id);

        if ($ledgerFIles && is_array($ledgerFIles)) {
            $imgArray = [];
            foreach ($ledgerFIles as $file) {
                if (!$file || !is_array($file)) {
                    continue;
                }
                if (0 === strpos($file['src'], 'http')) {
                    if (!empty($file['s3key']) && strpos($file['s3key'], $s3_key_prefix) !== false && $s3->doesObjectExist(getS3Bucket(), $file['s3key'])) {
                        array_push($imgArray, [
                            'title' => $file['title'],
                            's3key' => $file['s3key']
                        ]);
                    }
                } else {
                    $mime = @mime_content_type($file['src']);
                    if (!$mime) {
                        continue;
                    }
                    $ext = explode('/', $mime)[1];
                    if (!$ext || !in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'pdf'])) {
                        continue;
                    }
                    $name  = preg_replace('/[^a-zA-Z0-9\-\._]/', '-', $file['title']);
                    $name  = explode('.', $name)[0];
                    $name  = trim(preg_replace('/-+/', '-', $name), '-');
                    $s3key = \sprintf('%s%s-%s.%s', $s3_key_prefix, $name, randToken('alnumlc', 4), $ext);

                    //$imgstring = trim( str_replace("data:{$mime};base64,", "", $file['src'] ) );
                    $imgstring = trim(explode(',', $file['src'])[1]);
                    $imgstring = str_replace(' ', '+', $imgstring);
                    if (strlen($imgstring) > 10 * 1024 * 1024) {
                        continue;
                    }
                    // Upload file. The file size are determined by the SDK.
                    try {
                        $s3->putObject([
                            'Bucket'      => getS3Bucket(),
                            'Key'         => $s3key,
                            'Body'        => base64_decode($imgstring),
                            'ContentType' => $mime,
                        ]);
                        array_push($imgArray, [
                            'title' => $file['title'],
                            's3key' => $s3key
                        ]);
                    } catch (S3Exception $e) {
                        error_log($e->getAwsErrorMessage());
                        continue;
                    }
                }
            }
            if ($imgArray) {
                DB::instance()->update('t_ledger', ['l_files' => maybeJsonEncode($imgArray)], ['l_id' => $l_id]);
            }
        }

        return true;
    }
}

if (!function_exists('uploadToS3')) {
    function uploadToS3($id, $file, $folder = 'order', $fileName = '', $mime = '', $prevS3key = '')
    {
        $s3keyReturn = '';
        try {
            $s3key = sprintf('%s/%d/%s', $folder, \floor($id / 1000), $fileName ?: basename($file));
            if ($prevS3key) {
                Storage::disk('s3')->copy($prevS3key, $s3key);
                $s3keyReturn = $s3key;
            } elseif ($file) {
                Storage::disk('s3')->put($s3key, file_get_contents($file));
                $s3keyReturn = $s3key;
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
        return $s3keyReturn;
    }
}


if (!function_exists('sendAsyncNotification')) {
    function sendAsyncNotification($client, $fcm_token, $title, $message, $extraData = [])
    {
        if (!$fcm_token || !$title || !$message) {
            return false;
        }
        // if( !MAIN ) {
        //     return false;
        // }
        try {
            $promise = $client->postAsync('https://fcm.googleapis.com/fcm/send',
                ['body' => json_encode([
                    'notification' => [
                        'title' => $title,
                        'body'  => $message,
                        'sound' => 'default',
                        'badge' => '1',
                        //'icon' => 'https://api.arogga.com/static/icon.png',
                        //'image' => 'https://api.arogga.com/static/logo.png',
                    ],
                    'data'         => [
                        'title'     => $title,
                        'body'      => $message,
                        'extraData' => $extraData,
                    ],
                    'to'           => $fcm_token
                ])]
            );
            return $promise;
        } catch (\Exception $e) {
            return false;
        }
    }
}

if (!function_exists('url')) {
    function url(...$args)
    {
        if (is_array($args[0])) {
            if (count($args) < 2 || false === $args[1]) {
                $uri = SITE_URL . $_SERVER['REQUEST_URI'];
            } else {
                $uri = $args[1];
            }
        } else {
            if (count($args) < 3 || false === $args[2]) {
                $uri = SITE_URL . $_SERVER['REQUEST_URI'];
            } else {
                $uri = $args[2];
            }
        }

        $frag = strstr($uri, '#');
        if ($frag) {
            $uri = substr($uri, 0, -strlen($frag));
        } else {
            $frag = '';
        }

        if (0 === stripos($uri, 'http://')) {
            $protocol = 'http://';
            $uri      = substr($uri, 7);
        } elseif (0 === stripos($uri, 'https://')) {
            $protocol = 'https://';
            $uri      = substr($uri, 8);
        } else {
            $protocol = '';
        }

        if (strpos($uri, '?') !== false) {
            list($base, $query) = explode('?', $uri, 2);
            $base .= '?';
        } elseif ($protocol || strpos($uri, '=') === false) {
            $base  = $uri . '?';
            $query = '';
        } else {
            $base  = '';
            $query = $uri;
        }

        parse_str($query, $qs);
        //$qs = urlencode_deep( $qs ); // This re-URL-encodes things that were already in the query string.
        if (is_array($args[0])) {
            foreach ($args[0] as $k => $v) {
                $qs[$k] = $v;
            }
        } else {
            $qs[$args[0]] = $args[1];
        }

        foreach ($qs as $k => $v) {
            if (false === $v) {
                unset($qs[$k]);
            }
        }

        $ret = http_build_query($qs);
        $ret = trim($ret, '?');
        $ret = preg_replace('#=(&|$)#', '$1', $ret);
        $ret = $protocol . $base . $ret . $frag;
        $ret = rtrim($ret, '?');
        return $ret;
    }
}




if (!function_exists('getCategories')) {
     function getCategories(){
        $cache= new Cache();
        if ( $cache_data = $cache->get( 'categories' ) ){
            return $cache_data;
        }
      
        // $query = DB::db()->prepare( 'SELECT c_id, c_name FROM t_categories ORDER BY c_order' );
        $data = DB::table('t_categories')->orderBy('c_order')->get();

        $cache->set( 'categories', $data );

        return $data;
    }
}