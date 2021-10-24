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
use App\Models\GenericV2;
use App\Models\Medicine;
use App\Models\Token;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OMedicine;
use App\Models\Log;
use App\Models\CacheUpdate;
use App\Models\GenericV1;
use App\Models\Collection;
use GuzzleHttp\Client;
class AdminAppResponseController extends Controller
{
    public function orders(Request $request) {
      
        $status = $request->status ? $request->status : '';
        $page = $request->page ? $request->page : 1;
        $lat = $request->lat ? $request->lat : '';
        $long = $request->long ? $request->long : '';
        $search = $request->search ? $request->search : '';
        $zone = $request->zone ? $request->zone : '';
        $hideOutsideDhaka = $request->hideOutsideDhaka ? $request->hideOutsideDhaka : '';
        $per_page = 10;
        $limit    = $per_page * ( $page - 1 );

        $db = new DB;

        $db->add( 'SELECT SQL_CALC_FOUND_ROWS tr.* FROM t_orders tr' );
        $db->add( ' INNER JOIN t_locations tl ON tr.o_l_id = tl.l_id' );
        $db->add( ' WHERE 1 = 1' );

        // $orders = DB::table('t_orders')
        // ->leftJoin('t_locations', 't_orders.o_id', '=', 't_locations.l_id')
        // ->get();
        
        if ( $zone ){
            $db->add( ' AND tl.l_zone = ?', $zone);
        } elseif ( $hideOutsideDhaka && in_array( $this->user->u_role, [ 'packer', 'pharmacy' ] ) ){
            $db->add( ' AND tl.l_district = ?', 'Dhaka City' );
        }
        if ( $search && \is_numeric( $search ) ) {
            $db->add( ' AND tr.o_id = ?', $search );
        }
        if ( 'pharmacy' == $this->user->u_role ) {
            $db->add( ' AND tr.o_ph_id = ?', $this->user->u_id );
        } elseif( 'delivery' == $this->user->u_role ) {
            $db->add( ' AND tr.o_de_id = ?', $this->user->u_id );
        } elseif( 'packer' == $this->user->u_role ) {
            if( $ph_id = $this->user->getMeta( 'packer_ph_id' ) ){
                $db->add( ' AND tr.o_ph_id = ?', $ph_id );
            }
        }
        if ( \in_array( $status, [ 'ph_new' ] ) ) {
            $db->add( ' AND tr.o_status = ? AND tr.o_i_status = ?', 'confirmed', 'ph_fb' );
        } elseif ( \in_array( $status, [ 'de_new' ] ) ) {
            $db->add( ' AND tr.o_status = ? AND tr.o_i_status IN (?,?,?)', 'confirmed', 'ph_fb', 'packing', 'checking' );
        } elseif ( \in_array( $status, [ 'ph_issue' ] ) ) {
            $db->add( ' AND tr.o_is_status = ?', 'delivered' );
        } elseif ( \in_array( $status, [ 'packing', 'checking' ] ) ) {
            $db->add( ' AND ( ( tr.o_status = ? AND tr.o_i_status = ? ) OR tr.o_is_status = ? )', 'confirmed', $status, $status );
        } elseif ( \in_array( $status, [ 'confirmed' ] ) ) {
            $db->add( ' AND ( tr.o_status = ? OR tr.o_is_status = ? )', $status, 'packed' );
            $db->add( ' AND tr.o_i_status IN (?,?)', 'confirmed', 'paid' );
        } elseif ( \in_array( $status, [ 'delivering', 'delivered' ] ) ) {
            $db->add( ' AND ( tr.o_status = ? OR tr.o_is_status = ? )', $status, $status );
            $db->add( ' AND tr.o_i_status IN (?,?)', 'confirmed', 'paid' );
        } elseif ( $status ) {
            $db->add( ' AND tr.o_status = ?', $status );
            $db->add( ' AND tr.o_i_status IN (?,?)', 'confirmed', 'paid' );
        }
      

        if( 'delivered' == $status ) {
            $db->add( ' ORDER BY tr.o_delivered DESC' );
        } elseif( 'delivering' == $status ) {
            $db->add( ' ORDER BY tr.o_priority DESC, tr.o_id ASC' );
        } else {
            $db->add( ' ORDER BY tr.o_id ASC' );
        }

        $db->add( ' LIMIT ?, ?', $limit, $per_page );
        
        $query = $db->execute();
        $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Order');

        $orders = $query->fetchAll();
        if( ! $orders ){
            return response()->json( 'No Orders Found' );
        }
        $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
        CacheUpdate::add_to_queue( $o_ids , 'order_meta');
        CacheUpdate::update_cache( [], 'order_meta' );

        $laterCount = [];
        if ( 'pharmacy' == $this->user->u_role ) {
            $in  = str_repeat('?,', count($o_ids) - 1) . '?';
            $query2 = DB::db()->prepare( "SELECT o_id, COUNT(m_id) FROM t_o_medicines WHERE o_id IN ($in) AND om_status = ? GROUP BY o_id" );
            $query2->execute([...$o_ids, 'later']);
            $laterCount = $query2->fetchAll( \PDO::FETCH_KEY_PAIR );
        }

        $deliveredCount = [];
        if ( \in_array( $this->user->u_role, [ 'pharmacy', 'delivery' ] ) ) {
            $u_ids = array_map(function($o) { return $o->u_id;}, $orders);
            $u_ids = array_filter( array_unique( $u_ids ) );
            $in  = str_repeat('?,', count($u_ids) - 1) . '?';
            $query2 = DB::db()->prepare( "SELECT u_id, COUNT(o_id) FROM t_orders WHERE u_id IN ($in) AND o_status = ? GROUP BY u_id" );
            $query2->execute([...$u_ids, 'delivered']);
            $deliveredCount = $query2->fetchAll( \PDO::FETCH_KEY_PAIR );
        }

        foreach( $orders as $order ){
            $data = $order->toArray();
            $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';
            $data['o_i_note'] = (string)$order->getMeta('o_i_note');
            $data['cold'] = $order->hasColdItem();

            if ( in_array( $this->user->u_role, [ 'pharmacy', 'packer' ] ) && $order->o_l_id && ( $l_zone = Functions::getZoneByLocationId( $order->o_l_id ) ) ){
                $b_id = $order->getMeta( 'bag' );
                if( $b_id && ( $bag = Bag::getBag( $b_id ) ) ){
                    $data['zone'] = $bag->fullZone();
                } else {
                    $data['zone'] = $l_zone;
                }
            }

            if ( \in_array( $this->user->u_role, [ 'pharmacy' ] ) ) {
                if ( !empty($data['o_de_id']) && ( $user = User::getUser( $data['o_de_id'] ) ) ) {
                    $data['o_de_name'] = $user->u_name;
                }
            }
            if ( 'pharmacy' == $this->user->u_role && isset( $laterCount[ $order->o_id ] ) ) {
                $data['laterCount'] = $laterCount[ $order->o_id ];
            }
            if ( isset( $deliveredCount[ $order->u_id ] ) ) {
                $data['uOrderCount'] = $deliveredCount[ $order->u_id ];
            }
            if ( 'packing' === $order->o_i_status || 'packing' === $order->o_is_status ) {
                $data['packedWrong'] = (bool)$order->getMeta( 'packedWrong' );
            }

            return response()->json([
                'status'=>'success',
                'data'=>$data
            ]);
        }
        if ( ! $data) {
            return response()->json( 'No Orders Found' );
        } else {
            return response()->json([
                'status'=>'success',
                'total'=>$total
            ]);
        }
    }

    public function later(Request $request){
        $page = $request->page ? $request->page : 1;

        $per_page = 20;
        $limit    = $per_page * ( $page - 1 );

    

        $medicines = DB::table('t_later_medicines')
        ->leftJoin('t_medicines', 't_later_medicines.m_id', '=', 't_medicines.m_id')
        ->where('o_ph_id', Auth::id())
        ->where('u_id', Auth::id())
        ->limit($per_page)
        ->offset($limit)
        ->get();
        $total = DB::table('t_later_medicines')
        ->leftJoin('t_medicines', 't_later_medicines.m_id', '=', 't_medicines.m_id')
        ->where('o_ph_id', Auth::id())
        ->where('u_id', Auth::id())
        ->limit($per_page)
        ->offset($limit)
        ->get()->count();

        // $m_ids = array_map(function($d) { return $d['m_id'];}, $medicines);
        // CacheUpdate::add_to_queue( $m_ids , 'medicine_meta');
        // CacheUpdate::update_cache( [], 'medicine_meta' );

        foreach( $medicines as $medicine ){
            $data = [
                'm_id' => $medicine['m_id'],
                'm_text' => $medicine['m_text'],
                'name' => $medicine['m_name'],
                'strength' => $medicine['m_strength'],
                'form' => $medicine['m_form'],
                'unit' => $medicine['m_unit'],
                'price' => $medicine['m_price'],
                'pic_url' => getPicUrl( Medicine::get( 'medicine', $medicine['m_id'], 'images' ) ),
                'generic' => GenericV1::getName( $medicine['m_g_id'] ),
                'company' => Company::getName( $medicine['m_c_id'] ),
            ];

            return response()->json([
                'status'=>'success',
                'data'=>$data
            ]);
        }

        if ( ! $total) {
            return response()->json( 'No Medicines Found' );
        } else {
            return response()->json([
                'status'=>'success',
                'total'=>$total
            ]);
        }
    }



    public function collections(Request $request) {
        $page = $request->page ? $request->page : 1;
        $per_page = 10;
        $limit    = $per_page * ( $page - 1 );

        $datas= Collection::orderBy('co_created', 'desc')->where('co_fid', Auth::id())
        ->where('co_tid', Auth::id())->limit($per_page)->offset($limit)->get();

        foreach( $datas as $data){
            $data['co_fname'] = User::getName( $data['co_fid'] );
            $data['co_tname'] = User::getName( $data['co_tid'] );
            $data['co_bag'] = maybeJsonDecode( $data['co_bag'] );

            unset( $data['o_ids'] );

            return response()->json([
                'status'=>'success',
                'data'=>$data
            ]);
        }
        if ( ! $datas) {
            return response()->json( 'No Collections Found' );
        } else {
            return response()->json([
                'status'=>'success',
                'data'=>$datas
            ]);
        }
    }


    public function pendingCollection() {
        // if ( ! Auth::user()) {
        //     return response()->json( 'Your account does not have permission to do this.' );
        // }
        $data= Order::where('o_de_id', Auth::id())->where('o_status', '=', 'delivered')
        ->where('o_i_status', '=', 'confirmed')->get()->sum('o_total');

        if ( ! $data) {
            return response()->json( 'No Collections Found' );
        } else {
            return response()->json([
                'status'=>'success',
                'data'=>$data
            ]);
        }
    }


    public function zones(){
        if( 'pharmacy' == $this->user->u_role ) {
            $ph_id = $this->user->u_id;
        } elseif( 'packer' == $this->user->u_role ) {
            $ph_id = $this->user->getMeta( 'packer_ph_id' );
        } else {
            return response()->json( 'You cannot access zones' );
        }
        $zones = getPharmacyZones( $ph_id );
        return response()->json([
            'status'=>'success',
            'zones'=>$zones
        ]);
    }



    public function sendCollection(Request $request){
        if ( !\in_array( $this->user->u_role, [ 'delivery', 'pharmacy' ] ) ) {
            return response()->json( 'Your account does not have permission to do this.' );
        }

        $ph_id = $request->o_ph_id ? $request->o_ph_id : 0;
        $amount = $request->amount ? $request->amount : 0.00;

        if( ! $ph_id || ! $amount ) {
            return response()->json( 'Invalid pharmacy id or amount' );
        }
        
        $order= Order::where('o_de_id', Auth::id())->where('o_ph_id', $ph_id)
        ->where('o_status', '=', 'delivered')->where('o_i_status', '=', 'confirmed')->get();
        $total= Order::where('o_de_id', Auth::id())->where('o_ph_id', $ph_id)
        ->where('o_status', '=', 'delivered')->where('o_i_status', '=', 'confirmed')->get()->sum('o_total');

        if( \round( $amount ) != \round( $total ) ) {
            return response()->json( 'Amount Mismatch. Contact customer care.' );
        }
        $o_ids = [];
        $total = 0;
        while( $order){
            $o_ids[] = $order['o_id'];
            $total += $order['o_total'];
        }
        $s_price_total= OMedicine::where('om_status', '=', 'available')->where('o_id', $o_ids)->get()->sum('s_price*m_qty');
        $bag = Bag::deliveryBag( $ph_id, Auth::id() );
        $bag_data = [];
        $currentBag = '';
        if( $bag ){
            $bag_data['f_b_id'] = $bag->b_id;
            $bag_data['f_bag'] = $bag->fullZone();
            if( $undeliveredIds = $bag->bagUndeliveredIds() ){
                $currentBag = Bag::getCurrentBag( $ph_id, $bag->b_zone);
                if( ! $currentBag ){
                    return response()->json( 'No available bag for this zone' );
                }
                $bag_data['t_b_id'] = $currentBag->b_id;
                $bag_data['t_bag'] = $currentBag->fullZone();
                $bag_data['o_ids'] = $undeliveredIds;

                $notInBag_o_ids = array_values( array_diff( $bag->o_ids, $undeliveredIds ) );
                if( $notInBag_o_ids ){
                    $c_o_ids=Order::where('o_status', '=', 'available')->where('o_id', $o_ids)->get();
                    if( $c_o_ids ){
                        $bag_data['c_o_ids'] = $c_o_ids;
                    }
                }
            }
        }

        
      // $data_array= new Collection();
      
        $data_array = [
            'co_fid' => Auth::id(),
            'co_tid' => $ph_id,
            'o_ids' => \json_encode( $o_ids ),
            'co_amount' => \round( $total, 2 ),
            'co_s_amount' => \round( $s_price_total, 2 ),
            'co_created' => \date( 'Y-m-d H:i:s' ),
            'co_bag' => maybeJsonEncode( $bag_data ),
        ];

        Collection::insert($data_array);
        
        if( $data_array) {
            return response()->json([
                'status'=>'success', 
                'message'=>'Collection done', 
                'data'=>$data_array
            ]);
        }else{
            return response()->json( 'Something wrong. Plase try again.' );
        }

        
    }



    public function receivedCollection( $co_id ) {
        if( ! $co_id ) {
            return response()->json( 'No collection found.' );
        }

        $collection= Collection::where('co_id', $co_id)->where('co_tid', Auth::id())
        ->where('co_status','=', 'pending')->get();


        if( $collection){
           // $updated = DB::instance()->update( 't_collections', ['co_status' => 'confirmed'], [ 'co_id' => $co_id ] );
            if( $collection ){
                $user = User::getUser( Auth::id() );
                //$user->cashUpdate( -$collection['co_amount'] );

                $fm_name = User::getName( $collection['co_fid'] );
                $to_name = User::getName( $collection['co_tid'] );
                $reason = \sprintf( 'Collected by %s from %s', $to_name, $fm_name );
                ledgerCreate( $reason, $collection['co_amount'], 'collection' );

                $bag = maybeJsonDecode( $collection['co_bag'] );
                if( $bag ){
                    if( ! empty( $bag['o_ids'] ) && ! empty( $bag['t_b_id'] ) && ( $to_bag = Bag::getBag( $bag['t_b_id'] ) ) ){
                        $all_ids = array_merge( $to_bag->o_ids, $bag['o_ids'] );
                        $to_bag->update( [ 'o_ids' => $all_ids, 'o_count' => count( $all_ids ) ] );
                    }
                    if( ! empty( $bag['f_b_id'] ) && ( $from_bag = Bag::getBag( $bag['f_b_id'] ) ) ){
                        $from_bag->release();
                    }
                }
                return response()->json([
                    'status'=>'success', 
                    'message'=>'Confirmed received collection.', 
                ]);

            }
        } else {
            return response()->json( 'No collection found to confirm.' );
        }
    }



    public function statusTo( $o_id, $status ){
        
        if ( !$o_id || !$status || !\in_array( $status, ['delivering', 'delivered'] ) ) {
            return response()->json( 'No orders found.' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            return response()->json( 'No orders found.' );
        }
        if( Auth::id() != $order->o_de_id ){
            return response()->json( 'You are not delivery man for this order.' );
        }
        $medicines = $order->medicines;
        if( 'delivered' == $status ) {
            foreach ( $medicines as $key => $value ) {
                if( $value['qty'] && 'available' != $value['om_status'] ){
                    return response()->json( 'All medicines price are not set. Contact Pharmacy and tell them to input all medicines price.' );
                }
            }
        }

        if( $order->update( [ 'o_status' => $status ] ) ){
            $data = $order->toArray();
            $data['prescriptions'] = $order->prescriptions;
            $data['o_data'] = (array)$order->getMeta( 'o_data' );
            $data['o_data']['medicines'] = $medicines;
            $data['timeline'] = $order->timeline();

            return response()->json([
                'status'=>'success', 
                'data'=>$data, 
            ]);
            
        }
        return response()->json( 'Something wrong. Please try again.' );
    }


    public function internalStatusTo( $o_id, $status ){
        if ( !$o_id || !$status || !\in_array( $status, [ 'packing', 'checking', 'confirmed'] ) ) {
            return response()->json( 'No orders found.' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            return response()->json( 'No orders found.' );
        }
        $allowed_ids = [
            $order->o_ph_id
        ];
        if( 'packer' == $this->user->u_role && $order->o_ph_id == $this->user->getMeta( 'packer_ph_id' ) ) {
            $allowed_ids[] = Auth::id();
        }
        if( ! in_array( Auth::id(), $allowed_ids ) ){
            return response()->json( 'You cannot do this.' );
        }
        if( 'confirmed' == $status && $this->user->u_id == $order->getMeta( 'packedBy' ) ) {
            return response()->json( 'You cannot check your own packed order.' );
        }

        if ( \in_array( $status, [ 'checking' ] ) ) {
            $l_zone = getZoneByLocationId( $order->o_l_id );
            $bag = Bag::getCurrentBag( $order->o_ph_id, $l_zone );
            if( ! $bag ){
                return response()->json( 'No available bag for this zone' );
            }
            if( 'confirmed' !== $order->o_status ){
                return response()->json( 'You cannot pack this order' );
            }
        }

        if( $order->update( [ 'o_i_status' => $status ] ) ){
            $mgs = '';
            if( 'checking' == $status ){
                $mgs = sprintf( '%s: Packed by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'confirmed' == $status ){
                $mgs = sprintf( '%s: Checked by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            }
            if( $mgs ){
                $order->appendMeta( 'o_admin_note', $mgs );
            }
            if( 'packing' == $status ){
                $order->setMeta( 'packedWrong', 1 );
                if( $b_id = $order->getMeta( 'bag' ) ){
                    $bag = Bag::getBag( $b_id );
                    $bag->removeOrder( $order->o_id );
                    $order->deleteMeta( 'bag' );
                }
            } elseif( 'checking' == $status ){
                $order->setMeta( 'packedBy', $this->user->u_id );
                $order->deleteMeta( 'packedWrong' );
                $bag->addOrder( $order->o_id );
                $order->setMeta( 'bag', $bag->b_id );
            }
            return response()->json([
                'status'=>'success', 
                'message'=>'Successfully changed status', 
            ]);
           
        }
        return response()->json( 'Something wrong. Please try again.' );
    }



    public function issueStatusTo( $o_id, $status ){
        if ( !$o_id || !$status || !\in_array( $status, [ 'packing', 'checking', 'packed', 'delivering', 'delivered', 'operator', 'solved' ] ) ) {
            return response()->json( 'No orders found.' );
        }
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            return response()->json( 'No orders found.' );
        }
        $allowed_ids = [
            $order->o_ph_id,
            $order->o_de_id,
        ];
        if( 'packer' == $this->user->u_role && $order->o_ph_id == $this->user->getMeta( 'packer_ph_id' ) ) {
            $allowed_ids[] = Auth::id();
        }
        if( ! in_array( Auth::id(), $allowed_ids ) ){
            return response()->json( 'You cannot do this.' );
        }
        if( 'packed' == $status && $this->user->u_id == $order->getMeta( 'packedBy' ) ) {
            return response()->json( 'You cannot check your own packed order.' );
        }
        if ( \in_array( $status, [ 'checking' ] ) ) {
            $l_zone = getZoneByLocationId( $order->o_l_id );
            $bag = Bag::getCurrentBag( $order->o_ph_id, $l_zone );
            if( ! $bag ){
                return response()->json( 'No available bag for this zone' );
            }
        }

        if( $order->update( [ 'o_is_status' => $status ] ) ){
            $mgs = '';
            if( 'checking' == $status ){
                $mgs = sprintf( '%s: Issue packed by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'packed' == $status ){
                $mgs = sprintf( '%s: Issue checked by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'delivering' == $status ){
                $mgs = sprintf( '%s: Issue delivering by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'delivered' == $status ){
                $mgs = sprintf( '%s: Issue delivered by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'operator' == $status ){
                $mgs = sprintf( '%s: Issue marked to operator by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            } elseif( 'solved' == $status ){
                $mgs = sprintf( '%s: Issue marked solved by %s', \date( 'Y-m-d H:i:s' ), $this->user->u_name );
            }
            if( $mgs ){
                $order->appendMeta( 'o_admin_note', $mgs );
            }
            if( 'packing' == $status ){
                $order->setMeta( 'packedWrong', 1 );
                if( $b_id = $order->getMeta( 'bag' ) ){
                    $bag = Bag::getBag( $b_id );
                    $bag->removeOrder( $order->o_id );
                    $order->deleteMeta( 'bag' );
                }
            } elseif( 'checking' == $status ){
                $order->setMeta( 'packedBy', $this->user->u_id );
                $order->deleteMeta( 'packedWrong' );
                $bag->addOrder( $order->o_id );
                $order->setMeta( 'bag', $bag->b_id );
            }
            return response()->json([
                'status'=>'success', 
                'message'=>'Successfully changed issue status', 
            ]);
        }
        return response()->json( 'Something wrong. Please try again.' );
    }


    public function saveInternalNote( $o_id ){
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            return response()->json( 'No orders found.' );
        }

        if( ! \in_array( Auth::id(), [ $order->o_de_id, $order->o_ph_id ] ) ){
            return response()->json( 'No orders found.' );
        }
        $o_i_note = isset($_POST['o_i_note']) ? filter_var($_POST['o_i_note'], FILTER_SANITIZE_STRING) : '';
        $order->setMeta( 'o_i_note', $o_i_note );

        return response()->json([
            'status'=>'success', 
            'o_i_note'=>$order->getMeta( 'o_i_note' ), 
        ]);

    }

    public function sendDeSMS( $o_id ){
        if( ! ( $order = Order::getOrder( $o_id ) ) ){
            return response()->json( 'No orders found.' );
        }
        $mobile  = '';
        $s_address = $order->getMeta('s_address');
        if( is_array( $s_address ) && ! empty( $s_address['mobile'] ) ){
            $mobile = checkMobile( $s_address['mobile'] );
        }

        if( ! $mobile  ){
            return response()->json( 'No numbers found.' );
        }
        $deliveryman = User::getUser( $order->o_de_id );

        $message = sprintf("Dear client, Arogga's Deliveryman (%s: %s) has called you several times to deliver your order #%d. Please call him urgently to receive your order", $deliveryman ? $deliveryman->u_name : '', $deliveryman ? $deliveryman->u_mobile : '', $order->o_id );
        sendSMS( $mobile, $message );
        $order->appendMeta( 'o_i_note', date( "d-M h:ia" ) . ": SMS Sent" );
        //$order->addHistory( 'SMS', 'Delivery SMS sent' );

        return response()->json( 'SMS Sent', 'success' );
    }







}
