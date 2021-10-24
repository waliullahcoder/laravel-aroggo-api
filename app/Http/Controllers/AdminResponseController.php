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
class AdminResponseController extends Controller
{

    public function MyResponse()
    {

        $result = Medicine::get();
        if (!$result) {
            return response()->json([
                'status' => 'Fail',
                'message' => 'No uu medicines Found',
                'result' => []
            ]);
        } else {
            return response()->json([
                'status' => 'success',
                'result' => $result
            ]);
        }
    }

    function allLocations()
    {
        $locations = getLocations();
        foreach ($locations as &$v1) {
            foreach ($v1 as &$v2) {
                foreach ($v2 as &$v3) {
                    //remove postcode, deliveryman id
                    $v3 = [];
                }
            }
        }
        unset($v1, $v2, $v3);
        return response()->json([
            'status' => 'success',
            'locations' => $locations
        ]);
    }

    public function medicinesES(Request $request)
    {
        $ids = $request->ids ? $request->ids : '';
        $search = $request->_search ? $request->_search : '';
        $category = $request->_category ? $request->_category : '';
        $status = $request->_status ? $request->_status : '';
        $c_id = $request->_c_id ? $request->_c_id : 0;
        $g_id = $request->_g_id ? $request->_g_id : 0;
        $cat_id = $request->_cat_id ? $request->_cat_id : 0;
        $orderBy = $request->_orderBy ? $request->_orderBy : '';
        $order = $request->_order && 'DESC' == $request->_order  ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage = $request->id_perPages ? $request->id_perPages : 20;
        $available = $request->_available ? $request->_available : 0;

        $ids = \array_filter(\array_map('intval', \array_map('trim', \explode(',', $ids))));

        if ($search && \is_numeric($search)) {
            $ids[] = (int)$search;
            $search = '';
        }
        if ($ids) {
            $perPage = count($ids);
        }

        $args = [
            'ids' => $ids,
            'search' => $search,
            'per_page' => $perPage,
            'limit' => $perPage * ($page - 1),
            'm_status' => $status,
            'm_category' => $category,
            'm_c_id' => $c_id,
            'm_g_id' => $g_id,
            'orderBy' => $orderBy,
            'order' => $order,
            'available' => $available,
            'isAdmin' => true,
            'm_cat_id' => $cat_id,
        ];
        if ($available) {
            $args['m_rob'] = true;
        }
        $data = Medicine::init()->search($args);

        if ($data && $data['data']) {
            return response()->json([
                'status' => 'success',
                'data' => $data['data'],
                'total' => $data['total'],
            ]);
        } else {
            if ($page > 1) {
                return response()->json('No more medicines Found');
            } else {
                return response()->json('No medicines Found');
            }
        }
    }


    public function medicines(Request $request)
    {

        $ids = $request->ids ? $request->ids : '';
        $search = $request->_search ? $request->_search : '';
        $category = $request->_category ? $request->_category : '';
        $status = $request->_status ? $request->_status : '';
        $c_id = $request->_c_id ? $request->_c_id : 0;
        $g_id = $request->_g_id ? $request->_g_id : 0;
        $cat_id = $request->_cat_id ? $request->_cat_id : 0;
        $orderBy = $request->_orderBy ? $request->_orderBy : '';
        $order = $request->_order && 'DESC' == $request->_order  ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage = $request->id_perPages ? $request->id_perPages : 20;
        $available = $request->_available ? $request->_available : 0;



        if ($search) {
            $this->medicinesES();
        }

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_medicines WHERE 1=1' );
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND m_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }
        // if ( $search ) {
        //     if( \is_numeric($search) && !$ids ){
        //         $db->add( ' AND m_id = ?', $search );
        //     } else {
        //         $search = preg_replace('/[^a-z0-9\040\.\-]+/i', ' ', $search);
        //         $org_search = $search = \rtrim( \trim(preg_replace('/\s\s+/', ' ', $search ) ), '-' );

        //         if( false === \strpos( $search, ' ' ) ){
        //             $search .= '*';
        //         } else {
        //             $search = '+' . \str_replace( ' ', ' +', $search) . '*';
        //         }
        //         if( \strlen( $org_search ) > 2 ){
        //             $db->add( " AND (MATCH(m_name) AGAINST (? IN BOOLEAN MODE) OR m_name LIKE ?)", $search, "{$org_search}%" );
        //         } elseif( $org_search ) {
        //             $db->add( ' AND m_name LIKE ?', "{$org_search}%" );
        //         }
        //     }
        // }
        // if( $category ) {
        //     $db->add( ' AND m_category = ?', $category );
        // }
        // if( $status ) {
        //     $db->add( ' AND m_status = ?', $status );
        // }
        // if( $c_id ){
        //     $db->add( ' AND m_c_id = ?', $c_id );
        // }
        // if( $g_id ){
        //     $db->add( ' AND m_g_id = ?', $g_id );
        // }
        // if ( $cat_id ) {
        //     $db->add( ' AND m_cat_id = ?', $cat_id );
        // }
        // if( $available ){
        //     $db->add( ' AND m_rob = ?', 1 );
        // } 

        // if( $orderBy && \property_exists('\OA\Factory\Medicine', $orderBy ) ) {
        //     $db->add( " ORDER BY $orderBy $order" );
        // }


        $cache = new Cache();
        $limit    = $perPage * ($page - 1);

        $cache_key = Medicine::limit($perPage)->offset($limit)->get();

        if ($cache_data = $cache->get($cache_key, 'adminMedicines')) {
            return response()->json([
                'status' => 'success',
                'total' => $cache_data['total'],
            ]);
        }

        $total = Medicine::limit($perPage)->offset($limit)->get()->count();
        $medicine = Medicine::limit($perPage)->offset($limit)->get();
        while ($medicine) {
            $data = $medicine->toArray();
            $data['id'] = $medicine->m_id;
            $data['m_generic'] = $medicine->m_generic;
            $data['m_company'] = $medicine->m_company;
            $data['attachedFiles'] = getPicUrlsAdmin($medicine->getMeta('images'));

            return response()->json([
                'data' => $data
            ]);
        }
        if ($medicine) {
            $cache_data = [
                'data' => $medicine,
                'total' => $total,
            ];

            $cache->set($cache_key, $cache_data, 'adminMedicines', 60 * 60 * 24);

            return response()->json([
                'status' => 'success',
                'total' => $total
            ]);
        } else {
            return response()->json('No medicines Found');
        }
    }




    public function medicineCreate(Request $request)
    {
        // if( ! $this->user->can( 'medicineCreate' ) ) {
        //     return response()->json( 'Your account does not have medicine create capabilities.');
        // }
        if (empty($request->m_name)) {
            return response()->json('Name is Required');
        }
        $medicine = new Medicine;
        $request->m_u_id = Auth::id();
        $medicine->save();
        if ('allopathic' !== $medicine->m_category && $request->description) {
            $medicine->setMeta('description', $request->description);
        }
        if ($request->m_status && 'active' != $request->m_status) {
            //If status active, then it will incr from Medicine class
            $cache = new Cache();
            $cache->incr('suffixForMedicines');
        }

        if (isset($_POST['attachedFiles'])) {
            modifyMedicineImages($medicine->m_id, $request->attachedFiles);
        }

        $this->medicineSingle($medicine->m_id);
    }


    public function medicineSingle($m_id)
    {

        if (!$m_id) {
            return response()->json('No medicines Found');
        }

        if ($medicine = Medicine::getMedicine($m_id)) {
            //return response()->json( 'success' );
            //$price = $medicine->m_price * (intval($medicine->m_unit));
            //$d_price = ( ( $price * 90 ) / 100 );

            $data = $medicine->toArray();
            $data['id'] = $medicine->m_id;
            $data['m_generic'] = $medicine->m_generic;
            $data['m_company'] = $medicine->m_company;
            $data['attachedFiles'] = getPicUrlsAdmin($medicine->getMeta('images'));
            if ('allopathic' !== $medicine->m_category) {
                $data['description'] = (string)$medicine->getMeta('description');
            }

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } else {
            return response()->json('No medicines Found');
        }
    }




    public function medicineUpdate(Request $request, $m_id)
    {

        // if( ! $this->user->can( 'medicineEdit' ) ) {
        //     return response()->json( 'Your account does not have medicine edit capabilities.');
        // }
        if (!$m_id) {
            return response()->json('No medicines Found');
        }

        if ($medicine = Medicine::getMedicine($m_id)) {
            //$_POST['m_comment'] = isset($_POST['m_comment'])? $_POST['m_comment'] : '';
            $medicine->update($_POST);
            if ('allopathic' !== $medicine->m_category && isset($_POST['description'])) {
                $medicine->setMeta('description', $_POST['description']);
            }

            modifyMedicineImages($medicine->m_id, $request->attachedFiles ? $request->attachedFiles : []);

            $this->medicineSingle($medicine->m_id);
        } else {
            return response()->json('No medicines Found');
        }
    }


    public function medicineDelete($m_id)
    {

        // if( ! $this->user->can( 'medicineDelete' ) ) {
        //     return response()->json( 'Your account does not have medicine delete capabilities.');
        // }

        if (!$m_id) {
            return response()->json('No medicines Found');
        }

        if ($medicine = Medicine::getMedicine($m_id)) {
            $medicine->delete();
            return response()->json([
                'status' => 'success',
                'id' => $m_id
            ]);
        } else {
            return response()->json('No medicines Found');
        }
    }




    public function medicineImageDelete($m_id)
    {
        if (!$this->user->can('medicineEdit')) {
            return response()->json('Your account does not have medicine edit capabilities.');
        }
        $s3key = isset($_GET['s3key']) ? $_GET['s3key'] : '';

        if (!$m_id || !$s3key) {
            return response()->json('No medicines Found');
        }

        if ($medicine = Medicine::getMedicine($m_id)) {
            $images = $medicine->getMeta('images');
            if (!$images || !is_array($images)) {
                return response()->json('No images Found');
            }
            // Instantiate an Amazon S3 client.
            $s3 = getS3();

            // foreach( $images as $k => $image ){
            //     if( $s3key == $image['s3key'] ){
            //         try {
            //             $s3->deleteObject([
            //                 'Bucket' => getS3Bucket(),
            //                 'Key'    => $s3key,
            //             ]);
            //             unset( $images[ $k ] );
            //         } catch (S3Exception $e) {
            //             error_log( $e->getAwsErrorMessage() );
            //             return response()->json( 'Something wrong, please try again' );
            //         }
            //         break;
            //     }
            // }
            $imgArray = array_values($images);
            $cache = new Cache();

            if ($medicine->setMeta('images', $imgArray)) {
                Medicine::init()->update($medicine->m_id, [
                    'images' => $imgArray,
                    'imagesCount' => count($imgArray)
                ]);
                $cache->incr('suffixForMedicines');
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Image successfully deleted'
            ]);
        } else {
            return response()->json('No medicines Found');
        }
    }



    public function users(Request $request)
    {

        $ids = $request->ids ? $request->ids : '';
        $search = $request->_search ? $request->_search : '';
        $status = $request->_status ? $request->_status : '';
        $role = $request->_role ? $request->_role : '';
        $orderBy = $request->_orderBy ? $request->_orderBy : '';
        $order = $request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage = $request->_perPage ? $request->_perPage : 20;


        // $db = new DB;
        // $db->add('SELECT SQL_CALC_FOUND_ROWS * FROM t_users WHERE 1=1');
        // if ($ids) {
        //     $ids = \array_filter(\array_map('intval', \array_map('trim', \explode(',', $ids))));
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add(" AND u_id IN ($in)", ...$ids);

        //     $perPage = count($ids);
        // }
        // if ($search) {
        //     if (\is_numeric($search) && 0 === \strpos($search, '0')) {
        //         $search = "+88{$search}";
        //         $search = addcslashes($search, '_%\\');
        //         $db->add(' AND u_mobile LIKE ?', "{$search}%");
        //     } elseif (\is_numeric($search)) {
        //         $db->add(' AND u_id = ?', $search);
        //     } else {
        //         $search = addcslashes($search, '_%\\');
        //         $db->add(' AND u_name LIKE ?', "{$search}%");
        //     }
        // }
        // if ($status) {
        //     $db->add(' AND u_status = ?', $status);
        // }
        // if ($role) {
        //     if (false !== \strpos($role, ',')) {
        //         $roles = \array_filter(\array_map('trim', \explode(',', $role)));
        //         $in  = str_repeat('?,', count($roles) - 1) . '?';
        //         $db->add(" AND u_role IN ($in)", ...$roles);
        //     } else {
        //         $db->add(' AND u_role = ?', $role);
        //     }
        // }
        // if ($orderBy && \property_exists('\OA\Factory\User', $orderBy)) {
        //     $db->add(" ORDER BY $orderBy $order");
        // }

        $limit    = $perPage * ($page - 1);
        $cache = new Cache();
        $cache_key = User::limit($perPage)->offset($limit)->get();

        if ($role && ($cache_data =  $cache->get($cache_key, 'adminUsers'))) {
            //send cached data only if there user role. Users are changing too much
            //Currently when searching for roles users
            return response()->json([
                'status' => 'success',
                'total' => $cache_data['total'],
                'data' => $cache_data['data'],
            ]);
        }

        $total = User::limit($perPage)->offset($limit)->get()->count();
        $all_data = User::limit($perPage)->offset($limit)->get();
        if ($all_data) {
            return response()->json([
                'status' => 'success',
                'total' => $total,
                'data' => $all_data,
            ]);
        } else {
            return response()->json('No users Found');
        }
    }



    public function userCreate()
    {
        if (!$this->user->can('userCreate')) {
            return response()->json('Your account does not have user create capabilities.');
        }
        if (empty($_POST['u_name']) || empty($_POST['u_mobile'])) {
            return response()->json('All Fields Required');
        }
        if (!($_POST['u_mobile'] = checkMobile($_POST['u_mobile']))) {
            return response()->json('Invalid mobile number.');
        }
        if (User::getBy('u_mobile', $_POST['u_mobile'])) {
            return response()->json('Mobile number already exists.');
        }
        do {
            $u_referrer = randToken('distinct', 6);
        } while (User::getBy('u_referrer', $u_referrer));

        $_POST['u_referrer'] = $u_referrer;

        $user = new User;
        if ($user->save()) {
            $cache = new Cache();
            //we want cache update only when admin changes user.
            $cache->incr('suffixForUsers');
        }
        if ('packer' === $user->u_role) {
            $user->setMeta('packer_ph_id', $_POST['packer_ph_id'] ?? 0);
        }

        $this->userSingle($user->u_id);
    }

    public function userSingle($u_id)
    {

        if (!$u_id) {
            return response()->json('No users Found');
        }

        if ($user = User::getUser($u_id)) {
            return response()->json('success');

            $data = $user->toArray();
            $data['id'] = $user->u_id;
            if ('packer' === $user->u_role) {
                $data['packer_ph_id'] = (int)$user->getMeta('packer_ph_id');
            }
            if (!$this->user->can('userChangeRole') && $user->can('userChangeRole')) {
                $data['u_otp'] = 0;
            }
            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } else {
            return response()->json('No users Found');
        }
    }


    public function userUpdate($u_id)
    {

        // if( ! $this->user->can( 'userEdit' ) ) {
        //     return response()->json( 'Your account does not have user edit capabilities.');
        // }

        if (!$u_id) {
            return response()->json('No users Found');
        }
        if ($user = User::getUser($u_id)) {
            $data = $_POST;
            if (!$this->user->can('userChangeRole')) {
                unset($data['u_role'], $data['u_cash'], $data['u_p_cash']);
            }
            if ($user->update($data)) {
                $cache = new Cache();
                //we want cache update only when admin changes user.
                $cache->incr('suffixForUsers');
            }
            if ('packer' === $user->u_role) {
                $user->setMeta('packer_ph_id', $_POST['packer_ph_id'] ?? 0);
            }

            $this->userSingle($user->u_id);
        } else {
            return response()->json('No users Found');
        }
    }


    public function userDelete($u_id)
    {
        // if( ! $this->user->can( 'userDelete' ) ) {
        //     return response()->json( 'Your account does not have user delete capabilities.');
        // }

        if (!$u_id) {
            return response()->json('No users Found');
        }

        if ($user = User::getUser($u_id)) {
            if ($user->delete()) {
                $cache = new Cache();
                //we want cache update only when admin changes user.
                $cache->incr('suffixForUsers');
            }
            return response()->json([
                'status' => 'success',
                'id' => $u_id
            ]);
        } else {
            return response()->json('No users Found');
        }
    }



    public function orders(Request $request)
    {
        $ids = $request->ids ? $request->ids : '';
        $u_id = $request->u_id ? $request->u_id : 0;
        $search = $request->_search ? $request->_search : '';
        $status = $request->_status ? $request->_status : '';
        $i_status = $request->_i_status ? $request->_i_status : '';
        $is_status = $request->_is_status ? $request->_is_status : '';
        $ex_status = $request->_ex_status ? $request->_ex_status : '';
        $o_created = $request->_o_created ? $request->_o_created : '';
        $o_created_end = $request->_o_created_end ? $request->_o_created_end : '';
        $o_delivered = $request->_o_delivered ? $request->_o_delivered : '';
        $o_delivered_end = $request->_o_delivered_end ? $request->_o_delivered_end : '';
        $de_id = $request->_de_id ? $request->_de_id : 0;
        $payment_method = $request->_payment_method ? $request->_payment_method : '';
        $priority = empty($request->_priority) || 'false' === $request->_priority ? 0 : 1;
        $issue = $request->_issue ? $request->_issue : '';
        $orderBy = $request->_orderBy ? $request->_orderBy : '';
        $order = $request->_order && 'DESC' == $request->_order  ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage = $request->_perPage ? $request->_perPage : 20;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_orders WHERE 1=1' );
        // if ( $search ) {
        //     if( \is_numeric( $search ) && 0 === \strpos( $search, '0' ) ) {
        //         $search = "+88{$search}";
        //         $search = addcslashes( $search, '_%\\' );
        //         $db->add( ' AND u_mobile LIKE ?', "{$search}%" );
        //     } elseif( \is_numeric( $search ) ) {
        //         $search = addcslashes( $search, '_%\\' );
        //         $db->add( ' AND o_id LIKE ?', "{$search}%" );
        //     }
        // }

        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND o_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }
        // if( $u_id ){
        //     $db->add( ' AND u_id = ?', $u_id );
        // } else {
        //     //For offline orders there is no user. So show only online orders
        //     $db->add( ' AND u_id > ?', 0 );
        // }
        // if( $status ) {
        //     $db->add( ' AND o_status = ?', $status );
        // }
        // if( $i_status ) {
        //     $db->add( ' AND o_i_status = ?', $i_status );
        // }
        // if( $is_status ) {
        //     $db->add( ' AND o_is_status = ?', $is_status );
        // }
        // if( $ex_status ) {
        //     $db->add( ' AND o_status != ?', $ex_status );
        // }
        // if( $o_created ) {
        //     $db->add( ' AND o_created >= ? AND o_created <= ?', $o_created . ' 00:00:00', ($o_created_end ?: $o_created) . ' 23:59:59' );
        // }
        // if( $o_delivered ){
        //     $db->add( ' AND o_delivered >= ? AND o_delivered <= ?', $o_delivered . ' 00:00:00', ($o_delivered_end ?: $o_delivered) . ' 23:59:59' );
        // }
        // if( $de_id ){
        //     $db->add( ' AND o_de_id = ?', $de_id );
        // }
        // if( $payment_method ){
        //     $db->add( ' AND o_payment_method = ?', $payment_method );
        // }
        // if( $priority ){
        //     $db->add( ' AND o_priority = ?', $priority );
        // }
        // if( $issue ){
        //     $db->add( ' AND o_is_status != ? AND o_is_status != ?', '', 'solved' );
        // }
        // if( $orderBy && \property_exists('\OA\Factory\Order', $orderBy ) ) {
        //     $db->add( " ORDER BY $orderBy $order" );
        // }

        $limit    = $perPage * ($page - 1);
        $orders = Order::limit($perPage)->offset($limit)->get();
        $total = Order::limit($perPage)->offset($limit)->get()->count();
        if (!$orders) {
            return response()->json('No Orders Found');
        }

        $o_ids = [];
        foreach ($orders as $order) {
            $o_ids[] = $order->id;
        }
        CacheUpdate::add_to_queue($o_ids, 'order_meta');
        CacheUpdate::update_cache([], 'order_meta');

        // $in  = str_repeat('?,', count($o_ids) - 1) . '?';
        // $query2 = DB::db()->prepare( "SELECT o_id, COUNT(m_id) FROM t_o_medicines WHERE 
        // o_id IN ($in) AND om_status = ? GROUP BY o_id" );
        // $query2->execute([...$o_ids, 'later']);
        // $laterCount = $query2->fetchAll( \PDO::FETCH_KEY_PAIR );

        $laterCount = OMedicine::where('o_id', $o_ids)->where('om_status', '=', 'later')->get()->count();

        foreach ($orders as $order) {
            $data = $order->toArray();
            // $data['id'] = $order->o_id;
            // $data['o_i_note'] = (string)$order->getMeta('o_i_note');
            // $data['supplierPrice'] = 'delivered' == $order->o_status ? $order->getMeta( 'supplierPrice' ) : 0.00;

            // $data['d_code'] = (string)$order->getMeta( 'd_code' );
            // $data['o_note'] = (string)$order->getMeta('o_note');
            // $data['prescriptions'] = $order->prescriptions;
            // $data['medicineQty'] = $order->medicineQty;

            // $data['paymentGatewayFee'] = $order->getMeta( 'paymentGatewayFee' ) ?: 0.00;

            // if( isset( $laterCount[ $order->o_id ] ) ){
            //     $data['laterCount'] = $laterCount[ $order->o_id ];
            // }

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        }
        if (!$orders) {
            return response()->json('No orders Found');
        } else {
            return response()->json([
                'status' => 'success',
                'total' => $total
            ]);
        }
    }




    public function orderCreate(Request $request)
    {
        // if( ! $this->user->can( 'orderCreate' ) ) {
        //     return response()->json( 'Your account does not have order create capabilities.');
        // }
        if (empty($request->u_name) || empty($request->u_mobile) || empty($request->medicineQty)) {
            return response()->json('All Fields Required');
        }
        if (!($request->u_mobile = checkMobile($request->u_mobile))) {
            return response()->json('Invalid mobile number.');
        }
        $user = User::getBy('u_mobile', $request->u_mobile);

        if (!$user) {
            do {
                $u_referrer = randToken('distinct', 6);
            } while (User::getBy('u_referrer', $u_referrer));

            $_POST['u_referrer'] = $u_referrer;
            $user = new User;
            $user->save();
        }
        $s_address = $request->s_address && is_array($request->s_address) ? $request->s_address : [];
        if ($s_address) {
            $s_address['location'] = sprintf('%s, %s, %s, %s', $s_address['homeAddress'], $s_address['area'], $s_address['district'], $s_address['division']);
            $_POST['o_gps_address'] = $s_address['location'];
        }

        $order = new Order;
        $cart_data = cartData($user, $_POST['medicineQty'], isset($_POST['d_code']) ? $_POST['d_code'] : '', null, false, ['s_address' => $s_address]);

        if (isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
            $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
        }
        if (isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
            $cart_data['additions']['delivery']['info'] = str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
        }

        $c_medicines = $cart_data['medicines'];
        unset($cart_data['medicines']);

        $o_data = $_POST;
        $o_data['o_status'] = 'processing';
        $o_data['o_i_status'] = 'processing';
        $o_data['o_subtotal'] = $cart_data['subtotal'];
        $o_data['o_addition'] = $cart_data['a_amount'];
        $o_data['o_deduction'] = $cart_data['d_amount'];
        $o_data['o_total'] = $cart_data['total'];
        $o_data['u_id'] = $user->u_id;
        $o_data['o_l_id'] = getIdByLocation('l_id', $s_address['division'], $s_address['district'], $s_address['area']);

        Order::insert($o_data);
        ModifyOrderMedicines($order, $c_medicines);
        $meta = [
            'o_data' => $cart_data,
            'o_secret' => randToken('alnumlc', 16),
            's_address' => $s_address,
        ];
        if (!empty($_POST['d_code'])) {
            $meta['d_code'] = $_POST['d_code'];
        }
        if (!empty($_POST['subscriptionFreq'])) {
            $meta['subscriptionFreq'] = $_POST['subscriptionFreq'];
        }

        if (isset($_POST['attachedFiles'])) {
            modifyPrescriptionsImages($order->o_id, $_POST['attachedFiles']);
        }

        $order->insertMetas($meta);
        $order->addHistory('Created', 'Created through Admin');

        $cash_back = $order->cashBackAmount();

        //again get user. User data may changed.
        $user = User::getUser($order->u_id);

        if ($cash_back) {
            $user->u_p_cash = $user->u_p_cash + $cash_back;
        }
        if (isset($cart_data['deductions']['cash'])) {
            $user->u_cash = $user->u_cash - $cart_data['deductions']['cash']['amount'];
        }
        $user->update();

        $this->orderSingle($order->o_id);
    }



    public function orderSingle($o_id)
    {

        if (!$o_id) {
            return response()->json('No orders Found');
        }

        if ($order = Order::getOrder($o_id)) {
            $defaultSAddress = [
                'division' => '',
                'district' => '',
                'area' => '',
            ];

            $data = $order->toArray();
            $data['id'] = $order->o_id;
            $data['d_code'] = (string)$order->getMeta('d_code');
            $data['o_note'] = (string)$order->getMeta('o_note');
            $data['o_i_note'] = (string)$order->getMeta('o_i_note');
            $data['o_admin_note'] = (string)$order->getMeta('o_admin_note');
            $data['subscriptionFreq'] = (string)$order->getMeta('subscriptionFreq');
            $data['addressChecked'] = (bool)$order->getMeta('addressChecked');
            $data['s_address'] = $order->getMeta('s_address') ?: $defaultSAddress;
            //$data['man_discount'] = (string)$order->getMeta( 'man_discount' );
            //$data['man_addition'] = (string)$order->getMeta('man_addition');
            $data['prescriptions'] = $order->prescriptions;
            $data['medicineQty'] = $order->medicineQty;
            $data['supplierPrice'] = 'delivered' == $order->o_status ? $order->getMeta('supplierPrice') : 0.00;
            $data['paymentGatewayFee'] = 'fosterPayment' == $order->o_payment_method ? $order->getMeta('paymentGatewayFee') : 0.00;
            $data['attachedFiles'] = getOrderPicUrlsAdmin($order->o_id, $order->getMeta('prescriptions'));

            $data['invoiceUrl'] = $order->signedUrl('/v1/invoice');
            $data['paymentResponse'] = $order->getMeta('paymentResponse');
            if (\in_array($order->o_status, ['confirmed', 'delivering', 'delivered']) && \in_array($order->o_i_status, ['packing', 'checking', 'confirmed']) && 'paid' !== $order->getMeta('paymentStatus')) {
                $data['paymentUrl'] = $order->signedUrl('/payment/v1');
            }

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } else {
            return response()->json('No orders Found');
        }
    }



    public function orderUpdate(Request $request, $o_id)
    {

        // if( ! $this->user->can( 'orderEdit' ) ) {
        //     return response()->json( 'Your account does not have order edit capabilities.');
        // }
        if (!$o_id) {
            return response()->json('No orders Found');
        }
        $updated = false;

        if ($order = Order::getOrder($o_id)) {
            $prev_order = clone $order;

            $o_note = $request->o_note ? $request->o_note : '';
            $o_i_note = $request->o_note ? $request->o_note : '';
            $subscriptionFreq = $request->o_note ? $request->o_note : (string)$order->getMeta('subscriptionFreq');
            $s_address = $request->s_address && is_array($request->s_address) ? $request->s_address : [];
            $o_priority = $request->o_priority || 'false' === $request->o_priority ? 0 : 1;
            $new_status = $request->o_status ? $request->o_status : '';
            $new_i_status = $request->o_i_status ? $request->o_i_status : '';
            $new_is_status = $request->o_is_status ? $request->o_is_status : '';
            $o_de_id = $request->o_de_id ? $request->o_de_id : 0;

            if (!\in_array($order->o_status, ['delivered', 'cancelled']) && $order->o_is_status !== $new_is_status) {
                return response()->json('You can not modify issue in this status');
            }
            if ($new_status !== $order->o_status && \in_array($new_status, ['delivering', 'delivered'])) {
                return response()->json('You cannot set this status from admin panel.');
            }
            if ($new_i_status !== $order->o_i_status && \in_array($new_i_status, ['packing', 'checking', 'confirmed', 'paid'])) {
                return response()->json('You cannot set this status from admin panel.');
            }
            if ($order->u_mobile !== $request->u_mobile) {
                return response()->json('You cannot change user mobile number. If require change shipping mobile number');
            }
            if (!$s_address || !isLocationValid($s_address['division'] ?? '', $s_address['district'] ?? '', $s_address['area'] ?? '')) {
                return response()->json('invalid location.');
            }
            if ($order->o_subtotal &&  empty($request->medicineQty)) {
                return response()->json('Medicines are empty. Order not saved.');
            }
            if ('operator' == $this->user->u_role && 'delivering' == $order->o_status && 'cancelled' == $new_status) {
                return response()->json('You cannot cancel this order, Contact pharmacy');
            }
            if ('operator' == $this->user->u_role && 'confirmed' == $order->o_status && in_array($order->o_i_status, ['checking', 'confirmed']) && 'cancelled' == $new_status) {
                return response()->json('You cannot cancel this order, Contact pharmacy');
            }
            if ($s_address) {
                $s_address['location'] = sprintf('%s, %s, %s, %s', $s_address['homeAddress'] ?? '', $s_address['area'], $s_address['district'], $s_address['division']);
            }

            if ($o_note != $order->getMeta('o_note')) {
                $updated2 = $order->setMeta('o_note', $o_note);
            }
            if ($o_i_note != $order->getMeta('o_i_note')) {
                $updated2 = $order->setMeta('o_i_note', $o_i_note);
                $updated = $updated ?: $updated2;
            }
            if ($subscriptionFreq != $order->getMeta('subscriptionFreq')) {
                if ($subscriptionFreq) {
                    $updated2 = $order->setMeta('subscriptionFreq', $subscriptionFreq);
                    $updated = $updated ?: $updated2;
                } else {
                    $updated2 = $order->deleteMeta('subscriptionFreq');
                    $updated = $updated ?: $updated2;
                }
            }
            $order->setMeta('s_address', $s_address);

            $order->o_gps_address = $s_address['location'];
            $order->o_l_id = getIdByLocation('l_id', $s_address['division'], $s_address['district'], $s_address['area']);
            $order->o_is_status = $new_is_status;
            $order->o_priority = $o_priority;
            $order->o_de_id = $o_de_id;

            if (\in_array($order->o_status, ['cancelled', 'damaged'])) {
                if ($order->update() || $updated) {
                    $this->orderSingle($order->o_id);
                }
                return response()->json('You can not edit this order anymore.');
            }
            if (!($user = User::getUser($request->u_id))) {
                //Response::instance()->sendMessage( 'Invalid order user.');
            }

            if ('delivered' == $order->o_status) {
                if ('returned' == $new_status) {
                    $order->o_status = 'returned';
                } elseif (!empty($_POST['refund']) && $user) {
                    $refund = round($_POST['refund'], 2);
                    if ($refund > 20) {
                        return response()->json('You can not refund more than 20 Taka.');
                    }
                    $user->cashUpdate($refund);

                    $order->appendMeta('o_admin_note', sprintf('%s: %s TK refunded by %s', \date('Y-m-d H:i:s'), $refund, $this->user->u_name));
                    $prev_refund = $order->getMeta('refund');
                    if (!is_numeric($prev_refund)) {
                        $prev_refund = 0;
                    }
                    $order->setMeta('refund', $prev_refund + $refund);
                    $order->addHistory('Refund', $prev_refund, $prev_refund + $refund);
                }
                if ($order->update() || $updated) {
                    $this->orderSingle($order->o_id);
                }

                return response()->json('You can not edit this order anymore.');
            } elseif ('returned' == $new_status) {
                if ($order->update() || $updated) {
                    $this->orderSingle($order->o_id);
                }
                return response()->json('You can not return an order which not yet delivered.');
            }

            if ('paid' == $order->o_i_status || 'paid' === $order->getMeta('paymentStatus')) {
                if ($new_status !== $order->o_status && 'cancelled' == $new_status) {
                    $order->o_status = $new_status;
                }
                if ($order->update() || $updated) {
                    $this->orderSingle($order->o_id);
                }
                return response()->json('You can not edit this order anymore.');
            }

            $prev_o_data = (array)$prev_order->getMeta('o_data');
            $prev_cash_back = 0;
            $prev_applied_cash = 0;
            if ('call' != $prev_order->o_i_status) {
                $prev_cash_back = $prev_order->cashBackAmount();
            }

            if (isset($prev_o_data['deductions']['cash'])) {
                $prev_applied_cash = $prev_o_data['deductions']['cash']['amount'];
            }
            $d_code = isset($_POST['d_code']) ? $_POST['d_code'] : '';

            $cart_data = cartData($user, $_POST['medicineQty'] ?? [], $d_code, $order, false, ['s_address' => $s_address]);

            if (isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
                $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
            }
            if (isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
                $cart_data['additions']['delivery']['info'] = str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
            }
            $c_medicines = $cart_data['medicines'];
            unset($cart_data['medicines']);

            if (!$c_medicines && ($new_status === 'confirmed' || $new_i_status === 'ph_fb')) {
                return response()->json('Please input medicine to confirm this order.');
            }
            if ('pharmacy' !== $this->user->u_role && ('delivering' === $order->o_status || ('confirmed' === $order->o_status && in_array($order->o_i_status, ['checking', 'confirmed'])))) {
                if ($c_medicines) {
                    $old_data = [];
                    $query = DB::instance()->select('t_o_medicines', ['o_id' => $order->o_id], 'm_id, m_qty');
                    while ($old = $query->fetch()) {
                        $old_data[$old['m_id']] = $old;
                    }
                    foreach ($c_medicines as $med) {
                        if (isset($old_data[$med['m_id']])) {
                            if ($med['qty'] > $old_data[$med['m_id']]['m_qty']) {
                                return response()->json('You can not increase item quantities for this order.');
                            }
                        } else {
                            return response()->json('You can not add new items for this order.');
                        }
                    }
                }
            }
            if ('pharmacy' == $this->user->u_role) {
                if ('confirmed' == $order->o_status && in_array($order->o_i_status, ['confirmed']) && 'cancelled' == $new_status) {
                    if ($b_id = $order->getMeta('bag')) {
                        $bag = Bag::getBag($b_id);
                        $bag->removeOrder($order->o_id);
                        $order->deleteMeta('bag');
                    }
                }
            }

            $o_data = $_POST;
            $o_data['o_subtotal'] = $cart_data['subtotal'];
            $o_data['o_addition'] = $cart_data['a_amount'];
            $o_data['o_deduction'] = $cart_data['d_amount'];
            $o_data['o_total'] = $cart_data['total'];

            unset($o_data['o_gps_address'], $o_data['o_l_id']);

            $order->update($o_data);
            $order->setMeta('d_code', $d_code);
            $order->setMeta('o_data', $cart_data);
            ModifyOrderMedicines($order, $c_medicines, $prev_order);

            if (isset($_POST['attachedFiles'])) {
                modifyPrescriptionsImages($order->o_id, $_POST['attachedFiles']);
            }

            //again get user. User data may changed.
            $user = User::getUser($order->u_id);
            if ($user) {
                $user->u_cash += $prev_applied_cash;
                if (isset($cart_data['deductions']['cash'])) {
                    $user->u_cash -= $cart_data['deductions']['cash']['amount'];
                }
                $cash_back = 0;
                if ('call' != $order->o_i_status) {
                    $cash_back = $order->cashBackAmount();
                    $user->u_p_cash = $user->u_p_cash - $prev_cash_back + $cash_back;
                }
                $user->update();

                if ($cash_back && !$prev_cash_back) {
                    $message = "Congratulations!!! You have received a cashback of ৳{$cash_back} from arogga. The cashback will be automatically applied at your next order.";
                    sendNotification($user->fcm_token, 'Cashback Received.', $message);
                }
            }

            $this->orderSingle($order->o_id);
        } else {
            return response()->json('No orders Found');
        }
    }


    public function orderDelete($o_id)
    {

        // if( ! $this->user->can( 'orderDelete' ) ) {
        //     return response()->json( 'Your account does not have order delete capabilities.');
        // }
        if (!$o_id) {
            return response()->json('No orders Found');
        }

        if ($order = Order::getOrder($o_id)) {
            $order->delete();
            return response()->json([
                'status' => 'success',
                'id' => $o_id
            ]);
        } else {
            return response()->json('No orders Found');
        }
    }


    function adminGetType($type, $id)
    {
        switch ($type) {
            case 'history':
                $this->history($id);
                break;
            default:
                return response()->json('No types provided.');
                break;
        }
    }


    public function history(Request $request, $o_id)
    {
        $orderBy = $request->_orderBy ? $request->_orderBy : 'h_id';
        $order = $request->_orderBy && 'DESC' == $request->_orderBy ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage = $request->_perPage ? $request->_perPage : 20;

        $limit    = $perPage * ($page - 1);
        $data = History::orderBy('h_id', 'desc')->where('order', $o_id)->limit($perPage)->offset($limit)->get();
        $total = History::orderBy('h_id', 'desc')->where('order', $o_id)->limit($perPage)->offset($limit)->get()->count();
        while ($data) {
            $data['id'] = $data['h_id'];
            $data['u_name'] = $data['u_id'] ? User::getName($data['u_id']) : 'System';

            return response()->json([
                '' => $data
            ]);
        }

        if (!$data) {
            return response()->json('No history Found');
        } else {
            return response()->json([
                'status' => 'success',
                'total' => $total,
            ]);
        }
    }




    public function adminPostAction($action, $id)
    {
        switch ($action) {
            case 'reOrder':
                $this->reOrder($id);
                break;
            case 'sendAdminSMS':
                $this->sendAdminSMS($id);
                break;
            case 'returnItems':
                $this->returnItems($id);
                break;
            case 'refundItems':
                $this->refundItems($id);
                break;
            case 'shippingAddress':
                $this->shippingAddress($id);
                break;
            case 'assignToDeliveryMan':
                $this->assignToDeliveryMan($id);
                break;
            default:
                return response()->json('No actions provided.');
                break;
        }
    }

    private function reOrder($o_id)
    {
        // if( ! $this->user->can( 'orderCreate' ) ) {
        //     return response()->json( 'Your account does not have order create capabilities.');
        // }
        if ($order = reOrder($o_id)) {
            //trigger changes
            $order->update(['o_status' => 'confirmed', 'o_i_status' => 'ph_fb']);
            return response()->json([
                'status' => 'success',
                'message' => 'Successfully re-ordered.',
            ]);
        } else {
            return response()->json('Something wrong.');
        }
    }


    private function sendAdminSMS(Request $request, $o_id)
    {
        if (!($order = Order::getOrder($o_id))) {
            return response()->json('No orders found.');
        }

        $message = $request->message ?? '';
        $to = $request->to ?? '';
        $mobile  = '';
        switch ($to) {
            case 'shipping':
                $s_address = $order->getMeta('s_address');
                if (is_array($s_address) && !empty($s_address['mobile'])) {
                    $mobile = checkMobile($s_address['mobile']);
                }
                break;
            case 'billing':
                if ($mobile = $order->u_mobile) {
                    $mobile = checkMobile($mobile);
                }
                break;
            default:
                break;
        }

        if (!$mobile) {
            return response()->json('No numbers found.');
        }
        $deliveryman = User::getUser($order->o_de_id);


        sendSMS($mobile, $message);
        $order->appendMeta('o_i_note', sprintf("%s : SMS Sent to %s", date("d-M h:ia"), $to));
        //$order->addHistory( 'SMS', sprintf( 'SMS Sent to %s', $to ) );

        return response()->json([
            'status' => 'success',
            'message' => 'SMS Sent',
        ]);
    }

    private function returnItems($o_id)
    {
        if (!$this->user->can('orderEdit')) {
            return response()->json('Your account does not have order edit capabilities.');
        }
        if (!($order = Order::getOrder($o_id))) {
            return response()->json('No orders found.');
        }
        $medicineQty = $_POST['medicineQty'] ?? [];
        if (!$medicineQty || !is_array($medicineQty)) {
            return response()->json('Invalid medicines');
        }
        $status = '';
        $note = [];
        foreach ($medicineQty as $m) {
            if (!$m || empty($m['m_id'])) {
                continue;
            }
            if (!($medicine = Medicine::getMedicine($m['m_id']))) {
                continue;
            }
            if (!empty($m['missing_qty'])) {
                $note[] = sprintf(
                    'দিবেন: %s %s - %s',
                    $medicine->m_name,
                    $medicine->m_strength,
                    qtyTextClass($m['missing_qty'], $medicine)
                );
                if ('packing' !== $status) {
                    $status = 'packing';
                }
            }
        }
        foreach ($medicineQty as $m) {
            if (!$m || empty($m['m_id'])) {
                continue;
            }
            if (!($medicine = Medicine::getMedicine($m['m_id']))) {
                continue;
            }
            if (!empty($m['return_qty'])) {
                $note[] = sprintf(
                    'আনবেন: %s %s - %s',
                    $medicine->m_name,
                    $medicine->m_strength,
                    qtyTextClass($m['return_qty'], $medicine)
                );
            }
            if (!empty($m['replace_id']) && !empty($m['replace_qty'])) {
                if ($replace_medicine = Medicine::getMedicine($m['replace_id'])) {
                    $note[] = sprintf(
                        'আনবেন: %s %s - %s',
                        $replace_medicine->m_name,
                        $replace_medicine->m_strength,
                        qtyTextClass($m['replace_qty'], $replace_medicine)
                    );
                }
            }
        }
        if (!$status && $note) {
            $status = 'delivering';
        }
        $note = array_filter(array_merge([$order->getMeta('o_i_note')], $note));

        $order->setMeta('o_i_note', implode("\n", $note));
        if ($status && (!$order->o_is_status || $order->o_is_status === 'solved')) {
            $order->update(['o_is_status' => $status]);
        }
        if ($status) {
            $order->appendMeta('o_admin_note', sprintf('%s: Issue created by %s', \date('Y-m-d H:i:s'), $this->user->u_name));
            return response()->json([
                'status' => 'success',
                'message' => 'Successfully added returned note',
            ]);
        }

        return response()->json('Nothing to save');
    }


    private function refundItems($o_id)
    {
        // if( ! in_array( $this->user->u_role, [ 'administrator', 'pharmacy' ] ) ){
        //     return response()->json( 'You cannot refund items. Contact pharmacy.' );
        // }
        if (!($order = Order::getOrder($o_id))) {
            return response()->json('No orders found.');
        }
        if ('delivered' !== $order->o_status) {
            return response()->json('Order not in delivered stage.');
        }
        $medicineQty = $_POST['medicineQty'] ?? [];
        if (!$medicineQty || !is_array($medicineQty)) {
            return response()->json('Invalid medicines');
        }

        $total = isset($_POST['total']) ? round($_POST['total'], 2) : 0;
        $m_d_price_total = 0;
        $midArray = [];
        $oldReturnQty = [];
        foreach ($medicineQty as $mqty) {
            if ($mqty["m_id"]) {
                $midArray[$mqty["m_id"]] = ['refund_qty' => $mqty['refund_qty'] ?? 0, 'damage_qty' => $mqty['damage_qty'] ?? 0];
            }
        }
        $orderMedicines = OMedicine::where('o_id', $o_id)->where('refund_qty', 0)->where('damage_qty', 0)->get();
        while ($orderMedicines) {
            $m_d_price_total += $orderMedicines['m_d_price'] * $midArray[$orderMedicines['m_id']]['refund_qty'];
            $oldReturnQty[$orderMedicines['m_id']] = ['refund_qty' => $orderMedicines['refund_qty'], 'damage_qty' => $orderMedicines['damage_qty']];

            if ($orderMedicines['m_qty'] < ($orderMedicines['refund_qty'] + $orderMedicines['damage_qty'] + $midArray[$orderMedicines['m_id']]['refund_qty'] + $midArray[$orderMedicines['m_id']]['damage_qty'])) {
                return response()->json('Medicine return more than Order.');
            }
        }
        if (round($m_d_price_total) != round($total)) {
            return response()->json('Medicines Total not match.');
        }
        if ($total) {
            if ($user = User::getUser($order->u_id)) {
                $user->cashUpdate($total);
                $order->appendMeta('o_admin_note', sprintf('%s: %s TK refunded by %s', \date('Y-m-d H:i:s'), $total, $this->user->u_name));
            }

            $prev_refund = $order->getMeta('refund');
            if (!is_numeric($prev_refund)) {
                $prev_refund = 0;
            }
            $order->setMeta('refund', $prev_refund + $total);
            $order->addHistory('Refund', $prev_refund, $prev_refund + $total);
        }

        foreach ($medicineQty as $mqty) {
            if (empty($mqty["m_id"])) {
                continue;
            }
            $newRefund = $mqty['refund_qty'] ?? 0;
            $newDamage = $mqty['damage_qty'] ?? 0;

            $refund_qty = isset($oldReturnQty[$mqty['m_id']]) ? $oldReturnQty[$mqty['m_id']]['refund_qty'] + $newRefund : $newRefund;
            $damage_qty = isset($oldReturnQty[$mqty['m_id']]) ? $oldReturnQty[$mqty['m_id']]['damage_qty'] + $newDamage : $newDamage;
            DB::instance()->update('t_o_medicines', ['refund_qty' => $refund_qty, 'damage_qty' => $damage_qty], ['o_id' => $order->o_id, 'm_id' => $mqty['m_id']]);
            if ($newRefund) {
                Inventory::qtyUpdateByPhMid($order->o_ph_id, $mqty['m_id'], $newRefund);
            }
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully refunded.',
        ]);
    }


    private function shippingAddress(Request $request, $o_id)
    {
        // if( ! $this->user->can( 'orderEdit' ) ) {
        //     return response()->json( 'Your account does not have order edit capabilities.');
        // }
        if (!($order = Order::getOrder($o_id))) {
            return response()->json('No orders found.');
        }

        $s_address = $request->s_address && is_array($request->s_address)  ? $request->s_address : [];

        if (!$s_address || !isLocationValid($s_address['division'] ?? '', $s_address['district'] ?? '', $s_address['area'] ?? '')) {
            return response()->json('invalid location.');
        }

        $s_address['location'] = sprintf('%s, %s, %s, %s', $s_address['homeAddress'] ?? '', $s_address['area'], $s_address['district'], $s_address['division']);
        $data = [
            'o_gps_address' => $s_address['location'],
            'o_de_id' => getIdByLocation('l_de_id', $s_address['division'], $s_address['district'], $s_address['area']),
            'o_l_id' => getIdByLocation('l_id', $s_address['division'], $s_address['district'], $s_address['area']),
        ];
        $change = "No Changes";
        if ($order->o_gps_address != $s_address['location']) {
            $order->addHistory('Address Check', $order->o_gps_address, $s_address['location']);
            $change = sprintf('%s TO %s', $order->o_gps_address, $s_address['location']);
        } else {
            $order->addHistory('Address Check', 'No Changes');
        }

        $order->update($data);
        $order->setMeta('addressChecked', 1);
        $order->setMeta('s_address', $s_address);
        $order->appendMeta('o_admin_note', sprintf('%s: Address checked by %s (%s)', \date('Y-m-d H:i:s'), $this->user->u_name, $change));

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully checked address.',
        ]);
    }



    private function assignToDeliveryMan(Request $request, $o_de_id)
    {
        $l_zone = $request->zone ? $request->zone : '';
        $bag = $request->bag ? $request->bag : '';
        if (!$o_de_id || !$l_zone || !$bag) {
            return response()->json('invalid information.');
        }

        $deliveryman = User::getUser($o_de_id);
        if ('delivery' !== $deliveryman->u_role) {
            return response()->json('invalid delivery man.');
        }

        $order = DB::table('t_orders')
            ->leftJoin('t_locations', 't_orders.o_l_id', '=', 't_locations.l_id')
            ->innerJoin('t_order_meta', 't_orders.o_id', '=', 't_order_meta.o_id')
            ->where('t_locations.l_zone', $l_zone)
            ->where('t_orders.o_status', '=', 'confirmed')
            ->orWhere('t_orders.o_is_status', '=', 'packed')
            ->where('t_orders.o_is_status', '=', 'paid')
            ->where('t_order_meta.meta_value', $bag)
            ->get();

        $o_ids = [];
        while ($order) {
            $data = [
                'o_de_id' => $o_de_id,
                'o_status' => 'delivering',
            ];
            if ($order->update($data)) {
                $o_ids[] = $order->o_id;
            }
        }
        return response()->json([
            'status' => 'success',
            'message' => 'Successfully delivery man assigned.',
            'o_ids' => $o_ids
        ]);
    }


    public function orderUpdateMany(Request $request)
    {

        $o_ids = $request->ids ?? [];
        $data = $request->data ?? [];
        if (!$o_ids || !$data || !is_array($o_ids) || !is_array($data)) {
            return response()->json('Invalid request');
        }
        if (!$this->user->can('orderEdit')) {
            return response()->json('Your account does not have order edit capabilities.');
        }
        $allowed_keys = ['o_de_id'];
        $data = array_intersect_key($data, array_flip($allowed_keys));

        $updated = DB::instance()->update('t_orders', $data, ['o_id' => $o_ids]);

        if ($updated) {
            foreach ($o_ids as $o_id) {
                $cache = new Cache();

                $cache->delete($o_id, 'order');
            }

            return response()->json([
                'status' => 'success',
                'updated' => sprintf('%d orders updated', $updated),
            ]);
        } else {
            return response()->json('No orders updated');
        }
    }



    public function offlineOrders(Request $request)
    {
        $ids = $request->ids ? $request->ids : '';
        $search = $request->_search ? $request->_search : '';
        $ph_id = $request->_ph_id ? $request->_ph_id : 0;
        $status = $request->_status ? $request->_status : '';
        $i_status = $request->_i_status ? $request->_i_status : '';
        $o_created = $request->_o_created ? $request->_o_created : '';
        $orderBy = $request->_orderBy ? $request->_orderBy : '';
        $order = $request->_order && 'DESC' == $request->_order  ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage = $request->_perPage ? $request->_perPage : 20;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_orders WHERE 1=1' );
        // if ( $search && \is_numeric( $search ) ) {
        //     $search = addcslashes( $search, '_%\\' );
        //     $db->add( ' AND o_id LIKE ?', "{$search}%" );
        // }
        // //For offline orders there is no user 
        // $db->add( ' AND u_id = ?', 0 );

        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND o_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }
        // if( 'pharmacy' == $this->user->u_role ){
        //     $db->add( ' AND o_ph_id = ?', Auth::id() );
        // } elseif( $ph_id ) {
        //     $db->add( ' AND o_ph_id = ?', $ph_id );
        // } else {
        //     $db->add( ' AND o_ph_id > ?', 0 );
        // }

        // if( $status ) {
        //     $db->add( ' AND o_status = ?', $status );
        // }
        // if( $i_status ) {
        //     $db->add( ' AND o_i_status = ?', $i_status );
        // }
        // if( $o_created ) {
        //     $db->add( ' AND o_created >= ? AND o_created <= ?', $o_created . ' 00:00:00', $o_created . ' 23:59:59' );
        // }
        // if( $orderBy && \property_exists('\OA\Factory\Order', $orderBy ) ) {
        //     $db->add( " ORDER BY $orderBy $order" );
        // }

        $limit    = $perPage * ($page - 1);
        $orders = Order::limit($perPage)->offset($limit)->get();
        $total = Order::limit($perPage)->offset($limit)->get()->count();

        foreach ($orders as $order) {
            $data = $order->toArray();
            $data['id'] = $order->o_id;
            $data['supplierPrice'] = 'delivered' == $order->o_status ? $order->getMeta('supplierPrice') : 0.00;
            //$data['medicineQty'] = $order->medicineQty;

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        }
        if ($orders) {
            return response()->json([
                'status' => 'success',
                'total' => $total,
            ]);
        } else {
            return response()->json('No orders Found');
        }
    }


    public function offlineOrderCreate(Request $request)
    {
        // if( ! $this->user->can( 'offlineOrderCreate' ) ) {
        //     return response()->json( 'Your account does not have order create capabilities.');
        // }
        if (empty($request->medicineQty)) {
            return response()->json('Medicines Required');
        }
        $args = [];
        $man_discount = (!empty($request->man_discount) && is_numeric($request->man_discount)) ? \round($request->man_discount, 2) : 0;
        $man_addition = (!empty($request->man_addition) && is_numeric($request->man_addition)) ? \round($request->man_addition, 2) : 0;
        $args['man_discount'] = $man_discount;
        $args['man_addition'] = $man_addition;

        $order = new Order;
        $cart_data = cartData('', $request->medicineQty, '', null, true, $args);

        $c_medicines = $cart_data['medicines'];
        unset($cart_data['medicines']);

        $o_data = $_POST;
        $o_data['o_subtotal'] = $cart_data['subtotal'];
        $o_data['o_addition'] = $cart_data['a_amount'];
        $o_data['o_deduction'] = $cart_data['d_amount'];
        $o_data['o_total'] = $cart_data['total'];
        $o_data['u_name'] = 'Offline';
        $o_data['u_id'] = 0;
        $o_data['o_ph_id'] = Auth::id();
        $o_data['o_de_id'] = Auth::id();
        $o_data['o_status'] = 'delivered';
        $o_data['o_i_status'] = 'confirmed';
        $o_data['o_delivered'] = \date('Y-m-d H:i:s');

        $order->insert($o_data);
        ModifyOrderMedicines($order, $c_medicines);
        $meta = [
            'o_data' => $cart_data,
            'man_discount' => $man_discount,
            'man_addition' => $man_addition,
        ];
        $order->insertMetas($meta);

        foreach ($order->medicineQty as $id_qty) {
            $m_id = isset($id_qty['m_id']) ? (int)$id_qty['m_id'] : 0;
            $quantity = isset($id_qty['qty']) ? (int)$id_qty['qty'] : 0;

            if ($inventory = Inventory::getByPhMid($order->o_ph_id, $m_id)) {
                $inventory->i_qty = $inventory->i_qty - $quantity;
                $inventory->update();
                // DB::instance()->update( 't_o_medicines', ['om_status' => 'available', 's_price' => $inventory->i_price ], [ 'o_id' => $order->o_id, 'm_id' => $m_id ] );

            }
        }


        $supplierPrice = OMedicine::where('o_id', $order->o_id)->where('om_status', '=', 'available')->get()->sum('s_price*m_qty');
        $order->setMeta('supplierPrice', $supplierPrice);

        //To trigger
        //$order->update( ['o_status' => 'delivering', 'o_i_status' => 'confirmed'] );
        //$order->update( ['o_status' => 'delivered'] );

        $this->orderSingle($order->o_id);
    }

    public function offlineOrderUpdate($o_id)
    {

        return response()->json('Offline order update is not possible right now.');

        // if( ! $this->user->can( 'orderEdit' ) ) {
        //     return response()->json( 'Your account does not have order edit capabilities.');
        // }
        if (!$o_id) {
            return response()->json('No orders Found');
        }

        if ($order = Order::getOrder($o_id)) {
            $prev_order = clone $order;
            $prev_o_data = (array)$prev_order->getMeta('o_data');
            $cart_data = cartData('', $_POST['medicineQty'], '', $order, true);

            $c_medicines = $cart_data['medicines'];
            unset($cart_data['medicines']);

            $o_data = $_POST;
            $o_data['o_subtotal'] = $cart_data['subtotal'];
            $o_data['o_addition'] = $cart_data['a_amount'];
            $o_data['o_deduction'] = $cart_data['d_amount'];
            $o_data['o_total'] = $cart_data['total'];

            $order->update($o_data);
            $order->setMeta('o_data', $cart_data);
            ModifyOrderMedicines($order, $c_medicines, $prev_order);

            $this->orderSingle($order->o_id);
        } else {
            return response()->json('No orders Found');
        }
    }

    public function orderMedicines(Request $request)
    {


        $ids = $request->ids ?? '';
        $search = $request->_search ?? '';
        $category = $request->_category ?? '';
        $c_id = $request->_c_id ?? 0;
        $ph_id = $request->_ph_id ?? 0;
        $om_status = $request->_om_status ?? '';
        $status = $request->_status ?? '';
        $i_status = $request->_i_status ?? '';
        $cat_id = $request->_cat_id ?? 0;
        $o_delivered = $request->_o_delivered ?? '';
        $orderBy = $request->_orderBy ?? '';
        $page = $request->_page ?? 1;
        $perPage = $request->_perPage ?? 20;
        $order = (($request->_order) && ('DESC' == $request->_order))  ? 'DESC' : 'ASC';

        $query =  DB::select('tom.*', DB::raw('(100 - (tom.s_price/tom.m_d_price*100)) as supplier_percent'), 'tm.m_name', 'tm.m_form', 'tm.m_strength', 'tr.o_created', 'tr.o_delivered', 'tr.o_status', 'tr.o_i_status')
            ->table('t_o_medicines AS tom')
            ->join('t_medicines AS tm', 'tom.m_id', '=', 'tm.m_id')
            ->join('t_orders AS tr', 'tom.o_id', '=', 'tom.o_id = tr.o_id');

        //  $db->add( 'SELECT SQL_CALC_FOUND_ROWS tom.*, (100 - (tom.s_price/tom.m_d_price*100)) as supplier_percent, tm.m_name, tm.m_form, tm.m_strength, tr.o_created, tr.o_delivered, tr.o_status, tr.o_i_status FROM t_o_medicines tom INNER JOIN t_medicines tm ON tom.m_id = tm.m_id INNER JOIN t_orders tr ON tom.o_id = tr.o_id WHERE 1=1' );


         if ( $search ) {
             if( \is_numeric( $search ) ) {
                 $search = addcslashes( $search, '_%\\' );
                 $query = $query->where('tom.o_id', 'LIKE', "%{$search}%" );
             } else {
                 $search = addcslashes( $search, '_%\\' );
                 $query = $query->where('ttm.m_name', 'LIKE', "%{$search}%" );
             }
         }
         if( $category ) {
             $query = $query->where( 'tm.m_category', '=', $category );
         }
         if( $c_id ) {
             $query = $query->where( 'tm.m_c_id', '=', $c_id );
         }
         if ( $cat_id ) {
             $query = $query->where( 'tm.m_cat_id', '=', $cat_id );
         }
         if( $ph_id ) {
             $query = $query->where( 'tr.o_ph_id', '=', $ph_id );
         }
         if( $ids ) {
             $ids = array_filter( array_map( 'intval', array_map( 'trim', explode( ',', $ids ) ) ) );
             $query = $query->whereIn( "tom.om_id", $ids );
             $perPage = count($ids);
         }

         if( $status ) {
             $query = $query->where( 'tr.o_status', '=', $status );
         }
         if( $i_status ) {
             $query = $query->where( 'tr.o_i_status', '=', $i_status );
         }
         if( $om_status ) {
             $query = $query->where( 'tom.om_status', '=', $om_status );
         }

         if( $o_delivered ) {
             $query = $query->where([['tr.o_delivered', '>=', $o_delivered. ' 00:00:00'], ['tr.o_delivered', '<=', $o_delivered . ' 23:59:59']]);
         }

         if( $orderBy && \in_array( $orderBy, ['o_id', 'm_qty', 'm_unit', 'm_price', 'm_d_price', 's_price', 'om_status'] ) ) {
             $query = $query->orderBy( "tom.{$orderBy}", $order);
         } elseif( $orderBy && \in_array( $orderBy, ['m_name', 'm_form', 'm_strength'] ) ) {
             $query = $query->orderBy( "tm.{$orderBy}", $order);
//             $db->add( " ORDER BY tm.{$orderBy} $order" );
         } elseif( $orderBy && \in_array( $orderBy, ['o_created', 'o_delivered', 'o_status'] ) ) {
             $query = $query->orderBy( "tr.{$orderBy}", $order);
//             $db->add( " ORDER BY tr.{$orderBy} $order" );
         } elseif( $orderBy && \in_array( $orderBy, ['supplier_percent'] ) ) {
             $query = $query->orderBy( "supplier_percent", $order);
//             $db->add( " ORDER BY supplier_percent $order" );
         }

        $limit    = $perPage * ($page - 1);
        $query = $query->limit($perPage)->offset($limit);
        $data = $query->get();
        $total = $query->count();


        while ($data) {
            $data['id'] = $data['om_id'];
            $data['m_price_total'] = \round($data['m_qty'] * $data['m_price'], 2);
            $data['m_d_price_total'] = \round($data['m_qty'] * $data['m_d_price'], 2);
            $data['s_price_total'] = \round($data['m_qty'] * $data['s_price'], 2);
            $data['supplier_percent'] = \round($data['supplier_percent'], 1) . '%';
            unset($data['o_delivered']);
            $data['attachedFiles'] = getPicUrlsAdmin(Meta::get('medicine', $data['m_id'], 'images'));

            return response()->json([
                'data' => $data
            ]);
        }

        if (!$data) {
            return response()->json('No orders Found');
        } else {
            return response()->json([
                'status' => 'success',
                'total' => $total,
            ]);
        }
    }


    function orderMedicineSingle($om_id)
    {
        $om = DB::table('t_o_medicines')
            ->leftJoin('t_medicines', 't_o_medicines.m_id', '=', 't_medicines.m_id')
            ->where('m_id', $om_id)
            ->first();
        if ($om) {
            $data = $om;
            $data['id'] = $om['om_id'];
            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } else {
            return response()->json('No order medicine Found');
        }
    }


    public function orderMedicineUpdate(Request $request, $om_id)
    {
        $s_price = $request->s_price ? \round($request->s_price, 2) : 0.00;
        $om_status = $request->om_status ? $request->om_status : '';

        DB::instance()->update('t_o_medicines', ['s_price' => $s_price, 'om_status' => $om_status], ['om_id' => $om_id]);

        $this->orderMedicineSingle($om_id);
    }

    public function orderMedicineDelete($om_id)
    {
        return response()->json('Deleting not alowed. Delete from Order Edit page.');
    }




    public function laterMedicines(Request $request)
    {
        $ids = $request->ids ? $request->ids : '';
        $search = $request->_search ? $request->_search : '';
        $c_id = $request->_c_id ? $request->_c_id : 0;
        $g_id = $request->_g_id ? $request->_g_id : 0;
        $ph_id = $request->_ph_id ? $request->_ph_id : 0;
        $u_id = $request->_u_id ? $request->_u_id : 0;
        $orderBy = $request->_orderBy ? $request->_orderBy : '';
        $order = $request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage = $request->_perPage ? $request->_perPage : 20;
        $cat_id = $request->_cat_id ? $request->_cat_id : 0;
        $not_assigned = $request->_not_assigned ? $request->_not_assigned : false;

        if (!$ph_id && 'pharmacy' == $this->user->u_role) {
            $ph_id = Auth::id();
        }

        // $db = new DB;
        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS tlm.*, tm.m_name, tm.m_unit, tm.m_form, tm.m_strength, tm.m_g_id, tm.m_c_id, ti.wkly_req FROM t_later_medicines AS tlm INNER JOIN t_medicines AS tm ON tlm.m_id = tm.m_id LEFT JOIN t_inventory AS ti ON tlm.m_id = ti.i_m_id  WHERE 1 = 1' );



        // if ( $search ) {
        //     $search = addcslashes( $search, '_%\\' );
        //     $db->add( ' AND tm.m_name LIKE ?', "{$search}%" );
        // }
        // if( $g_id ) {
        //     $db->add( ' AND tm.m_g_id = ?', $g_id );
        // }
        // if( $c_id ) {
        //     $db->add( ' AND tm.m_c_id = ?', $c_id );
        // }
        // if ( $cat_id ) {
        //     $db->add( ' AND tm.m_cat_id = ?', $cat_id );
        // }
        // if( $ph_id ) {
        //     $db->add( ' AND tlm.o_ph_id = ?', $ph_id );
        // }
        // if( $u_id ) {
        //     $db->add( ' AND tlm.u_id = ?', $u_id );
        // }
        // if( $not_assigned ) {
        //     $db->add( ' AND tlm.u_id = ?', 0 );
        // }
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND tlm.lm_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }

        // if ( $orderBy && \in_array( $orderBy, ['m_name', 'm_form', 'm_g_id', 'm_c_id', 'm_strength', 'm_unit', 'm_price', 'm_d_price' ] ) ) {
        //     $db->add( " ORDER BY tm.{$orderBy} $order" );
        // } elseif( $orderBy && \in_array($orderBy, ['wkly_req'] ) ) {
        //     $db->add( " ORDER BY ti.{$orderBy} $order" );
        // } elseif( $orderBy && \in_array( $orderBy, ['o_created', 'total_qty', 'u_id'] ) ) {
        //     $db->add( " ORDER BY tlm.{$orderBy} $order" );
        // }

        $limit    = $perPage * ($page - 1);
        $total = DB::table('t_later_medicines')
            ->leftJoin('t_medicines', 't_later_medicines.m_id', '=', 't_medicines.m_id')
            ->leftJoin('t_inventory', 't_inventory.i_m_id', '=', 't_medicines.m_id')
            ->limit($perPage)
            ->offset($limit)
            ->get()->count();
        $datas = DB::table('t_later_medicines')
            ->leftJoin('t_medicines', 't_later_medicines.m_id', '=', 't_medicines.m_id')
            ->leftJoin('t_inventory', 't_inventory.i_m_id', '=', 't_medicines.m_id')
            ->limit($perPage)
            ->offset($limit)
            ->get();

        foreach ($datas as $data) {
            $data['purchaseAssigned'] = User::getName($data['u_id']);
            $data['id'] = $data['lm_id'];
            $data['m_generic'] = GenericV1::getName($data['m_g_id']);
            $data['m_company'] = Company::getName($data['m_c_id']);
            return response()->json([
                '' => $data
            ]);
        }

        if ($datas) {
            return response()->json([
                'status' => 'success',
                'total' => $total,
            ]);
        } else {
            return response()->json('No Medicines Found');
        }
    }

    public function inventory(Request $request)
    {
        // if( ! $this->user->can( 'inventoryView' ) ) {
        //     return response()->json( 'Your account does not have inventory view capabilities.');
        // }

        $ids = $request->ids ? $request->ids : '';
        $search = $request->_search ? $request->_search : '';
        $category = $request->_category ? $request->_category : '';
        $c_id = $request->_c_id ? $request->_c_id : 0;
        $ph_id = $request->_ph_id ? $request->_ph_id : 0;
        $qty = $request->_qty ? $request->_qty : '';
        $orderBy = $request->_orderBy ? $request->_orderBy : '';
        $order = $request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage = $request->_perPage ? $request->_perPage : 20;
        $cat_id = $request->_cat_id ? $request->_cat_id : 0;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS ti.*, (100 - (ti.i_price/tm.m_price*100)) as discount_percent, (100 - (ti.i_price/tm.m_d_price*100)) as profit_percent, (ti.i_qty*ti.i_price) as i_price_total, ROUND(ti.i_qty/(ti.wkly_req/7)) as stock_days, tm.m_id, tm.m_name, tm.m_form, tm.m_unit, tm.m_strength, tm.m_price, tm.m_d_price FROM t_inventory ti INNER JOIN t_medicines tm ON ti.i_m_id = tm.m_id WHERE 1=1' );



        // if ( $search ) {
        //     $search = addcslashes( $search, '_%\\' );
        //     $db->add( ' AND tm.m_name LIKE ?', "{$search}%" );
        // }
        // if( $category ) {
        //     $db->add( ' AND tm.m_category = ?', $category );
        // }
        // if( $c_id ) {
        //     $db->add( ' AND tm.m_c_id = ?', $c_id );
        // }
        // if ( $cat_id ) {
        //     $db->add( ' AND tm.m_cat_id = ?', $cat_id );
        // }
        // if( $ph_id ) {
        //     $db->add( ' AND ti.i_ph_id = ?', $ph_id );
        // }
        // if( '<0' == $qty ) {
        //     $db->add( ' AND ti.i_qty < ?', 0 );
        // } elseif( 'zero' == $qty ) {
        //     $db->add( ' AND ti.i_qty = ?', 0 );
        // } elseif( '>0' == $qty ) {
        //     $db->add( ' AND ti.i_qty > ?', 0 );
        // } elseif( '>100' == $qty ) {
        //     $db->add( ' AND ti.i_qty > ?', 100 );
        // } elseif( '1-10' == $qty ) {
        //     $db->add( ' AND ti.i_qty BETWEEN ? AND ?', 1, 10 );
        // } elseif( '11-100' == $qty ) {
        //     $db->add( ' AND ti.i_qty BETWEEN ? AND ?', 11, 100 );
        // }
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND ti.i_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }

        // if( $orderBy && \in_array( $orderBy, [ 'm_name', 'm_form', 'm_unit', 'm_strength', 'm_price', 'm_d_price'] ) ) {
        //     $db->add( " ORDER BY tm.{$orderBy} $order" );
        // } elseif( $orderBy && \in_array($orderBy, ['discount_percent', 'profit_percent', 'i_price_total', 'stock_days'] ) ) {
        //     $db->add( " ORDER BY $orderBy $order" );
        // } elseif( $orderBy ) {
        //     $db->add( " ORDER BY ti.{$orderBy} $order" );
        // }

        $limit    = $perPage * ($page - 1);
        $total = DB::table('t_inventory')->limit($perPage)
            ->offset($limit)
            ->get()->count();

        $datas = DB::table('t_inventory')->limit($perPage)
            ->offset($limit)
            ->get();

        foreach ($datas as $data) {
            $data['id'] = $data['i_id'];
            $data['ph_name'] = User::getName($data['i_ph_id']);
            $data['i_price'] = \round($data['i_price'], 2);
            $data['i_price_total'] = \round($data['i_price_total']);
            $data['discount_percent'] = \round($data['discount_percent'], 1) . '%';
            $data['profit_percent'] = \round($data['profit_percent'], 1) . '%';
            $data['attachedFiles'] = getPicUrlsAdmin(Meta::get('medicine', $data['m_id'], 'images'));
            return response()->json([
                '' => $data
            ]);
        }

        if ($datas) {
            return response()->json([
                'status' => 'success',
                'total' => $total,
            ]);
        } else {
            return response()->json('No inventory items Found');
        }
    }

    function inventorySingle($i_id)
    {
        if ($inventory = Inventory::getInventory($i_id)) {
            $data = $inventory->toArray();
            $data['id'] = $inventory->i_id;
            $data['i_price'] = \round($data['i_price'], 2);
            $data['i_note'] = (string)($inventory->getMeta('i_note'));

            if ($medicine = Medicine::getMedicine($inventory->i_m_id)) {
                $data['m_name'] = $medicine->m_name;
                $data['m_form'] = $medicine->m_form;
                $data['m_unit'] = $medicine->m_unit;
                $data['m_strength'] = $medicine->m_strength;
            }

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        } else {
            return response()->json('No inventory items Found');
        }
    }


    public function inventoryUpdate(Request $request, $i_id)
    {
        // if( ! $this->user->can( 'inventoryEdit' ) ) {
        //     return response()->json( 'Your account does not have inventory edit capabilities.');
        // }
        $i_price = $request->i_price ? \round($request->i_price, 4) : 0.0000;
        $i_qty = $request->i_qty ? \intval($request->i_qty) : 0;

        $qty_damage = $request->qty_damage ? \intval($request->qty_damage) : 0;
        $qty_lost = $request->qty_lost ? \intval($request->qty_lost) : 0;
        $qty_found = $request->qty_found ? \intval($request->qty_found) : 0;

        if ($inventory = Inventory::getInventory($i_id)) {
            if (($qty_damage || $qty_lost || $qty_found) && ($medicine = Medicine::getMedicine($inventory->i_m_id))) {
                $note = [];
                if ($qty_damage) {
                    $note[] = sprintf('%s: Damage: %s (Change %s to %s)', \date('Y-m-d H:i:s'), qtyTextClass($qty_damage, $medicine), qtyTextClass($i_qty, $medicine), qtyTextClass($i_qty - $qty_damage, $medicine));
                    $i_qty -= $qty_damage;
                }
                if ($qty_lost) {
                    $note[] = sprintf('%s: Lost: %s (Change %s to %s)', \date('Y-m-d H:i:s'), qtyTextClass($qty_lost, $medicine), qtyTextClass($i_qty, $medicine), qtyTextClass($i_qty - $qty_lost, $medicine));
                    $i_qty -= $qty_lost;
                }
                if ($qty_found) {
                    $note[] = sprintf('%s: Found: %s (Change %s to %s)', \date('Y-m-d H:i:s'), qtyTextClass($qty_found, $medicine), qtyTextClass($i_qty, $medicine), qtyTextClass($i_qty + $qty_found, $medicine));
                    $i_qty += $qty_found;
                }
                if ($note) {
                    $note = array_filter(array_merge([$inventory->getMeta('i_note')], $note));
                    $inventory->setMeta('i_note', implode("\n", $note));
                }
            }
            $inventory->i_price = $i_price;
            $inventory->i_qty = $i_qty;
            $inventory->update();
        }

        $this->inventorySingle($i_id);
    }


    public function inventoryDelete($i_id)
    {
        return response()->json('Inventory cannot be deleted.');

        if (!$this->user->can('inventoryEdit')) {
            return response()->json('Your account does not have inventory edit capabilities.');
        }
        if ($inventory = Inventory::getInventory($i_id)) {
            $inventory->delete();
            return response()->json([
                'status' => 'success',
                'id' => $i_id,
            ]);
        } else {
            return response()->json('No inventory items Found');
        }
    }


    public function inventoryBalance()
    {
        // $query = DB::db()->query( 'SELECT SUM(i_price*i_qty) as totalBalance FROM t_inventory' );
        // $balance = $query->fetchColumn();
        $balance = Inventory::get()->sum('i_price*i_qty');
        $data = [
            'totalBalance' => \round($balance, 2),
        ];
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }



    public function purchases(Request $request)
    {
        // if( ! $this->user->can( 'purchasesView' ) ) {
        //     return response()->json( 'Your account does not have purchases view capabilities.');
        // }

        $ids = $request->ids ? $request->ids : '';
        $search = $request->_search ? $request->_search : '';
        $category = $request->_category ? $request->_category : '';
        $c_id = $request->_c_id ? $request->_c_id : 0;
        $ph_id = $request->_ph_id ? $request->_ph_id : 0;
        $status = $request->_status ? $request->_status : '';
        $expiry = $request->_expiry ? $request->_expiry : '';
        $orderBy = $request->_orderBy ? $request->_orderBy : '';
        $order = $request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage = $request->_perPage ? $request->_perPage : 20;
        $cat_id = $request->_cat_id ? $request->_cat_id : 0;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS tpu.*, (100 - (tpu.pu_price/tm.m_price*100)) as discount_percent, (100 - (tpu.pu_price/tm.m_d_price*100)) as profit_percent, (tpu.pu_qty*tpu.pu_price) as pu_price_total, tm.m_id, tm.m_name, tm.m_form, tm.m_strength, tm.m_price, tm.m_d_price, ti.wkly_req FROM t_purchases tpu INNER JOIN t_medicines tm ON tpu.pu_m_id = tm.m_id LEFT JOIN t_inventory ti ON tpu.pu_ph_id = ti.i_ph_id AND tpu.pu_m_id = ti.i_m_id WHERE 1=1' );

        // if ( $search ) {
        //     $search = addcslashes( $search, '_%\\' );
        //     if( 0 === \stripos( $search, 'i-' ) ){
        //         $search = substr( $search, 2 );
        //         if( $search )
        //         $db->add( ' AND tpu.pu_inv_id = ?', $search );
        //     } elseif( 0 === \stripos( $search, 'b-' ) ){
        //         $search = substr( $search, 2 );
        //         if( $search )
        //         $db->add( ' AND tpu.m_batch = ?', $search );
        //     } else {
        //         $db->add( ' AND tm.m_name LIKE ?', "{$search}%" );
        //     }
        // }
        // if( $category ) {
        //     $db->add( ' AND tm.m_category = ?', $category );
        // }
        // if( $c_id ) {
        //     $db->add( ' AND tm.m_c_id = ?', $c_id );
        // }
        // if ( $cat_id ) {
        //     $db->add( ' AND tm.m_cat_id = ?', $cat_id );
        // }
        // if( $ph_id ) {
        //     $db->add( ' AND tpu.pu_ph_id = ?', $ph_id );
        // }
        // if( $status ) {
        //     $db->add( ' AND tpu.pu_status = ?', $status );
        // }
        // if( 'expired' == $expiry ) {
        //     $db->add( ' AND tpu.m_expiry BETWEEN ? AND ?', '0000-00-00', \date( 'Y-m-d H:i:s' ) );
        // } elseif( 'n3' == $expiry ){
        //     $db->add( ' AND tpu.m_expiry BETWEEN ? AND ?', \date( 'Y-m-d H:i:s' ), \date( 'Y-m-d H:i:s', strtotime("+3 months") ) );
        // } elseif( 'n6' == $expiry ){
        //     $db->add( ' AND tpu.m_expiry BETWEEN ? AND ?', \date( 'Y-m-d H:i:s' ), \date( 'Y-m-d H:i:s', strtotime("+6 months") ) );
        // }
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND tpu.pu_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }

        // if( $orderBy && \in_array( $orderBy, [ 'm_name', 'm_form', 'm_strength', 'm_price', 'm_d_price'] ) ) {
        //     $db->add( " ORDER BY tm.{$orderBy} $order" );
        // } elseif( $orderBy && \in_array($orderBy, ['discount_percent', 'profit_percent', 'pu_price_total'] ) ) {
        //     $db->add( " ORDER BY $orderBy $order" );
        // } elseif( $orderBy && \in_array($orderBy, ['wkly_req'] ) ) {
        //     $db->add( " ORDER BY ti.{$orderBy} $order" );
        // } elseif( $orderBy ) {
        //     $db->add( " ORDER BY tpu.{$orderBy} $order" );
        // }

        $limit    = $perPage * ($page - 1);

        $total = DB::table('t_purchases')
            ->leftJoin('t_medicines', 't_purchases.m_id', '=', 't_medicines.m_id')
            ->leftJoin('t_inventory', 't_inventory.pu_m_id', '=', 't_medicines.m_id')
            ->limit($perPage)
            ->offset($limit)
            ->get()->count();
        $datas = DB::table('t_purchases')
            ->leftJoin('t_medicines', 't_purchases.m_id', '=', 't_medicines.m_id')
            ->leftJoin('t_inventory', 't_inventory.pu_m_id', '=', 't_medicines.m_id')
            ->limit($perPage)
            ->offset($limit)
            ->get();

        foreach ($datas as $data) {
            $data['id'] = $data['pu_id'];
            $data['ph_name'] = User::getName($data['pu_ph_id']);
            $data['pu_price'] = \round($data['pu_price'], 2);
            $data['pu_price_total'] = \round($data['pu_price_total']);
            $data['discount_percent'] = \round($data['discount_percent'], 1) . '%';
            $data['profit_percent'] = \round($data['profit_percent'], 1) . '%';
            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        }

        if ($datas) {
            return response()->json([
                'status' => 'success',
                'total' => $total,
            ]);
        } else {
            return response()->json('No purchases items Found');
        }
    }



    public function purchaseCreate(Request $request)
    {
        $items = $request->items ? $request->items : '';
        if (!\is_array($items)) {
            return response()->json('Items are malformed.');
        }

        $ph_id = $request->pu_ph_id ? \intval($request->pu_ph_id) : 0;
        if (!$ph_id) {
            return response()->json('No pharmacy selected.');
        }

        $pu_inv_id = $request->pu_inv_id ? $request->pu_inv_id : '';
        if (!$pu_inv_id) {
            $pu_inv_id = Purchase::get()->max('pu_inv_id');
        }

        $d_percent = $request->d_percent ? \round($request->d_percent, 8) : 0;

        $insert = [];
        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }
            $m_id = isset($item['pu_m_id']) ? \intval($item['pu_m_id']) : 0;
            $pu_price = isset($item['pu_price']) ? \round($item['pu_price'], 4) : 0.0000;
            $pu_qty = isset($item['pu_qty']) ? \intval($item['pu_qty']) : 0;
            $m_unit = isset($item['m_unit']) ? filter_var($item['m_unit'], FILTER_SANITIZE_STRING) : '';
            $expMonth = isset($item['expMonth']) ? \intval($item['expMonth']) : 0;
            $expYear = isset($item['expYear']) ? \intval($item['expYear']) : 0;
            $batch = (isset($item['batch']) && 'undefined' != $item['batch']) ? filter_var($item['batch'], FILTER_SANITIZE_STRING) : '';

            $exp = '0000-00-00';
            if ($expMonth && $expYear && checkdate($expMonth, 1, $expYear)) {
                $exp = $expYear . '-' . $expMonth . '-01';
            }

            if (!$m_id) {
                continue;
            }

            if ($pu_price && $d_percent) {
                $pu_price = $pu_price - (($pu_price * $d_percent) / 100);
            }

            if ($pu_qty) {
                $per_price = \round($pu_price / $pu_qty, 4);
            } else {
                $per_price = 0.0000;
            }

            $insert[] = [
                'pu_inv_id' => $pu_inv_id,
                'pu_ph_id' => $ph_id,
                'pu_m_id' => $m_id,
                'pu_price' => $per_price,
                'pu_qty' => $pu_qty,
                'm_unit' => $m_unit,
                'pu_created' => \date('Y-m-d H:i:s'),
                'm_expiry' => $exp,
                'm_batch' => $batch,
                //'pu_status' => 'pending', //default
            ];
        }
        $id = 0;
        if ($insert) {
            $id = DB::instance()->insertMultiple('t_purchases', $insert);
        }

        return response()->json([
            'status' => 'success',
            'id' => $id
        ]);
    }



    function purchaseSingle($pu_id)
    {
        if (!$pu_id) {
            return response()->json('No purchase item Found');
        }

        $data = DB::table('t_purchases')
            ->innerJoin('t_medicines', 't_purchases.m_id', '=', 't_medicines.m_id')
            ->where('t_purchases.pu_m_id', '=', 't_medicines.m_id')
            ->get();
        if ($data) {
            $data['id'] = $data['pu_id'];
            $data['pu_price'] = \round($data['pu_price'], 2);
            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        } else {
            return response()->json('No purchase item Found');
        }
    }


    public function purchaseUpdate(Request $request, $pu_id)
    {
        if (!$pu_id) {
            return response()->json('No purchase items Found');
        }
        $query = DB::db()->prepare('SELECT * FROM t_purchases WHERE pu_id = ? LIMIT 1');
        $query->execute([$pu_id]);
        $purchase = $query->fetch();



        $pu_price = $request->pu_price ? \round($request->pu_price, 4) : 0.0000;
        $pu_qty = $request->pu_qty ? \intval($request->pu_qty) : 0;
        $m_expiry = $request->m_expiry ? filter_var($request->m_expiry, FILTER_SANITIZE_STRING) : '0000-00-00';
        $m_batch = $request->m_batch  && 'undefined' != $request->m_batch ? filter_var($request->m_batch, FILTER_SANITIZE_STRING) : '';

        $data = [
            'm_expiry' => $m_expiry,
            'm_batch' => $m_batch
        ];
        if ($purchase && $purchase['pu_status'] != 'sync') {
            $data['pu_price'] = $pu_price;
            $data['pu_qty'] = $pu_qty;
        }

        $updated = DB::instance()->update('t_purchases', $data, ['pu_id' => $pu_id]);

        $this->purchaseSingle($pu_id);
    }


    public function purchaseDelete($pu_id)
    {
        if (!$pu_id) {
            return response()->json('No purchase item Found');
        }
        $data = Purchase::where('pu_id', $pu_id)->first();

        if ($data && 'sync' == $data['pu_status']) {
            return response()->json('You can not delete this purchase anymore');
        }

        $deleted = DB::instance()->delete('t_purchases', ['pu_id' => $pu_id]);

        if ($deleted) {
            return response()->json([
                'status' => 'success',
                'id' => $pu_id
            ]);
        } else {
            return response()->json('No purchase item Found');
        }
    }


    public function purchasesPendingTotal()
    {

        $data = Purchase::where('pu_status', '=', 'pending')->get()->sum('pu_price*pu_qty');

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }


    public function purchasesSync(Request $request)
    {
        $ph_m_ids = [];
        $inv_ids = [];
        $pu_inv_id = $request->pu_inv_id ?? 0;
        if (!$pu_inv_id) {
            return response()->json('No invoice selected');
        }
        try {
            $pu = Purchase::where('pu_inv_id', $pu_inv_id)->where('pu_status', '=', 'pending')->get();

            $insert = [];
            while ($pu) {
                $ph_m_ids[$pu['pu_ph_id']][] = $pu['pu_m_id'];
                if (!isset($inv_ids[$pu['pu_inv_id']])) {
                    $inv_ids[$pu['pu_inv_id']] = 0;
                }
                $inv_ids[$pu['pu_inv_id']] += $pu['pu_price'] * $pu['pu_qty'];

                if ($inventory = Inventory::getByPhMid($pu['pu_ph_id'], $pu['pu_m_id'])) {
                    if (($inventory->i_qty + $pu['pu_qty'])) {
                        $inventory->i_price = (($inventory->i_price * $inventory->i_qty) + ($pu['pu_price'] * $pu['pu_qty'])) / ($inventory->i_qty + $pu['pu_qty']);
                    } else {
                        $inventory->i_price = '0.00';
                    }
                    $inventory->i_qty = $inventory->i_qty + $pu['pu_qty'];
                    $inventory->update();
                } else {
                    $insert[] = [
                        'i_ph_id' => $pu['pu_ph_id'],
                        'i_m_id' => $pu['pu_m_id'],
                        'i_price' => $pu['pu_price'],
                        'i_qty' => $pu['pu_qty'],
                    ];
                }
            }
            if ($insert) {
                $i_id = DB::instance()->insertMultiple('t_inventory', $insert);
            }
            if ($inv_ids) {
                foreach ($inv_ids as $inv_id => $amount) {
                    $reason = \sprintf('Payment for Invoice %s', $inv_id);
                    ledgerCreate($reason, -$amount, 'purchase');
                    DB::instance()->update('t_purchases', ['pu_status' => 'sync'], ['pu_inv_id' => $inv_id]);
                }
            }
            foreach ($ph_m_ids as $ph_id => $m_ids) {
                DB::instance()->delete('t_later_medicines', ['o_ph_id' => $ph_id, 'm_id' => $m_ids]);

                $medicine = Medicine::where('m_id', $m_ids)->where('m_rob', 0)->get();
                while ($medicine) {
                    $medicine->updateCache();
                    $medicine->m_rob = 1;
                    $medicine->update();
                }
            }
            DB::db()->commit();
        } catch (\PDOException $e) {
            DB::db()->rollBack();
            \error_log($e->getMessage());
            return response()->json('Something wrong, Please try again.');
        }

        checkOrdersForInventory($ph_m_ids);
        checkOrdersForPacking();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully sync.',
        ]);
    }


    public function collections(Request $request) {
        // if( ! $this->user->can( 'collectionsView' ) ) {
        //     return response()->json( 'Your account does not have collections view capabilities.');
        // }

        $ids = $request->ids ? $request->ids : '';
        $fm_id =$request->_fm_id ? $request->_fm_id : 0;
        $to_id = $request->_to_id ? $request->_to_id : 0;
        $status = $request->_status ? $request->_status : '';
        $orderBy = $request->_orderBy ? $request->_orderBy : '';
        $order = $request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page = $request->_page ? $request->_page : 1;
        $perPage =$request->_perPage ? $request->_perPage : 20;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS *, (co_amount - co_s_amount) AS profit FROM t_collections WHERE 1=1' );
        

        // if( $fm_id ) {
        //     $db->add( ' AND co_fid = ?', $fm_id );
        // }
        // if( $to_id ) {
        //     $db->add( ' AND co_tid = ?', $to_id );
        // }
        // if( $status ) {
        //     $db->add( ' AND co_status = ?', $status );
        // }
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND co_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }

        // if( $orderBy ) {
        //     $db->add( " ORDER BY {$orderBy} $order" );
        // }

        $limit    = $perPage * ( $page - 1 );
        $total= Collection::where('')->limit($perPage)->offset($limit)->get()->count();
        $data= Collection::where('')->limit($perPage)->offset($limit)->get();

        // $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        // $query = $db->execute();
        // $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();

        while( $data) {
            $data['id'] = $data['co_id'];
            $data['fm_name'] = User::getName( $data['co_fid'] );
            $data['to_name'] = User::getName( $data['co_tid'] );
            $data['profit'] = \round( $data['profit'], 2);

            return response()->json([
                'status' => 'success',
                'data' => $data,
            ]);
        }

        if ( $data ) {
            return response()->json([
                'status' => 'success',
                'total' => $total,
            ]);
        } else {
            return response()->json( 'No collections Found' );
        }
    }



    function collectionSingle( $co_id ) {
        $data= Collection::where('co_id', $co_id)->get();
        if( $data){
            $data['id'] = $data['co_id'];
            $data['fm_name'] = User::getName( $data['co_fid'] );
            $data['to_name'] = User::getName( $data['co_tid'] );
			$data['co_bag'] = maybeJsonDecode( $data['co_bag'] );
            $data['profit'] = \round( $data['co_amount'] - $data['co_s_amount'], 2);

            return response()->json([
                 'status'=>'success',
                 'data'=>$data,
            ]);
        } else {
            return response()->json( 'No items Found' );
        }

    }



    public function ledger(Request $request) {
        // if( ! $this->user->can( 'ledgerView' ) ) {
        //     return response()->json( 'Your account does not have ledger view capabilities.');
        // }
        $search =$request->_search ? $request->_search : '';
        $ids = $request->ids ? $request->ids : '';
        $u_id = $request->_u_id ? $request->_u_id : 0;
        $created =$request->_created ? $request->_created : '';
        $created_end =$request->_created_end ? $request->_created_end : '';
        $type =$request->_type ? $request->_type : '';
        $orderBy =$request->_orderBy ? $request->_orderBy : '';
        $order =$request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page =$request->_page ? $request->_page : 1;
        $perPage =$request->_perPage ? $request->_perPage : 20;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_ledger WHERE 1=1' );
        // if ( $search ) {
        //     $search = addcslashes( $search, '_%\\' );
        //     $db->add( ' AND l_reason LIKE ?', "%{$search}%" );
        // }
        // if( $u_id ) {
        //     $db->add( ' AND l_uid = ?', $u_id );
        // }
        // if( $created ) {
        //     $db->add( ' AND l_created >= ? AND l_created <= ?', $created . ' 00:00:00', ($created_end ?: $created) . ' 23:59:59' );
        // }
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND l_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }
        // if( $type ) {
        //     $db->add( ' AND l_type = ?', $type );
        // }

        // if( $orderBy ) {
        //     $db->add( " ORDER BY {$orderBy} $order" );
        // }

         $limit    = $perPage * ( $page - 1 );
        // $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        // $query = $db->execute();
        // $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        $total= Ledger::limit($perPage)->offset($limit)->get()->count();
        $data= Ledger::limit($perPage)->offset($limit)->get();

        while( $data ) {
            $data['id'] = $data['l_id'];
            $data['u_name'] = User::getName( $data['l_uid'] );
            $data['attachedFiles'] = getLedgerFiles(maybeJsonDecode( $data['l_files'] ) ) ;
            return response()->json([
                ''=>$data,
            ]);
        }

        if ( $data ) {
            return response()->json([
                 'status'=>'success',
                 'total'=>$total
            ]);
        } else {
            return response()->json( 'No items Found' );
        }
    }



    public function ledgerCreate( $reason, $amount, $type ) {
        if( \in_array( $type, ['collection', 'input', 'Share Money Deposit', 'Directors Loan', 'Other Credit'] ) ){
          $amount = \abs( $amount );
        } else {
          $amount = \abs( $amount ) * -1;
        }
        $data = [
          'l_uid' => Auth::id(),
          'l_created' => \date( 'Y-m-d H:i:s' ),
          'l_reason' => \mb_strimwidth( $reason, 0, 255, '...' ),
          'l_type'   => $type,
          'l_amount' => \round( $amount, 2 ),
        ];
        return Ledger::insert( 't_ledger', $data );
    }

    public function ledgerSingle( $l_id ) {
        $data= Ledger::where('l_id', $l_id)->first();
        if( $data){
            $data['id'] = $data['l_id'];
            $data['u_name'] = User::getName( $data['l_uid'] );
            $data['attachedFiles'] = getLedgerFiles(maybeJsonDecode( $data['l_files'] ) ) ;
            return response()->json([
                'status'=>'success',
                'data'=>$data, 
            ]);
        } else {
            return response()->json( 'No items Found' );
        }

    }

    public function ledgerUpdate(Request $request, $l_id ) {
        // if( ! $this->user->can( 'ledgerEdit' ) ) {
        //     return response()->json( 'Your account does not have ledger edit capabilities.');
        // }

        if( ! $l_id ){
            return response()->json( 'No items Found' );
        }

        if(empty($request->l_reason) || ! isset($request->l_amount) || empty($request->l_type) ) {
            return response()->json( 'All Fields Required' );
        }
        DB::instance()->update( 't_ledger', ['l_reason' =>$request->l_reason, 'l_type' =>$request->l_type, 'l_amount' => \round($request->l_amount, 2)], [ 'l_id' => $l_id ] );

        $attachedFiles = isset($request->attachedFiles) ? $request->attachedFiles : [];
        modifyLedgerFiles( $l_id, $attachedFiles );

        $this->ledgerSingle( $l_id );
    }


    public function ledgerDelete( $l_id ) {
        return response()->json( 'Deleting ledger item is not permitted' );

        if( ! $l_id ){
            return response()->json( 'No items Found' );
        }
        $deleted = DB::instance()->delete( 't_ledger', [ 'l_id' => $l_id ] );
        
        if( $deleted ){
            return response()->json([
                'status'=>'success', 
                'id' => $l_id,
            ]);
        } else {
            return response()->json( 'No items Found' );
        }
    }


    public function ledgerBalance(){
        $balance = Ledger::where('l_type', '!=', 'Credit')->get()->sum('l_amount') ;
        $data = [
            'totalBalance' => \round( $balance, 2 ),
        ];
        return response()->json([
            'status'=>'success', 
            'data' => $data,
        ]);
    }


    public function companies(Request $request) {
        $ids = $request->ids ? $request->ids : '';
        $search =$request->_search ? $request->_search : '';
        $orderBy =$request->_orderBy ? $request->_orderBy : '';
        $order =$request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page =$request->_page ? $request->_page : 1;
        $perPage =$request->_perPage ? $request->_perPage : 20;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_companies WHERE 1=1' );
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND c_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }
        // if ( $search ) {
        //     $search = addcslashes( $search, '_%\\' );
        //     $db->add( ' AND c_name LIKE ?', "{$search}%" );
        // }

        // if( $orderBy ) {
        //     $db->add( " ORDER BY {$orderBy} $order" );
        // }

        $limit    = $perPage * ( $page - 1 );
        $total=Company::limit($perPage)->offset($limit)->get()->count();
        $company=Company::limit($perPage)->offset($limit)->get();
        // $db->add( ' LIMIT ?, ?', $limit, $perPage );
        
        // $query = $db->execute();
        // $total = DB::db()->query('SELECT FOUND_ROWS()')->fetchColumn();
        // $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Company');

        while( $company) {
            $data = $company->toArray();
            $data['id'] = $company->c_id;

            return response()->json([
                'status'=>'success', 
                'data' => $data,
            ]);

        }

        if ( $data ) {
            return response()->json([
                'status'=>'success', 
                'total' => $total,
            ]);
        } else {
            return response()->json( 'No companies Found' );
        }
    }



    function companySingle( $c_id ) {
        if( $company = Company::getCompany( $c_id ) ){
            $data = $company->toArray();
            $data['id'] = $company->c_id;

            return response()->json([
                'status'=>'success', 
                'data' => $data,
            ]);

        } else {
            return response()->json( 'No items Found' );
        }

    }


    public function generics(Request $request) {
        $ids =$request->ids ? $request->ids : '';
        $search =$request->_search ? $request->_search : '';
        $orderBy =$request->_orderBy ? $request->_orderBy : '';
        $order =$request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page =$request->_page ? $request->_page : 1;
        $perPage =$request->_perPage ? $request->_perPage : 20;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_generics_v2 WHERE 1=1' );
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND g_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }
        // if ( $search ) {
        //     $search = addcslashes( $search, '_%\\' );
        //     $db->add( ' AND g_name LIKE ?', "{$search}%" );
        // }

        // if( $orderBy ) {
        //     $db->add( " ORDER BY {$orderBy} $order" );
        // }

        $limit    = $perPage * ( $page - 1 );
        $total= GenericV2::limit($perPage)->offset($limit)->get()->count();
        $generic= GenericV2::limit($perPage)->offset($limit)->get();

        while( $generic) {
            //$data = $generic->toArray();
            $data = [];
            $data['g_id'] = $generic->g_id;
            $data['g_name'] = $generic->g_name;
            $data['id'] = $generic->g_id;

            return response()->json([
                ''=>$data,
            ]);
        }

        if ( $data ) {
            return response()->json([
                'status'=>'success',
                'total'=>$total,
            ]);
        } else {
            return response()->json( 'No companies Found' );
        }
    }


    function genericSingle( $g_id ) {
        if( $generic = GenericV1::getGeneric( $g_id ) ){
            $data = $generic->toArray();
            $data['id'] = $generic->g_id;

            return response()->json([
                'status'=>'success',
                'data'=>$data,
            ]);
        } else {
            return response()->json( 'No items Found' );
        }

    }


    public function locations(Request $request) {
        $ids =$request->ids ? $request->ids : '';
        $search =$request->_search  ? $request->_search : '';
		$only_zones =$request->_only_zones ? filter_var($request->_only_zones, FILTER_VALIDATE_BOOLEAN ) : false;
        $orderBy =$request->_orderBy ? $request->_orderBy : '';
        $order =$request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page =$request->_page ? $request->_page : 1;
        $perPage =$request->_perPage ? $request->_perPage : 20;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_locations WHERE 1=1' );
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND l_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }
        // if ( $search ) {
        //     $search = addcslashes( $search, '_%\\' );
		// 	if( $only_zones ){
		// 		$db->add( ' AND l_zone LIKE ?', "{$search}%" );
		// 	} else {
		// 		$db->add( ' AND l_area LIKE ?', "{$search}%" );
		// 	}
        // }
		// if( $only_zones ){
		// 	$db->add( " GROUP BY l_zone" );
		// }

        // if( $orderBy ) {
        //     $db->add( " ORDER BY {$orderBy} $order" );
        // }

        $limit    = $perPage * ( $page - 1 );
        $total=Location::limit($perPage)->offset($limit)->get()->count();
        $data=Location::limit($perPage)->offset($limit)->get();
      
        while( $data) {
            $data['id'] = $data['l_id'];

            return response()->json([
                'status'=>'success',
                'data'=>$data,
            ]);
        }

        if ( $data ) {
            return response()->json([
                'status'=>'success',
                'total'=>$total,
            ]);
        } else {
            return response()->json( 'No companies Found' );
        }
    }

    function locationSingle( $l_id ) {
		$data = DB::instance()->select( 't_locations', [ 'l_id' => $l_id ] );
        if( $data ){
            $data['id'] = $data['l_id'];
            return response()->json([
                'status'=>'success',
                'data'=>$data,
            ]);
        } else {
            return response()->json( 'No items Found' );
        }

    }

    public function bags(Request $request) {
        $ids =$request->ids ? $request->ids : '';
		$l_id =$request->_l_id ? $request->_l_id : '';
        $zone =$request->_zone ? $request->_zone : '';
        $ph_id =$request->_ph_id ? $request->_ph_id : 0;
        $not_assigned =$request->_not_assigned ? filter_var( $request->_not_assigned, FILTER_VALIDATE_BOOLEAN ) : false;
		$hide_empty =$request->_hide_empty ? filter_var( $request->_hide_empty, FILTER_VALIDATE_BOOLEAN ) : false;
        $orderBy =$request->_orderBy ? $request->_orderBy : '';
        $order =$request->_order && 'DESC' == $request->_order ? 'DESC' : 'ASC';
        $page =$request->_page ? $request->_page : 1;
        $perPage =$request->_perPage ? $request->_perPage : 25;

        // $db = new DB;

        // $db->add( 'SELECT SQL_CALC_FOUND_ROWS * FROM t_bags WHERE 1=1' );
        
        // if( $zone ) {
        //     $db->add( ' AND b_zone = ?', $zone );
        // } elseif( $l_id && ( $zone = getZoneByLocationId( $l_id ) ) ){
		// 	$db->add( ' AND b_zone = ?', $zone );
		// }
        // if( $ph_id || 'pharmacy' == $this->user->u_role ) {
        //     $db->add( ' AND b_ph_id = ?', $ph_id ?: $this->user->u_id );
        // }
        // if( $ph_id ) {
        //     $db->add( ' AND b_ph_id = ?', $ph_id );
        // }
		// if( $not_assigned ){
		// 	$db->add( ' AND b_de_id = ?', 0 );
		// }
		// if( $hide_empty ){
		// 	$db->add( ' AND o_count > ?', 0 );
		// }
        // if( $ids ) {
        //     $ids = \array_filter( \array_map( 'intval', \array_map( 'trim', \explode( ',', $ids ) ) ) );
        //     $in  = str_repeat('?,', count($ids) - 1) . '?';
        //     $db->add( " AND b_id IN ($in)", ...$ids );

        //     $perPage = count($ids);
        // }

        // if( $orderBy ) {
        //     $db->add( " ORDER BY $orderBy $order" );
        // }

        $limit    = $perPage * ( $page - 1 );
        $total= Bag::limit($perPage)->offset($limit)->get()->count();
        $bag= Bag::limit($perPage)->offset($limit)->get();
        while( $bag) {
			$data = $bag->toArray();
            $data['id'] = $data['b_id'];
            $data['assign_name'] = User::getName( $data['b_de_id'] );
			$data['invoiceUrl'] = \sprintf( SITE_URL . '/v1/invoice/bag/%d/%s/', $data['b_id'], jwtEncode( ['b_id' => $data['b_id'], 'exp' => time() + 60 * 60 ] ) );

            return response()->json([
                'status'=>'success',
                'data'=>$data,
            ]);
        }

        if ( ! $data ) {
            return response()->json( 'No bags Found' );
        } else {
            return response()->json([
                'status'=>'success',
                'total'=>$total,
            ]);
        }
    }


    public function bagCreate(Request $request) {
		// if( 'administrator' !== $this->user->u_role ) {
        //     return response()->json( 'You cannot add new bag' );
		// }

		$b_ph_id =$request->b_ph_id ? $request->b_ph_id : 0;
		$b_zone =$request->b_zone ? $request->b_zone : '';

		if( !$b_ph_id || !$b_zone ) {
            return response()->json( 'All Fields Required' );
        }
		$zones = getPharmacyZones( $b_ph_id );
		if( ! in_array( $b_zone, $zones ) ){
			return response()->json( 'Zone not exists.' );
		}
		$b_no = (int)DB::instance()->select( 't_bags', [ 'b_ph_id' => $b_ph_id, 'b_zone' => $b_zone ], 'MAX(b_no)' )->fetchColumn();

		$data = $_POST;
		$data['b_no'] = $b_no + 1;
		$bag = new Bag;
		$b_id = $bag->insert( $data );

        if( ! $b_id ){
			return response()->json( 'Something wrong, Please try again.' );
		}
        
        $this->bagSingle( $b_id );
    }
    function bagSingle( $b_id ) {
        if( $bag = Bag::getBag( $b_id ) ){
			$data = $bag->toArray();
            $data['id'] = $data['b_id'];

            return response()->json([
                'status'=>'success',
                'data'=>$data,
            ]);
        } else {
            return response()->json( 'No order medicine Found' );
        }

    }


    public function bagUpdate(Request $request, $b_id ) {
		// if( ! in_array( $this->user->u_role, [ 'administrator', 'pharmacy' ] ) ){
        //     return response()->json( 'You cannot assign deliveryman' );
        // }
		$bag = Bag::getBag( $b_id );
		if( !$bag ){
            return response()->json( 'No bag found.' );
		}

		$b_de_id =$request->b_de_id ? $request->b_de_id : 0;
		$move_ids =$request->move_ids ?? [];
		$move_zone =$request->move_zone ?? '';
		$move_bag =$request->move_bag ?? 0;
		$is_move_checked = $request->is_move_checked ? filter_var( $request->is_move_checked , FILTER_VALIDATE_BOOLEAN ) : false;
		if( $is_move_checked ){
			$move_ids = \array_filter( \array_map( 'intval', \array_map( 'trim', $move_ids ) ) );
			if( !$move_ids || !is_array( $move_ids ) ){
				return response()->json( 'No ids selected.' );
			}
			if( !$move_zone || !$move_bag ){
				return response()->json( 'No zone/bag selected.' );
			}
			$zones = getPharmacyZones( $bag->b_ph_id );
			if( ! in_array( $move_zone, $zones ) ){
				return response()->json( 'Invalid zone.' );
			}
			$query = DB::instance()->select( 't_bags', [ 'b_ph_id' => $bag->b_ph_id, 'b_zone' => $move_zone, 'b_no' => $move_bag ] );
			$query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Bag');
			$newBag = $query->fetch();
			if( !$newBag ){
				return response()->json( 'No destination bag found.' );
			}
			if( $newBag->b_id === $bag->b_id ){
				return response()->json( 'You cannot move to same bag.' );
			}
			$all_o_ids = array_unique( array_merge( $newBag->o_ids, $move_ids ) );
			$newBag->update( [ 'o_ids' => $all_o_ids, 'o_count' => count( $all_o_ids ) ] );

			$diff_o_ids = array_values( array_diff( $bag->o_ids, $move_ids ) );
			if( $diff_o_ids ){
				$bag->update( [ 'o_ids' => $diff_o_ids, 'o_count' => count( $diff_o_ids ) ] );
			} else {
				$bag->release();
			}
			CacheUpdate::add_to_queue( $move_ids, 'order_meta');
			CacheUpdate::add_to_queue( $move_ids, 'order');
			CacheUpdate::update_cache( [], 'order_meta' );
			CacheUpdate::update_cache( [], 'order' );

			DB::instance()->update( 't_order_meta', [ 'meta_value' => $newBag->b_id ], [ 'o_id' => $move_ids, 'meta_key' => 'bag' ] );
			foreach ( $move_ids as $o_id ) {
				if( $order = Order::getOrder( $o_id ) ){
                    $cache=new Cache();
					$cache->delete( $o_id, 'order_meta' );

					$data = [];
					if( $newBag->b_de_id ){
						$data['o_de_id'] = $newBag->b_de_id;
					}
					if( $newBag->b_de_id && 'confirmed' == $order->o_status  ){
						$data['o_status'] = 'delivering';
					}
					if( $newBag->b_de_id && 'packed' == $order->o_is_status  ){
						$data['o_is_status'] = 'delivering';
					}
					$order->update( $data );
				}
			}
			$this->bagSingle( $b_id );
		}

		if( ! $b_de_id ){
			return response()->json( 'No deliveryman selected.' );
		}
		
		if( $bag->b_de_id ){
			return response()->json( 'Deliveryman is already assigned to this bag' );
		}
		$o_ids = $bag->o_ids;
		if( !$bag->o_count || !$o_ids ){
			return response()->json( 'No orders in this bag' );
		}
		if( $de_bag = Bag::deliveryBag( $bag->b_ph_id, $b_de_id ) ){
			return response()->json( 'This deliveryman is already assigned' );
		}
		$in  = str_repeat('?,', count($o_ids) - 1) . '?';
		$order_check = DB::db()->prepare( "SELECT COUNT(*) FROM t_orders WHERE ( o_i_status = ? OR o_is_status = ? ) AND o_id IN ($in)" );
        $order_check->execute( [ 'checking', 'checking', ...$o_ids ] );
        if( $order_check->fetchColumn() ){
            return response()->json( 'Some orders are in checking status, check them first.' );
        }

		if( $bag->update( [ 'b_de_id' => $b_de_id ] ) ){
			CacheUpdate::add_to_queue( $o_ids, 'order');
			CacheUpdate::add_to_queue( $o_ids, 'order_meta');
			CacheUpdate::update_cache( [], 'order' );
			CacheUpdate::update_cache( [], 'order_meta' );

			foreach ( $o_ids as $o_id ) {
				if( $order = Order::getOrder( $o_id ) ){
					$data = [
						'o_de_id' => $b_de_id,
					];
					if( 'confirmed' == $order->o_status  ){
						$data['o_status'] = 'delivering';
					}
					if( 'packed' == $order->o_is_status  ){
						$data['o_is_status'] = 'delivering';
					}
					$order->update( $data );
				}
			}
			$this->bagSingle( $b_id );
		}
		return response()->json( 'Something wrong, Please try again.' );
    }


    public function bagDelete( $b_id ) {
        return response()->json( 'Deleting not alowed.');
    }






    
}
