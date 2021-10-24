<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Cache\Cache;

class Bag extends Model
{
    use HasFactory;

    protected $fillable = [
        'b_id',
        'b_ph_id',
        'b_zone',
        'b_no',
        'b_de_id',
        'o_count',
        'o_ids',
    ];

    protected $primaryKey = 'b_id';

    public $timestamps = false;

    protected $table = "t_bags";

    public static function deliveryBag( $ph_id, $de_id ){
        if( !$ph_id || !$de_id ) {
            return [];
        }
        $query= Bag::where('b_ph_id', $ph_id)->where('b_de_id', $de_id)->first();
        return $query;
    }

    public static function getCurrentBag( $ph_id, $zone ){
        if( !$ph_id || !$zone ) {
            return false;
        }
        $cache= new Cache();
        $cached_id = $cache->get( "{$ph_id}_{$zone}", 'currentBag' );
        if( $cached_id && ( $cached = static::getBag( $cached_id ) ) && !$cached->b_de_id && $cached->o_count < 30 ){
            return $cached;
        }
        $query = DB::db()->prepare( "SELECT * FROM t_bags WHERE b_ph_id = ? AND b_zone = ? 
        AND b_de_id = ? AND o_count < ? ORDER BY o_count DESC LIMIT 1" );

        $bag= Bag::orderBy('o_count', 'desc')->where('b_ph_id', $ph_id)->where('b_zone', $zone)
        ->where('b_de_id', 0)
        ->where('o_count', '<', 30)
        ->first();
        if( $bag){
            $cache->set( $bag->b_id, $bag, 'bag' );
            $cache->set( "{$ph_id}_{$zone}", $bag->b_id, 'currentBag' );
            return $bag;
        } else {
            return false;
        }
    }

    public static function getBag( $id ) {
        if( !$id || !is_numeric( $id ) ){
            return false;
        }
        $cache= new Cache();
        if ( $bag = $cache->get( $id, 'bag' ) ){
            return $bag;
        }
        $bag= Bag::where('b_id', $id)->first();
        if( $bag){
            $cache->set( $bag->b_id, $bag, 'bag' );
            return $bag;
        } else {
            return false;
        }
    }

    

}
