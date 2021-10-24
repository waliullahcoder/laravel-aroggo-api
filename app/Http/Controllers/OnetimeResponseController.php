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
class OnetimeResponseController extends Controller
{
    public function updateLocationsTable(){
        $url = sprintf( 'https://barikoi.xyz/v1/api/search/%s/rupantor/geocode', BARIKOI_API_KEY );
        $client = new Client();

        $location= Location::get();

        while ( $location){
            $update_data = [];
            $district = trim( str_replace( [ 'City', 'District' ], '', $location['l_district'] ) );
            $address = sprintf( '%s, %s, %s', $location['l_area'], $district, $location['l_division'] );

            $res = $client->post($url,
                ["form_params" => [
                    'q' => $address,
                    'zone' => 'true',
                ]]
            );
            $res_data = maybeJsonDecode( $res->getBody()->getContents() );
            if( !$res_data || !is_array( $res_data ) ){
                $update_data = [
                    'l_comment' => 'No data',
                ];
            } elseif( empty( $res_data['geocoded'] ) || empty( $res_data['geocoded']['latitude'] ) ){
                $update_data = [
                    'l_comment' => 'No lat',
                ];
            } else {
                $update_data = [
                    'l_lat' => round( $res_data['geocoded']['latitude'], 6 ),
                    'l_long' => round( $res_data['geocoded']['longitude'], 6 ),
                ];
                if( $location['l_postcode'] === 0 ){
                    $update_data['l_postcode'] = $res_data['geocoded']['postCode'];
                }
            }
            DB::instance()->update('t_locations', $update_data, ['l_id' => $location['l_id']]);
            //To prevent too many requests error as api rate is 60 requests per minute
            //sleep(1);
        }
        return response()->json('success' );
    }

    function sitemap(){
        header('Content-Description: File Transfer');
        header('Content-Type: application/xml');
        header('Content-Disposition: attachment; filename="sitemap.xml"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        echo '<?xml version="1.0" encoding="UTF-8"?>'. PHP_EOL;
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">'. PHP_EOL;

        $query = DB::db()->prepare( 'SELECT m_id, m_name, m_form, m_strength FROM t_medicines WHERE m_status = ?' );
        $query->execute( [ 'active' ] );
        $data = [];
        while( $m = $query->fetch() ){
            echo '<url>'. PHP_EOL;
            echo '<loc>'.sprintf( 'https://www.arogga.com/brand/%d/%s', $m['m_id'], $this->brandSlug($m) ).'</loc>'. PHP_EOL;
            echo '</url>'. PHP_EOL;
        }

        echo '</urlset>'. PHP_EOL;
        die;
    }


    public function stockUpdate( $rob, $by, $id ){
        if( ! $this->enabled ){
            return response()->json( 'Not enabled.' );
        }
        if( ! $by || ! $id ){
            return response()->json( 'all fields required' );
        }
        $rob = (bool) $rob;

        switch ( $by ) {
            case 'm_id':
            case 'm_g_id':
            case 'm_c_id':
                break;
            
            default:
            return response()->json( 'No valid field name' );
                break;
        }
        $medicine=Medicine::where('m_rob', $rob)->where('m_status', '=', 'active')->get();

        $m_ids = [];
        while( $medicine){
            $medicine->update( [ 'm_rob' => $rob ] );
            $m_ids[] = $medicine->m_id;
        }
        return response()->json([
             'status'=>'success',
             'm_ids'=>$m_ids
        ]);
    }


    public function deliverymanUpdate( $prev_u_id, $curr_u_id ){
        if( ! $this->enabled ){
            return response()->json( 'Not enabled.' );
        }
        $updated = DB::instance()->update( 't_locations', ['l_de_id' => $curr_u_id], ['l_de_id' => $prev_u_id] );

        if( $updated ){
            $cache=new Cache();
            $cache->delete( 'locations' );
            return response()->json([
                'status'=>'success',
                'count'=>$updated
           ]);
           
        }
        return response()->json( 'Failed.' );
    }


    public function priceUpdate( $discountPercent, $by, $id, $prevDiscountPercent = null ){
        if( ! $this->enabled ){
            return response()->json( 'Not enabled.' );
        }
        if( ! is_numeric( $discountPercent ) || ! $by || ! $id ){
            return response()->json( 'all fields required' );
        }

       
        

        switch ( $by ) {
            case 'm_id':
            case 'm_g_id':
            case 'm_c_id':
                //$db->add( " AND $by = ?", $id );
                break;
            
            default:
            return response()->json( 'No valid field name' );
                break;
        }
        // if( is_numeric( $prevDiscountPercent ) ){
        //     $db->add( " AND m_d_price BETWEEN ( m_price * ? ) AND ( m_price * ? )", (100-$prevDiscountPercent-0.5)/100, (100-$prevDiscountPercent+0.5)/100 );
        // }
        $medicine=Medicine::where('m_price', 0)->where('m_status', '=', 'active')->get();

        $m_ids = [];
        while( $medicine){
            $medicine->update( [ 'm_d_price' => $medicine->m_price * ((100-$discountPercent) / 100) ] );
            $m_ids[] = $medicine->m_id;
        }
        return response()->json([
            'status'=>'success',
            'm_ids'=>$m_ids
       ]);
    }



    public function medicineCSVImport($number){
        $ids = [];
        if (($file = fopen(STATIC_DIR . "/import/medicines-{$number}.csv","r")) !== FALSE) {
            while(! feof($file))
            {
                
                $fileRow = fgetcsv($file);
                $g_id = 0;
                $c_id = 0;
                if ($fileRow && $fileRow[0] != "m_id") {

                    //Generic
                    if($fileRow[2]){
                        $generic = GenericV1::getGeneric($fileRow[2]);
                        if( $generic ){
                            $g_id = $generic->g_id;
                        }
                    }
                    if( !$g_id && $fileRow[3] ){
                        $g_id=GenericV2::where('g_name', $fileRow[3])->first();
                    }
                    if( !$g_id && $fileRow[3] ){
                        $newGeneric = new GenericV1;
                        $g_id = $newGeneric->insert([ 'g_name' => $fileRow[3] ]);
                    }

                    //Company
                    if($fileRow[4]){
                        $company = Company::getCompany($fileRow[4]);
                        if( $company ){
                            $c_id = $company->c_id;
                        }
                    }
                    if( !$c_id && $fileRow[5] ){
                        $query = DB::db()->prepare( 'SELECT c_id FROM t_companies WHERE c_name = ? LIMIT 1' );
                        $query->execute( [ $fileRow[5] ] );
                        $c_id =Company::where('c_name', $fileRow[5])->first();
                    }
                    if(!$c_id && $fileRow[5] ){
                        $newCompany = new Company;
                        $c_id = $newCompany->insert([ 'c_name' => $fileRow[5] ]);
                    }

                    //medicine
                    $medicineDetails = [
                        "m_name" => $fileRow[1],
                        "m_g_id" => $g_id,
                        "m_c_id" => $c_id,
                        "m_form" => trim( $fileRow[6] ),
                        "m_strength" => str_replace( ' ', '', $fileRow[7] ),
                        "m_price" => $fileRow[8],
                        "m_d_price" => $fileRow[9],
                        "m_unit" => trim( $fileRow[10] ),
                        "m_category" => $fileRow[11],
                        "m_status" => $fileRow[12],
                        "m_rob" => $fileRow[13],
                        "m_cat_id" => $fileRow[14],
                        "m_comment" => $fileRow[15],
                        "m_i_comment" => $fileRow[16],
                        "m_u_id" => $fileRow[17],
                    ];
                    $medicine = false;
                    if( $fileRow[0] ){
                        $medicine = Medicine::getMedicine($fileRow[0]);
                        if( $medicine ){
                            $medicine->update($medicineDetails);
                        } else {
                            $medicine = new Medicine;
                            $medicine->insert($medicineDetails);
                        }
                    } else {
                        $medicine = new Medicine;
                        $medicine->insert($medicineDetails);
                    }
                    if($medicine && $fileRow[11] == "healthcare"){
                        $medicine->setMeta('description', trim( $fileRow[18] ));
                    }
                    if( $medicine ){
                        array_push($ids, $medicine->m_id );
                    }
                }
            }
        }
        fclose($file);
        return response()->json([
            'status'=>'success',
            'ids'=>$ids
       ]);
    }




}
