<?php

namespace App\Http\Controllers;

use App\Cache\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Bag;
use App\Models\Company;
use App\Models\Option;
use App\Models\GenericV1;
use App\Models\GenericV2;
use App\Models\Medicine;
use App\Models\Token;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OMedicine;
use App\Models\Log;
use App\Models\CacheUpdate;
use App\Models\Collection;
use App\Models\History;
use App\Models\Inventory;
use App\Models\Meta;
use App\Models\Purchase;
use App\Models\Ledger;
use App\Models\Location;
use Illuminate\Support\Facades\Redis;
use GuzzleHttp\Client;
class PartnerResponseController extends Controller
{
    public function locationData(){
        (new RouteResponseController)->locationData();
     }
    

    public function orders(Request $request) {
        $per_page = 10;

        $status =!empty( $request->o_status  ) ? $request->o_status  : 'all';
        $page = !empty( $request->page ) ? $request->page : 1;
        $u_id = !empty( $request->u_id ) ? $request->u_id : 0;

    

        // $db = new DB;

        // $db->add( 'SELECT tr.* FROM t_orders tr INNER JOIN t_order_meta tm ON tr.o_id = tm.o_id AND tm.meta_key = ? WHERE tm.meta_value = ?', 'partner', Auth::id() );
       
        // if ( 'all' !== $status ) {
        //     $db->add( ' AND tr.o_status = ?', $status );
        // }
        // if ( $u_id ) {
        //     $db->add( ' AND tr.u_id = ?', $u_id );
        // }
        // $db->add( ' ORDER BY tr.o_id DESC' );
        // $db->add( ' LIMIT ?, ?', $limit, $per_page );
        
        // $query = $db->execute();
        // $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');

        $limit    = $per_page * ( $page - 1 );
        $order = DB::table('t_orders')
        ->innerJoin('t_order_meta', 't_orders.o_id', '=', 't_order_meta.o_id')
        ->where('t_order_meta.meta_value','=' ,'partner')
        ->where('t_order_meta.meta_key', Auth::id())
        ->limit($per_page)
        ->offset($limit)
        ->get();


        while( $order){
            $data = $order->toArray();
            $data['o_address'] = $data['o_gps_address'];
            unset( $data['o_gps_address'], $data['o_i_status'] );
            //TO-DO: Later queue all ids then get meta so that it will use one query
            $data['o_note'] = (string)$order->getMeta('o_note');
            $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';

            return response()->json([
                ''=>$data
            ]);
        }
        if ( ! $order ) {
            return response()->json( 'No Orders Found' );
        } else {
            return response()->json( 'success' );
        }
    }



    public function orderCreate(Request $request) {
        //Response::instance()->sendMessage( "Dear valued clients.\nOur Dhaka city operation will resume from 29th November 2020.\nThanks for being with Arogga.");
        //Response::instance()->sendMessage( "Due to some unavoidable circumstances we cannot take orders now. We will send you a notification once we start taking orders.\nSorry for this inconvenience.");
        //Response::instance()->sendMessage( "Due to covid19 outbreak, there is a severe short supply of medicine.\nUntil regular supply of medicine resumes, we may not take anymore orders.\nSorry for this inconvenience.");
        //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPls try after 24 hours. We will be back!!");
        //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPlease try again after 2nd Jun, 11PM. We will be back!!");
        //Response::instance()->sendMessage( "Due to recent coronavirus outbreak, we are facing delivery man shortage.\nOnce our delivery channel is optimised, we may resume taking your orders.\nThanks for your understanding.");
        //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after EID. We will be back!!");
        //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after 28th May, 10PM. We will be back!!");

        $medicines = $request->medicines  && is_array( $request->medicines  )  ?  $request->medicines  : [];
        $d_code = $request->d_code ?  $request->d_code : '';
        $prescriptions =$request->prescriptions ? $request->prescriptions : [];
        $prescriptions_urls =$request->prescriptions_urls && is_array($request->prescriptions_urls) ? $request->prescriptions_urls : [];

        $name =$request->name ?  filter_var($request->name, FILTER_SANITIZE_STRING) : '';
        $mobile =$request->mobile ?  filter_var($request->mobile, FILTER_SANITIZE_STRING) : '';
        $gps_address = '';
        $s_address = $request->s_address  && is_array($request->s_address)? $request->s_address : [];
        $monthly = !empty($request->monthly ) ?  1 : 0;
        $payment_method = $request->payment_method && in_array($request->payment_method, ['cod', 'online']) ? $request->payment_method : 'cod';

        if ( ! $name ){
            return response()->json( 'name required.');
        }
        if ( ! $mobile ){
            return response()->json( 'Mobile number required.');
        }
        if( ! ( $mobile = checkMobile( $mobile ) ) ) {
            return response()->json( 'Invalid mobile number.');
        }
        $user = User::getBy( 'u_mobile', $mobile );
        if( !$user ){
            $user = new User;
            $user->u_name = $name;
            $user->u_mobile = $mobile;

            do{
                $u_referrer = randToken( 'distinct', 6 );

            } while( User::getBy( 'u_referrer', $u_referrer ) );

            $user->u_referrer = $u_referrer;
            $user->insert();
        }


        if ( ! $s_address ){
            return response()->json( 'Address is required.');
        }
        if ( ! isLocationValid( @$s_address['division'], @$s_address['district'], @$s_address['area'] ) ){
            return response()->json( 'invalid location.');
        }
        if( $s_address ){
            $s_address['location'] = sprintf('%s, %s, %s, %s', $s_address['homeAddress'], $s_address['area'], $s_address['district'], $s_address['division'] );
            $gps_address = $s_address['location'];
        }

        if ( ! $medicines && ! $prescriptions && ! $prescriptions_urls ){
            return response()->json( 'medicines or prescription are required.');
        }
        if ( $medicines && ! is_array( $medicines ) ){
            return response()->json( 'medicines need to be an array with id as key and quantity as value.');
        }
        if ( $prescriptions && ! is_array( $prescriptions ) ){
            return response()->json( 'prescription need to be an file array.');
        }

        $discount = Discount::getDiscount( $d_code );

        if( ! $discount || ! $discount->canUserUse( $user->u_id ) ) {
            $d_code = '';
        }
        if ( !$user->u_name && $name ) {
            $user->u_name = $name;
        }
        if ( ! $user->u_mobile && $mobile ) {
            $user->u_mobile = $mobile;
        }

        $files_to_save = [];
        if ( $prescriptions ) {
            if ( empty( $prescriptions['tmp_name'] ) || ! is_array( $prescriptions['tmp_name'] ) ) {
                return response()->json( 'prescription need to be an file array.');
            }
            if ( count( $prescriptions['tmp_name'] ) > 5 ) {
                return response()->json( 'Maximum 5 prescription pictures allowed.');
            }
            $i = 1;
            foreach( $prescriptions['tmp_name'] as $key => $tmp_name ) {
                if( $i > 5 ){
                    break;
                }
                if( ! $tmp_name ) {
                    continue;
                }
                if ( UPLOAD_ERR_OK !== $prescriptions['error'][$key] ) {
                    return response()->json( \sprintf('Upload error occured when upload %s. Please try again', \strip_tags( $prescriptions['name'][$key] ) ) );
                }
                $size = \filesize( $tmp_name );
                if( $size < 12 ) {
                    return response()->json( \sprintf('File %s is too small.', \strip_tags( $prescriptions['name'][$key] ) ) );
                } elseif ( $size > 10 * 1024 * 1024 ) {
                    return response()->json( \sprintf('File %s is too big. Maximum size is 10MB.', \strip_tags( $prescriptions['name'][$key] ) ) );
                }
                $imagetype = exif_imagetype( $tmp_name );
                $mime      = ( $imagetype ) ? image_type_to_mime_type( $imagetype ) : false;
                $ext       = ( $imagetype ) ? image_type_to_extension( $imagetype ) : false;
                if( ! $ext || ! $mime ) {
                    return response()->json( 'Only prescription pictures are allowed.');
                }
                $files_to_save[ $tmp_name ] = ['name' => $i++ . randToken( 'alnumlc', 12 ) . $ext, 'mime' => $mime ];
            }
        }

        $cart_data = cartData( $user, $medicines, $d_code, null, false, ['s_address' => $s_address] );
        if ( ! empty( $cart_data['rx_req'] ) && ! $files_to_save ) {
            return response()->json( 'Rx required.');
        }
        if( isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
            $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
        }
        if( isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
            $cart_data['additions']['delivery']['info'] = str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
        }
        $c_medicines = $cart_data['medicines'];
        unset( $cart_data['medicines'] );

        $order = new Order;
        $order->u_id = $user->u_id;
        $order->u_name = $user->u_name;
        $order->u_mobile = $user->u_mobile;
        $order->o_subtotal = $cart_data['subtotal'];
        $order->o_addition = $cart_data['a_amount'];
        $order->o_deduction = $cart_data['d_amount'];
        $order->o_total = $cart_data['total'];
        $order->o_status = 'processing';
        $order->o_i_status = 'processing';
        //$order->o_address = $address;
        $order->o_gps_address = $gps_address;
        $order->o_payment_method = $payment_method;


        $order->o_ph_id = 6139;

        if( !isset( $s_address['district'] ) ){
        } elseif( $s_address['district'] != 'Dhaka City' ){
            //Outside Dhaka delivery ID
            $order->o_de_id = 143;
            $order->o_payment_method = 'online';
        } elseif( $d_id = getIdByLocation( 'l_de_id', $s_address['division'], $s_address['district'], $s_address['area'] ) ) {
            $order->o_de_id = $d_id;
        }
        if( isset( $s_address['district'] ) ){
            $order->o_l_id = getIdByLocation( 'l_id', $s_address['division'], $s_address['district'], $s_address['area'] );
        }
        $user->update();
        $order->insert();
       ModifyOrderMedicines( $order, $c_medicines );
        $meta = [
            'o_data' => $cart_data,
            'o_secret' => randToken( 'alnumlc', 16 ),
            's_address' => $s_address,
            'partner' => $this->user->u_id,
            'from' => 'partner',
            'o_i_note' => sprintf( 'Created through %s', $this->user->u_name ),
        ];
        if( $d_code ) {
            $meta['d_code'] = $d_code;
        }
        if( $monthly ) {
            $meta['subscriptionFreq'] = 'monthly';
        }

        $imgArray = [];
        if ( $files_to_save ) {
            $upload_folder = STATIC_DIR . '/orders/' . \floor( $order->o_id / 1000 );

            if ( ! is_dir($upload_folder)) {
                @mkdir($upload_folder, 0755, true);
            }
            foreach ( $files_to_save as $tmp_name => $file ) {
                $fileName = \sprintf( '%s-%s', $order->o_id, $file['name'] );
                $s3key = uploadToS3( $order->o_id, $tmp_name, 'order', $fileName, $file['mime'] );
                if ( $s3key ){
                    array_push( $imgArray, $s3key );
                }
            }
        } elseif( $prescriptions_urls ){
            $upload_folder = STATIC_DIR . '/orders/' . \floor( $order->o_id / 1000 );

            if ( ! is_dir($upload_folder)) {
                @mkdir($upload_folder, 0755, true);
            }

            $client = new Client(['verify' => false, 'http_errors' => false]);

            $i = 1;
            foreach ( $prescriptions_urls as $prescriptions_url ) {
                if( $i > 5 ){
                    break;
                }
                $filename = $i++ . randToken( 'alnumlc', 12 );
                $new_file = \sprintf( '%s/%s-%s', $upload_folder, $order->o_id, $filename );

                try {
                    $client->request('GET', $prescriptions_url, ['sink' => $new_file]);
                } catch (\Exception $e) {
                    continue;
                }

                $imagetype = exif_imagetype( $new_file );
                $mime      = ( $imagetype ) ? image_type_to_mime_type( $imagetype ) : false;
                $ext       = ( $imagetype ) ? image_type_to_extension( $imagetype ) : false;
                if( ! $ext || ! $mime ) {
                    if( 'application/pdf' === @mime_content_type( $new_file ) ){
                        $imagick = new \Imagick();
                        //$imagick->setResolution(595, 842);
                        $imagick->setResolution(300, 300);
                        //$imagick->setBackgroundColor('white');
                        $imagick->readImage( $new_file );
                        $imagick->setImageFormat('jpeg');
                        $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
                        $imagick->setImageCompressionQuality(82);
                        $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                        $imagick->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
                        $imagick->writeImages( $new_file . '.jpeg', false);
                        $imagick->clear();
                        $imagick->destroy();

                        //@rename( $new_file, $new_file . '.pdf' );
                        $prescriptionArray = glob( $upload_folder . '/' . $order->o_id . "-*.jpeg", GLOB_NOSORT );
                        foreach ( $prescriptionArray as $fileName ) {
                            $s3key = uploadToS3( $order->o_id, $fileName);
                            if ( $s3key ){
                                array_push( $imgArray, $s3key );
                                unlink($fileName);
                            }
                        }
                    } else {
                        @unlink( $new_file );
                        return response()->json( 'Invalid file type.');
                    }
                    @unlink( $new_file );
                } else {
                    $s3key = uploadToS3( $order->o_id, $new_file, 'order', basename( $new_file . $ext ), $mime );
                    if ( $s3key ){
                        array_push( $imgArray, $s3key );
                        unlink($new_file);
                    }
                }

            }
        }

        if ( count($imgArray) ){
            $meta['prescriptions'] = $imgArray ;

            $oldMeta = $user->getMeta( 'prescriptions' );
            $user->setMeta( 'prescriptions', ( $oldMeta && is_array($oldMeta ) ) ? array_merge( $oldMeta, $imgArray ) : $imgArray );
        }
        $order->insertMetas( $meta );
        $order->addHistory( 'Created', sprintf( 'Created through %s', $this->user->u_name ) );

        //Get user again, User data may changed
        $user = User::getUser( $user->u_id );

        $cash_back = $order->cashBackAmount();
        if ( $cash_back ) {
            $user->u_p_cash = $user->u_p_cash + $cash_back;
        }
        
        if( isset($cart_data['deductions']['cash']) ){
            $user->u_cash = $user->u_cash - $cart_data['deductions']['cash']['amount'];
        }
        $user->update();

        $message = 'Order created successfully.';

        return response()->json([
            'status'=>'success',
            'message'=>$message,
            'o_id'=>$order->o_id,
            'u_id'=>$user->u_id,
        ]);
    }


    function orderSingle( $o_id ) {
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            return response()->json( 'No orders found.' );
        }

        if( Auth::id() != $order->getMeta('partner') ){
            return response()->json( 'No orders found.' );
        }
        
        $data = $order->toArray();
        $data['prescriptions'] = $order->prescriptions;
        $data['o_data'] = (array)$order->getMeta( 'o_data' );
        $data['o_data']['medicines'] = $order->medicines;
        $data['s_address'] = $order->getMeta('s_address')?:[];

        $data['invoiceUrl'] = $order->signedUrl( '/v1/invoice' );
        $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';
        if( \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) && \in_array( $order->o_i_status, ['packing', 'checking', 'confirmed'] ) && 'paid' !== $order->getMeta( 'paymentStatus' ) ){
            $data['paymentUrl'] = $order->signedUrl( '/payment/v1' );
        }

        return response()->json([
            'status'=>'success',
            'data'=>$data,
        ]);
    }



    function userSingle( $mobile ) {
        $mobile = checkMobile( $mobile );
        if( ! $mobile ) {
            return response()->json( 'Invalid mobile number.');
        }
        $user = User::getBy( 'u_mobile', $mobile );
        if( ! $user ){
            return response()->json( 'No users found.' );
        }
        $data = [
            'u_id' => $user->u_id,
            'u_name' => $user->u_name,
            'u_mobile' => $user->u_mobile,
        ];
        
        return response()->json([
            'status'=>'success',
            'data'=>$data,
        ]);
    }
     





}
