<?php

namespace App\Http\Controllers;

use App\Cache\Cache;
use App\Models\CacheUpdate;
use App\Models\GenericV1;
use App\Models\PDF;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
use App\Helpers\FPDF_Merge;
use App\Helpers\Response;

class RouteResponseController extends Controller
{
    public function dataInitial($version, $table, $page)
    {
        if (!in_array($version, ['v1', 'v2'])) {
            return notFoundURL();
        }

        $per_page = 300;
        $limit    = $per_page * ($page - 1);


        $data = [
            'currrent' => "/$version/data/initial/$table/$page/"
        ];


        switch ($table) {
            case 'companies':
                $companies = Company::orderBy('c_id', 'asc')->limit($per_page)->offset($limit)->get();
                if (count($companies) < $per_page) {
                    $data['next'] = "/$version/data/initial/generics/1/";
                } else {
                    $data['next'] = sprintf('/%s/data/initial/companies/%d/', $version, ++$page);
                }
                $data['table'] = 't_companies';
                $data['insert'] = $companies;
                break;

            case 'generics':

                if ('v1' == $version) {
                    $generics = GenericV1::orderBy('g_id', 'asc')->limit($per_page)->offset($limit)->get();
                } else if ('v2' == $version) {
                    $generics = GenericV2::orderBy('g_id', 'asc')->limit($per_page)->offset($limit)->get();
                } else {
                    return response()->json('Something wrong. Please try again.');
                }
                if (count($generics) < $per_page) {
                    $data['next'] = "/$version/data/initial/medicines/1/";
                } else {
                    $data['next'] = sprintf('/%s/data/initial/generics/%d/', $version, ++$page);
                }
                if ('v2' == $version) {
                    $updatedGenerics = [];
                    foreach ($generics as $generic) {
                        $newGen = [];
                        foreach ($generic->getAttributes() as $key => $value) {
                            $newGen[$key] = maybeJsonDecode($value);
                        }
                        unset($value);
                        $updatedGenerics[] = $newGen;
                    }
                    unset($newGen);
                }
                $data['table'] = 't_generics';
                $data['insert'] = $updatedGenerics ?? $generics;
                break;

            case 'medicines':
                $medicine = Medicine::orderBy('m_name', 'asc')->orderBy('m_form', 'desc')->orderBy('m_strength', 'asc')->orderBy('m_unit', 'asc')->where('m_status', '=', 'active')->limit($per_page)->offset($limit)->distinct()->get(['m_id', 'm_name', 'm_form', 'm_strength', 'm_unit', 'm_g_id', 'm_c_id', 'm_category']);

                $data['insert'] = $medicine;

                if (count($medicine) < $per_page) {
                    $data['next'] = "/$version/data/initial/medicines/1/";
                    $dbVersion = Option::where('option_name', 'dbVersion')->first();
                    $data['dbVersion'] = $dbVersion ? $dbVersion->option_value : false;
                } else {
                    $data['next'] = sprintf('/%s/data/initial/medicines/%d/', $version, ++$page);
                }
                $data['table'] = 't_medicines';
                break;

            default:
                $data['next'] = "/$version/data/initial/companies/1/";
                break;
        }
        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }


    public function dataCheck($version, $dbVersion)
    {

        if (!in_array($version, ['v1'])) {
            return notFoundURL();
        }

        $dbVersion = \intval($dbVersion);
        $current_dbVersion = \intval(Option::where('option_name', 'dbVersion')->first());

        if ($dbVersion === $current_dbVersion) {
            return response()->json('No data update available');
        }
        $v = min($dbVersion + 10, $current_dbVersion); //Send maximum 10 updates in one call
        $data = [];
        for ($i = $dbVersion + 1; $i <= $v; $i++) {
            $db_data = Option::get("dbData_{$i}", true);
            if ($db_data) {
                $data = \array_merge_recursive($data, $db_data);
            }
        }
        return response()->json([
            'dbVersion' => $v,
            'status' => 'success',
            'data' => $data,
        ]);
    }


    public function home()
    {
        // if (!in_array($version, ['v1'])) {
        //     return notFoundURL();
        // }
        $carousel = [];
        // foreach ( glob( STATIC_DIR . '/images/carousel/*.{jpg,jpeg,png,gif}', GLOB_BRACE ) as $value ) {
        //     $carousel[] = \str_replace( STATIC_DIR, STATIC_URL, $value ) . '?v=' . @\filemtime($value) ?: 1;
        // }
        $deals = [];
        foreach ([27948, 27933, 27621, 27932, 27931, 27716, 27620, 27619, 28163, 28165, 28164, 27930] as $m_id) {
            if ($medicine = Medicine::getMedicine($m_id)) {
                $deals[] = [
                    'id' => $medicine->m_id,
                    'name' => $medicine->m_name,
                    'price' => $medicine->m_price,
                    'd_price' => $medicine->m_d_price,
                    'pic_url' => $medicine->m_pic_url,
                ];
            }
        }

        $feature = [];
        foreach ([27931, 27932, 27360, 27336, 27369, 27525, 27716, 27619, 27458, 27374] as $m_id) {
            if ($medicine = Medicine::getMedicine($m_id)) {
                $feature[] = [
                    'id' => $medicine->m_id,
                    'name' => $medicine->m_name,
                    'price' => $medicine->m_price,
                    'd_price' => $medicine->m_d_price,
                    'pic_url' => $medicine->m_pic_url,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'carousel' => $carousel,
                'feature' => $feature,
                'deals' => $deals,
            ]
        ]);
    }

    public function medicinesQueryES( $args = [] ) {
        $args = array_merge([
            'per_page' => 10,
            'm_status' => 'active',
            'havePic' => true,
            'm_rob' => true,
        ], $args );
        $data = \OA\Search\Medicine::init()->search( $args );

        if ( $data && $data['data'] ) {
            return $data['data'];
        }
        return [];
    }

    public function categoryMedicinesES( $cat_id, $per_page = 15 ) {
        $args = [
            'per_page' => $per_page,
            'm_cat_id' => $cat_id,
        ];
        return $this->medicinesQueryES( $args );
    }

    public function home_v2(Request $request) {
        $from = isset( $request->f ) ? preg_replace("/[^a-zA-Z0-9]+/", "",$request->f) : 'app';
        /*
        if ( $cache_data = Cache::instance()->get( $from, 'HomeData' ) ){
            Response::instance()->replaceResponse( $cache_data );
            Response::instance()->send();
        }
        */
        Response::instance()->setResponse( 'refBonus', changableData('refBonus') );
        Response::instance()->setResponse( 'versions', [
            'current' => '4.2.1',
            'min' => '3.1.1',
            'android' => [
                'current' => '4.2.1',
                'min' => '3.1.1',
            ],
            'ios' => [
                'current' => '4.2.1',
                'min' => '3.1.1',
            ],
        ]);

        $extraData = [
            'yt_video' => [
                'key' => getOption('yt_video_key'),
                'title' => getOption('yt_video_title'),
            ],
        ];
        if ( ( $banners = getOption( 'attachedFilesHomepageBanner' ) ) && is_array( $banners ) ){
            $extraData['banner1']= getS3Url( $banners[0]['s3key']??'', 1000, 1000 );
        }
        Response::instance()->setResponse( 'extraData', $extraData );
        /*
        Response::instance()->appendData( '', [
            'type' => 'notice',
            'bgColor' => '#FFA07A',
            'color' => '#FF0000',
            'title' => "Due to Covid-19 pandmic some of our delivery is getting delyaed.\nPlease have paitence if your order is not delivered yet.\nWe will reach you ASAP.",
            'data' => [],
        ]);
        */

        $data = [];
        /*
        foreach ( glob( STATIC_DIR . '/images/carousel/*.{jpg,jpeg,png,gif}', GLOB_BRACE ) as $value ) {
            $data[] = \str_replace( STATIC_DIR, STATIC_URL, $value ) . '?v=' . @\filemtime($value) ?: 1;
        }
        */

        $carouselImgType = $from == 'web' ? 'attachedFilesWeb' : 'attachedFilesApp';
        if ( ( $carouselImages = Option::get( $carouselImgType ) ) && is_array( $carouselImages ) ){
            foreach ($carouselImages as $image){
                if( $from == 'web' ){
                    $data[] = getS3Url( $image['s3key'], 2732, 500 );
                } else {
                    $data[] = getS3Url( $image['s3key'], 750, 300 );
                }
            }
        }

        if( $data ){
            Response::instance()->appendData( '', [
                'type' => 'carousel',
                'title' => '',
                'data' => $data,
            ]);
        }
        Response::instance()->appendData( '', [
            'type' => 'actions',
            'title' => '',
            'data' => [
                //discount percents
                'order' => (int)getOption('prescription_percent'),
                'call' => (int)getOption('call_percent'),
                'healthcare' => (int)getOption('healthcare_percent'),
                //heading text
                'callTime' => getOption('call_time'),
            ],
        ]);
        $categories = getCategories();
        foreach ( $categories as $cat_id => $catName ) {
            $data = [];
            if( ( $cat_m_ids = getOption( "categories_sidescroll-{$cat_id}" ) ) && is_array( $cat_m_ids ) ){
                $data = $this->medicinesQueryES(['ids' => $cat_m_ids]);
            }
            $data = array_merge( $data, $this->categoryMedicinesES( $cat_id, 15 - count( $data ) ) );

            if( $data ){
                Response::instance()->appendData( '', [
                    'type' => "sideScroll-{$cat_id}",
                    'title' => $catName,
                    'cat_id' => $cat_id,
                    'data' => $data,
                ]);
            }
        }
        Response::instance()->setStatus( 'success' );

        //Cache::instance()->set( $from, Response::instance()->getResponse(), 'HomeData', 60 * 60 );

        Response::instance()->send();
    }


    public function medicines(Request $request, $search = '', $page = 0 ) {
        $this->medicinesES( $search, $page );

        if( ! $search ){
            $search = $request->search ? $request->search : '';
        }
        if( ! $page ){
            $page = !empty( $request->page ) ? (int)$request->page : 1;
        }
        $category = $request->category ? $request->category : '';
        $cat_id = $request->cat_id  ? (int)$request->cat_id : 0;

        if( 'healthcare' == $category ){
            $per_page = 12;
        } else {
            $per_page = 10;
        }
        $limit    = $per_page * ( $page - 1 );
        $db = new DB;



        //$db->add( 'SELECT * FROM t_medicines WHERE 1=1' );
        $query=DB::table('t_medicines')->get();
        if ( $search ) {
            $search = preg_replace('/[^a-z0-9\040\.\-]+/i', ' ', $search);

            //$search = \rtrim( addcslashes( $search, '_%\\' ), '-');
            $org_search = $search = \rtrim( \trim(preg_replace('/\s\s+/', ' ', $search ) ), '-' );

            //$db->add( ' AND ( m_name LIKE ? OR m_generic LIKE ? )', "{$search}%", "{$search}%" );
            //$db->add( ' AND m_name LIKE ?', "{$search}%" );
            if( false === \strpos( $search, ' ' ) ){
                $search .= '*';
            } else {
                $search = '+' . \str_replace( ' ', ' +', $search) . '*';
            }
            if( \strlen( $org_search ) > 2 ){
                $query->where( " AND (MATCH(m_name) AGAINST (? IN BOOLEAN MODE) OR m_name LIKE ?)", $search, "{$org_search}%" );
                //$db->add( " AND MATCH(m_name) AGAINST (? IN BOOLEAN MODE)", $search );
            } elseif( $org_search ) {
                $query->where( ' AND m_name LIKE ?', "{$org_search}%" );
            }
        }
        if ( $cat_id ) {
            $query->where( ' AND m_cat_id = ?', $cat_id );
        }
        if( $category ) {
            $query->where( ' AND m_category = ?', $category );
        }
        $query->where( ' AND m_status = ?', 'active' );
        $query->where( ' ORDER BY m_rob DESC, m_category, m_name, m_form DESC, m_strength, m_unit LIMIT ?, ?', $limit, $per_page );
        
        $cache_key = \md5( $db->getSql() . \json_encode($db->getParams()) );
        $cache= new Cache();
        if ( $cache_data = $cache->get( $cache_key, 'userMedicines' ) ){
            return response()->json([
                'status'=>'success',
                'total'=>$cache_data['data']
            ]);
        }

        $query = $db->execute();
        $query->setFetchMode( \PDO::FETCH_CLASS, '\OA\Factory\Medicine');

        while( $medicine = $query->fetch() ){
            $data = [
                'id' => $medicine->m_id,
                'name' => $medicine->m_name,
                'generic' => $medicine->m_generic,
                'strength' => $medicine->m_strength,
                'form' => $medicine->m_form,
                'company' => $medicine->m_company,
                'unit' => $medicine->m_unit,
                'pic_url' => $medicine->m_pic_url,
                'rx_req' => $medicine->m_rx_req,
                'rob' => $medicine->m_rob,
                'comment' => $medicine->m_comment,
                'price' => $medicine->m_price,
                'd_price' => $medicine->m_d_price,
            ];
            return response()->json([
                'status'=>'success',
                'data'=>$data
            ]);
        }
        if ( $all_data = Response::instance()->getData() ) {
            $cache_data = [
                'data' => $all_data,
                //'total' => $total,
            ];
            //pic_url may change. So cache for sort period of time
            $cache->set( $cache_key, $cache_data, 'userMedicines', 60 * 60 );

            //Response::instance()->setResponse( 'total', $total );
            return response()->json('success'); 
        } else {
            if( $page > 1 ){
                return response()->json('No more medicines Found' );
            } else {
                return response()->json('No medicines Found' );
            }
        }
    }

    function medicinesES(Request $request, $q = '', $page = 0) {
        $from = $request->f ? preg_replace("/[^a-zA-Z0-9]+/", "", $request->f) : 'app';
        if( ! $q ){
            $q = $request->search ? $request->search : '';
        }
        $q = $org_q = mb_strtolower( $q );
        $q = trim( preg_replace('/[^\w\ \.\-]+/', '', $q) );
        if( !$q && $q != $org_q ){
            return response()->json('No medicines Found' );
        }

        if( ! $page ){
            $page = !empty( $request->page) ? (int) $request->page : 1;
        }
        $category = $request->category ? $request->category : '';
        $cat_id = $request->cat_id ? (int) $request->cat_id : 0;
        $havePic = !empty( $request->havePic) ? true : false;

        if( 'healthcare' == $category || 'web' == $from ){
            $per_page = 12;
        } else {
            $per_page = 10;
        }
        $args = [
            'search' => $q,
            'per_page' => $per_page,
            'limit' => $per_page * ( $page - 1 ),
            'm_status' => 'active',
            'm_category' => $category,
            'm_cat_id' => $cat_id,
            'havePic' => $havePic,
        ];
        $data = Medicine::init()->search( $args );

        if ( $data && $data['data'] ) {
            return response()->json([
               'status'=>'success',
               'data'=>$data['data'], 
            ]);
        } else {
            if( $page > 1 ){
                return response()->json('No more medicines Found');
            } else {
                return response()->json('No medicines Found');
            }
        }
    }
    public function sameGeneric($g_id, $page = 1)
    {
        $per_page = 10;
        $limit    = $per_page * ($page - 1);

        $medicinecheck = Medicine::orderBy('m_rob', 'desc')->orderBy('m_name', 'asc')
            ->orderBy('m_form', 'desc')->orderBy('m_strength', 'asc')
            ->orderBy('m_unit', 'asc')->where('m_g_id', $g_id)->limit($per_page)->offset($limit)->get();
        if ($medicinecheck->count() < 1) {
            return response()->json([
                'status' => 'fail',
                'message' => "No medicines Found",
                'data' => []
            ]);
        } else {
            $data = Medicine::orderBy('m_rob', 'desc')->orderBy('m_name', 'asc')
                ->orderBy('m_form', 'desc')->orderBy('m_strength', 'asc')
                ->orderBy('m_unit', 'asc')->where('m_g_id', $g_id)->limit($per_page)->offset($limit)->get(
                    [
                        'id' => 'm_id',
                        'name' => 'm_name',
                        'generic' => 'm_generic',
                        'strength' => 'm_strength',
                        'form' => 'm_form',
                        'company' => 'm_company',
                        'unit' => 'm_unit'
                    ]
                );

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        }
    }



    public function medicineSingle($version, $m_id)
    {

        if (!in_array($version, ['v1', 'v2', 'v3'])) {
            return notFoundURL();
        }


        $m_id = (int)$m_id;
        if (!$m_id) {
            return response()->json('No medicines Found');
        }

        if ($medicine = getMedicine($m_id)) {
            $medicine->incrCount('Viewed');

            //Response::instance()->setStatus( 'success' );
            //$price = $medicine->m_price * (intval($medicine->m_unit));
            //$d_price = ( ( $price * 90 ) / 100 );
            $data = [
                'id' => $medicine->m_id,
                'name' => $medicine->m_name,
                'g_id' => $medicine->m_g_id,
                'generic' => $medicine->m_generic,
                'strength' => $medicine->m_strength,
                'form' => $medicine->m_form,
                'c_id' => $medicine->m_c_id,
                'cat_id' => $medicine->m_cat_id ?? 0,
                'company' => $medicine->m_company,
                'unit' => $medicine->m_unit,
                'price' => $medicine->m_price,
                'd_price' => $medicine->m_d_price,
                'pic_url' => $medicine->m_pic_url,
                'pic_urls' => $medicine->m_pic_urls,
                'rob' => $medicine->m_rob,
                'rx_req' => $medicine->m_rx_req,
                'r_bought' => $medicine->getCount('Viewed'),
                'comment' => $medicine->m_comment ?? "",
                'category' => $medicine->m_category,
                'min' => $medicine->m_min,
                'max' => $medicine->m_max,
                'cold' => $medicine->isCold(),
                'note1' => $medicine->isCold() ? 'শুধুমাত্র ঢাকা শহরে ডেলিভারি হবে।' : 'সারা বাংলাদেশ থেকে অর্ডার করা যাবে।',
                //'note' => 'Use coupon code "arogga11" at checkout to get 11% cashback',
            ];
            if ('v1' == $version) {
                $data['description'] = $medicine->m_description;
            } elseif ('v2' == $version) {
                if ('allopathic' == $medicine->m_category) {
                    $data['description'] = $medicine->m_description_v2;
                    if (!empty($data['description']['g_quick_tips'])) {
                        $data['description']['brief_description'] = $medicine->m_description_dims;
                    }
                } else {
                    $data['description'] = ['html' => (string)$medicine->getMeta('description')];
                }
            }
            //$data['sideScroll'] = $this->getSingleMedicineSideScroll( $medicine );

            /* Response::instance()->addData( 'medicine', $data );
             if( in_array( $version, ['v1', 'v2'] ) ){
                 Response::instance()->addData( 'same_generic', $this->getSingleMedicineSameGeneric( 'v1', $medicine ) );
             }*/
        } else {
            return response()->json([
                'status' => 'success',
                'data' => [],
                'message' => 'No medicines Found'
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'medicine' => $data
            ]
        ]);
    }


    public function medicineSingleExtra($version, $m_id)
    {

        if (!in_array($version, ['v1', 'v2'])) {
            return notFoundURL();
        }

        if ($medicine = getMedicine($m_id)) {
            $description = [];
            if ('allopathic' == $medicine->m_category) {
                $description = $medicine->m_description_v2;
                if (!empty($description['g_quick_tips'])) {
                    $description['brief_description'] = $medicine->m_description_dims;
                }
            } else {
                $description = ['html' => (string)$medicine->getMeta('description')];
            }
            return response()->json([
                'status' => 'success',
                'data' => [
                    'description' => $description
                ],
                'same_generic' => $this->getSingleMedicineSameGeneric($version, $medicine),
            ]);
        } else {
            return response()->json([
                'status' => 'Fail',
                'message' => 'No medicines Found',
                'data' => []
            ]);
        }
    }

    private function getSingleMedicineSameGeneric($version, $medicine)
    {
        if (!$medicine || !$medicine->m_g_id) {
            return [];
        }
        if ($cache_data = getMedicine($medicine->m_id, 'userSameGeneric')) {
            return $cache_data;
        }
        if ('v1' == $version) {
            $munit = $medicine->m_unit;
        } else {
            $munit = 0;
        }
        $data = Medicine::orderBy('m_rob', 'desc')->orderBy('m_name', 'asc')
            ->where('m_g_id', $medicine->m_g_id)->where('m_strength', $medicine->m_strength)
            ->where('m_form', $medicine->m_form)->where('m_status', 'active')->where('m_id', $medicine->m_id)
            ->where('m_unit', $munit)
            ->get([
                'id' => 'm_id',
                'name' => 'm_name',
                'form' => 'm_form',
                'unit' => 'm_unit',
                'form' => 'm_form',
                'price' => 'm_price',
                'd_price' => 'm_d_price',
                'rob' => 'm_rob',
                'strength' => 'm_strength',
            ]);

        if ($data) {
            $data = getMedicine($medicine->m_id, $data, 'userSameGeneric', 60 * 60 * 24);
        }

        return $data;
    }


    public function medicinePrice($m_ids)
    {
        $m_ids = \array_filter(\array_map('trim', \explode(',', $m_ids)));
        $in  = str_repeat('?,', count($m_ids) - 1) . '?';
        if (!$m_ids) {
            return response()->json([
                'status' => 'Fail',
                'message' => 'No medicines Found',
                'data' => []
            ]);
        } else {
            $medicines = Medicine::whereIn('m_id',$m_ids)->get();

            $data = [];
            foreach($medicines as $medicine){
                $data[$medicine->m_id] = [
                    'price' => $medicine->m_price,
                    'd_price' => $medicine->m_d_price,
                    'pic_url'=>$medicine->m_pic_url,
                    'rx_req' => $medicine->m_rx_req,
                    'r_bought' => $medicine->m_rob ? \round( intval($medicine->m_id) % 70 + \floor(\time()/3600) - 439625 ) : 0,
                    'rob' => $medicine->m_rob,
                    'comment' => $medicine->m_comment,
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => $data
            ]);
        }
    }




    public function medicineSuggest(Request $request)
    {
        $name = $request->name;
        $generic = $request->generic;
        $strength = $request->strength;
        $form = $request->form;
        $company = $request->company;
        if ($name == '' || $generic == '' || $strength == '' || $form == '' || $company == '') {
            return response()->json('Fields are required.');
        } else if (Auth::id() == '') {
            return response()->json('Login required.');
        } else {

            $medicine = new Medicine;
            $medicine->m_name = $name;
            $medicine->m_generic = $generic;
            $medicine->m_strength = $strength;
            $medicine->m_form = $form;
            $medicine->m_company = $company;
            $medicine->m_status = 'suggested';
            $medicine->m_u_id = Auth::id();
            if ($medicine->save()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Thank you. we will manually review this medicine and update.',
                    'medicine' => $medicine

                ]);
            }
        }
    }


    public function token(Request $request)
    {
        
        $fcm = $request->fcm ? filter_var($request->fcm, env('FILTER_SANITIZE_STRING')) : '';

        if ($tokens = Token::get()->count() < 1) {
        } else {
            $existtoken = Token::where('t_token', $fcm)->get()->count();
        }

        if (!$fcm) {
            return response()->json('Invalid token');
        } else if ($existtoken > 0) {
            return response()->json('Token already exists.');
        } else {
            $tokens= new Token;
            $tokens->t_uid=Auth::id();
            $tokens->t_created=date('Y-m-d H:i:s');
            $tokens->t_token=$fcm;
            $tokens->t_ip=$_SERVER['REMOTE_ADDR'];
            if ($tokens->save()) {
                return response()->json([
                    'Token saved' => 'success',
                    't_tokens' => $tokens

                ]);
            }
        }
    }


    public function cartDetails(Request $request) {
        $medicines = $request->medicines ;
        $d_code = $request->d_code;
        $s_address = $request->s_address && is_array($request->s_address)? $request->s_address : [];

        if ( ! $medicines ){
            return response()->json([
                'status' => 'fail',
                'message' => 'medicines are required.',
            ]);
        }
        if ( ! is_array( $medicines ) ){
            return response()->json( [
                'status' => 'fail',
                'message' => 'medicines need to be an array with m_id as key and quantity as value.',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => cartData( Auth::user() , $medicines, $d_code, null, false, ['s_address' => $s_address])
        ]);
    }

    public function dicountCheck(Request $request) {
        $d_code = $request->d_code;

        if ( ! $d_code ){
            return response()->json( 'd_code is required.');
        }
        if ( ! (Auth::check())) {
            return response()->json([
                'loginRequired'=>true,
                'message'=>'Invalid id token',
            ]);
        }
        $discount = getDiscount( $d_code );
        $uid= Auth::user()->u_id;
        if ( $discount && $discount->canUserUse($uid) ) {
            $data = [
                'code' => $d_code,
                'type' => $discount->d_type,
                'amount' => $discount->d_amount,
                'max'    => $discount->d_max,
            ];
            return response()->json([
                'status'=>'success',
                'data'=>$data
            ]);
        } else {
            return response()->json( 'wrong discount code.');
        }
    }

    public function checkoutInitiated(){
        // if ( ! ( $user = User::getUser( Auth::id() ) ) ) {
        //     Response::instance()->loginRequired( true );
        //     Response::instance()->sendMessage( 'Invalid id token' );
        // }

        if ( ! (Auth::id()) ) {
            return response()->json([
                'loginRequired'=>true,
                'status'=>'Fail',
                'message'=>'Invalid id token',
                'data'=>[],
            ]);
        }

        $s_address = ( isset( $_GET['s_address'] ) && is_array($_GET['s_address']) )? $_GET['s_address']: [];

        $data = [];

        /*
        if( $s_address && 'Dhaka City' == $s_address['district'] ){
            $data['note'] = '* Estimated Delivery Time: 12-48 hours';
        } else {
            $data['note'] = '* Estimated Delivery Time: 1-5 days';
        }
        */
		$data['maxCod'] = 20000;
        $data['note'] = "* Estimated Delivery Time for Dhaka 12-48 Hours\n* Estimated Delivery time for Outside Dhaka 1-5 Days";

        //$data['note'] = "ঈদ মোবারক! ঈদের ছুটিতে ফার্মাসিউটিক্যাল কোম্পানিগুলো বন্ধ থাকায় সম্মানিত গ্রাহকদের অর্ডার ডেলিভারি বিলম্বিত হচ্ছে।\nতাই ১১-১৫ তারিখ পর্যন্ত আমরা অর্ডার নেওয়া বন্ধ রাখছি।\nসম্মানিত গ্রাহকদের সাময়িক এই অসুবিধার জন্য আমরা আন্তরিকভাবে দুঃখিত। আনন্দে কাটুক সবার ঈদ।\n\nEid Mubarak! Due to Eid holiday, the pharmaceutical companies will be closed during the period 11th - 15th May. We will not be taking orders during this period and will resume delivery from the 15th of May. We sincerely apologize for the inconvenience caused. Wishing everyone a happy and safe Eid festivities!";

        return response()->json([
            'status'=>'success',
            'data'=>$data
        ]);
    }

    // function orderAdd(Request $request) {
    //     //Response::instance()->sendMessage( "ঈদ মোবারক! ঈদের ছুটিতে ফার্মাসিউটিক্যাল কোম্পানিগুলো বন্ধ থাকায় সম্মানিত গ্রাহকদের অর্ডার ডেলিভারি বিলম্বিত হচ্ছে।\nতাই ১১-১৫ তারিখ পর্যন্ত আমরা অর্ডার নেওয়া বন্ধ রাখছি।\nসম্মানিত গ্রাহকদের সাময়িক এই অসুবিধার জন্য আমরা আন্তরিকভাবে দুঃখিত। আনন্দে কাটুক সবার ঈদ।");
    //     //Response::instance()->sendMessage( "Dear valued clients.\nOur Dhaka city operation will resume from 29th November 2020.\nThanks for being with Arogga.");
    //     //Response::instance()->sendMessage( "Due to some unavoidable circumstances we cannot take orders now. We will send you a notification once we start taking orders.\nSorry for this inconvenience.");
    //     //Response::instance()->sendMessage( "Due to covid19 outbreak, there is a severe short supply of medicine.\nUntil regular supply of medicine resumes, we may not take anymore orders.\nSorry for this inconvenience.");
    //     //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPls try after 24 hours. We will be back!!");
    //     //Response::instance()->sendMessage( "Due to Software maintainance we cannot receive orders now.\nPlease try again after 2nd Jun, 11PM. We will be back!!");
    //     //Response::instance()->sendMessage( "Due to recent coronavirus outbreak, we are facing delivery man shortage.\nOnce our delivery channel is optimised, we may resume taking your orders.\nThanks for your understanding.");
    //     //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after EID. We will be back!!");
    //     //Response::instance()->sendMessage( "Due to EID holiday we cannot receive orders now.\nPlease try again after 28th May, 10PM. We will be back!!");

    //     $from = isset( $_GET['f'] ) ? preg_replace("/[^a-zA-Z0-9]+/", "",$_GET['f']) : 'app';
    //     $medicines = $request->medicines && is_array( $request->medicines ) ?  $request->medicines : [];
    //     $d_code = $request->d_code ?  $request->d_code : '';
    //     $prescriptions = $request->prescriptions ? $request->prescriptions : [];
    //     $prescriptionKeys = $request->prescriptionKeys && is_array( $request->prescriptionKeys ) ?  $request->prescriptionKeys : [];

    //     $name = $request->name ? $request->name : '';
    //     $mobile = $request->mobile ?  $request->mobile : '';
    //     $address = $request->address ?  $request->address : '';
    //     $lat = $request->lat ?  $request->lat : '';
    //     $long = $request->long ?  $request->long : '';
    //     $gps_address = $request->gps_address ?  $request->gps_address : '';
    //     $s_address = $request->s_address && is_array($request->s_address )? $request->s_address: [];
    //     $monthly = !empty( $request->monthly ) ?  1 : 0;
    //     $payment_method = $request->payment_method && in_array($request->payment_method, ['cod', 'online']) ? $request->payment_method : 'cod';

    //     if ( ! $name || ! $mobile ){
    //         return response()->json( 'name and mobile are required.');
    //     }
    //     $lat = $long = '';

    //     if ( ! $address && ( ! $lat || ! $long ) && ! $s_address ){
    //         return response()->json( 'Address is required.');
    //     }

    //     if ( $s_address && ! isLocationValid( @$s_address['division'], @$s_address['district'], @$s_address['area'] ) ){
    //         return response()->json( 'invalid location.');
    //     }

    //     /*
    //     if ( $lat && $long ){
    //         if( Functions::isInside( $lat, $long, 'chittagong' ) ){
    //             Response::instance()->sendMessage( "Our Chattogram operation temporarily off due to some unavoidable circumstances. We will send you a notification once our Chattogram operation resumes.\nSorry for this inconvenience.");
    //         }
    //         if( ! Functions::isInside( $lat, $long ) ){
    //             Response::instance()->sendMessage( "Our delivery service comming to this area very soon, please stay with us.");
    //         }
    //     }
    //     */
    //     if ( ! $medicines && ! $prescriptions && !$prescriptionKeys ){
    //         return response()->json( 'medicines or prescription are required.');
    //     }
    //     if ( $medicines && ! is_array( $medicines ) ){
    //         return response()->json( 'medicines need to be an array with m_id as key and quantity as value.');
    //     }
    //     if ( $prescriptions && ! is_array( $prescriptions ) ){
    //         return response()->json( 'prescription need to be an file array.');
    //     }

    //     if ( ! (Auth::id())) {
    //         return response()->json([
    //             'loginRequired'=>true,
    //             'message'=>'Invalid id token',
    //         ]);
    //     }
    //     $user= User::where('u_id', Auth::id())->get();
    //     if ( 'blocked' == $user->u_status ){
    //         return response()->json( 'You are blocked. Please contact customer care.');
    //     }
    //     if ( 'user' !== $user->u_role ){
    //         return response()->json( 'You cannot make order using this number.');
    //     }
    //     // $order_check = DB::db()->prepare( 'SELECT COUNT(*) FROM t_orders WHERE u_id = ? AND o_status = ?' );
    //     // $order_check->execute( [ Auth::id(), 'processing' ] );
    //     $order_check= Order::where('u_id', Auth::id())->where('o_status', '=', 'processing')->get()->count();
    //     if( $order_check >= 3  ){
    //         return response()->json( 'Please wait until your current orders are confirmed. After that you can submit another order OR call customer care if you need further assistance.');
    //     }
    //     $discount = Discount::getDiscount( $d_code );

    //     if( ! $discount || ! $discount->canUserUse( $user->u_id ) ) {
    //         $d_code = '';
    //     }
    //     if ( $name && !$user->u_name ) {
    //         $user->u_name = $name;
    //     }
    //     if ( ! $user->u_mobile && $mobile ) {
    //         $m_user = $user->u_mobile;//User::getBy( 'u_mobile', $mobile );
    //         if ( $m_user ) {
    //             return response()->json( 'Sorry for this but this number is already registered with another account. Please sign in with that account if it is you or login with your own phone number.');
    //         } else {
    //             $user->u_mobile = $mobile;
    //         }
    //     }
    //     if ( $lat && $user->u_lat != $lat ) {
    //         $user->u_lat = $lat;
    //     }
    //     if ( $long &&  $user->u_long != $long ) {
    //         $user->u_long = $long;
    //     }

    //     $files_to_save = [];
    //     if ( $prescriptions ) {
    //         if ( empty( $prescriptions['tmp_name'] ) || ! is_array( $prescriptions['tmp_name'] ) ) {
    //             return response()->json( 'prescription need to be an file array.');
    //         }
    //         if ( count( $prescriptions['tmp_name'] ) > 5 ) {
    //             return response()->json( 'Maximum 5 prescription pictures allowed.');
    //         }
    //         $i = count( $prescriptionKeys ) ?: 1;
    //         foreach( $prescriptions['tmp_name'] as $key => $tmp_name ) {
    //             if( $i > 5 ){
    //                 break;
    //             }
    //             if( ! $tmp_name ) {
    //                 continue;
    //             }
    //             if ( UPLOAD_ERR_OK !== $prescriptions['error'][$key] ) {
    //                 return response()->json( \sprintf('Upload error occured when upload %s. Please try again', \strip_tags( $prescriptions['name'][$key] ) ) );
    //             }
    //             $size = \filesize( $tmp_name );
    //             if( $size < 12 ) {
    //                 return response()->json( \sprintf('File %s is too small.', \strip_tags( $prescriptions['name'][$key] ) ) );
    //             } elseif ( $size > 10 * 1024 * 1024 ) {
    //                 return response()->json( \sprintf('File %s is too big. Maximum size is 10MB.', \strip_tags( $prescriptions['name'][$key] ) ) );
    //             }
    //             $imagetype = exif_imagetype( $tmp_name );
    //             $mime      = ( $imagetype ) ? image_type_to_mime_type( $imagetype ) : false;
    //             $ext       = ( $imagetype ) ? image_type_to_extension( $imagetype ) : false;
    //             if( ! $ext || ! $mime ) {
    //                 return response()->json( 'Only prescription pictures are allowed.');
    //             }
    //             $files_to_save[ $tmp_name ] = ['name' => $i++ . randToken( 'alnumlc', 12 ) . $ext, 'mime' => $mime ];
    //         }
    //     }

    //     $cart_data = cartData( $user, $medicines, $d_code, null, false, ['s_address' => $s_address] );
    //     if ( ! empty( $cart_data['rx_req'] ) && ! $files_to_save ) {
    //         return response()->json( 'Rx required.');
    //     }
    //     if( isset($cart_data['deductions']['cash']) && !empty($cart_data['deductions']['cash']['info'])) {
    //         $cart_data['deductions']['cash']['info'] = "Didn't apply because the order value was less than ৳499.";
    //     }
    //     if( isset($cart_data['additions']['delivery']) && !empty($cart_data['additions']['delivery']['info'])) {
    //         $cart_data['additions']['delivery']['info'] = str_replace('To get free delivery order more than', 'Because the order value was less than', $cart_data['additions']['delivery']['info']);
    //     }
    //     $c_medicines = $cart_data['medicines'];
    //     unset( $cart_data['medicines'] );

    //     $order = new Order;
    //     $order->u_id = $user->u_id;
    //     $order->u_name = $user->u_name;
    //     $order->u_mobile = $user->u_mobile;
    //     $order->o_subtotal = $cart_data['subtotal'];
    //     $order->o_addition = $cart_data['a_amount'];
    //     $order->o_deduction = $cart_data['d_amount'];
    //     $order->o_total = $cart_data['total'];
    //     $order->o_status = 'processing';
    //     $order->o_i_status = 'processing';
    //     $order->o_address = $address;
    //     $order->o_gps_address = $gps_address;
    //     $order->o_lat = $lat;
    //     $order->o_long = $long;
    //     $order->o_payment_method = $payment_method;

    //     /*
    //     if( $p_id = $this->closest( 'pharmacy', $lat, $long ) ) {
    //         $order->o_ph_id = $p_id;
    //     }
    //     */
    //     //Currently we have only one pharmacy
    //     $order->o_ph_id = 6139;

    //     if( !isset( $s_address['district'] ) ){
    //         if( $d_id = $this->closest( 'delivery', $lat, $long ) ){
    //             $order->o_de_id = $d_id;
    //         }
    //     } elseif( $s_address['district'] != 'Dhaka City' ){
    //         //Outside Dhaka delivery ID
    //         $order->o_de_id = 143;
    //         $order->o_payment_method = 'online';
    //     } elseif( $d_id = getIdByLocation( 'l_de_id', $s_address['division'], $s_address['district'], $s_address['area'] ) ) {
    //         $order->o_de_id = $d_id;
    //     }
    //     if( isset( $s_address['district'] ) ){
    //         $order->o_l_id = getIdByLocation( 'l_id', $s_address['division'], $s_address['district'], $s_address['area'] );
    //     }
    //     $user->update();
    //     $order->insert();
    //     Functions::ModifyOrderMedicines( $order, $c_medicines );
    //     $meta = [
    //         'o_data' => $cart_data,
    //         'o_secret' => randToken( 'alnumlc', 16 ),
    //         's_address' => $s_address,
    //         'from' => $from,
    //     ];
    //     if( $d_code ) {
    //         $meta['d_code'] = $d_code;
    //     }
    //     if( $monthly ) {
    //         $meta['subscriptionFreq'] = 'monthly';
    //     }

    //     $imgArray = [];
    //     if ( $files_to_save ) {
    //         $upload_folder = STATIC_DIR . '/orders/' . \floor( $order->o_id / 1000 );

    //         if ( ! is_dir($upload_folder)) {
    //             @mkdir($upload_folder, 0755, true);
    //         }

    //         foreach ( $files_to_save as $tmp_name => $file ) {
    //             $fileName = \sprintf( '%s-%s', $order->o_id, $file['name'] );
    //             $s3key = Functions::uploadToS3( $order->o_id, $tmp_name, 'order', $fileName, $file['mime'] );
    //             if ( $s3key ){
    //                 array_push( $imgArray, $s3key );
    //             }
    //         }
    //         if ( count($imgArray) ){
    //             $oldMeta = $user->getMeta( 'prescriptions' );
    //             $user->setMeta( 'prescriptions', ( $oldMeta && is_array($oldMeta ) ) ? array_merge( $oldMeta, $imgArray ) : $imgArray );
    //        }
    //     }
    //     if( $prescriptionKeys ){
    //         $oldMeta = $user->getMeta( 'prescriptions' );

    //         foreach ( $prescriptionKeys as $prescriptionKey ) {
    //             if( !$prescriptionKey || !$oldMeta || !in_array( $prescriptionKey, $oldMeta ) ){
    //                 continue;
    //             }
    //             $imgNameArray = explode( '-', $prescriptionKey );
    //             $imgName = end( $imgNameArray );

    //             $fileName = \sprintf( '%s-%s', $order->o_id, $imgName );
    //             $s3key = Functions::uploadToS3( $order->o_id, '', 'order', $fileName, '', $prescriptionKey );
    //             if ( $s3key ){
    //                 array_push( $imgArray, $s3key );
    //             }
    //         }
    //     }
    //     if ( count($imgArray) ){
    //          $meta['prescriptions'] = $imgArray;
    //     }

    //     $order->insertMetas( $meta );
    //     $order->addHistory( 'Created', sprintf( 'Created through %s', $from ) );
    //     //Get user again, User data may changed
    //     $user = User::getUser( Auth::id() );
	// 	$cash_back = $order->cashBackAmount();

    //     $message = 'Order added successfully.';
    //     if ( !$medicines && $files_to_save ) {
    //         $message = "Thank you for submitting prescription. You will receive a call shortly from our representatives.\nNote: Depending on the order value,  you may receive cashback from arogga.";
    //     } else {
    //         if ( $cash_back ) {
    //             $user->u_p_cash = $user->u_p_cash + $cash_back;
    //             $message = "Congratulations!!! You have received a cashback of ৳{$cash_back} from arogga. The cashback will be automatically applied at your next order.";
    //             Functions::sendNotification( $user->fcm_token, 'Cashback Received.', $message );
    //         }
    //     }
        
    //     if( isset($cart_data['deductions']['cash']) ){
    //         $user->u_cash = $user->u_cash - $cart_data['deductions']['cash']['amount'];
    //     }

    //     $user->update();
    //     $u_data = $user->toArray();
    //     $u_data['authToken'] = $user->authToken();

    //     $o_data = [
    //         'o_id' => $order->o_id,
    //     ];

    //     return response()->json([
    //        'status'=>'success',
    //        'message'=>$message,
    //        'user'=>$u_data,
    //        'order'=>$o_data,
    //     ]);

    // }


    public function orderSingle( $o_id ) {
        if ( ! (Auth::check()) ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Invalid id token' );
        }
        if( ! ( $order = getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $allowed_ids = [
            $order->u_id,
            $order->o_de_id,
            $order->o_ph_id
        ];
        $user= Auth::user();
        if( 'packer' == $user->u_role && $order->o_ph_id == $user->getMeta( 'packer_ph_id' ) ) {
            $allowed_ids[] = Auth::id();
        }
        if( ! \in_array( Auth::id(), array_unique( $allowed_ids ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        
        $data = $order->toArray();
        $data['prescriptions'] = $order->prescriptions;
        $data['o_data'] = (array) $order->getMeta( 'o_data' );
        $data['o_data']['medicines'] = $order->medicines;
        $data['s_address'] = $order->getMeta('s_address')?:[];
        $data['timeline'] = $order->timeline();
		//$data['refund'] = $order->getMeta( 'refund' ) ? round( $order->getMeta( 'refund' ), 2 ) : 0;

        
        if ( 'user' !== $user->u_role && $order->o_l_id && ( $l_zone = getZoneByLocationId( $order->o_l_id ) ) ){
            $b_id = $order->getMeta( 'bag' );
            if( $b_id && ( $bag = Bag::getBag( $b_id ) ) ){
                $data['zone'] = $bag->fullZone();
            } else {
                $data['zone'] = $l_zone;
            }
        }

        $data['invoiceUrl'] = $order->signedUrl( '/v1/invoice' );
        $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';
        if( \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) && \in_array( $order->o_i_status, ['packing', 'checking', 'confirmed'] ) && 'paid' !== $order->getMeta( 'paymentStatus' ) ){
            $data['paymentUrl'] = $order->signedUrl( '/payment/v1' );
        }

        if( 'user' !== $user->u_role ) {
            $data['o_i_note'] = (string)$order->getMeta('o_i_note');
        }

        if( 'pharmacy' == $user->u_role ) {
            // $query = DB::db()->prepare( 'SELECT SUM(s_price*m_qty) FROM t_o_medicines WHERE o_id = ? AND om_status = ?' );
            // $query->execute( [ $order->o_id, 'available' ] );
            $sum = OMedicine::where('o_id', $order->o_id)->where('om_status', '=', 'available')->select( DB::raw('SUM(s_price * m_qty) AS total'))->first()->total;
            $data['o_data']['a_message'] = "Pharmacy Total = $sum";
        }
        if( \in_array( $user->u_role, [ 'pharmacy' ] ) ) {
            $data['o_de_name'] = User::find( $order->o_de_id )->u_name ?? '';
        }
        Response::instance()->sendData( $data, 'success' );
    }

    public function invoiceGenerate( $order ) {
        if ( ! ( $user = User::find( $order->u_id ) ) ) {
            return false;
        }

        $o_data = (array)$order->getMeta( 'o_data' );
        $deductions = isset( $o_data['deductions'] ) ? $o_data['deductions'] : [];
        $additions = isset( $o_data['additions'] ) ? $o_data['additions'] : [];
        $address = $order->o_gps_address;
        if( $order->o_gps_address && $order->o_address ){
            $address .= "\n";
        }
        $address .= $order->o_address;

        $pdf = new PDF();
        $pdf->paidAmount = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? $order->o_total : 0 ;
        $pdf->SetTitle( 'Invoice-' . $order->o_id . '.pdf' );
        $pdf->AliasNbPages();
        $pdf->AddPage();

        $pdf->SetFont('Times','B',9);

        $pdf->Cell(63,5,'Bill From', 0, 0, 'L');
        $pdf->Cell(23,5,'', 0, 0, 'L');
        $pdf->Cell(41,5,'', 0, 0, 'L');
        $pdf->Cell(63,5,'Bill To', 0, 1, 'L');

        $pdf->SetFont('Times','',9);

        $pdf->Cell(63,5,'Arogga Limited', 0, 0, 'L');
        $pdf->Cell(23,5,'Order ID:', 0, 0, 'L');
        $pdf->Cell(41,5,$order->o_id, 0, 0, 'L');
        $pdf->Cell(63,5,$user->u_name, 0, 1, 'L');

        $pdf->Cell(63,5,'+8801810117100', 0, 0, 'L');
        $pdf->Cell(23,5,'Order Date:', 0, 0, 'L');
        $pdf->Cell(41,5, \date('d/m/Y', \strtotime($order->o_created) ), 0, 0, 'L');
        $pdf->Cell(63,5,$user->u_mobile, 0, 1, 'L');

        $pdf->Cell(63,5,'www.arogga.com', 0, 0, 'L');
        $pdf->Cell(23,5,'Invoice Date:', 0, 0, 'L');
        $pdf->Cell(41,5,\date('d/m/Y'), 0, 0, 'L');
        $pdf->MultiCell(63,5,$address, 0, 'L');

        $pdf->Ln(10);

        $pdf->SetFont('Times','B',8);

        $pdf->Cell(20,5,'SL No.', 1, 0, 'C');
        $pdf->Cell(65,5,'Medicine', 1, 0, 'C');
        $pdf->Cell(30,5,'Quantity', 1, 0, 'C');
        $pdf->Cell(25,5,'MRP', 1, 0, 'C');
        $pdf->Cell(25,5,'Discount', 1, 0, 'C');
        $pdf->Cell(25,5,'Amount', 1, 1, 'C');

        $pdf->SetFont('Times','',8);

        $pdf->SetWidths([20,65,30,25,25,25]);
        $pdf->SetAligns(['C','L','L','R','R','R']);
        $i = 1;
        foreach ( $order->medicines as $medicine ) {
            $pdf->Row( [
                $i++,
                \rtrim( $medicine['name'] . '-' . $medicine['strength'], '-' ),
                qtyText( $medicine['qty'], $medicine),
                $medicine['price'],
                \round( $medicine['price']-$medicine['d_price'], 2),
                $medicine['d_price'],
            ]);
        }

        $pdf->Ln(10);

        $x = $pdf->GetX();
        $y = $pdf->GetY();
        $pdf->Image(  asset('/play_app_store.png'),null,null,40);
        $pdf->SetXY($x,$y);

        $pdf->SetWidths([60,30]);
        $pdf->SetAligns(['L','R']);

        $pdf->Cell(100);
        $pdf->Row( [ 'Subtotal', $order->o_subtotal ] );

        foreach ( $deductions as $deduction ) {
            $pdf->Cell(100);
            $pdf->Row( [ $deduction['text'] ."\n". str_replace( '৳', '', $deduction['info'] ), '-' . $deduction['amount'] ] );
        }
        if( $additions ) {
            $pdf->Cell(100);
            $pdf->Row( [ 'Total order value', $order->o_total - $order->o_addition ] );
        }

        foreach ($additions as $addition ) {
            $pdf->Cell(100);
            $pdf->Row( [ $addition['text'] ."\n". str_replace( '৳', '', $addition['info'] ), $addition['amount'] ] );
        }
        $pdf->SetFont('Times','B',8);
        $pdf->Cell(100);
        $pdf->Row( [ 'Amount Payable', $order->o_total . ( $pdf->paidAmount ? ' (Paid)' : '' ) ] );

        // cashback
        //if( isset($o_data['cash_back']) && $o_data['cash_back'] ){
        $amount = $o_data['cash_back']??'00';
        $pdf->Ln(40);
        $pdf->SetFont('Arial', 'B', 20);
        $pdf->Cell(190,5, $amount . ' Taka Cashback Rewarded For This Order', 0, 1, 'C');
        $pdf->Ln(3);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->Cell(190,5,'* N.B: This cashback will be applicable at your next Order', 0, 1, 'C');
        //}

        @mkdir( public_path() . '/temp', 0755, true );

        $pdf->Output( 'F', public_path() . '/temp/Invoice-' . $order->o_id . '.pdf' );
        return  asset('/temp/Invoice-' . $order->o_id . '.pdf');
    }

    public function invoice( $o_id, $token) {
        if ( ! (Auth::check()) ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'Invalid id token' );
        }

        if( ! ( $order = getOrder( $o_id ) ) ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        if( ! $order->validateToken( $token ) ){
            Response::instance()->sendMessage( 'Invalid request' );
        }

        if ( ! ( $user = User::find( $order->u_id ) ) ) {
            Response::instance()->sendMessage( 'No order user found.' );
        }
        if( ! ( $invoice = $this->invoiceGenerate( $order ) ) ){
            Response::instance()->sendMessage( 'No invoices generated.' );
        }

        header('Content-Type: application/pdf');
        header( sprintf( 'Content-Disposition: inline; filename="%s"', 'Invoice-' . $order->o_id . '.pdf' ) );
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        readfile( $invoice );
        unlink( $invoice );

        Log::create([
            'log_response_code' => 200,
            'log_response' => 'Invoice-' . $order->o_id . '.pdf',
        ]);
        exit;
    }


    public function invoiceBag( $b_id, $token ) {
        $tokenDecoded = jwtDecode( $token );
        if( !$tokenDecoded || empty( $tokenDecoded['b_id'] ) || $b_id != $tokenDecoded['b_id']  ){
            Response::instance()->sendMessage( 'Invalid request' );
        }
        $bag = Bag::getBag( $b_id );

        if( !$bag || !$bag->o_count ){
            Response::instance()->sendMessage( 'No orders found.' );
        }
        $o_ids = $bag->o_ids;

        $cacheUpdate = new CacheUpdate();
        $cacheUpdate->add_to_queue( $o_ids, 'order_meta');
        $cacheUpdate->add_to_queue( $o_ids, 'order');
        $cacheUpdate->update_cache( [], 'order_meta' );
        $cacheUpdate->update_cache( [], 'order' );

        $merge = new FPDF_Merge();
        $gen_o_ids = [];
        foreach ( $o_ids as $o_id ) {
            if( ! ( $order = getOrder( $o_id ) ) ){
                continue;
            }
            if( 'confirmed' != $order->o_status ){
                continue;
            }
            if( $invoice = $this->invoiceGenerate( $order ) ){
                $merge->add($invoice);
                unlink($invoice);
            }
            $gen_o_ids[] = $order->o_id;
        }
        if( !$gen_o_ids ){
            Response::instance()->sendMessage( 'Nothing to output.' );
        }
        Log::instance()->insert([
            'log_response_code' => 200,
            'log_response' => $gen_o_ids,
        ]);

        $merge->output();
        exit;
    }



    public function orders( $status = 'all', $page = 1 ) {
        $per_page = 10;
        $page     = (int) $page;
        $limit    = $per_page * ( $page - 1 );

         if ( ! (Auth::id()) ) {
             Response::instance()->loginRequired( true );
             Response::instance()->sendMessage( 'Invalid id token' );
         }


        $orders= Order::orderBy('o_id', 'desc')->where('u_id', Auth::id())
        ->where('o_status', $status)->limit($per_page)->offset($limit)->get();
        if( ! $orders ){
            Response::instance()->sendMessage( 'No Orders Found' );
        }
         $o_ids = array_map(function($o) { return $o->o_id;}, $orders);
         CacheUpdate::instance()->add_to_queue( $o_ids , 'order_meta');
         CacheUpdate::instance()->update_cache( [], 'order_meta' );

        foreach( $orders as $order ){
            $data = $order->toArray();
            if( 'processing' == $order->o_status && ( \strtotime( $order->o_created ) + ( 10 * 60 ) ) < \strtotime( \date( 'Y-m-d H:i:s' ) ) ){
                $data['o_status'] = 'awaiting feedback';
            }
			
			//$data['refund'] = $order->getMeta( 'refund' ) ? round( $order->getMeta( 'refund' ), 2 ) : 0;
            $data['o_note'] = (string)$order->getMeta('o_note');
            $data['paymentStatus'] = ( 'paid' === $order->o_i_status || 'paid' === $order->getMeta( 'paymentStatus' ) ) ? 'paid' : '';

            if( \in_array( $order->o_status, [ 'confirmed', 'delivering', 'delivered' ] ) && \in_array( $order->o_i_status, ['packing', 'checking', 'confirmed'] ) && 'paid' !== $order->getMeta( 'paymentStatus' ) ){
                $data['paymentUrl'] = $order->signedUrl( '/payment/v1' );
            }
            Response::instance()->appendData( '', $data );
        }
        if ( ! Response::instance()->getData() ) {
            Response::instance()->sendMessage( 'No Orders Found' );
        } else {
            Response::instance()->setStatus( 'success' );
            Response::instance()->send();
        }
    }


    function cashBalance(){
        $user = User::getUser( Auth::id() );
        if( ! $user ) {
            Response::instance()->loginRequired( true );
            Response::instance()->sendMessage( 'No users Found' );
        }
        
        $data = [
            'u_cash' => \round( $user->u_cash, 2 ),
            'u_p_cash' => \round( $user->u_p_cash, 2 ),
        ];
        Response::instance()->sendData( $data, 'success' );
    }


    function location(Request $request){
        $lat = isset( $request['lat'] ) ? $request['lat'] : 0;
        $long = isset( $request['long'] ) ? $request['long'] : 0;
        if( ! $lat || ! $long ){
            Response::instance()->sendMessage( 'Invalid location.' );
        }

        /*
        if( isInside( $lat, $long, 'chittagong' ) ){
            Response::instance()->sendMessage( "Our Chattogram operation temporarily off due to some unavoidable circumstances. We will send you a notification once our Chattogram operation resumes.\nSorry for this inconvenience.");
        }
        if( !isInside( $lat, $long ) ){
            Response::instance()->sendMessage( "Our delivery service comming to this area very soon, please stay with us.");
        }
        */

        $client = new Client();
        $res = $client->get( \sprintf( 'https://barikoi.xyz/v1/api/search/reverse/geocode/server/%s/place', env('BARIKOI_API_KEY', '') ), [
            'query' => [
                'latitude' => $lat,
                'longitude' => $long,
                'post_code' => 'true',
            ],
        ]);
        if( 200 !== $res->getStatusCode() ){
            Response::instance()->sendMessage( 'Something went wrong, Please try again.' );
        }
        $body = maybeJsonDecode( $res->getBody()->getContents() );
        if( ! $body || ! \is_array( $body ) || 200 !== $body['status'] ){
            Response::instance()->sendMessage( 'Something went wrong, Please try again' );
        }
        $location = [];
        if( ! empty( $body['place']['address'] ) ){
            $location[] = trim($body['place']['address']);
        }
        if( ! empty( $body['place']['area'] ) ){
            $location[] = trim($body['place']['area']);
        }
        if( ! empty( $body['place']['city'] ) ){
            $location[] = trim($body['place']['city']);
        }
        $postCode = ! empty( $body['place']['postCode'] ) ? trim($body['place']['postCode']) : '';
        $data = getAddressByPostcode( $postCode, trim($body['place']['area']) );

        $location = \array_unique( \array_filter( $location ) );
        if( ! $location ){
            Response::instance()->sendMessage( 'No address found, Please try again.' );
        }
        $data['homeAddress'] = ! empty( $body['place']['address'] ) ? trim($body['place']['address']) : '';
        $data['location'] = \implode( ', ', $location );
        $data['place'] = $body['place'];

        Response::instance()->sendData( $data, 'success' );
    }



    function profile() {
        $user = Auth::user();
        if( ! $user ){
            Response::instance()->sendMessage( 'You are not logged in' );
        }
        $data = $user->toArray();
        //$data['authToken'] = $user->authToken();
        $data['u_pic_url'] =  getProfilePicUrl( Auth::id() );

        Response::instance()->sendData( ['user' =>  $data], 'success' );
    }


    function profileUpdate(Request $request) {
        //$file_to_save = isset( $_FILES['u_profile_pic'] ) ? $_FILES['u_profile_pic'] : [];
        $file_to_save =$request->u_profile_pic ? $request->u_profile_pic : [];
         if( ! Auth::check() ){
             return response()->json( 'You are not logged in' );
         }
        $user = Auth::user();
        $u_name = $request->u_name ?  $request->u_name : '';
        $u_mobile = $request->u_mobile ?  $request->u_mobile : '';
        $u_email = $request->u_email ? $request->u_email : '';

        if( !$user->u_mobile && !$u_mobile ){
            return response()->json( 'Mobile number required' );
        }

        $update_data = [
            'u_name' => $u_name,
            'u_mobile' => $user->u_mobile ?: $u_mobile,
            'u_email' => $u_email,
        ];
        $user->update( $update_data );
        $data = $user->toArray();
        $data['authToken'] = $user->authToken();
                

        if ( $file_to_save ) {
            $upload_folder = STATIC_DIR . '/users/' . \floor( Auth::id() / 1000 );

            if ( ! is_dir($upload_folder)) {
                @mkdir($upload_folder, 0755, true);
            }
            
            // var_dump($file_to_save);
            $tmp_name = $file_to_save['tmp_name'];

            $imagetype = exif_imagetype( $tmp_name );
            $mime      = ( $imagetype ) ? image_type_to_mime_type( $imagetype ) : false;
            $ext       = ( $imagetype ) ? image_type_to_extension( $imagetype ) : false;
            if( ! $ext || ! $mime ) {
                return response()->json( [
                    'status' => 'success',
                    'data' => [],
                    'message' => 'Only profile picture are allowed.',
                ]);
            }
            //Delete previous pic here
            $prev_image = \str_replace( STATIC_URL, STATIC_DIR, getProfilePicUrl( Auth::id() ) );
            if( $prev_image && file_exists( $prev_image ) ){
                @unlink( $prev_image );
            }

            $new_file = randToken( 'alnumlc', 12 ) . $ext;

            $new_file = \sprintf( '%s/%s-%s', $upload_folder, Auth::id(), $new_file );
            if( @ move_uploaded_file( $tmp_name, $new_file ) ) {
                // Set correct file permissions.
                $stat  = stat( dirname( $new_file ) );
                $perms = $stat['mode'] & 0000666;
                @ chmod( $new_file, $perms );
            }
        
        }
        $data['u_pic_url'] = getProfilePicUrl( Auth::id() );
        return response()->json([
            'status'=>'success',
            'data'=>['user' => $data],
        ]);
    }



    function prescriptions(Request $request) {
        $page = isset( $request['page'] ) ? (int)$request['page'] : 1;
        if( ! Auth::check() ){
            Response::instance()->sendMessage( 'You are not logged in' );
        }
        $user = Auth::user();
        $p_array = maybeJsonDecode($user->getMeta( 'prescriptions' ));
        $p_array = ( $p_array && is_array($p_array) ) ? $p_array : [];

        $total = count( $p_array ); //total items in array    
        $limit = 20; //per page    
        $totalPages = ceil( $total / $limit ); //calculate total pages
        $page = max($page, 1); //get 1 page when $page <= 0

        if( $page > $totalPages ){
            return response()->json([
                "status" => "fail",
                "message" => "No saved prescription found",
                "data" => []
            ]);
        }
        
        $offset = ( $page - 1 ) * $limit;
        if( $offset < 0 ) $offset = 0;

        $p_array = array_slice( $p_array, $offset, $limit );

        $value = [];
        foreach ( $p_array as $s3key ) {
            $value[] = [
                'key' => $s3key,
                'src' => getPresignedUrl( $s3key ),
            ];
        }
        return response()->json( [ 
            'status'=>'success',
            'prescriptions' => $value 
        ]);
    }


    function offers(){
        $data = [
            [
                'heading' => 'Cashback ৳100',
                'desc' => 'For purchasing above ৳5000+',
            ],
            [
                'heading' => 'Cashback   ৳80',
                'desc' => 'For purchasing above ৳4000+',
            ],
            [
                'heading' => 'Cashback   ৳60',
                'desc' => 'For purchasing above ৳3000+',
            ],
            [
                'heading' => 'Cashback   ৳40',
                'desc' => 'For purchasing above ৳2000+',
            ],
            [
                'heading' => 'Cashback   ৳20',
                'desc' => 'For purchasing above ৳1000+',
            ],

        ];
        return response()->json( [ 
            'status'=>'success',
            'data' => $data 
        ]);
    }


    private function FAQs(){
        $return = [];
        $return[] = [
            'title' => 'Medicine and Healthcare Orders',
            'slug' => 'medicineAndHealthcareOrders',
            'data' => [
                [
                    'q' => 'When will I receive my order?',
                    'a' => 'Your order will be delivered within 18-48 hours inside dhaka city, 1-5 days outside dhaka city'
                ],
                [
                    'q' => 'I have received damaged items.',
                    'a' => 'We are sorry you had to experience this. Please do not accept the delivery of that order and let us know what happened'
                ],
                [
                    'q' => 'Items are different from what I ordered.',
                    'a' => 'We are sorry you have had to experience this. Please do not accept it from delivery man. Reject the order straightaway and call to arogga customer care'
                ],
                [
                    'q' =>'What if Items are missing from my order.',
                    'a' => 'In no circumstances, you should receive an order that is incomplete. Once delivery man reaches your destination, be sure to check expiry date of medicines and your all ordered items was delivered.',
                ],
                [
                    'q' => 'How do I cancel my order?',
                    'a' => 'Please call us with your order ID and we will cancel it for you.'
                ],
                [
                    'q' => 'I want to modify my order.',
                    'a' => 'Sorry, once your order is confirmed, it cannot be modified. Please place a fresh order with any modifications.'
                ],
                [
                    'q' => 'What is the shelf life of medicines being provided?',
                    'a' => 'We ensure that the shelf life of the medicines being supplied by our partner retailers is, at least, a minimum of 3 months from the date of delivery.'
                ]
            ]
        ];
        $return[] = [
            'title' => 'Delivery',
            'slug' => 'delivery',
            'data' => [
                [
                    'q' => 'When will I receive my order?',
                    'a' => 'Your order will be delivered within the Estimated Delivery Date.'
                ],
                [
                    'q' => 'Order status showing delivered but I have not received my order.',
                    'a' => 'Sorry that you are experiencing this. Please call to connect with us immediately.'
                ],
                [
                    'q' => 'Which cities do you operate in?',
                    'a' => 'We provide healthcare services in all over Bangladesh now'
                ],
                [
                    'q' => 'How can I get my order delivered faster?',
                    'a' => 'Sorry, we currently do not have a feature available to expedite the order delivery. We surely have a plan to introduce 2 hour expedite delivery soon'
                ],
                [
                    'q' => 'Can I modify my address after Order placement?',
                    'a' => 'Sorry, once the order is placed, we are unable to modify the address.'
                ],
            ]
        ];

        $return[] = [
            'title' => 'Payments',
            'slug' => 'payments',
            'data' => [
                [
                    'q' => 'How do customers get discounts.',
                    'a' => 'We deduct the value from every medicines and show it to you before order, so that you can see what you are really paying for each medicines  '
                ],
                [
                    'q' => 'When will I get my refund?',
                    'a' => 'Refund will be in credited in arogga cash (3-5 business days)'
                ],
                [
                    'q' => 'I did not receive cashback for my order.',
                    'a' => 'Please read the T&C of the offer carefully for the eligibility of cashback.'
                ],
                [
                    'q' => 'What are the payment modes at arogga?',
                    'a' => 'Cash on Delivery (COD) and Online payment method Bkash, Nagad, Cards etc.'
                ]
            ]
        ];

        $return[] = [
            'title' => 'Referrals',
            'slug' => 'referrals',
            'data' => [
                [
                    'q' => 'How does your referral program work?',
                    'a' => sprintf('Invite your friend and family members by sharing your referral code. Once they join with your referral code and place their first order, you will get extra %d Taka Referral bonus in your arogga cash.', changableData('refBonus') ),
                ],
                [
                    'q' => 'Why did I not get the referral benefit?',
                    'a' => "If you are not notified about your referral benefit, it is likely that one or more of the following things happened: \n 1. The referred member did not apply your referral code while placing the order \n 2. The user clicked on your link but did not create an account or complete their first purchase. \n 3. The referred member placed an eligible order, but the order was not fulfilled. \n 4. The person who used the code has already placed an order on arogga. \n 5. Your referral benefit has expired"
                ],
                [
                    'q' => 'Is there an expiry date to my referral benefit?',
                    'a' => 'No, there is no expiry date. Once you are eligible for the additional benefit, you will surely get it.'
                ],
            ]
        ];

        $return[] = [
            'title' => 'Arogga Cash',
            'slug' => 'AroggaCash',
            'data' => [
                [
                    'q' => 'What is arogga cash?',
                    'a' => 'This is a virtual wallet to store arogga Cash in your account..'
                ],
                [
                    'q' => 'How do I check my arogga cash balance?',
                    'a' => 'You can check your arogga cash in Account screen.'
                ],
                [
                    'q' => 'When will the arogga money expire?',
                    'a' => 'Any arogga Cash deposited in your arogga wallet through returns will never expire. At times, our marketing team may deposit promotional cash which will have an expiry that is communicated to you via an SMS.'
                ],
                [
                    'q' => 'Can I add money to my arogga cash?',
                    'a' => 'No, you are unable to transfer or add money to your arogga cash.'
                ],
                [
                    'q' => 'How can I redeem my arogga cash?',
                    'a' => 'If you have any money in your arogga cash, it will be automatically deducted from your next order amount and you will only have to pay for the balance amount (if any).'
                ],
                [
                    'q' => 'Can I transfer money from my arogga cash to the bank account?',
                    'a' => 'No, you are unable to transfer money from your arogga cash to the bank account.'
                ],
                [
                    'q' => 'How much arogga money can I redeem in an order?',
                    'a' => 'There is no limit for redemption of arogga cash  '
                ]
            ]
        ];

        $return[] = [
            'title' => 'Promotions',
            'slug' => 'promotions',
            'data' => [
                [
                    'q' =>'How do I apply a coupon code on my order?',
                    'a' =>'You can apply a coupon on the cart screen while placing an order. If you are getting a message that the coupon code has failed to apply, it may be because you are not eligible for the offer.'
                ],
                [
                    'q' => 'Does arogga offers return of the medicine?',
                    'a' => 'No, Arogga does not accept returns of the medicine from customer. Thats why customers are requested to thoroughly check all the medicine before accepting the order from delivery man. If for any reason you want to return the product, simply reject the order to delivery man. Do not receive it, your order will be automatically cancelled'
                ]
            ]
        ];
        $return[] = [
            'title' => 'Return',
            'slug' => 'return',
            'data' => [
                [
                    'q' => 'How does Arogga’s return policy work?',
                    'a' => "Arogga offers a flexible return policy for items ordered with us. Under this policy, unopened and unused items must be returned within 7 days from the date of delivery. The return window will be listed in the returns section of the order, once delivered.\n\nItems are not eligible for return under the following circumstances:\n\n - If items have been opened, partially used or disfigured. Please check the package carefully at the time of delivery before opening and using.\n - If the item’s packaging/box or seal has been tampered with. Do not accept the delivery if the package appears to be tampered with.\n - If it is mentioned on the product details page that the item is non-returnable.\n - If the return window for items in an order has expired. No items can be returned after 7 days from the the delivery date.\n - If any accessories supplied with the items are missing.\n - If the item does not have the original serial number/UPC number/barcode affixed, which was present at the time of delivery.\n - If there is any damage/defect which is not covered under the manufacturer's warranty.\n - If the item is damaged due to visible misuse.\n - Any refrigerated items like insulin or products that are heat sensitive are non-returnable.\n - Items related to baby care, food & nutrition, healthcare devices and sexual wellness such as but not limited to diapers, health drinks, health supplements, glucometers, glucometer strips/lancets, health monitors, condoms, pregnancy/fertility kits, etc."
                ],
                [
                    'q' => 'Do you sell medicine strips in full or it can be single units too?',
                    'a' => 'We sell in single units to give customers flexibility in selecting specific amounts of medicine required. We provide single units of medicine as our pharmacist can cut strips.'
                ],
                [
                    'q' => 'I have broken the seal, can I return it?',
                    'a' => 'No, you can not return any items with a broken seal.'
                ],
                [
                    'q' => 'Can I return medicine that is partially consumed?',
                    'a' => 'No, you cannot return partially consumed items. Only unopened items that have not been used can be returned.'
                ],
                [
                    'q' => 'Can I ask for a return if the strip is cut?',
                    'a' => 'We provide customers with the option of purchasing medicines as single units. Even if ordering a single tablet of paracetamol, we can deliver that. It is common to have medicines in your order with some strips that are cut. If you want to get a full strip in your order, please order a full strip amount and you will get it accordingly. If you do not order a full strip, you will get cut pieces. If you have ordered 4 single units which are cut pieces and want to return, all 4 pieces must be returned. We do not allow partial return of 1 or 2 pieces.'
                ],
            ]
        ];
        return $return;
    }
    function FAQsHeaders(){
        $FAQs = $this->FAQs();
        $data = array_map( function( $FAQ ){
            return [ 'title' => $FAQ['title'], 'slug' => $FAQ['slug'] ];
        }, $FAQs);
        $data = array_filter( $data );
        if( ! is_array( $data ) ){
            $data = [];
        }

        return response()->json( [ 
            'status'=>'success',
            'data' => $data 
        ]);
    }

    function FAQsReturn( $slug ){
        $FAQs = $this->FAQs();
        $data = array_map( function( $FAQ ) use ( $slug ){
            if( $FAQ['slug'] == $slug ){
                return $FAQ['data'];
            } else {
                return null;
            }
        }, $FAQs);
        $data = array_filter( $data );
        $return = reset( $data );
        if( ! is_array( $return ) ){
            $return = [];
        }
        return response()->json( [ 
            'status'=>'success',
            'return' => $return 
        ]);
    }

   

   public function locationData(){
        $get      = isset($_GET['get']) ? $_GET['get'] : '';
        $division = isset($_GET['division']) ? $_GET['division'] : '';
        $district = isset($_GET['district']) ? $_GET['district'] : '';

        $data = [];
        if( in_array( $get, [ 'all', 'divisions'] ) ){
            $data['divisions'] = getDivisions();
        }
        if( in_array( $get, [ 'all', 'districts'] ) ){
            $data['districts'] = getDistricts( $division );
        }
        if( in_array( $get, [ 'all', 'areas'] ) ){
            $data['areas'] = getAreas( $division, $district );
        }

        return response()->json( [ 
            'status'=>'success',
            'data' => $data 
        ]);
    }




}
