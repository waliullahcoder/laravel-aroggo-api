<?php

namespace App\Http\Controllers;

use App\Cache\Cache;
use App\Models\GenericV1;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
class CacheResponseController extends Controller
{
    public function cacheFlush(){
   
        $cache = new Cache();
        if( $cache){
            return response()->json([
                'status'=>'success',
                'message'=>'Cache Successfully flushed.',
            ]);
        } else {
            return response()->json( 'Something went wrong. Please try again' );
        }
    }

    public function cacheStats(){
        //header('Content-Type: text/html; charset=utf-8');
        $cache = new Cache();
        $cache->stats();
        die;
    }


    public function set( $key, $value, $group = 'default' ){
        $cache = new Cache();
        if( $cache->set( $key, $value, $group ) ){
            return response()->json([
                'status'=>'success',
                'message'=>'Cache Successfully set.',
            ]);
        } else {
            return response()->json( 'Something went wrong. Please try again' );
        }
    }

    public function get( $key, $group = 'default' ){
        $cache = new Cache();
        if( $data = $cache->get( $key, $group ) ){
            if( \is_array( $data ) || \is_object( $data ) ){
                return response()->json([
                    'status'=>'success',
                    'data'=>$data,
                ]);
            } else {
                return response()->json([
                    'status'=>'success',
                    'data'=>$data,
                ]);
            }
        } else {
            return response()->json( 'Something went wrong. Please try again2' );
        }
    }

    public function delete( $key, $group = 'default' ){
        $cache = new Cache();
        if( $cache->delete( $key, $group ) ){
            return response()->json([
                'status'=>'success',
                'message'=>'Cache Successfully deleted',
            ]);
        } else {
            return response()->json( 'Something went wrong. Please try again' );
        }
    }




}
