<?php

namespace App\Http\Controllers;

use App\Cache\Cache;
use App\Models\GenericV1;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
//use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\Bag;
use App\Models\Company;
use App\Models\Option;
use App\Models\GenericV2;
use App\Models\Medicine;
use App\Models\Token;
use App\Models\User;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OMedicine;
use App\Models\Log;
use App\Models\CacheUpdate;
class PaymentResponseController extends Controller
{
    public function home($o_id, $o_token, $method = '' ){
        if( ! env('MAIN') ){
            //$this->output( 'Error', 'This is a test site, can not pay here.' );
        }

        //$this->output( 'Error', 'Due to technical difficulty we cannot accept your online payment now. Please try again later or pay Cash On Delivery to our delivery man.' );

        //$_GET['method'] = 'fosterPayment';
        /*$this->proceed( $o_id, $o_token, 'fosterPayment' );
        exit;*/
        /*if( ! $o_id || ! $o_token ){
            $this->output( 'Error', 'No id or secret provided' );
        }*/
        $order = getOrder( $o_id );
        /*if( ! $order || ! $order->validateToken( $o_token ) ){
            $this->output( 'Error', 'No Order found' );
        }*/
        /*if( ! \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) ){
            $this->output( 'Error', 'You cannot pay for this order.' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            $this->output( 'Success', 'You have already paid for this order.' );
        }*/
        
//        if( $method ){
//            $this->proceed( $o_id, $o_token, $method );
//        }
        ob_start();
        ?>
        <div>
            <div>
                <div><a title="Nagad" href="#"><img src="<?php echo asset('/nagad-logo.png'); ?>" alt="" style="border:1px solid black; width: 90%; max-width: 300px" /></a></div>
                <div><a title="bKash" href="#"><img src="<?php asset( '/bKash-logo.png'); ?>" alt="" style="border:1px solid black; width: 90%; max-width: 300px" /></a></div>
                <div><a title="fosterPayment" href="#"><img src="<?php echo asset( '/visa-master-logo.png'); ?>" alt="" style="border:1px solid black; width: 90%; max-width: 300px" /></a></div>
            </div>
        </div>
        <?php

        $this->output( 'Payment', '', ob_get_clean() );

    }


    public function callback( $method ){
        $order_id = $_GET['order_id'] ?? '';
        $o_id = (int)$order_id;

        if( ! $method || ! $o_id ){
            $this->output( 'Error', 'No id provided' );
        }
        $order = getOrder( $o_id );
        if( ! $order ){
            $this->output( 'Error', 'No Order found' );
        }
        if( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ){
            $this->output( 'Success', 'You have already paid for this order.' );
        }
        $output = [];
        switch ($method) {
            case 'nagad':
                $output = Nagad::instance()->callback( $order );
                break;
            default:
                # code...
                break;
        }
        $this->output( $output['title']??'', $output['heading']??'', $output['body']??'' );
    }

    public function output( $title, $heading, $body = '' ){
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US" xml:lang="en-US">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo $title . ' - Arogga'; ?></title>
        </head>
        <body>
        <div style="text-align: center;">
            <h3 id="heading"><?php echo $heading; ?></h3>
            <?php if( $body ){
                echo "<div id='body'>$body</div>";
            } ?>
            <?php if( 'Success' == $title ){ ?>
                <script>
                    setTimeout(function(){
                        window.location.href = "https://www.arogga.com/account#orders";
                    }, 5000);
                </script>
            <?php } ?>
        </div>
        </body>
        </html>
        <?php
        exit;
    }


}
