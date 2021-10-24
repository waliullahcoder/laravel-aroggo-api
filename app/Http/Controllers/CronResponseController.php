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
use App\Models\LogBackup;
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
class CronResponseController extends Controller
{
    function daily( $type = '' ){
        switch ( $type ) {
            case 'dumpLog':
                $this->dumpLog();
                break;
            case 'dumpLogToS3':
                $this->dumpLogToS3();
                break;
            case 'reOrder':
                $this->reOrder();
                break;
            case 'updateWeeklyRequirements':
                $this->updateWeeklyRequirements();
                break;
            
            default:
                # code...
                break;
        }
        $this->die();
    }
    function hourly( $type = '' ){
        switch ( $type ) {
            case 'notifyPayment':
                $this->notifyPayment();
                break;
            case 'cancelOrders':
                $this->cancelOrders();
                break;
        
            default:
                # code...
                break;
        }
        $this->die();
    }

    function halfhourly( $type = '' ){
        switch ( $type ) {
            case 'updateLaterMedicines':
                $this->updateLaterMedicines();
                break;

            default:
                # code...
                break;
        }
        $this->die();
    }


    public function updateLaterMedicines(){
        $datas=DB::table('t_o_medicines')
        ->innerJoin('t_orders', 't_orders.o_id', '=', 't_o_medicines.o_id')
        ->where('t_orders.o_status','=', 'processing')
        ->where('t_orders.o_i_status','=', 'confirmed')
        ->where('t_order_meta.om_status','=', 'later')
        ->get()->sum('m_qty');

        if( !$datas ){
            $query = DB::table( 't_later_medicines' )->get();
            $query->delete();
            return true;
        }

        DB::instance()->insertMultiple( 't_later_medicines', $datas, true );

        $ph_m_ids = [];
        foreach ( $datas as $data ){
            $ph_m_ids[ $data['o_ph_id'] ][] = $data['m_id'];
        }

        foreach ( $ph_m_ids as $ph_id => $m_ids ){
            $query = DB::table('t_later_medicines')->where('o_ph_id', $ph_id)->where('m_id', $m_ids)->get();
        }


        $query = DB::table('t_later_medicines')->where('o_ph_id', $ph_id)->get();
        $query->delete();

        return true;
    }

    public function cancelOrders(){

        $orders=DB::table('t_orders')
        ->innerJoin('t_order_meta', 't_orders.o_id', '=', 't_order_meta.o_id')
        ->innerJoin('t_locations', 't_locations.o_l_id', '=', 't_locations.l_id')
        ->where('t_orders.o_status','=', 'paymentStatus')
        ->where('t_orders.o_payment_method','=', 'confirmed')
        ->where('t_order_meta.meta_key','=', 'online')
        ->where('t_locations.l_district','=', 'Dhaka City')
        ->where('t_order_meta.meta_key','=', 'paymentEligibleTime')
        ->get();

        if( ! $orders ){
            return false;
        }

        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        CacheUpdate::add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::update_cache( [], 'order_meta' );

        $this->response = $o_ids;

        foreach($orders as $order){
            $order->setMeta( 'o_note', 'Order cancelled automatically due to no payment within 3 days.' );
            $order->update(['o_status' => 'cancelled']);
        }
    }


    public function notifyPayment(Request $request){
        $page = ! empty( $request->_page ) ? $request->_page : 1;
        $perPage = 1000;
        $limit    = $perPage * ( $page - 1 );
        $orders=DB::table('t_orders')
        ->innerJoin('t_order_meta', 't_orders.o_id', '=', 't_order_meta.o_id')
        ->innerJoin('t_locations', 't_locations.o_l_id', '=', 't_locations.l_id')
        ->where('t_orders.o_status','=', 'paymentStatus')
        ->where('t_orders.o_payment_method','=', 'confirmed')
        ->where('t_order_meta.meta_key','=', 'online')
        ->where('t_locations.l_district','=', 'Dhaka City')
        ->where('t_order_meta.meta_key','=', 'paymentEligibleTime')
        ->limit($perPage)
        ->offset($limit)
        ->get();

        if( ! $orders ){
            return false;
        }

        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'key='. FCM_SERVER_KEY,
            ],
            'http_errors' => false,
        ]);
        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        $u_ids = array_map(function($o) { return $o->u_id;}, $orders);

        CacheUpdate::add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::add_to_queue( $u_ids , 'user');
        CacheUpdate::update_cache( [], 'order_meta' );
        CacheUpdate::update_cache( [], 'user' );

        $this->response = $o_ids;

        $promises = [];
        foreach($orders as $order){
            $datetime = new \DateTime( $order->getMeta('paymentEligibleTime') );
            $datetime->modify('+3 days');

            $user = User::getUser($order->u_id);
            $promise = sendAsyncNotification($client, $user->fcm_token, 'Payment Due', sprintf( 'Please pay online for order id #%s. Once we receive your payment your order will go out for delivery. If we do not receive payment within %s, this order will automatically be cancelled.', $order->o_id, $datetime->format('F j, Y, g:i a') ) );
            if( $promise ){
                $promises[] = $promise;
            }
        }
        $page++;

        if(count($orders) === $perPage){
            $client2 = new Client([
                'timeout' => 0.2,
                'http_errors' => false,
            ]);
            try {
                $client2->get( url( '_page', $page ) );
            } catch (\Exception $e) {
                //do nothing
            }
        }

        if( $promises ){
            Promise\Utils::settle($promises)->wait();
        }
    }
    public function updateWeeklyRequirements(){
        $today = date("Y-m-d H:i:s");
        $dateRange = date("Y-m-d H:i:s",strtotime('-10 weeks', strtotime($today)));

        DB::table('t_inventory')
        ->innerJoin('t_o_medicines', 't_o_medicines.m_id', '=', 't_inventory.o_ph_id')
        ->innerJoin('t_orders', 't_orders.o_id', '=', 't_inventory.o_id')
        ->where('o_status','=', 'delivered')
        ->where('wkly_req', $dateRange)
        ->where('wkly_req', $today)
        ->get();

    }
    public function reOrder(Request $request){
        $page = ! empty( $request->_page ) ? $request->_page : 1;
        $perPage = 1000;
        $limit    = $perPage * ( $page - 1 );
        $orders = DB::table('t_orders')
        ->innerJoin('t_order_meta', 't_orders.o_id', '=', 't_order_meta.o_id')
        ->where('t_orders.o_created', '=', 't_order_meta.o_created')
        ->orWhere('t_order_meta.meta_key', '=', 'subscriptionFreq')
        ->where('t_order_meta.meta_value', '=', 'monthly')
        ->limit($perPage)
        ->offset($limit)
        ->get();
        if( ! $orders ){
            return false;
        }

        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        CacheUpdate::add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::update_cache( [], 'order_meta' );

        $client = new Client([
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'key='. FCM_SERVER_KEY,
            ],
            'http_errors' => false,
        ]);
        $promises = [];
        $date = new \DateTime();
        $date->modify("-3 days");

        foreach($orders as $order){
            if( $order->getMeta('lastOrderTime') && new \DateTime( $order->getMeta('lastOrderTime') ) > $date ){
                continue;
            }
            $result = reOrder($order->o_id);
            if( !$result ){
                continue;
            }
            $this->response[] = [
                'old' => $order->o_id,
                'new' => $result->o_id,
            ];
            $o_i_note = $result->getMeta('o_i_note');
            if( $o_i_note ){
                $o_i_note .= "\n";
            }
            $o_i_note .= sprintf( 'Previous order id = %s', $order->o_id);
            $result->setMeta( 'o_i_note', $o_i_note );
            $result->setMeta( 'prevOrder', $order->o_id );

            $o_i_note = $order->getMeta('o_i_note');
            if( $o_i_note ){
                $o_i_note .= "\n";
            }
            $o_i_note .= sprintf( 'New order id = %s', $result->o_id);
            $order->setMeta( 'o_i_note', $o_i_note );
            $order->setMeta( 'lastOrderTime', \date( 'Y-m-d H:i:s' ) );
            $user = User::getUser($order->u_id);
            $promise = sendAsyncNotification($client, $user->fcm_token, 'Monthly Order', "Your monthly order has been placed!", ['screen' => 'Orders', 'btnScreen' => 'SingleOrder', 'btnScreenParams' => ['o_id' => $result->o_id], 'btnLabel' => 'View Order'] );
            if( $promise ){
                $promises[] = $promise;
            }
        }
        $page++;

        if(count($orders) === $perPage){
            $client2 = new Client([
                'timeout' => 0.2,
                'http_errors' => false,
            ]);
            try {
                $client2->get( url( '_page', $page ) );
            } catch (\Exception $e) {
                //do nothing
            }
        }
        if( $promises ){
            Promise\Utils::settle($promises)->wait();
        }
    }

    function dumpLogToS3(){
        
        $last_id=Log::orderBy('log_id','desc')->get();
        if( !$last_id ){
            return null;
        }

        DB::db()->beginTransaction();
        try {
            $output = fopen('php://memory', 'w+');
            $i=0;
            $log=Log::orderBy('log_id','<=', $last_id)->get();
            while( $log){
                if ($i==0){
                    fputcsv($output, array_keys($log));
                    $i++;
                }
                fputcsv($output, $log);
            }
            rewind($output);

            $s3 = getS3();
            $fileName = sprintf( 'apiLogs/%d/log_%d.csv', \date('Y'), \date('YmdHis') );
            $s3->putObject([
                'Bucket' => getS3Bucket(),  
                'Key' => $fileName,
                'Body' => stream_get_contents($output),
                'ContentType' => 'text/csv',
            ]);

            fclose($output);

            $log=Log::orderBy('log_id','<=', $last_id)->get();
            DB::db()->commit();
        } catch(\PDOException $e) {
            $this->response = $e->getMessage();
            DB::db()->rollBack();
        } catch (S3Exception $e) {
            $this->response = $e->getAwsErrorMessage();
            DB::db()->rollBack();
        } catch( \Exception $e ) {
			$this->response = $e->getMessage();
		}
    }

    
    function dumpLog(){
        $last_id =Log::get()->max('log_id');
        if( !$last_id ){
            return null;
        }
        DB::db()->beginTransaction();
        try {
            $logs=Log::where('log_id','<=', $last_id)->get();
            LogBackup::insert($logs);

            DB::db()->commit();
        } catch(\PDOException $e) {
            DB::db()->rollBack();
        }
    }

   

    private function die(){
        Log::instance()->insert([
            'log_response' => $this->response,
        ]);
        die();
    }
}
